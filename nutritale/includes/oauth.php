<?php
require_once __DIR__ . '/functions_core.php';

// Minimal OAuth2 "authorization code" sign-in for Google, Facebook, and
// Apple, added alongside the redesigned login/register pages. Every
// provider ships with EMPTY credentials by default (config.php) - the
// same pattern already used for GEMINI_API_KEY and the PayFast sandbox
// defaults elsewhere in this app: each is inert, not broken, until real
// credentials are added for it, and its button shows disabled rather
// than erroring when clicked - independently of the other two, so
// shipping Google today and Apple later never breaks the page.
//
// Deliberately hand-rolled rather than an SDK - none of the three ships
// one that works without Composer, which this app doesn't use anywhere
// (see PHPMailer's own vendored-as-plain-files approach for the one
// exception, and why a library wasn't vendored the same way for OAuth) -
// but checked against each provider's own current published flow rather
// than guessed:
//   Google:   developers.google.com/identity/protocols/oauth2/web-server
//   Facebook: developers.facebook.com/docs/facebook-login/guides/advanced/manual-flow
//   Apple:    developer.apple.com/documentation/sign_in_with_apple/generate_and_validate_tokens
//             developer.apple.com/documentation/sign_in_with_apple/request_an_authorization_to_the_sign_in_with_apple_server

// Pure logic behind all three "configured?" checks, kept separate from
// the constants so it's unit-testable without redefining a define()'d
// constant mid-test-run (PHP doesn't allow that) - see
// tests/oauth_test.php. A provider is configured only once every one of
// its required values is non-blank.
function oauth_provider_configured(string ...$values): bool
{
    foreach ($values as $value) {
        if ($value === '') {
            return false;
        }
    }
    return true;
}

function oauth_google_configured(): bool
{
    return oauth_provider_configured(GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET);
}

function oauth_facebook_configured(): bool
{
    return oauth_provider_configured(FACEBOOK_APP_ID, FACEBOOK_APP_SECRET);
}

function oauth_apple_configured(): bool
{
    return oauth_provider_configured(APPLE_OAUTH_CLIENT_ID, APPLE_OAUTH_TEAM_ID, APPLE_OAUTH_KEY_ID, APPLE_OAUTH_PRIVATE_KEY);
}

// A random, unguessable value stored in the session and echoed back by
// the provider on its callback - the standard CSRF defense for OAuth
// "state": it never leaves the victim's own session, so an attacker
// crafting a callback URL to trick someone into visiting can't forge it.
// One-time use, like csrf_token()'s check elsewhere in this app.
function oauth_new_state(string $provider): string
{
    $state = bin2hex(random_bytes(24));
    $_SESSION['oauth_state_' . $provider] = $state;
    return $state;
}

function oauth_verify_state(string $provider, ?string $received): bool
{
    $expected = $_SESSION['oauth_state_' . $provider] ?? null;
    unset($_SESSION['oauth_state_' . $provider]);
    return $expected !== null && $received !== null && hash_equals($expected, $received);
}

// One HTTP request returning the decoded JSON body, or null on any
// transport/HTTP/decode failure. Shared by both providers.
function oauth_http_json(string $method, string $url, array $fields = []): ?array
{
    $ch = curl_init($method === 'GET' ? $url . '?' . http_build_query($fields) : $url);
    $opts = [
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($fields);
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $httpCode !== 200) {
        return null;
    }
    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

// Pure decision logic for account linking - given what the two lookups
// below found (or didn't), decide what to do. Kept separate from the
// database so it's unit-testable with plain arrays - see
// tests/oauth_test.php - the same DB-wrapper/pure-function split this
// codebase already uses for vendor_owed_amount()/calculate_vendor_owed().
//
//   'use_existing' - this exact (provider, oauth_id) has signed in
//                    before; nothing to write, just log them in.
//   'link'         - no account has this (provider, oauth_id) yet, but
//                    one already exists with this verified email (a
//                    plain signup, or a different provider) - attach
//                    this provider/id to that account rather than
//                    creating a duplicate.
//   'create'       - neither matched; this is a genuinely new person.
function oauth_resolve_account(?array $byProviderId, ?array $byEmail): array
{
    if ($byProviderId !== null) {
        return ['action' => 'use_existing', 'user' => $byProviderId];
    }
    if ($byEmail !== null) {
        return ['action' => 'link', 'user' => $byEmail];
    }
    return ['action' => 'create', 'user' => null];
}

// Finds the existing account for a confirmed OAuth identity, links this
// provider onto a matching-by-email account, or creates one - the DB
// wrapper around oauth_resolve_account() above. Callers must only pass
// an email the provider has itself verified - see the checks in each
// oauth_*_callback.php before this is ever reached. A newly created
// account gets a password_hash nobody knows (a random value, not blank
// or null), so it can't be signed into with a guessed or empty password -
// only via OAuth again, or by setting a real one through "forgot
// password" first. A newly created account also has onboarded_at NULL,
// same as a plain signup, so index.php's existing check sends it through
// the normal diet/allergen onboarding on first visit - no separate path
// needed.
function oauth_find_or_create_user(string $provider, string $providerId, string $email, string $name): array
{
    $pdo = db();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE oauth_provider = ? AND oauth_id = ?');
    $stmt->execute([$provider, $providerId]);
    $byProviderId = $stmt->fetch() ?: null;

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $byEmail = $stmt->fetch() ?: null;

    $resolution = oauth_resolve_account($byProviderId, $byEmail);

    if ($resolution['action'] === 'use_existing') {
        return $resolution['user'];
    }

    if ($resolution['action'] === 'link') {
        $pdo->prepare('UPDATE users SET oauth_provider = ?, oauth_id = ? WHERE id = ?')
            ->execute([$provider, $providerId, $resolution['user']['id']]);
        $resolution['user']['oauth_provider'] = $provider;
        $resolution['user']['oauth_id'] = $providerId;
        return $resolution['user'];
    }

    $isFirstUser = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
    $unusablePassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $pdo->prepare('INSERT INTO users (name, email, password_hash, is_admin, oauth_provider, oauth_id) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$name !== '' ? $name : 'NutriTale user', $email, $unusablePassword, $isFirstUser ? 1 : 0, $provider, $providerId]);

    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int)$pdo->lastInsertId()]);
    return $stmt->fetch();
}

// Apple has no static client secret like Google/Facebook's - instead it
// wants a JWT, signed with the ES256 (EC P-256) private key from your
// Sign in with Apple key, that's regenerated fresh for each token
// request (Apple allows up to 6 months' validity but there's no reason
// to keep one around longer than a single request needs). Structure per
// developer.apple.com/documentation/sign_in_with_apple/generate_and_validate_tokens.
function apple_oauth_client_secret(): string
{
    $header = ['alg' => 'ES256', 'kid' => APPLE_OAUTH_KEY_ID];
    $now = time();
    $claims = [
        'iss' => APPLE_OAUTH_TEAM_ID,
        'iat' => $now,
        'exp' => $now + 300,
        'aud' => 'https://appleid.apple.com',
        'sub' => APPLE_OAUTH_CLIENT_ID,
    ];
    $signingInput = base64url_encode(json_encode($header)) . '.' . base64url_encode(json_encode($claims));

    $key = openssl_pkey_get_private(APPLE_OAUTH_PRIVATE_KEY);
    if ($key === false) {
        throw new RuntimeException('APPLE_OAUTH_PRIVATE_KEY is not a valid PEM private key.');
    }
    // openssl_sign() on an EC key produces a DER-encoded ASN.1 signature
    // (a SEQUENCE of two INTEGERs, r and s) - JOSE/JWT's ES256 instead
    // wants the raw concatenation of r and s, each fixed to 32 bytes, so
    // it has to be converted rather than used as-is.
    openssl_sign($signingInput, $derSignature, $key, OPENSSL_ALGO_SHA256);
    $signature = der_to_raw_ecdsa($derSignature, 32);

    return $signingInput . '.' . base64url_encode($signature);
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    $padded = str_pad($data, strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + (4 - strlen($data) % 4), '=');
    return base64_decode(strtr($padded, '-_', '+/'));
}

// Converts an EC signature from OpenSSL's DER ASN.1 encoding (a SEQUENCE
// of two INTEGERs, r and s - each INTEGER possibly carrying a leading
// 0x00 padding byte if its high bit would otherwise look like a negative
// number) to the fixed-width raw r||s concatenation JWT's ES256 requires.
// $componentSize is 32 for the P-256 curve Apple's key uses.
function der_to_raw_ecdsa(string $der, int $componentSize): string
{
    $offset = 2; // skip the outer SEQUENCE tag (0x30) and its length byte
    $r = der_read_integer($der, $offset, $componentSize);
    $s = der_read_integer($der, $offset, $componentSize);
    return $r . $s;
}

function der_read_integer(string $der, int &$offset, int $componentSize): string
{
    // Expect an INTEGER tag (0x02) then its length byte.
    $offset++; // tag
    $len = ord($der[$offset]);
    $offset++;
    $value = substr($der, $offset, $len);
    $offset += $len;

    // Strip a single leading 0x00 padding byte (added when the top bit of
    // the actual value would otherwise be mistaken for a sign bit).
    if (strlen($value) > $componentSize && $value[0] === "\x00") {
        $value = substr($value, 1);
    }
    // Left-pad with zero bytes if the value came out shorter.
    return str_pad($value, $componentSize, "\x00", STR_PAD_LEFT);
}

// Decodes (but does not cryptographically verify) the id_token Apple's
// own token endpoint returns directly over TLS - the same trust decision
// this app already makes for Google's and Facebook's server-to-server
// responses in oauth_http_json() above: the token came straight from
// Apple's server, not through the user's browser, so there's no
// untrusted party in a position to forge it in transit. Full JWKS
// signature verification would be needed if this token could arrive by
// any other path (e.g. Apple's native "form_post" body from the client
// side) - it can't, since oauth_apple_callback.php only reads the
// id_token out of the same POST body Apple's server just built for us.
function apple_decode_id_token(string $idToken): ?array
{
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        return null;
    }
    $claims = json_decode(base64url_decode($parts[1]), true);
    return is_array($claims) ? $claims : null;
}

// Logs a resolved user in exactly the way login.php does after a password
// check succeeds - same session key, same failed-attempt reset.
function oauth_login_user(array $user): void
{
    db()->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
    $_SESSION['user_id'] = (int)$user['id'];
}

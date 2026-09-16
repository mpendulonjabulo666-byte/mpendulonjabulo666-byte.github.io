<?php
require_once __DIR__ . '/functions.php';

// Minimal OAuth2 "authorization code" sign-in for Google and Facebook,
// added alongside the redesigned login/register pages. Both providers
// ship with EMPTY credentials by default (config.php) - the same pattern
// already used for GEMINI_API_KEY and the PayFast sandbox defaults
// elsewhere in this app: the feature is inert, not broken, until real
// credentials are added, and the buttons show disabled rather than
// erroring when clicked.
//
// Deliberately hand-rolled rather than an SDK - neither provider ships
// one that works without Composer, which this app doesn't use - but
// checked against each provider's own current published flow rather than
// guessed:
//   Google:   developers.google.com/identity/protocols/oauth2/web-server
//   Facebook: developers.facebook.com/docs/facebook-login/guides/advanced/manual-flow

function oauth_google_configured(): bool
{
    return GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '';
}

function oauth_facebook_configured(): bool
{
    return FACEBOOK_APP_ID !== '' && FACEBOOK_APP_SECRET !== '';
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

// Finds the existing account for a confirmed OAuth email, or creates one.
// Callers must only pass an email the provider has itself verified - see
// the checks in each oauth_*_callback.php before this is ever reached.
// A newly created account gets a password_hash nobody knows (a random
// value, not blank or null), so it can't be signed into with a guessed or
// empty password - only via OAuth again, or by setting a real one through
// "forgot password" first.
function oauth_find_or_create_user(string $email, string $name): array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        return $user;
    }

    $isFirstUser = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
    $unusablePassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, is_admin) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name !== '' ? $name : 'NutriTale user', $email, $unusablePassword, $isFirstUser ? 1 : 0]);

    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int)db()->lastInsertId()]);
    return $stmt->fetch();
}

// Logs a resolved user in exactly the way login.php does after a password
// check succeeds - same session key, same failed-attempt reset.
function oauth_login_user(array $user): void
{
    db()->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')->execute([$user['id']]);
    $_SESSION['user_id'] = (int)$user['id'];
}

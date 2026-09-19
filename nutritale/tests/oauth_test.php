<?php
// Tests the pure logic in includes/oauth.php against fixture values, no
// database:
//   - oauth_provider_configured() - each provider's "is it set up?" check
//   - oauth_resolve_account() - the new-signup/link/existing-login decision
//
//   php tests/oauth_test.php
//
// Deliberately not testing oauth_find_or_create_user(), oauth_login_user(),
// apple_oauth_client_secret(), or the oauth_*.php/oauth_*_callback.php
// routes here: all are thin DB/network wrappers (or call out to Apple's
// live token endpoint) with no decision logic of their own to get wrong -
// verified instead against the live dev database and, for Apple's JWT
// client secret, jwt.io's own decoder - see CONTINUE.md's OAuth step for
// how those were checked.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/oauth.php';

$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        return;
    }
    $fail++;
    echo "FAIL  $label\n";
}

function user(string $provider, string $oauthId): array
{
    return ['id' => 1, 'oauth_provider' => $provider, 'oauth_id' => $oauthId, 'email' => 'someone@example.com'];
}

// --- oauth_provider_configured() -------------------------------------------

check('all values non-blank -> configured', oauth_provider_configured('id', 'secret') === true);
check('one blank value -> not configured', oauth_provider_configured('id', '') === false);
check('first value blank -> not configured', oauth_provider_configured('', 'secret') === false);
check('no values at all -> configured (vacuously true)', oauth_provider_configured() === true);
check('four values, one blank in the middle (Apple shape) -> not configured', oauth_provider_configured('client', 'team', '', 'key') === false);
check('four values, all set (Apple shape) -> configured', oauth_provider_configured('client', 'team', 'key', 'pem') === true);

// --- oauth_resolve_account() ------------------------------------------------

$byId = user('google', 'g-123');
$result = oauth_resolve_account($byId, null);
check('exact (provider, oauth_id) match -> use_existing', $result['action'] === 'use_existing');
check('use_existing returns the provider-id match, not null', $result['user'] === $byId);

$byEmail = ['id' => 2, 'oauth_provider' => null, 'oauth_id' => null, 'email' => 'someone@example.com'];
$result = oauth_resolve_account(null, $byEmail);
check('no provider-id match but email matches -> link', $result['action'] === 'link');
check('link returns the email match', $result['user'] === $byEmail);

$result = oauth_resolve_account(null, null);
check('neither matches -> create', $result['action'] === 'create');
check('create has no user to return', $result['user'] === null);

// A provider-id match must win even when an (unrelated) email match is also
// passed in - it never should be in practice (the DB query only looks up
// one or the other), but the decision function shouldn't silently prefer
// the wrong one if it ever were.
$result = oauth_resolve_account($byId, $byEmail);
check('provider-id match takes priority over an email match', $result['action'] === 'use_existing' && $result['user'] === $byId);

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);

<?php
require_once __DIR__ . '/config/config.php';

$_SESSION = [];
// session_destroy() alone only clears server-side session data - the
// browser would still hold the now-useless cookie until it naturally
// expires. Explicitly expiring it too (same hardened flags config.php's
// session_set_cookie_params() sets, so this doesn't get silently ignored
// the same way the old "remember me" setcookie() call was) is real
// defense in depth, not just tidiness - it also invalidates a "remember
// me" cookie set with a 30-day lifetime immediately, rather than leaving
// a dead cookie sitting in the browser for a month.
setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => '',
    'secure' => is_https_request(),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_destroy();

header('Location: login.php');
exit;

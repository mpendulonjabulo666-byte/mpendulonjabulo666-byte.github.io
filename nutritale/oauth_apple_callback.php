<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/oauth.php';

if (!oauth_apple_configured()) {
    http_response_code(404);
    die('Apple sign-in is not configured on this server.');
}

// Apple POSTs here (response_mode=form_post, required whenever the "name
// email" scope is requested) rather than the GET redirect Google/Facebook
// use - everything below reads $_POST, not $_GET.
if (!oauth_verify_state('apple', $_POST['state'] ?? null)) {
    flash_set('error', "That sign-in link expired or was invalid — please try again.");
    redirect('login.php');
}

if (isset($_POST['error']) || !isset($_POST['code'])) {
    redirect('login.php'); // cancelled at Apple's consent screen
}

$token = oauth_http_json('POST', 'https://appleid.apple.com/auth/token', [
    'code' => $_POST['code'],
    'client_id' => APPLE_OAUTH_CLIENT_ID,
    'client_secret' => apple_oauth_client_secret(),
    'redirect_uri' => app_base_url() . 'oauth_apple_callback.php',
    'grant_type' => 'authorization_code',
]);

if (!$token || empty($token['id_token'])) {
    flash_set('error', "Couldn't complete Apple sign-in. Please try again.");
    redirect('login.php');
}

$claims = apple_decode_id_token($token['id_token']);

// Apple encodes this as the string "true"/"false", not a JSON boolean -
// only ever create or sign into an account on an address it has
// confirmed, exactly like the Google/Facebook checks above.
if (!$claims || empty($claims['email']) || empty($claims['sub'])
    || !in_array($claims['email_verified'] ?? '', [true, 'true'], true)) {
    flash_set('error', "Apple didn't confirm a verified email address, so we couldn't sign you in.");
    redirect('login.php');
}

// Apple only ever includes the person's name once, on the very first
// authorization, as a separate JSON blob in the form post - never again
// on subsequent sign-ins, so there's nothing to fall back to but blank
// (oauth_find_or_create_user() already treats a blank name as fine).
$name = '';
if (!empty($_POST['user'])) {
    $userInfo = json_decode($_POST['user'], true);
    if (is_array($userInfo) && !empty($userInfo['name'])) {
        $name = trim(($userInfo['name']['firstName'] ?? '') . ' ' . ($userInfo['name']['lastName'] ?? ''));
    }
}

$user = oauth_find_or_create_user('apple', $claims['sub'], $claims['email'], $name);
oauth_login_user($user);
redirect($user['is_admin'] ? 'admin.php' : 'index.php');

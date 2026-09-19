<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/oauth.php';

if (!oauth_facebook_configured()) {
    http_response_code(404);
    die('Facebook sign-in is not configured on this server.');
}

if (!oauth_verify_state('facebook', $_GET['state'] ?? null)) {
    flash_set('error', "That sign-in link expired or was invalid — please try again.");
    redirect('login.php');
}

if (isset($_GET['error']) || !isset($_GET['code'])) {
    redirect('login.php'); // cancelled at Facebook's consent screen
}

$token = oauth_http_json('GET', 'https://graph.facebook.com/v25.0/oauth/access_token', [
    'client_id' => FACEBOOK_APP_ID,
    'client_secret' => FACEBOOK_APP_SECRET,
    'redirect_uri' => app_base_url() . 'oauth_facebook_callback.php',
    'code' => $_GET['code'],
]);

if (!$token || empty($token['access_token'])) {
    flash_set('error', "Couldn't complete Facebook sign-in. Please try again.");
    redirect('login.php');
}

$profile = oauth_http_json('GET', 'https://graph.facebook.com/v25.0/me', [
    'fields' => 'id,name,email',
    'access_token' => $token['access_token'],
]);

// Unlike Google, Facebook has no separate "is this verified" flag - it
// only ever returns this field for a confirmed address, omitting it
// entirely otherwise (e.g. a phone-only account). No email here means no
// way to match or create an account in an app that's keyed by email
// throughout, so this is a hard stop either way.
if (!$profile || empty($profile['email']) || empty($profile['id'])) {
    flash_set('error', "Facebook didn't share a confirmed email address, so we couldn't sign you in.");
    redirect('login.php');
}

$user = oauth_find_or_create_user('facebook', $profile['id'], $profile['email'], $profile['name'] ?? '');
oauth_login_user($user);
redirect($user['is_admin'] ? 'admin.php' : 'index.php');

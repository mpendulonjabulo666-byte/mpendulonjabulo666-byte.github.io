<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/oauth.php';

if (!oauth_google_configured()) {
    http_response_code(404);
    die('Google sign-in is not configured on this server.');
}

if (!oauth_verify_state('google', $_GET['state'] ?? null)) {
    flash_set('error', "That sign-in link expired or was invalid — please try again.");
    redirect('login.php');
}

if (isset($_GET['error']) || !isset($_GET['code'])) {
    redirect('login.php'); // cancelled at Google's consent screen
}

$token = oauth_http_json('POST', 'https://oauth2.googleapis.com/token', [
    'code' => $_GET['code'],
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => app_base_url() . 'oauth_google_callback.php',
    'grant_type' => 'authorization_code',
]);

if (!$token || empty($token['access_token'])) {
    flash_set('error', "Couldn't complete Google sign-in. Please try again.");
    redirect('login.php');
}

$profile = oauth_http_json('GET', 'https://openidconnect.googleapis.com/v1/userinfo', [
    'access_token' => $token['access_token'],
]);

// Google explicitly signals whether it has confirmed this address belongs
// to the person signing in - never create or log into an account on the
// strength of one it hasn't.
if (!$profile || empty($profile['email']) || empty($profile['email_verified']) || empty($profile['sub'])) {
    flash_set('error', "Google didn't confirm a verified email address, so we couldn't sign you in.");
    redirect('login.php');
}

$user = oauth_find_or_create_user('google', $profile['sub'], $profile['email'], $profile['name'] ?? '');
oauth_login_user($user);
redirect($user['is_admin'] ? 'admin.php' : 'index.php');

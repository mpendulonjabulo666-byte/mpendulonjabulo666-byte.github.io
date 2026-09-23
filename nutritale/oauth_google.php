<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/oauth.php';

if (!oauth_google_configured()) {
    http_response_code(404);
    die('Google sign-in is not configured on this server.');
}

$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => app_base_url() . 'oauth_google_callback.php',
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => oauth_new_state('google'),
    'prompt' => 'select_account',
];
header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));

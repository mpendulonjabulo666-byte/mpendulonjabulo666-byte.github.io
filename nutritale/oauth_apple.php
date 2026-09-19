<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/oauth.php';

if (!oauth_apple_configured()) {
    http_response_code(404);
    die('Apple sign-in is not configured on this server.');
}

// Requesting the "email name" scope obligates Apple to POST the result
// back rather than redirect with a GET - see oauth_apple_callback.php.
$params = [
    'client_id' => APPLE_OAUTH_CLIENT_ID,
    'redirect_uri' => app_base_url() . 'oauth_apple_callback.php',
    'response_type' => 'code',
    'response_mode' => 'form_post',
    'scope' => 'name email',
    'state' => oauth_new_state('apple'),
];
header('Location: https://appleid.apple.com/auth/authorize?' . http_build_query($params));

<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/oauth.php';

if (!oauth_facebook_configured()) {
    http_response_code(404);
    die('Facebook sign-in is not configured on this server.');
}

$params = [
    'client_id' => FACEBOOK_APP_ID,
    'redirect_uri' => app_base_url() . 'oauth_facebook_callback.php',
    'state' => oauth_new_state('facebook'),
    'scope' => 'email,public_profile',
];
header('Location: https://www.facebook.com/v25.0/dialog/oauth?' . http_build_query($params));

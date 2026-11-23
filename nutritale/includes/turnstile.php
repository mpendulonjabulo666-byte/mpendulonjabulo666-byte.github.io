<?php
// Cloudflare Turnstile helpers. Off (always passes) until TURNSTILE_SECRET_KEY
// is set in config.local.php, so deploying this can never lock anyone out.

function turnstile_enabled(): bool
{
    return TURNSTILE_SECRET_KEY !== '' && TURNSTILE_SITE_KEY !== '';
}

/** Widget markup + script. Empty string when Turnstile is off. */
function turnstile_widget(): string
{
    if (!turnstile_enabled()) {
        return '';
    }
    return '<div class="cf-turnstile" data-sitekey="' . h(TURNSTILE_SITE_KEY) . '" style="margin:12px 0;"></div>'
        . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
}

/**
 * True when the request passed the check (or Turnstile is off).
 * A definite "failed" answer from Cloudflare blocks. If Cloudflare itself
 * cannot be reached (host blocks outbound calls, outage) we let the request
 * through rather than lock every real user out; the login rate limit and
 * CSRF check still apply.
 */
function turnstile_verify(?string $token, string $ip = ''): bool
{
    if (!turnstile_enabled()) {
        return true;
    }
    if ($token === null || $token === '' || strlen($token) > 2048) {
        return false;
    }
    $body = http_build_query(array_filter([
        'secret' => TURNSTILE_SECRET_KEY,
        'response' => $token,
        'remoteip' => $ip,
    ]));
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6]);
        $raw = curl_exec($ch);
        curl_close($ch);
    }
    if ($raw === false || $raw === '') {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 6,
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $body]]);
        $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
    }
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        error_log('Turnstile: verification service unreachable, allowing request');
        return true;
    }
    return !empty($data['success']);
}

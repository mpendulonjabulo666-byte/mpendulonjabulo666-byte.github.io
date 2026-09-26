<?php
// Minimal PayFast integration: signature generation, process URL, and
// ITN (Instant Transaction Notification) validation, per PayFast's
// published integration guide (https://developers.payfast.co.za).

function payfast_new_payment_id(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function payfast_process_url(): string
{
    return PAYFAST_SANDBOX ? 'https://sandbox.payfast.co.za/eng/process' : 'https://www.payfast.co.za/eng/process';
}

function payfast_validate_url(): string
{
    return PAYFAST_SANDBOX ? 'https://sandbox.payfast.co.za/eng/query/validate' : 'https://www.payfast.co.za/eng/query/validate';
}

// $data must be an ordered array (insertion order matters — PayFast
// signs the fields in the order they're sent, not alphabetically).
function payfast_signature(array $data, string $passphrase = ''): string
{
    $pairs = [];
    foreach ($data as $key => $value) {
        if ($value === '' || $value === null) continue;
        $pairs[] = $key . '=' . urlencode(trim((string)$value));
    }
    $paramString = implode('&', $pairs);
    if ($passphrase !== '') {
        $paramString .= '&passphrase=' . urlencode(trim($passphrase));
    }
    return md5($paramString);
}

// CONTINUE.md §2.5: verifies an ITN request actually originated from
// PayFast's own servers, before payfast_notify.php does anything else with
// it. Resolves PayFast's published hostnames via DNS at request time
// rather than hardcoding IPs — PayFast's infrastructure can change, and a
// stale static list would eventually either block real payments or quietly
// stop protecting anything. This is the same approach long-standing
// third-party PayFast integrations use (e.g. WooCommerce's and
// ClientExec's PayFast gateways both resolve this same hostname list via
// gethostbynamel() and compare against REMOTE_ADDR).
//
// Assumes the app is reached directly, with no reverse proxy/CDN in front
// rewriting REMOTE_ADDR — true of both of DEPLOYMENT.md's recommended
// production paths (shared hosting, a plain VPS). It is NOT true of
// Railway (DEPLOYMENT.md Path C), which DEPLOYMENT.md already marks
// "not recommended for production" for unrelated reasons; a deployment
// behind a proxy would need to trust that proxy's forwarded-for header
// instead, which isn't implemented here since it's specific to whichever
// proxy is in front and there isn't one on either recommended path.
//
// Fails OPEN (returns true, logs a warning) only when DNS resolution
// itself is completely unavailable for every hostname — a transient
// failure of this server's own resolver shouldn't silently drop every
// real payment notification. This check is defense in depth on top of
// the signature check and payfast_confirm_with_payfast() below, which
// independently authenticates the payload's content by asking PayFast to
// confirm it themselves — not the only line of defence, so failing open
// here on a local DNS hiccup doesn't leave the endpoint unauthenticated.
// $resolver exists so the allow/deny/fail-open logic can be tested
// without a real DNS lookup (see tests/payfast_test.php) — the same
// pattern gemini_pantry_ideas() uses for $generator. Production callers
// leave it null and get PHP's own gethostbynamel().
function payfast_request_is_from_payfast(string $remoteAddr, ?callable $resolver = null): bool
{
    $resolver = $resolver ?? 'gethostbynamel';
    $hosts = ['www.payfast.co.za', 'sandbox.payfast.co.za', 'w1w.payfast.co.za', 'w2w.payfast.co.za'];
    $validIps = [];
    $resolvedAny = false;
    foreach ($hosts as $host) {
        $ips = $resolver($host);
        if ($ips !== false) {
            $resolvedAny = true;
            $validIps = array_merge($validIps, $ips);
        }
    }
    if (!$resolvedAny) {
        error_log('PayFast ITN: could not resolve any PayFast hostname to check the source IP against — allowing the request through on the signature + VALID-confirmation checks alone.');
        return true;
    }
    return in_array($remoteAddr, array_unique($validIps), true);
}

// Confirms an ITN payload with PayFast's servers (required — PayFast
// says never trust the ITN POST alone). Returns true only if PayFast
// itself echoes back "VALID".
function payfast_confirm_with_payfast(array $postData): bool
{
    $body = http_build_query($postData);
    $ch = curl_init(payfast_validate_url());
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return is_string($response) && trim($response) === 'VALID';
}

// --- PayFast's REST API (subscriptions: fetch/pause/cancel/update/adhoc) —
// a different, newer API from the checkout/ITN flow above, with its own
// signature scheme and its own single host for both live and sandbox
// (sandbox is a `?testing=true` query param, not a separate subdomain).
// CONTINUE.md §2.5's Step 12: lets a user cancel their own subscription
// instead of the only options being PayFast's own ITN or letting it lapse.
//
// The algorithm below is copied from, and cross-checked line-by-line
// against, PayFast's own official PHP SDK
// (github.com/PayFast/payfast-php-sdk, lib/Auth.php's
// generateApiSignature() and lib/Request.php's sendApiRequest()) rather
// than guessed — a wrong signature either breaks every call outright
// (401) or, worse, could look plausible and fail unpredictably. One
// specific, easy-to-get-backwards detail confirmed straight from their
// Request.php: the sandbox `testing=true` flag is sent on the actual
// request but deliberately EXCLUDED from what gets signed.

// Signs a call to the PayFast REST API. $data is every header value plus
// any query/JSON body data for this specific call — never the `testing`
// sandbox flag, which PayFast's own SDK deliberately excludes from the
// signature too (see the file-level comment above).
function payfast_api_signature(array $data, string $passphrase): string
{
    if ($passphrase !== '') {
        $data['passphrase'] = $passphrase;
    }
    ksort($data);
    $pairs = [];
    foreach ($data as $key => $value) {
        if ($key === 'signature') continue;
        $pairs[] = $key . '=' . urlencode((string)$value);
    }
    return md5(implode('&', $pairs));
}

// One authenticated call to PayFast's subscriptions API. $path is appended
// to https://api.payfast.co.za/ (e.g. "subscriptions/$token/cancel");
// $body, if given, is sent as the JSON body and also folded into the
// signature, matching the official SDK. Every call this app makes so far
// needs no body.
function payfast_api_request(string $method, string $path, array $body = []): array
{
    $headers = [
        'merchant-id' => PAYFAST_MERCHANT_ID,
        'version' => 'v1',
        'timestamp' => date('Y-m-d\TH:i:sO'),
    ];
    $headers['signature'] = payfast_api_signature(array_merge($headers, $body), PAYFAST_PASSPHRASE);

    $url = 'https://api.payfast.co.za/' . ltrim($path, '/');
    if (PAYFAST_SANDBOX) {
        $url .= '?testing=true'; // added to the request only, never to the signature — see above
    }

    $headerLines = ['Content-Type: application/json'];
    foreach ($headers as $key => $value) {
        $headerLines[] = "$key: $value";
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headerLines,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'Could not reach PayFast (' . $curlErr . ').'];
    }
    $data = json_decode($response, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        return ['ok' => false, 'error' => $data['message'] ?? "PayFast returned HTTP $httpCode.", 'http_code' => $httpCode];
    }
    return ['ok' => true, 'data' => $data];
}

// Cancels a subscription. $token is the subscription's pf_token, captured
// from the ITN history in premium_subscriptions — distinct from any one
// payment's m_payment_id, and the identifier PayFast's recurring-billing
// side uses for the ongoing subscription itself.
function payfast_cancel_subscription(string $token): array
{
    return payfast_api_request('PUT', "subscriptions/$token/cancel");
}

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

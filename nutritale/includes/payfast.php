<?php
// Minimal PayFast integration: signature generation, process URL, and
// ITN (Instant Transaction Notification) validation, per PayFast's
// published integration guide (https://developers.payfast.co.za).

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

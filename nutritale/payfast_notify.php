<?php
// PayFast posts payment status updates here (the "notify_url"). This is
// called server-to-server by PayFast, not by a logged-in browser — no
// session/CSRF here, and it must always respond 200 quickly.
//
// Production hardening this file intentionally skips for the sandbox
// build: verifying the request came from a genuine PayFast IP range, and
// checking the payment total against expected fees. Do that before
// accepting real money.

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/payfast.php';

http_response_code(200);

$post = $_POST;
if (!$post || !isset($post['m_payment_id'], $post['signature'])) {
    exit;
}

$receivedSignature = $post['signature'];
$dataForSignature = $post;
unset($dataForSignature['signature']);
$expectedSignature = payfast_signature($dataForSignature, PAYFAST_PASSPHRASE);

if (!hash_equals($expectedSignature, $receivedSignature)) {
    error_log('PayFast ITN: signature mismatch for m_payment_id ' . $post['m_payment_id']);
    exit;
}

if (!payfast_confirm_with_payfast($post)) {
    error_log('PayFast ITN: PayFast did not confirm payload as VALID for m_payment_id ' . $post['m_payment_id']);
    exit;
}

$stmt = db()->prepare('SELECT * FROM recipe_purchases WHERE m_payment_id = ?');
$stmt->execute([$post['m_payment_id']]);
$purchase = $stmt->fetch();

if (!$purchase) {
    error_log('PayFast ITN: unknown m_payment_id ' . $post['m_payment_id']);
    exit;
}

$expectedAmount = number_format((float)$purchase['amount'], 2, '.', '');
$receivedAmount = number_format((float)($post['amount_gross'] ?? $post['amount'] ?? 0), 2, '.', '');
if ($expectedAmount !== $receivedAmount) {
    error_log('PayFast ITN: amount mismatch for ' . $post['m_payment_id'] . " expected $expectedAmount got $receivedAmount");
    exit;
}

$status = strtoupper($post['payment_status'] ?? '');
$newStatus = match ($status) {
    'COMPLETE' => 'paid',
    'FAILED' => 'failed',
    'CANCELLED' => 'cancelled',
    default => null,
};

if ($newStatus) {
    $upd = db()->prepare('UPDATE recipe_purchases SET status = ?, pf_payment_id = ? WHERE m_payment_id = ?');
    $upd->execute([$newStatus, $post['pf_payment_id'] ?? null, $post['m_payment_id']]);
}

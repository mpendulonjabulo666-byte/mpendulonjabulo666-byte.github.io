<?php
// PayFast posts payment status updates here (the "notify_url") for every
// transaction type this app creates: once-off recipe purchases, once-off
// ingredient marketplace orders, and recurring Premium subscription
// billing (both the first charge and every monthly renewal). This is
// called server-to-server by PayFast, not by a logged-in browser — no
// session/CSRF here, and it must always respond 200 quickly.
//
// Production hardening this file intentionally skips for the sandbox
// build: verifying the request came from a genuine PayFast IP range.
// Do that before accepting real money.

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/payfast.php';

http_response_code(200);

$post = $_POST;
if (!$post || !isset($post['signature'])) {
    exit;
}

$receivedSignature = $post['signature'];
$dataForSignature = $post;
unset($dataForSignature['signature']);
$expectedSignature = payfast_signature($dataForSignature, PAYFAST_PASSPHRASE);

if (!hash_equals($expectedSignature, $receivedSignature)) {
    error_log('PayFast ITN: signature mismatch for m_payment_id ' . ($post['m_payment_id'] ?? '?'));
    exit;
}

if (!payfast_confirm_with_payfast($post)) {
    error_log('PayFast ITN: PayFast did not confirm payload as VALID for m_payment_id ' . ($post['m_payment_id'] ?? '?'));
    exit;
}

$status = strtoupper($post['payment_status'] ?? '');
$newStatus = match ($status) {
    'COMPLETE' => 'paid',
    'FAILED' => 'failed',
    'CANCELLED' => 'cancelled',
    default => null,
};
$mPaymentId = $post['m_payment_id'] ?? '';
$pfToken = $post['token'] ?? null;

// 1. Premium subscription — first charge (matched by m_payment_id) or a
//    monthly renewal charge (PayFast reuses the same token but issues a
//    fresh m_payment_id/pf_payment_id we've never seen).
$subStmt = db()->prepare('SELECT * FROM premium_subscriptions WHERE m_payment_id = ?');
$subStmt->execute([$mPaymentId]);
$sub = $subStmt->fetch();

if (!$sub && $pfToken) {
    $subStmt = db()->prepare('SELECT * FROM premium_subscriptions WHERE pf_token = ? ORDER BY created_at DESC LIMIT 1');
    $subStmt->execute([$pfToken]);
    $sub = $subStmt->fetch();
}

if ($sub) {
    if ($newStatus === 'paid') {
        db()->prepare('UPDATE premium_subscriptions SET status = ?, pf_token = ? WHERE id = ?')
            ->execute(['active', $pfToken ?? $sub['pf_token'], $sub['id']]);
        db()->prepare('UPDATE users SET is_premium_member = 1 WHERE id = ?')->execute([$sub['user_id']]);
    } elseif ($newStatus === 'failed') {
        db()->prepare('UPDATE premium_subscriptions SET status = ? WHERE id = ?')->execute(['past_due', $sub['id']]);
    } elseif ($newStatus === 'cancelled') {
        db()->prepare('UPDATE premium_subscriptions SET status = ? WHERE id = ?')->execute(['cancelled', $sub['id']]);
        db()->prepare('UPDATE users SET is_premium_member = 0 WHERE id = ?')->execute([$sub['user_id']]);
    }
    exit;
}

// 2. Premium recipe purchase.
$purchaseStmt = db()->prepare('SELECT * FROM recipe_purchases WHERE m_payment_id = ?');
$purchaseStmt->execute([$mPaymentId]);
$purchase = $purchaseStmt->fetch();

if ($purchase) {
    $expectedAmount = number_format((float)$purchase['amount'], 2, '.', '');
    $receivedAmount = number_format((float)($post['amount_gross'] ?? $post['amount'] ?? 0), 2, '.', '');
    if ($expectedAmount !== $receivedAmount) {
        error_log("PayFast ITN: recipe purchase amount mismatch for $mPaymentId expected $expectedAmount got $receivedAmount");
        exit;
    }
    if ($newStatus) {
        [$fee, $vendorAmount] = $newStatus === 'paid' ? platform_fee_split((float)$purchase['amount']) : [null, null];
        db()->prepare('UPDATE recipe_purchases SET status = ?, pf_payment_id = ?, platform_fee = ?, vendor_amount = ? WHERE m_payment_id = ?')
            ->execute([$newStatus, $post['pf_payment_id'] ?? null, $fee, $vendorAmount, $mPaymentId]);
    }
    exit;
}

// 3. Ingredient marketplace order.
$orderStmt = db()->prepare('SELECT * FROM ingredient_orders WHERE m_payment_id = ?');
$orderStmt->execute([$mPaymentId]);
$order = $orderStmt->fetch();

if ($order) {
    $expectedAmount = number_format((float)$order['amount'], 2, '.', '');
    $receivedAmount = number_format((float)($post['amount_gross'] ?? $post['amount'] ?? 0), 2, '.', '');
    if ($expectedAmount !== $receivedAmount) {
        error_log("PayFast ITN: ingredient order amount mismatch for $mPaymentId expected $expectedAmount got $receivedAmount");
        exit;
    }
    if ($newStatus) {
        db()->prepare('UPDATE ingredient_orders SET status = ?, pf_payment_id = ? WHERE m_payment_id = ?')
            ->execute([$newStatus, $post['pf_payment_id'] ?? null, $mPaymentId]);
        if ($newStatus === 'paid') {
            db()->prepare("UPDATE ingredient_listings SET status = 'sold' WHERE id = ?")->execute([$order['listing_id']]);
        }
    }
    exit;
}

error_log('PayFast ITN: unknown m_payment_id ' . $mPaymentId);

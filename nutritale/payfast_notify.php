<?php
// PayFast posts payment status updates here (the "notify_url") for every
// transaction type this app creates: once-off recipe purchases, once-off
// ingredient marketplace orders, and recurring Premium subscription
// billing (both the first charge and every monthly renewal). This is
// called server-to-server by PayFast, not by a logged-in browser — no
// session/CSRF here, and it must always respond 200 quickly.

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/payfast.php';

http_response_code(200);

$post = $_POST;
if (!$post || !isset($post['signature'])) {
    exit;
}

// Cheapest check first, before touching the DB or calling PayFast at all
// — see payfast_request_is_from_payfast()'s own comment for what this
// does and doesn't cover.
if (!payfast_request_is_from_payfast($_SERVER['REMOTE_ADDR'] ?? '')) {
    error_log('PayFast ITN: request did not come from a PayFast IP (' . ($_SERVER['REMOTE_ADDR'] ?? '?') . ') for m_payment_id ' . ($post['m_payment_id'] ?? '?'));
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
        // Same check the once-off branches below already do, extended
        // here to cover a renewal charge too, not just the first one
        // (CONTINUE.md §2.5). $sub['amount'] is fixed at signup and this
        // app has no per-user pricing, so it's the correct amount for
        // every renewal of this subscription too, not just its first
        // charge.
        $expectedAmount = number_format((float)$sub['amount'], 2, '.', '');
        $receivedAmount = number_format((float)($post['amount_gross'] ?? $post['amount'] ?? 0), 2, '.', '');
        if ($expectedAmount !== $receivedAmount) {
            error_log("PayFast ITN: subscription amount mismatch for m_payment_id $mPaymentId (subscription {$sub['id']}) expected $expectedAmount got $receivedAmount");
            exit;
        }

        // Extend from whichever is later: the existing period-end (a
        // renewal that fired a little early shouldn't lose those days) or
        // now (a late-recovered renewal after a lapse shouldn't backdate
        // from a stale expiry). See premium_enforce_expiry() in
        // includes/functions_core.php for the other half of this - what
        // happens once this date passes with no further successful charge.
        $extendFrom = max(strtotime($sub['current_period_end'] ?? 'now') ?: time(), time());
        $newPeriodEnd = date('Y-m-d H:i:s', strtotime('+1 month', $extendFrom));

        db()->prepare('UPDATE premium_subscriptions SET status = ?, pf_token = ?, current_period_end = ? WHERE id = ?')
            ->execute(['active', $pfToken ?? $sub['pf_token'], $newPeriodEnd, $sub['id']]);
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

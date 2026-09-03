<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/payfast.php';

$user = require_login();

if ($user['is_premium_member']) {
    redirect('pantry.php');
}

$mPaymentId = payfast_new_payment_id();

$ins = db()->prepare('INSERT INTO premium_subscriptions (user_id, m_payment_id, amount, status) VALUES (?, ?, ?, ?)');
$ins->execute([$user['id'], $mPaymentId, PREMIUM_MONTHLY_PRICE, 'pending']);

$baseUrl = app_base_url();
$pfData = [
    'merchant_id' => PAYFAST_MERCHANT_ID,
    'merchant_key' => PAYFAST_MERCHANT_KEY,
    'return_url' => $baseUrl . 'premium_return.php?m=' . urlencode($mPaymentId),
    'cancel_url' => $baseUrl . 'premium_cancel.php?m=' . urlencode($mPaymentId),
    'notify_url' => $baseUrl . 'payfast_notify.php',
    'name_first' => $user['name'],
    'email_address' => $user['email'],
    'm_payment_id' => $mPaymentId,
    'amount' => number_format((float)PREMIUM_MONTHLY_PRICE, 2, '.', ''),
    'item_name' => APP_NAME . ' Premium (monthly)',
    'item_description' => 'Unlimited AI ingredient recommendations, billed monthly.',
    // PayFast recurring subscription fields — see
    // https://developers.payfast.co.za/docs#subscriptions
    'subscription_type' => '1',
    'billing_date' => date('Y-m-d'),
    'recurring_amount' => number_format((float)PREMIUM_MONTHLY_PRICE, 2, '.', ''),
    'frequency' => '3', // 3 = monthly
    'cycles' => '0', // 0 = bill indefinitely, until cancelled
];
$pfData['signature'] = payfast_signature($pfData, PAYFAST_PASSPHRASE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Checkout · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <div class="center-text mb-16"><?= nutritale_logo_svg(56) ?></div>
        <h1 class="center-text">Redirecting to secure payment</h1>
        <div class="card center-text">
            <p class="muted">Setting up your <strong>R<?= number_format((float)PREMIUM_MONTHLY_PRICE, 2) ?>/month</strong> Premium subscription via PayFast.</p>
            <?php if (PAYFAST_SANDBOX): ?>
                <p class="muted" style="font-size:12px;">Sandbox mode — no real money moves. Use PayFast's test card details on the next screen.</p>
            <?php endif; ?>
            <form method="post" action="<?= h(payfast_process_url()) ?>" id="pf-form">
                <?php foreach ($pfData as $key => $value): ?>
                    <input type="hidden" name="<?= h($key) ?>" value="<?= h($value) ?>">
                <?php endforeach; ?>
                <button type="submit" class="btn btn-primary btn-block">Continue to PayFast</button>
            </form>
        </div>
    </div>
</div>
<script>document.getElementById('pf-form').submit();</script>
</body>
</html>

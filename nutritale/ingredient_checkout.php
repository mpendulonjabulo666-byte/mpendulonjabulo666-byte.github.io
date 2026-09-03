<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/payfast.php';

$user = require_login();

$listingId = (int)($_GET['listing_id'] ?? 0);
$stmt = db()->prepare("SELECT * FROM ingredient_listings WHERE id = ? AND status = 'available'");
$stmt->execute([$listingId]);
$listing = $stmt->fetch();

if (!$listing) {
    http_response_code(404);
    die('This listing is no longer available.');
}
if ((int)$listing['seller_id'] === (int)$user['id']) {
    redirect('listing.php?id=' . $listingId);
}

$mPaymentId = payfast_new_payment_id();
[$platformFee, $sellerAmount] = platform_fee_split((float)$listing['price']);

$ins = db()->prepare(
    'INSERT INTO ingredient_orders (m_payment_id, listing_id, buyer_id, seller_id, amount, platform_fee, seller_amount, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);
$ins->execute([$mPaymentId, $listing['id'], $user['id'], $listing['seller_id'], $listing['price'], $platformFee, $sellerAmount, 'pending']);

$baseUrl = app_base_url();
$pfData = [
    'merchant_id' => PAYFAST_MERCHANT_ID,
    'merchant_key' => PAYFAST_MERCHANT_KEY,
    'return_url' => $baseUrl . 'ingredient_checkout_return.php?m=' . urlencode($mPaymentId),
    'cancel_url' => $baseUrl . 'ingredient_checkout_cancel.php?m=' . urlencode($mPaymentId),
    'notify_url' => $baseUrl . 'payfast_notify.php',
    'name_first' => $user['name'],
    'email_address' => $user['email'],
    'm_payment_id' => $mPaymentId,
    'amount' => number_format((float)$listing['price'], 2, '.', ''),
    'item_name' => mb_substr($listing['ingredient_name'], 0, 100),
    'item_description' => 'Ingredient marketplace purchase on ' . APP_NAME,
];
$pfData['signature'] = payfast_signature($pfData, PAYFAST_PASSPHRASE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Checkout · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <div class="center-text mb-16"><?= nutritale_logo_svg(56) ?></div>
        <h1 class="center-text">Redirecting to secure payment</h1>
        <div class="card center-text">
            <p class="muted">Taking you to PayFast to pay <strong>R<?= number_format((float)$listing['price'], 2) ?></strong> for <strong><?= h($listing['ingredient_name']) ?></strong> (<?= h($listing['quantity']) ?>).</p>
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

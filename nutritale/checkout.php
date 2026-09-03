<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/payfast.php';

$user = require_login();

$recipeId = $_GET['recipe_id'] ?? '';
$stmt = db()->prepare('SELECT * FROM recipes WHERE id = ? AND is_premium = 1');
$stmt->execute([$recipeId]);
$recipe = $stmt->fetch();

if (!$recipe) {
    http_response_code(404);
    die('This recipe is not available for purchase.');
}
if ((int)$recipe['created_by'] === (int)$user['id']) {
    redirect('recipe.php?id=' . urlencode($recipeId));
}

$existingStmt = db()->prepare("SELECT 1 FROM recipe_purchases WHERE buyer_id = ? AND recipe_id = ? AND status = 'paid'");
$existingStmt->execute([$user['id'], $recipeId]);
if ($existingStmt->fetch()) {
    redirect('recipe.php?id=' . urlencode($recipeId));
}

$mPaymentId = sprintf(
    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);

$ins = db()->prepare(
    'INSERT INTO recipe_purchases (m_payment_id, buyer_id, recipe_id, vendor_id, amount, status) VALUES (?, ?, ?, ?, ?, ?)'
);
$ins->execute([$mPaymentId, $user['id'], $recipeId, $recipe['created_by'], $recipe['price'], 'pending']);

$baseUrl = app_base_url();
$pfData = [
    'merchant_id' => PAYFAST_MERCHANT_ID,
    'merchant_key' => PAYFAST_MERCHANT_KEY,
    'return_url' => $baseUrl . 'checkout_return.php?m=' . urlencode($mPaymentId),
    'cancel_url' => $baseUrl . 'checkout_cancel.php?m=' . urlencode($mPaymentId),
    'notify_url' => $baseUrl . 'payfast_notify.php',
    'name_first' => $user['name'],
    'email_address' => $user['email'],
    'm_payment_id' => $mPaymentId,
    'amount' => number_format((float)$recipe['price'], 2, '.', ''),
    'item_name' => mb_substr($recipe['title'], 0, 100),
    'item_description' => 'Premium recipe on ' . APP_NAME,
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
            <p class="muted">Taking you to PayFast to pay <strong>R<?= number_format((float)$recipe['price'], 2) ?></strong> for <strong><?= h($recipe['title']) ?></strong>.</p>
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

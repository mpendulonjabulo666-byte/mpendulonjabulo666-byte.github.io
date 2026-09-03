<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

$mPaymentId = $_GET['m'] ?? '';
$stmt = db()->prepare('SELECT o.*, l.ingredient_name FROM ingredient_orders o JOIN ingredient_listings l ON l.id = o.listing_id WHERE o.m_payment_id = ? AND o.buyer_id = ?');
$stmt->execute([$mPaymentId, $user['id']]);
$order = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment status · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <div class="center-text mb-16"><?= nutritale_logo_svg(56) ?></div>
        <div class="card center-text">
            <?php if (!$order): ?>
                <div class="alert alert-error">We couldn't find that payment.</div>
                <a class="btn btn-primary btn-block" href="marketplace.php">Back to marketplace</a>
            <?php elseif ($order['status'] === 'paid'): ?>
                <div class="alert alert-success">Payment confirmed!</div>
                <p class="muted">You bought <strong><?= h($order['ingredient_name']) ?></strong>. Arrange pickup or delivery with the seller directly.</p>
                <a class="btn btn-primary btn-block" href="marketplace.php">Back to marketplace</a>
            <?php else: ?>
                <div class="alert alert-success">Payment received by PayFast — confirming now.</div>
                <p class="muted" style="font-size:13px;">This usually only takes a few seconds.</p>
                <a class="btn btn-primary btn-block" href="marketplace.php">Back to marketplace</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

$mPaymentId = $_GET['m'] ?? '';
$stmt = db()->prepare('SELECT * FROM ingredient_orders WHERE m_payment_id = ? AND buyer_id = ?');
$stmt->execute([$mPaymentId, $user['id']]);
$order = $stmt->fetch();
if ($order && $order['status'] === 'pending') {
    db()->prepare("UPDATE ingredient_orders SET status = 'cancelled' WHERE m_payment_id = ?")->execute([$mPaymentId]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment cancelled · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=3">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <div class="center-text mb-16"><?= nutritale_logo_svg(56) ?></div>
        <div class="card center-text">
            <div class="alert alert-error">Payment cancelled — nothing was charged.</div>
            <?php if ($order): ?>
                <a class="btn btn-primary btn-block" href="listing.php?id=<?= (int)$order['listing_id'] ?>">Back to listing</a>
            <?php else: ?>
                <a class="btn btn-primary btn-block" href="marketplace.php">Back to marketplace</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

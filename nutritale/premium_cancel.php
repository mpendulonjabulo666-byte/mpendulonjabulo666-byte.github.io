<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

$mPaymentId = $_GET['m'] ?? '';
$stmt = db()->prepare('SELECT * FROM premium_subscriptions WHERE m_payment_id = ? AND user_id = ?');
$stmt->execute([$mPaymentId, $user['id']]);
$sub = $stmt->fetch();
if ($sub && $sub['status'] === 'pending') {
    db()->prepare("UPDATE premium_subscriptions SET status = 'cancelled' WHERE m_payment_id = ?")->execute([$mPaymentId]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Subscription cancelled · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <div class="center-text mb-16"><?= nutritale_logo_svg(56) ?></div>
        <div class="card center-text">
            <div class="alert alert-error">Checkout cancelled — nothing was charged.</div>
            <a class="btn btn-primary btn-block" href="premium.php">Back to Premium</a>
        </div>
    </div>
</div>
</body>
</html>

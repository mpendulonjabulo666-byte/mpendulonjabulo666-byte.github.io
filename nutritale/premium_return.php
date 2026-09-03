<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

$mPaymentId = $_GET['m'] ?? '';
$stmt = db()->prepare('SELECT * FROM premium_subscriptions WHERE m_payment_id = ? AND user_id = ?');
$stmt->execute([$mPaymentId, $user['id']]);
$sub = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Subscription status · <?= APP_NAME ?></title>
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
            <?php if (!$sub): ?>
                <div class="alert alert-error">We couldn't find that subscription attempt.</div>
                <a class="btn btn-primary btn-block" href="index.php">Back to recipes</a>
            <?php elseif ($sub['status'] === 'active'): ?>
                <div class="alert alert-success">You're Premium!</div>
                <p class="muted">Unlimited ingredient lookups are unlocked.</p>
                <a class="btn btn-primary btn-block" href="pantry.php">Try What Can I Make?</a>
            <?php else: ?>
                <div class="alert alert-success">Payment received by PayFast — confirming now.</div>
                <p class="muted" style="font-size:13px;">This usually only takes a few seconds. Refresh in a moment if Premium hasn't unlocked yet.</p>
                <a class="btn btn-primary btn-block" href="pantry.php">Go to What Can I Make?</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

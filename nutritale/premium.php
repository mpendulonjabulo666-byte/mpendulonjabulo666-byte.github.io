<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

if ($user['is_premium_member']) {
    redirect('pantry.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Go Premium · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main" style="max-width:520px;">
    <div class="page-hero-banner center-text" style="background-image:url('assets/img/banners/premium-plated.jpg');justify-content:center;">
        <div>
            <h1><?= icon('wand', 28) ?> Go Premium</h1>
            <p>Unlimited ingredient recommendations, matched to your diet.</p>
        </div>
    </div>

    <div class="card">
        <ul style="padding-left:20px;margin:0 0 20px;font-size:14px;line-height:1.9;">
            <li>Unlimited use of <strong>What Can I Make?</strong> — no trial limit</li>
            <li>Recommendations ranked to match your diet preferences first</li>
            <li>A premium badge on your profile</li>
        </ul>

        <div class="macro-pill mb-16" style="text-align:center;max-width:none;">
            <strong>R<?= number_format(PREMIUM_MONTHLY_PRICE, 2) ?></strong>
            <span>per month, cancel anytime</span>
        </div>

        <?php if (PAYFAST_SANDBOX): ?>
            <p class="muted center-text" style="font-size:12px;">Sandbox mode — no real money moves.</p>
        <?php endif; ?>

        <a href="premium_checkout.php" class="btn btn-primary btn-block">Subscribe with PayFast</a>
    </div>
</main>
</body>
</html>

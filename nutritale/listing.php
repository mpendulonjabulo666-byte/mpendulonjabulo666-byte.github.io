<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT l.*, u.name AS seller_name FROM ingredient_listings l JOIN users u ON u.id = l.seller_id WHERE l.id = ?');
$stmt->execute([$id]);
$listing = $stmt->fetch();

if (!$listing) {
    http_response_code(404);
    die('Listing not found.');
}

$isOwn = (int)$listing['seller_id'] === (int)$user['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($listing['ingredient_name']) ?> · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js?v=24"></script>
<link rel="stylesheet" href="assets/css/style.css?v=44">
<script src="assets/js/theme-toggle.js?v=24" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main" style="max-width:520px;">
    <a href="marketplace.php" class="btn-back mb-16"><span class="btn-back-disc"><?= icon('chevron-left', 18) ?></span><span class="btn-back-label">Back to marketplace</span></a>

    <div class="card">
        <h1 style="margin:0 0 4px;font-size:22px;"><?= h($listing['ingredient_name']) ?></h1>
        <p class="muted" style="margin:0 0 16px;"><?= h($listing['quantity']) ?> · sold by <?= h($listing['seller_name']) ?></p>

        <?php if ($listing['description']): ?>
            <p style="margin:0 0 16px;"><?= nl2br(h($listing['description'])) ?></p>
        <?php endif; ?>

        <div class="macro-pill mb-16" style="text-align:center;max-width:none;">
            <strong>R<?= number_format((float)$listing['price'], 2) ?></strong>
            <span><?= $listing['status'] === 'sold' ? 'Sold' : 'Available' ?></span>
        </div>

        <?php if ($isOwn): ?>
            <p class="muted center-text" style="font-size:13px;">This is your own listing — manage it from the <a href="marketplace.php">marketplace page</a>.</p>
        <?php elseif ($listing['status'] === 'sold'): ?>
            <button class="btn btn-block" disabled>Already sold</button>
        <?php elseif (!empty($listing['is_premium']) && empty($user['is_premium_member']) && empty($user['is_admin'])): ?>
            <a href="premium.php" class="btn btn-primary btn-block">Premium members only</a>
        <?php else: ?>
            <?php $digits = preg_replace('/[^0-9+]/', '', (string)($listing['contact'] ?? '')); ?>
            <?php if ($digits !== ''): ?>
                <p style="margin:0 0 12px;"><strong>Contact the seller:</strong> <a href="tel:<?= h($digits) ?>"><?= h($listing['contact']) ?></a></p>
            <?php endif; ?>
            <p class="muted" style="font-size:13px;margin:0;">Paying inside the app is coming soon. For now, agree the price and pickup with the seller and pay them directly. <?= APP_NAME ?> does not take payment for this sale. Meet in a public place and check the ingredient before you pay.</p>
        <?php endif; ?>
    </div>
</main>
</body>
</html>

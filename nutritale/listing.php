<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($listing['ingredient_name']) ?> · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main" style="max-width:520px;">
    <a href="marketplace.php" class="muted" style="display:inline-flex;align-items:center;gap:4px;margin-bottom:16px;"><?= icon('chevron-left', 14) ?> Back to marketplace</a>

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
        <?php else: ?>
            <a href="ingredient_checkout.php?listing_id=<?= (int)$listing['id'] ?>" class="btn btn-primary btn-block"><?= icon('shopping-cart', 16) ?> Buy for R<?= number_format((float)$listing['price'], 2) ?></a>
            <?php if (PAYFAST_SANDBOX): ?>
                <p class="muted center-text mt-16" style="font-size:12px;">Sandbox mode — no real money moves.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</main>
</body>
</html>

<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

$mPaymentId = $_GET['m'] ?? '';
$stmt = db()->prepare('SELECT * FROM recipe_purchases WHERE m_payment_id = ? AND buyer_id = ?');
$stmt->execute([$mPaymentId, $user['id']]);
$purchase = $stmt->fetch();

$recipe = null;
if ($purchase) {
    $recipeStmt = db()->prepare('SELECT * FROM recipes WHERE id = ?');
    $recipeStmt->execute([$purchase['recipe_id']]);
    $recipe = $recipeStmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payment status · <?= APP_NAME ?></title>
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
            <?php if (!$purchase): ?>
                <div class="alert alert-error">We couldn't find that payment.</div>
                <a class="btn btn-primary btn-block" href="index.php">Back to recipes</a>
            <?php elseif ($purchase['status'] === 'paid'): ?>
                <div class="alert alert-success">Payment confirmed!</div>
                <p class="muted">You now have full access to <strong><?= h($recipe['title'] ?? 'this recipe') ?></strong>.</p>
                <a class="btn btn-primary btn-block" href="recipe.php?id=<?= urlencode($purchase['recipe_id']) ?>">View recipe</a>
            <?php else: ?>
                <div class="alert alert-success">Payment received by PayFast — confirming now.</div>
                <p class="muted" style="font-size:13px;">This usually only takes a few seconds. If the recipe still shows as locked, give it a moment and refresh.</p>
                <a class="btn btn-primary btn-block" href="recipe.php?id=<?= urlencode($purchase['recipe_id']) ?>">View recipe</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

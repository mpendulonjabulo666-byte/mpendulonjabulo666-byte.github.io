<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

if (empty($user['is_vendor'])) {
    http_response_code(403);
    die('Turn on "Sell your recipes" in your profile to access the vendor dashboard.');
}

$recipesStmt = db()->prepare(
    'SELECT r.*,
     (SELECT COUNT(*) FROM recipe_purchases p WHERE p.recipe_id = r.id AND p.status = "paid") AS sales_count,
     (SELECT COALESCE(SUM(p.amount), 0) FROM recipe_purchases p WHERE p.recipe_id = r.id AND p.status = "paid") AS gross_revenue
     FROM recipes r
     WHERE r.created_by = ? AND r.is_premium = 1
     ORDER BY r.created_at DESC'
);
$recipesStmt->execute([$user['id']]);
$recipes = $recipesStmt->fetchAll();

$totalsStmt = db()->prepare(
    'SELECT COUNT(*) AS sales_count, COALESCE(SUM(amount), 0) AS gross_revenue
     FROM recipe_purchases WHERE vendor_id = ? AND status = "paid"'
);
$totalsStmt->execute([$user['id']]);
$totals = $totalsStmt->fetch();

$recentStmt = db()->prepare(
    'SELECT p.*, r.title, u.name AS buyer_name
     FROM recipe_purchases p
     JOIN recipes r ON r.id = p.recipe_id
     JOIN users u ON u.id = p.buyer_id
     WHERE p.vendor_id = ? AND p.status = "paid"
     ORDER BY p.created_at DESC LIMIT 10'
);
$recentStmt->execute([$user['id']]);
$recent = $recentStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vendor Dashboard · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div class="planner-header">
        <h1>Vendor Dashboard</h1>
        <a class="btn btn-primary" href="add_recipe.php"><?= icon('plus', 16) ?> New recipe</a>
    </div>

    <div class="macro-row" style="grid-template-columns:repeat(2,1fr);max-width:400px;margin-bottom:24px;">
        <div class="macro-pill"><strong>R<?= number_format((float)$totals['gross_revenue'], 2) ?></strong><span>Total revenue</span></div>
        <div class="macro-pill"><strong><?= (int)$totals['sales_count'] ?></strong><span>Recipes sold</span></div>
    </div>

    <h2 class="mb-16">Your premium recipes</h2>
    <?php if (!$recipes): ?>
        <p class="muted">You haven't listed any premium recipes yet. Mark one as premium from the recipe form to start selling.</p>
    <?php else: ?>
        <div class="card mb-16" style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr><th>Recipe</th><th>Price</th><th>Sold</th><th>Revenue</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recipes as $r): ?>
                        <tr>
                            <td><a href="recipe.php?id=<?= urlencode($r['id']) ?>"><?= h($r['title']) ?></a></td>
                            <td>R<?= number_format((float)$r['price'], 2) ?></td>
                            <td><?= (int)$r['sales_count'] ?></td>
                            <td>R<?= number_format((float)$r['gross_revenue'], 2) ?></td>
                            <td><a class="btn btn-text btn-small" href="add_recipe.php?id=<?= urlencode($r['id']) ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2 class="mb-16">Recent sales</h2>
    <?php if (!$recent): ?>
        <p class="muted">No sales yet.</p>
    <?php else: ?>
        <div class="card" style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr><th>Date</th><th>Recipe</th><th>Buyer</th><th>Amount</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $sale): ?>
                        <tr>
                            <td><?= h((new DateTime($sale['created_at']))->format('M j, Y')) ?></td>
                            <td><?= h($sale['title']) ?></td>
                            <td><?= h($sale['buyer_name']) ?></td>
                            <td>R<?= number_format((float)$sale['amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted mt-16" style="font-size:12px;">Payouts to your bank account aren't automated yet — for now this is a running ledger of what's owed to you.</p>
    <?php endif; ?>
</main>
</body>
</html>

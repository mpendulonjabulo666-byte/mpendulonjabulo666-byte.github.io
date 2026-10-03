<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/vendor_payouts.php';

$user = require_login();

if (empty($user['is_vendor'])) {
    http_response_code(403);
    die('Turn on "Sell your recipes" in your profile to access the vendor dashboard.');
}

$recipesStmt = db()->prepare(
    'SELECT r.*,
     (SELECT COUNT(*) FROM recipe_purchases p WHERE p.recipe_id = r.id AND p.status = "paid") AS sales_count,
     (SELECT COALESCE(SUM(p.vendor_amount), 0) FROM recipe_purchases p WHERE p.recipe_id = r.id AND p.status = "paid") AS net_revenue
     FROM recipes r
     WHERE r.created_by = ? AND r.is_premium = 1
     ORDER BY r.created_at DESC'
);
$recipesStmt->execute([$user['id']]);
$recipes = $recipesStmt->fetchAll();

$totalsStmt = db()->prepare(
    'SELECT COUNT(*) AS sales_count, COALESCE(SUM(amount), 0) AS gross_revenue, COALESCE(SUM(vendor_amount), 0) AS net_revenue
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

$ingredientTotalsStmt = db()->prepare(
    'SELECT COUNT(*) AS sales_count, COALESCE(SUM(seller_amount), 0) AS net_revenue
     FROM ingredient_orders WHERE seller_id = ? AND status = "paid"'
);
$ingredientTotalsStmt->execute([$user['id']]);
$ingredientTotals = $ingredientTotalsStmt->fetch();

$owed = calculate_vendor_owed(db(), (int)$user['id']);

$payoutHistoryStmt = db()->prepare(
    'SELECT * FROM vendor_payouts WHERE vendor_id = ? ORDER BY created_at DESC LIMIT 20'
);
$payoutHistoryStmt->execute([$user['id']]);
$payoutHistory = $payoutHistoryStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vendor Dashboard · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=11">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div class="page-hero-banner" style="background-image:url('assets/img/banners/vendor-flame.jpg');">
        <div style="display:flex;justify-content:space-between;align-items:flex-end;width:100%;flex-wrap:wrap;gap:12px;">
            <div>
                <h1>Vendor Dashboard</h1>
                <p>Track earnings from your premium recipes and ingredient sales.</p>
            </div>
            <a class="btn btn-primary" href="add_recipe.php"><?= icon('plus', 16) ?> New recipe</a>
        </div>
    </div>

    <div class="macro-row" style="grid-template-columns:repeat(3,1fr);max-width:560px;margin-bottom:8px;">
        <div class="macro-pill"><strong>R<?= number_format((float)$totals['net_revenue'], 2) ?></strong><span>Your earnings</span></div>
        <div class="macro-pill"><strong>R<?= number_format((float)$totals['gross_revenue'], 2) ?></strong><span>Gross sales</span></div>
        <div class="macro-pill"><strong><?= (int)$totals['sales_count'] ?></strong><span>Recipes sold</span></div>
    </div>
    <p class="muted mb-16" style="font-size:12px;"><?= APP_NAME ?> keeps a <?= PLATFORM_COMMISSION_PCT ?>% commission on every recipe sale — "Your earnings" is what you're owed after that.</p>

    <h2 class="mb-16">Your premium recipes</h2>
    <?php if (!$recipes): ?>
        <p class="muted">You haven't listed any premium recipes yet. Mark one as premium from the recipe form to start selling.</p>
    <?php else: ?>
        <div class="card mb-16" style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr><th>Recipe</th><th>Price</th><th>Sold</th><th>Your earnings</th><th></th></tr>
                </thead>
                <tbody>
                    <?php $vendorCurrentlyPremium = !empty($user['is_admin']) || !empty($user['is_premium_member']); ?>
                    <?php foreach ($recipes as $r): ?>
                        <tr>
                            <td>
                                <a href="recipe.php?id=<?= urlencode($r['id']) ?>"><?= h($r['title']) ?></a>
                                <?php if (!$vendorCurrentlyPremium): ?>
                                    <br><span class="muted" style="font-size:11.5px;color:var(--error);"><?= icon('alert-triangle', 11) ?> Unpublished - renew Premium to relist</span>
                                <?php endif; ?>
                            </td>
                            <td>R<?= number_format((float)$r['price'], 2) ?></td>
                            <td><?= (int)$r['sales_count'] ?></td>
                            <td>R<?= number_format((float)$r['net_revenue'], 2) ?></td>
                            <td><a class="btn btn-text btn-small" href="add_recipe.php?id=<?= urlencode($r['id']) ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="card mb-16" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div>
            <h2 style="margin:0 0 4px;font-size:16px;">Ingredient marketplace earnings</h2>
            <p class="muted" style="margin:0;font-size:13px;">
                R<?= number_format((float)$ingredientTotals['net_revenue'], 2) ?> earned from <?= (int)$ingredientTotals['sales_count'] ?> ingredient sale<?= (int)$ingredientTotals['sales_count'] === 1 ? '' : 's' ?>.
            </p>
        </div>
        <a class="btn btn-text btn-small" href="marketplace.php"><?= icon('shopping-cart', 14) ?> Manage listings</a>
    </div>

    <h2 class="mb-16">Recent sales</h2>
    <?php if (!$recent): ?>
        <p class="muted">No sales yet.</p>
    <?php else: ?>
        <div class="card" style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr><th>Date</th><th>Recipe</th><th>Buyer</th><th>Sale price</th><th>Your earnings</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $sale): ?>
                        <tr>
                            <td><?= h((new DateTime($sale['created_at']))->format('M j, Y')) ?></td>
                            <td><?= h($sale['title']) ?></td>
                            <td><?= h($sale['buyer_name']) ?></td>
                            <td>R<?= number_format((float)$sale['amount'], 2) ?></td>
                            <td>R<?= number_format((float)$sale['vendor_amount'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2 class="mb-16">Payouts</h2>
    <div class="card mb-16" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
        <div>
            <h3 style="margin:0 0 4px;font-size:15px;">Currently owed to you</h3>
            <p class="muted" style="margin:0;font-size:13px;">
                R<?= number_format($owed['amount'], 2) ?>
                <?php if ($owed['amount'] > 0 && $owed['period_start']): ?>
                    from sales since <?= h((new DateTime($owed['period_start']))->format('j F Y')) ?>
                <?php endif; ?>
                — paid out by EFT, not automatically through NutriTale yet.
            </p>
        </div>
    </div>
    <?php if (!$payoutHistory): ?>
        <p class="muted">No payouts recorded yet.</p>
    <?php else: ?>
        <div class="card" style="overflow-x:auto;">
            <table class="admin-table">
                <thead><tr><th>Period</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($payoutHistory as $p):
                        $statusPill = ['paid' => 'resolved', 'pending' => 'open', 'reversed' => 'dismissed'][$p['status']] ?? 'open';
                    ?>
                        <tr>
                            <td>
                                <?= $p['period_start'] ? h((new DateTime($p['period_start']))->format('M j')) : '—' ?>
                                – <?= h((new DateTime($p['period_end']))->format('M j, Y')) ?>
                            </td>
                            <td>R<?= number_format((float)$p['amount'], 2) ?></td>
                            <td style="white-space:normal;max-width:260px;">
                                <span class="pill pill-<?= $statusPill ?>"><?= h(ucfirst($p['status'])) ?></span>
                                <?php if ($p['status'] === 'reversed'): ?>
                                    <div class="muted" style="font-size:12px;margin-top:4px;">Reason: <?= h($p['reversal_reason']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= $p['paid_at'] ? h((new DateTime($p['paid_at']))->format('M j, Y')) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="muted mt-16" style="font-size:12px;">A reversed payout means that amount is owed to you again — it'll show under "Currently owed to you" above.</p>
    <?php endif; ?>
</main>
</body>
</html>

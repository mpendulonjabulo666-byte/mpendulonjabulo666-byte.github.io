<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/vendor_payouts.php';

$user = require_admin();

// Real counts only - every number on this page comes straight from the
// same tables the rest of the app reads and writes, never a placeholder.
$totalUsers = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$newUsersWeek = (int)db()->query('SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 7 DAY')->fetchColumn();
$activeWeek = (int)db()->query('SELECT COUNT(*) FROM users WHERE last_login_at >= NOW() - INTERVAL 7 DAY')->fetchColumn();
$totalRecipes = (int)db()->query('SELECT COUNT(*) FROM recipes')->fetchColumn();
$newRecipesWeek = (int)db()->query('SELECT COUNT(*) FROM recipes WHERE created_at >= NOW() - INTERVAL 7 DAY')->fetchColumn();
$premiumMembers = (int)db()->query('SELECT COUNT(*) FROM users WHERE is_premium_member = 1')->fetchColumn();
$vendors = (int)db()->query('SELECT COUNT(*) FROM users WHERE is_vendor = 1')->fetchColumn();
$totalFavorites = (int)db()->query('SELECT COUNT(*) FROM favorites')->fetchColumn();
$totalRatings = (int)db()->query('SELECT COUNT(*) FROM recipe_ratings')->fetchColumn();
$aiGenerationsToday = (int)db()->query('SELECT COUNT(*) FROM ai_generations WHERE created_at >= CURDATE()')->fetchColumn();
$totalOwedToVendors = array_sum(array_column(vendors_with_owed_balance(db()), 'amount'));

$openReports = 0;
try {
    $openReports = (int)db()->query("SELECT COUNT(*) FROM recipe_reports WHERE status = 'open'")->fetchColumn();
} catch (PDOException $e) {
    // recipe_reports doesn't exist until its migration has been applied -
    // treat that as "no reports feature yet" rather than a fatal error.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=18">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div class="admin-page-head mb-16">
        <h1 style="margin:0;"><?= icon('shield', 20) ?> Admin portal</h1>
        <a href="admin_profile.php" class="btn btn-text btn-small"><?= icon('user', 16) ?> My profile</a>
    </div>

    <?php $adminCurrent = 'admin.php'; include __DIR__ . '/includes/admin_nav.php'; ?>

    <div class="admin-stat-grid mb-16">
        <div class="card admin-stat-card">
            <div class="admin-stat-label">Total users</div>
            <div class="admin-stat-value"><?= number_format($totalUsers) ?></div>
            <div class="admin-stat-sub"><?= $newUsersWeek ?> new · <?= $activeWeek ?> active this week</div>
        </div>
        <div class="card admin-stat-card">
            <div class="admin-stat-label">Recipes</div>
            <div class="admin-stat-value"><?= number_format($totalRecipes) ?></div>
            <div class="admin-stat-sub"><?= $newRecipesWeek ?> added this week</div>
        </div>
        <div class="card admin-stat-card">
            <div class="admin-stat-label">Premium members</div>
            <div class="admin-stat-value"><?= number_format($premiumMembers) ?></div>
            <div class="admin-stat-sub"><?= $vendors ?> selling recipes</div>
        </div>
        <div class="card admin-stat-card">
            <div class="admin-stat-label">Open reports</div>
            <div class="admin-stat-value"><?= number_format($openReports) ?></div>
            <div class="admin-stat-sub">awaiting review</div>
        </div>
    </div>

    <div class="card mb-16">
        <h2 style="margin-top:0;font-size:17px;">Engagement so far</h2>
        <p class="muted" style="font-size:13px;margin:0 0 12px;">Real totals from favorites, ratings and AI pantry use — not sampled or estimated.</p>
        <div style="display:flex;gap:32px;flex-wrap:wrap;">
            <div><strong style="font-size:20px;"><?= number_format($totalFavorites) ?></strong><div class="muted" style="font-size:12px;">Recipes saved to favorites</div></div>
            <div><strong style="font-size:20px;"><?= number_format($totalRatings) ?></strong><div class="muted" style="font-size:12px;">Ratings &amp; reviews left</div></div>
            <div><strong style="font-size:20px;"><?= number_format($aiGenerationsToday) ?></strong><div class="muted" style="font-size:12px;">AI pantry lookups today</div></div>
            <div><strong style="font-size:20px;">R<?= number_format($totalOwedToVendors, 2) ?></strong><div class="muted" style="font-size:12px;">Currently owed to vendors</div></div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0;font-size:17px;">Quick actions</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="admin_recipes.php" class="btn btn-primary btn-small"><?= icon('plus', 14) ?> Add a recipe</a>
            <a href="admin_users.php" class="btn btn-text btn-small"><?= icon('users', 14) ?> Manage users</a>
            <a href="admin_reports.php" class="btn btn-text btn-small"><?= icon('alert-triangle', 14) ?> Review reports</a>
            <a href="admin_payouts.php" class="btn btn-text btn-small"><?= icon('download', 14) ?> Vendor payouts</a>
            <a href="admin_meal_plans.php" class="btn btn-text btn-small"><?= icon('calendar', 14) ?> Meal plans</a>
            <a href="admin_analytics.php" class="btn btn-text btn-small"><?= icon('bar-chart', 14) ?> Analytics</a>
        </div>
    </div>
</main>
</body>
</html>

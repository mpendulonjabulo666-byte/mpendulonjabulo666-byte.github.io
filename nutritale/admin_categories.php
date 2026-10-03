<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_admin();

// "Categories" here means the two groupings recipes already carry in the
// schema - meal_type and cuisine - not a new taxonomy. meal_type counts
// link through to the real, already-working index.php filter; cuisine is
// shown read-only since no page currently filters by it (adding that
// filter is separate scope from restyling this admin view).
$mealTypeIcons = ['breakfast' => 'sun', 'lunch' => 'flame', 'dinner' => 'moon', 'snack' => 'star'];
$mealTypeCounts = db()->query(
    'SELECT meal_type, COUNT(*) AS n FROM recipes GROUP BY meal_type ORDER BY n DESC'
)->fetchAll(PDO::FETCH_KEY_PAIR);

$cuisineCounts = db()->query(
    "SELECT cuisine, COUNT(*) AS n FROM recipes WHERE cuisine IS NOT NULL AND cuisine != '' GROUP BY cuisine ORDER BY n DESC LIMIT 20"
)->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Categories · Admin · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=14">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div class="admin-page-head mb-16">
        <h1 style="margin:0;"><?= icon('shield', 20) ?> Admin portal</h1>
        <a href="admin_profile.php" class="btn btn-text btn-small"><?= icon('user', 16) ?> My profile</a>
    </div>

    <?php $adminCurrent = 'admin_categories.php'; include __DIR__ . '/includes/admin_nav.php'; ?>

    <h2 style="font-size:16px;">By meal type</h2>
    <div class="admin-category-grid mb-16">
        <?php foreach ($mealTypeCounts as $mt => $count): ?>
            <a href="index.php?meal_type=<?= urlencode($mt) ?>" class="card admin-category-card">
                <span class="admin-category-icon"><?= icon($mealTypeIcons[$mt] ?? 'list', 20) ?></span>
                <div>
                    <h3><?= h(ucfirst($mt)) ?></h3>
                    <p><?= number_format($count) ?> recipe<?= $count === 1 ? '' : 's' ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <h2 style="font-size:16px;">By cuisine</h2>
    <p class="muted" style="font-size:13px;margin-top:0;">Read-only for now — recipe browsing doesn't filter by cuisine yet.</p>
    <?php if (!$cuisineCounts): ?>
        <p class="muted">No recipes have a cuisine set.</p>
    <?php else: ?>
        <div class="card" style="overflow-x:auto;">
            <table class="admin-table">
                <thead><tr><th>Cuisine</th><th>Recipes</th></tr></thead>
                <tbody>
                    <?php foreach ($cuisineCounts as $cuisine => $count): ?>
                        <tr><td><?= h(ucfirst($cuisine)) ?></td><td><?= number_format($count) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

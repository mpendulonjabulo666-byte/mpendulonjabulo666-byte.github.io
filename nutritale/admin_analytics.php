<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_admin();

// Every number below comes from a table this app already writes to in the
// course of normal use (signups, recipe.php page loads, ai_generations,
// favorites) - see includes/functions.php's platform_setting() sibling
// migrations for what each table backs. Nothing here is sampled,
// estimated, or a placeholder. What's deliberately NOT here: session
// length / time-on-site, which would need real client-side instrumentation
// this app doesn't have - see CONTINUE.md-adjacent notes for that gap.

function daily_series(PDO $pdo, string $table, string $dateCol, int $days): array
{
    $stmt = $pdo->prepare(
        "SELECT DATE($dateCol) AS d, COUNT(*) AS c FROM $table
         WHERE $dateCol >= CURDATE() - INTERVAL ? DAY
         GROUP BY DATE($dateCol)"
    );
    $stmt->execute([$days - 1]);
    $byDate = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $series = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = (new DateTime())->modify("-$i days")->format('Y-m-d');
        $series[$date] = (int)($byDate[$date] ?? 0);
    }
    return $series;
}

$days = 14;
$signups = daily_series(db(), 'users', 'created_at', $days);
$recipesAdded = daily_series(db(), 'recipes', 'created_at', $days);
$aiGenerations = daily_series(db(), 'ai_generations', 'created_at', $days);
$recipeViews = daily_series(db(), 'recipe_views', 'created_at', $days);

$activeToday = (int)db()->query("SELECT COUNT(*) FROM users WHERE last_login_at >= NOW() - INTERVAL 1 DAY")->fetchColumn();
$activeWeek = (int)db()->query("SELECT COUNT(*) FROM users WHERE last_login_at >= NOW() - INTERVAL 7 DAY")->fetchColumn();
$neverLoggedIn = (int)db()->query("SELECT COUNT(*) FROM users WHERE last_login_at IS NULL")->fetchColumn();

$topViewed = db()->query(
    "SELECT r.id, r.title, COUNT(*) AS n FROM recipe_views rv
     JOIN recipes r ON r.id = rv.recipe_id
     GROUP BY r.id ORDER BY n DESC LIMIT 5"
)->fetchAll();

$topFavorited = db()->query(
    "SELECT r.id, r.title, COUNT(*) AS n FROM favorites f
     JOIN recipes r ON r.id = f.recipe_id
     GROUP BY r.id ORDER BY n DESC LIMIT 5"
)->fetchAll();

$totalListings = (int)db()->query('SELECT COUNT(*) FROM ingredient_listings')->fetchColumn();
$soldListings = (int)db()->query("SELECT COUNT(*) FROM ingredient_listings WHERE status = 'sold'")->fetchColumn();
$paidOrdersStmt = db()->query("SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total FROM ingredient_orders WHERE status = 'paid'");
$paidOrders = $paidOrdersStmt->fetch();

function render_bar_chart(array $series, string $unit = ''): string
{
    $max = max(1, max($series));
    $html = '<div class="mini-chart">';
    foreach ($series as $date => $count) {
        $pct = max(4, round($count / $max * 100));
        $label = (new DateTime($date))->format('D j');
        $html .= '<div class="mini-bar-col" title="' . h($label . ': ' . $count . ' ' . $unit) . '">'
            . '<div class="mini-bar" style="height:' . $pct . '%;"></div>'
            . '<span class="mini-bar-label">' . h((new DateTime($date))->format('j')) . '</span>'
            . '</div>';
    }
    return $html . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Analytics · Admin · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=9">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;" class="mb-16">
        <h1 style="margin:0;"><?= icon('shield', 20) ?> Admin portal</h1>
        <a href="admin_profile.php" class="btn btn-text btn-small"><?= icon('user', 16) ?> My profile</a>
    </div>

    <?php $adminCurrent = 'admin_analytics.php'; include __DIR__ . '/includes/admin_nav.php'; ?>

    <div class="admin-stat-grid mb-16">
        <div class="card admin-stat-card">
            <div class="admin-stat-label">Active today</div>
            <div class="admin-stat-value"><?= number_format($activeToday) ?></div>
            <div class="admin-stat-sub">logged in within 24h</div>
        </div>
        <div class="card admin-stat-card">
            <div class="admin-stat-label">Active this week</div>
            <div class="admin-stat-value"><?= number_format($activeWeek) ?></div>
            <div class="admin-stat-sub">logged in within 7 days</div>
        </div>
        <div class="card admin-stat-card">
            <div class="admin-stat-label">Never logged in</div>
            <div class="admin-stat-value"><?= number_format($neverLoggedIn) ?></div>
            <div class="admin-stat-sub">signed up before tracking started, or haven't returned</div>
        </div>
        <div class="card admin-stat-card">
            <div class="admin-stat-label">Recipe views (14d)</div>
            <div class="admin-stat-value"><?= number_format(array_sum($recipeViews)) ?></div>
            <div class="admin-stat-sub">real page loads, not estimated</div>
        </div>
    </div>

    <div class="card mb-16">
        <h2 style="margin-top:0;font-size:16px;">Recipe views — last 14 days</h2>
        <?= render_bar_chart($recipeViews, 'views') ?>
    </div>

    <div class="card mb-16">
        <h2 style="margin-top:0;font-size:16px;">New signups — last 14 days</h2>
        <?= render_bar_chart($signups, 'signups') ?>
    </div>

    <div class="card mb-16">
        <h2 style="margin-top:0;font-size:16px;">Recipes added — last 14 days</h2>
        <?= render_bar_chart($recipesAdded, 'recipes') ?>
    </div>

    <div class="card mb-16">
        <h2 style="margin-top:0;font-size:16px;">AI pantry generations — last 14 days</h2>
        <?= render_bar_chart($aiGenerations, 'generations') ?>
    </div>

    <div class="grid-2col mb-16" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="card">
            <h2 style="margin-top:0;font-size:16px;">Most viewed recipes</h2>
            <?php if (!$topViewed): ?>
                <p class="muted">No views logged yet.</p>
            <?php else: ?>
                <?php foreach ($topViewed as $r): ?>
                    <div class="settings-row"><a href="recipe.php?id=<?= urlencode($r['id']) ?>"><?= h($r['title']) ?></a><strong><?= (int)$r['n'] ?></strong></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="card">
            <h2 style="margin-top:0;font-size:16px;">Most favorited recipes</h2>
            <?php if (!$topFavorited): ?>
                <p class="muted">No favorites yet.</p>
            <?php else: ?>
                <?php foreach ($topFavorited as $r): ?>
                    <div class="settings-row"><a href="recipe.php?id=<?= urlencode($r['id']) ?>"><?= h($r['title']) ?></a><strong><?= (int)$r['n'] ?></strong></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0;font-size:16px;">Marketplace</h2>
        <div style="display:flex;gap:32px;flex-wrap:wrap;">
            <div><strong style="font-size:20px;"><?= number_format($totalListings) ?></strong><div class="muted" style="font-size:12px;">Total listings</div></div>
            <div><strong style="font-size:20px;"><?= number_format($soldListings) ?></strong><div class="muted" style="font-size:12px;">Sold</div></div>
            <div><strong style="font-size:20px;"><?= number_format((int)$paidOrders['n']) ?></strong><div class="muted" style="font-size:12px;">Paid orders</div></div>
            <div><strong style="font-size:20px;">R<?= number_format((float)$paidOrders['total'], 2) ?></strong><div class="muted" style="font-size:12px;">Total sales value</div></div>
        </div>
    </div>
</main>
</body>
</html>

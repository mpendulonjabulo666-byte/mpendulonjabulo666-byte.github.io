<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_admin();

$reasonLabels = [
    'incorrect_info' => 'Incorrect information',
    'inappropriate' => 'Inappropriate content',
    'duplicate' => 'Duplicate recipe',
    'other' => 'Other',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $reportId = (int)($_POST['report_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['resolve', 'dismiss'], true)) {
        $status = $action === 'resolve' ? 'resolved' : 'dismissed';
        db()->prepare("UPDATE recipe_reports SET status = ?, resolved_at = NOW() WHERE id = ?")->execute([$status, $reportId]);
        flash_set('success', 'Report updated.');
    }
    redirect('admin_reports.php');
}

$filterStatus = $_GET['status'] ?? 'open';
if (!in_array($filterStatus, ['open', 'resolved', 'dismissed', 'all'], true)) {
    $filterStatus = 'open';
}

$where = $filterStatus === 'all' ? '' : 'WHERE rr.status = ?';
$params = $filterStatus === 'all' ? [] : [$filterStatus];

$stmt = db()->prepare(
    "SELECT rr.*, r.title AS recipe_title, u.name AS reporter_name, u.email AS reporter_email
     FROM recipe_reports rr
     JOIN recipes r ON r.id = rr.recipe_id
     JOIN users u ON u.id = rr.reporter_user_id
     $where
     ORDER BY rr.created_at DESC
     LIMIT 200"
);
$stmt->execute($params);
$reports = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reports · Admin · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js?v=21"></script>
<link rel="stylesheet" href="assets/css/style.css?v=21">
<script src="assets/js/theme-toggle.js?v=21" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div class="admin-page-head mb-16">
        <h1 style="margin:0;"><?= icon('shield', 20) ?> Admin portal</h1>
        <a href="admin_profile.php" class="btn btn-text btn-small"><?= icon('user', 16) ?> My profile</a>
    </div>

    <?php $adminCurrent = 'admin_reports.php'; include __DIR__ . '/includes/admin_nav.php'; ?>

    <?php if ($success = flash_get('success')): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <div class="admin-tabbar mb-16" style="border-bottom:none;padding-bottom:0;">
        <?php foreach (['open' => 'Open', 'resolved' => 'Resolved', 'dismissed' => 'Dismissed', 'all' => 'All'] as $status => $label): ?>
            <a href="?status=<?= $status ?>" class="admin-tab<?= $filterStatus === $status ? ' is-active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (!$reports): ?>
        <p class="muted">No reports here.</p>
    <?php else: ?>
        <div class="card" style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Recipe</th>
                        <th>Reason</th>
                        <th>Reported by</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td><a href="recipe.php?id=<?= urlencode($report['recipe_id']) ?>"><?= h($report['recipe_title']) ?></a></td>
                            <td>
                                <?= h($reasonLabels[$report['reason']] ?? $report['reason']) ?>
                                <?php if ($report['details']): ?>
                                    <div class="muted" style="font-size:12px;"><?= h($report['details']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= h($report['reporter_name']) ?> <span class="muted">(<?= h($report['reporter_email']) ?>)</span></td>
                            <td><?= h((new DateTime($report['created_at']))->format('M j, Y')) ?></td>
                            <td><span class="pill pill-<?= h($report['status']) ?>"><?= h(ucfirst($report['status'])) ?></span></td>
                            <td>
                                <?php if ($report['status'] === 'open'): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                                        <input type="hidden" name="action" value="resolve">
                                        <button type="submit" class="btn btn-text btn-small" style="color:var(--green-dark);"><?= icon('check', 14) ?> Resolve</button>
                                    </form>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="report_id" value="<?= (int)$report['id'] ?>">
                                        <input type="hidden" name="action" value="dismiss">
                                        <button type="submit" class="btn btn-text btn-small"><?= icon('x', 14) ?> Dismiss</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

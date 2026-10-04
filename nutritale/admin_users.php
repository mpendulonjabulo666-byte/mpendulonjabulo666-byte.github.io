<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $targetId = (int)($_POST['user_id'] ?? 0);

    if (($_POST['action'] ?? '') === 'unlock') {
        db()->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')->execute([$targetId]);
        flash_set('success', 'Account unlocked.');
        redirect('admin_users.php' . (($_GET['q'] ?? '') !== '' ? '?q=' . urlencode($_GET['q']) : ''));
    }

    $field = $_POST['toggle'] ?? '';
    $allowedFields = ['is_admin', 'is_premium_member', 'is_vendor'];

    if (!in_array($field, $allowedFields, true)) {
        $errors[] = 'Unknown action.';
    } elseif ($field === 'is_admin' && $targetId === (int)$user['id']) {
        // Removing your own admin flag here would lock you out of the
        // admin portal with no UI path back in - the only recovery would
        // be a direct database edit. Refusing this one combination costs
        // nothing (another admin can still demote this account) and
        // avoids a real, easy-to-hit lockout.
        $errors[] = "You can't remove your own admin access. Ask another admin to do it if needed.";
    } else {
        $stmt = db()->prepare("SELECT $field FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $current = $stmt->fetchColumn();
        if ($current === false) {
            $errors[] = 'User not found.';
        } else {
            db()->prepare("UPDATE users SET $field = ? WHERE id = ?")->execute([$current ? 0 : 1, $targetId]);
            flash_set('success', 'Updated.');
            redirect('admin_users.php' . (($_GET['q'] ?? '') !== '' ? '?q=' . urlencode($_GET['q']) : ''));
        }
    }
}

$search = trim($_GET['q'] ?? '');
$where = '';
$params = [];
if ($search !== '') {
    $where = 'WHERE name LIKE ? OR email LIKE ?';
    $params = ['%' . $search . '%', '%' . $search . '%'];
}
$stmt = db()->prepare(
    "SELECT id, name, email, is_admin, is_premium_member, is_vendor, failed_attempts, locked_until, created_at FROM users $where ORDER BY created_at DESC LIMIT 200"
);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Users · Admin · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js?v=18"></script>
<link rel="stylesheet" href="assets/css/style.css?v=19">
<script src="assets/js/theme-toggle.js?v=18" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div class="admin-page-head mb-16">
        <h1 style="margin:0;"><?= icon('shield', 20) ?> Admin portal</h1>
        <a href="admin_profile.php" class="btn btn-text btn-small"><?= icon('user', 16) ?> My profile</a>
    </div>

    <?php $adminCurrent = 'admin_users.php'; include __DIR__ . '/includes/admin_nav.php'; ?>

    <?php if ($success = flash_get('success')): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <form method="get" class="mb-16" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search by name or email..." class="field" style="max-width:300px;">
        <button type="submit" class="btn btn-text btn-small"><?= icon('search', 14) ?> Search</button>
        <a href="admin_export.php?type=users" class="btn btn-text btn-small" style="margin-left:auto;"><?= icon('download', 14) ?> Export CSV</a>
    </form>

    <div class="card" style="overflow-x:auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Joined</th>
                    <th>Admin</th>
                    <th>Premium</th>
                    <th>Vendor</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php $isLocked = $u['locked_until'] && new DateTime($u['locked_until']) > new DateTime(); ?>
                    <tr>
                        <td>
                            <strong><?= h($u['name']) ?></strong> <span class="muted"><?= h($u['email']) ?></span>
                            <?php if ($isLocked): ?><span class="tag" style="color:var(--error);border-color:var(--error);"><?= icon('lock', 12) ?> Locked out</span><?php endif; ?>
                        </td>
                        <td><?= h((new DateTime($u['created_at']))->format('M j, Y')) ?></td>
                        <?php foreach (['is_admin', 'is_premium_member', 'is_vendor'] as $field): ?>
                            <td>
                                <form method="post" style="display:inline;" onsubmit="<?= $field === 'is_admin' && !$u[$field] ? "return confirm('Grant admin access to " . h(addslashes($u['name'])) . "?');" : '' ?>">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="toggle" value="<?= $field ?>">
                                    <button type="submit" class="btn btn-text btn-small" style="<?= $u[$field] ? 'color:var(--green-dark);' : 'color:var(--muted);' ?>">
                                        <?= $u[$field] ? icon('check', 14) . ' On' : icon('x', 14) . ' Off' ?>
                                    </button>
                                </form>
                            </td>
                        <?php endforeach; ?>
                        <td>
                            <?php if ($isLocked): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <input type="hidden" name="action" value="unlock">
                                    <button type="submit" class="btn btn-text btn-small"><?= icon('lock', 14) ?> Unlock</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>

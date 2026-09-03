<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

if (current_user()) {
    redirect('index.php');
}

$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$errors = [];
$done = false;

$stmt = db()->prepare('SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()');
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    $errors[] = 'This reset link is invalid or has expired. Please request a new one.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($new) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($new !== $confirm) $errors[] = 'Passwords do not match.';

        if (!$errors) {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $reset['user_id']]);
            $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?')->execute([$token]);
            $pdo->commit();
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<div class="auth-shell">
<?= render_theme_toggle() ?>
    <div class="auth-card">
        <div class="center-text mb-16"><?= nutritale_logo_svg(56) ?></div>
        <h1 class="center-text">Set a new password</h1>
        <div class="card">
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endforeach; ?>

            <?php if ($done): ?>
                <div class="alert alert-success">Password changed. You can log in now.</div>
                <a class="btn btn-primary btn-block" href="login.php">Log in</a>
            <?php elseif ($reset): ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="token" value="<?= h($token) ?>">
                    <label class="field">
                        <span>New password</span>
                        <input type="password" name="new_password" required minlength="8">
                    </label>
                    <label class="field">
                        <span>Confirm new password</span>
                        <input type="password" name="confirm_password" required minlength="8">
                    </label>
                    <button type="submit" class="btn btn-primary btn-block">Set new password</button>
                </form>
            <?php else: ?>
                <a class="btn btn-text btn-block" href="forgot_password.php">Request a new link</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

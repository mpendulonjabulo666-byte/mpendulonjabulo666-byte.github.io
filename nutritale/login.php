<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

if (current_user()) {
    redirect('index.php');
}

$errors = [];
$email = '';

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_MINUTES = 15;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = db()->prepare('SELECT id, name, password_hash, failed_attempts, locked_until FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['locked_until'] && new DateTime($user['locked_until']) > new DateTime()) {
            $minutesLeft = max(1, (int)ceil((strtotime($user['locked_until']) - time()) / 60));
            $errors[] = "Too many failed attempts. Try again in $minutesLeft minute" . ($minutesLeft === 1 ? '' : 's') . ', or reset your password.';
        } elseif (!$user || !password_verify($password, $user['password_hash'])) {
            if ($user) {
                $attempts = (int)$user['failed_attempts'] + 1;
                $lockedUntil = null;
                if ($attempts >= MAX_LOGIN_ATTEMPTS) {
                    $lockedUntil = (new DateTime())->modify('+' . LOCKOUT_MINUTES . ' minutes')->format('Y-m-d H:i:s');
                    $attempts = 0;
                }
                $upd = db()->prepare('UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?');
                $upd->execute([$attempts, $lockedUntil, $user['id']]);
            }
            $errors[] = 'Incorrect email or password.';
        } else {
            db()->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?')->execute([$user['id']]);
            $_SESSION['user_id'] = (int)$user['id'];
            redirect('index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<div class="auth-shell">
<?= render_theme_toggle() ?>
    <div class="auth-card">
        <a href="landing.php" class="center-text mb-16" style="display:block;"><?= nutritale_logo_svg(56) ?></a>
        <h1 class="center-text">Welcome back</h1>
        <div class="card">
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endforeach; ?>
            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <label class="field">
                    <span>Email</span>
                    <input type="email" name="email" value="<?= h($email) ?>" required>
                </label>
                <label class="field">
                    <span>Password</span>
                    <input type="password" name="password" required>
                </label>
                <button type="submit" class="btn btn-primary btn-block">Log in</button>
            </form>
            <a class="btn btn-text btn-block" href="forgot_password.php">Forgot password?</a>
            <a class="btn btn-text btn-block" href="register.php">Create an account</a>
        </div>
    </div>
</div>
</body>
</html>

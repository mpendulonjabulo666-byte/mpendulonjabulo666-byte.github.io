<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

if (current_user()) {
    redirect('index.php');
}

$errors = [];
$resetLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $userId = $stmt->fetchColumn();

            if ($userId) {
                $token = bin2hex(random_bytes(32));
                $ins = db()->prepare('INSERT INTO password_resets (token, user_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))');
                $ins->execute([$token, $userId]);
                $resetLink = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']
                    . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;

                // No SMTP is configured for this app. In production, email $resetLink
                // to the user instead of displaying it. Shown here so the flow is
                // testable without mail setup.
                @mail($email, 'Reset your ' . APP_NAME . ' password', "Reset your password: $resetLink");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot Password · <?= APP_NAME ?></title>
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
        <h1 class="center-text">Reset your password</h1>
        <div class="card">
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endforeach; ?>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors): ?>
                <div class="alert alert-success">If that email has an account, a reset link has been generated.</div>
                <?php if ($resetLink): ?>
                    <p class="muted" style="font-size:12.5px;">No email service is configured on this install, so here's your link directly (valid for 1 hour):</p>
                    <p style="word-break:break-all;font-size:13px;"><a href="<?= h($resetLink) ?>"><?= h($resetLink) ?></a></p>
                <?php endif; ?>
                <a class="btn btn-text btn-block" href="login.php">Back to log in</a>
            <?php else: ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <label class="field">
                        <span>Email</span>
                        <input type="email" name="email" required>
                    </label>
                    <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
                </form>
                <a class="btn btn-text btn-block" href="login.php">Back to log in</a>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>

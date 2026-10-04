<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
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

                send_notification_email($email, 'Reset your ' . APP_NAME . ' password', "Reset your password: $resetLink");

                // Only shown on the page when no real email is configured
                // (SMTP_HOST blank - see config.php) - otherwise this was a
                // real account-takeover path: submitting *any* registered
                // email here would hand the requester a live reset link for
                // that account in their own browser response, whether or
                // not they own it, regardless of whether the email itself
                // actually got delivered.
                if (SMTP_HOST !== '') {
                    $resetLink = null;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot Password · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js?v=18"></script>
<link rel="stylesheet" href="assets/css/style.css?v=20">
<script src="assets/js/theme-toggle.js?v=18" defer></script>
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

<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/payfast_gateway.php';
require_once __DIR__ . '/includes/avatars.php';
require_once __DIR__ . '/includes/account_deletion.php';

$user = require_login();

// Accounts made with a password must confirm it. Accounts made through
// Google/Facebook/Apple have a random password nobody knows, so for those
// typing DELETE is the confirmation.
$needsPassword = empty($user['oauth_provider']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (trim($_POST['confirm_word'] ?? '') !== 'DELETE') {
        $errors[] = 'Type DELETE in capital letters to confirm.';
    } else {
        if ($needsPassword) {
            $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([$user['id']]);
            if (!password_verify($_POST['password'] ?? '', (string)$stmt->fetchColumn())) {
                $errors[] = 'That password is not correct.';
            }
        }
        if (!$errors) {
            $result = account_delete(
                db(),
                (int)$user['id'],
                'payfast_cancel_subscription',
                'avatar_delete'
            );
            if ($result['ok']) {
                $_SESSION = [];
                setcookie(session_name(), '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'secure' => is_https_request(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
                session_destroy();
                header('Location: landing.php?account_deleted=1');
                exit;
            }
            $errors[] = $result['error'];
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
<title>Delete account · <?= APP_NAME ?></title>
<meta name="robots" content="noindex">
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#2fae66">
<script src="assets/js/theme-init.js?v=24"></script>
<link rel="stylesheet" href="assets/css/style.css?v=40">
<script src="assets/js/theme-toggle.js?v=24" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main" style="max-width:640px;">
    <h1 class="mb-16">Delete your account</h1>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <div class="card">
        <p style="margin-top:0;">This permanently deletes your <?= APP_NAME ?> account. It cannot be undone.</p>
        <h2 style="font-size:16px;">What is removed</h2>
        <ul class="muted" style="font-size:14px;line-height:1.6;">
            <li>Your name, email address, password and profile photo</li>
            <li>Your pantry, allergies, diet preferences and nutrition goals</li>
            <li>Your meal plans, shopping lists, favourites, ratings and AI meal ideas</li>
            <li>Recipes you wrote, and marketplace listings that have not sold</li>
        </ul>
        <h2 style="font-size:16px;">What is kept, and why</h2>
        <ul class="muted" style="font-size:14px;line-height:1.6;">
            <li>Records of payments you made or received are kept without your name or email, because the law requires payment records to be kept and they also belong to the other person in the sale.</li>
            <li>A recipe of yours that someone bought stays hidden from everyone else, so the buyer keeps what they paid for.</li>
        </ul>
        <p class="muted" style="font-size:14px;">If you have an active Premium subscription, it is cancelled at PayFast first and you will not be charged again.</p>

        <form method="post" class="mt-16">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <?php if ($needsPassword): ?>
                <label class="field">
                    <span>Your password</span>
                    <input type="password" name="password" autocomplete="current-password" required>
                </label>
            <?php endif; ?>
            <label class="field mt-16">
                <span>Type DELETE to confirm</span>
                <input type="text" name="confirm_word" autocomplete="off" autocapitalize="characters" required pattern="DELETE">
            </label>
            <div class="mt-16" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                <button type="submit" class="btn btn-primary" style="background:#c0392b;border-color:#c0392b;">Delete my account</button>
                <a href="profile.php" class="btn btn-text">Keep my account</a>
            </div>
        </form>
        <p class="muted" style="font-size:12.5px;margin-bottom:0;">Questions first? Email <a href="mailto:<?= h(SUPPORT_EMAIL) ?>"><?= h(SUPPORT_EMAIL) ?></a>.</p>
    </div>
</main>
</body>
</html>

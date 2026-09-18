<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/oauth.php';

enforce_maintenance_mode();

if (current_user()) {
    redirect('index.php');
}

$errors = [];
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($name === '') $errors[] = 'Please enter your name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $confirm) $errors[] = 'Passwords do not match.';

        if (!$errors) {
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'An account with that email already exists.';
            }
        }

        if (!$errors) {
            $isFirstUser = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
            $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, is_admin) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $isFirstUser ? 1 : 0]);
            $_SESSION['user_id'] = (int)db()->lastInsertId();
            flash_set('success', 'Welcome to NutriTale, ' . $name . '!');
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
<title>Create account · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=4">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<div class="auth-split-shell">
    <?= render_theme_toggle() ?>
    <div class="auth-split">
        <div class="auth-brand-panel">
            <div class="auth-brand-photo">
                <img src="assets/img/banners/recipe-book-spread.jpg" alt="A NutriTale recipe page">
            </div>
            <div>
                <p class="auth-brand-quote">"Good food. Better choices. Your story."</p>
                <div class="auth-brand-features">
                    <div class="auth-brand-feature"><?= icon('leaf', 24) ?><span>Healthy<br>Recipes</span></div>
                    <div class="auth-brand-feature"><?= icon('calendar', 24) ?><span>Meal<br>Planning</span></div>
                    <div class="auth-brand-feature"><?= icon('shopping-cart', 24) ?><span>Shopping<br>Lists</span></div>
                    <div class="auth-brand-feature"><?= icon('target', 24) ?><span>Nutrition<br>Goals</span></div>
                </div>
                <p class="auth-brand-signature mt-16">Real food. Real change.</p>
            </div>
        </div>

        <div class="auth-form-panel">
            <div class="auth-form-card">
                <a href="landing.php" class="center-text mb-16" style="display:block;"><?= nutritale_logo_svg(48) ?></a>
                <h1>Create Account</h1>
                <p class="auth-form-subtitle">Start your food journey with NutriTale</p>

                <div class="auth-tabs">
                    <a class="auth-tab" href="login.php" style="text-decoration:none;">Login</a>
                    <span class="auth-tab is-active">Sign Up</span>
                </div>

                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error"><?= h($error) ?></div>
                <?php endforeach; ?>

                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <label class="auth-field-icon">
                        <?= icon('user', 18) ?>
                        <input type="text" name="name" value="<?= h($name) ?>" placeholder="Enter your name" required>
                    </label>
                    <label class="auth-field-icon">
                        <?= icon('mail', 18) ?>
                        <input type="email" name="email" value="<?= h($email) ?>" placeholder="Enter your email" required>
                    </label>
                    <label class="auth-field-icon">
                        <?= icon('lock', 18) ?>
                        <input type="password" name="password" placeholder="Create a password" required minlength="8">
                    </label>
                    <!-- A confirm-password field, kept even though the
                         mockup's card didn't have one: it catches a typo
                         before it locks someone out of a brand-new
                         account, and dropping an existing safeguard isn't
                         part of "match the visual design". -->
                    <label class="auth-field-icon">
                        <?= icon('lock', 18) ?>
                        <input type="password" name="confirm_password" placeholder="Confirm your password" required minlength="8">
                    </label>

                    <button type="submit" class="btn btn-emphasis btn-block mt-16" style="justify-content:center;gap:10px;">Sign Up <?= icon('arrow-right', 18) ?></button>
                </form>

                <div class="auth-divider">or continue with</div>
                <div class="auth-social-grid">
                    <a href="oauth_google.php" class="auth-social-btn"<?= oauth_google_configured() ? '' : ' aria-disabled="true" title="Google sign-in isn\'t set up on this server yet."' ?>><?= icon_google(20) ?> Google</a>
                    <a href="oauth_facebook.php" class="auth-social-btn"<?= oauth_facebook_configured() ? '' : ' aria-disabled="true" title="Facebook sign-in isn\'t set up on this server yet."' ?>><?= icon_facebook(20) ?> Facebook</a>
                </div>

                <p class="auth-switch-link">Already have an account? <a href="login.php">Log In →</a></p>
            </div>
        </div>
    </div>
</div>
</body>
</html>

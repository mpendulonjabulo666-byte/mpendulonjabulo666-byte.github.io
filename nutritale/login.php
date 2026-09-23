<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/oauth.php';

if (current_user()) {
    redirect('index.php');
}

$errors = [];
$email = '';

const MAX_LOGIN_ATTEMPTS = 5;
const LOCKOUT_MINUTES = 15;
// A simple, honest version of "remember me": extends this browser's own
// session cookie lifetime rather than issuing a separate persistent
// login token - real, not decorative (checking the box does keep you
// signed in across a browser restart), just not the more elaborate
// rotating-token scheme some sites use for it.
const REMEMBER_ME_DAYS = 30;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = db()->prepare('SELECT id, name, password_hash, failed_attempts, locked_until, is_admin FROM users WHERE email = ?');
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
            db()->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
            if (!empty($_POST['remember_me'])) {
                session_set_cookie_params(REMEMBER_ME_DAYS * 86400);
                // The session is already open (config.php starts it) -
                // params only take effect for a *new* cookie, so re-send
                // it under the new lifetime rather than the default one.
                setcookie(session_name(), session_id(), time() + REMEMBER_ME_DAYS * 86400, '/');
            }
            $_SESSION['user_id'] = (int)$user['id'];
            redirect($user['is_admin'] ? 'admin.php' : 'index.php');
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
                <a href="landing.php" class="btn btn-text btn-small mb-16"><?= icon('chevron-left', 16) ?> Back to home</a>
                <a href="landing.php" class="center-text mb-16" style="display:block;" aria-label="<?= h(APP_NAME) ?> home"><?= nutritale_logo_svg(48) ?></a>
                <h1>Welcome Back</h1>
                <p class="auth-form-subtitle">Log in to continue your food journey</p>

                <div class="auth-tabs">
                    <span class="auth-tab is-active">Login</span>
                    <a class="auth-tab" href="register.php" style="text-decoration:none;">Sign Up</a>
                </div>

                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error"><?= h($error) ?></div>
                <?php endforeach; ?>
                <?php if ($error = flash_get('error')): ?>
                    <div class="alert alert-error"><?= h($error) ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <label class="auth-field-icon">
                        <?= icon('mail', 18) ?>
                        <input type="email" name="email" value="<?= h($email) ?>" placeholder="Enter your email" aria-label="Email" required>
                    </label>
                    <label class="auth-field-icon">
                        <?= icon('lock', 18) ?>
                        <input type="password" name="password" id="login-password" placeholder="Enter your password" aria-label="Password" autocapitalize="off" autocorrect="off" spellcheck="false" required>
                        <button type="button" id="toggle-password" aria-label="Show password">
                            <span id="toggle-password-icon"><?= icon('eye', 18) ?></span>
                        </button>
                    </label>
                    <script>
                    document.getElementById('toggle-password').addEventListener('click', function () {
                        var field = document.getElementById('login-password');
                        var showing = field.type === 'text';
                        field.type = showing ? 'password' : 'text';
                        this.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                    });
                    </script>

                    <div class="auth-row-between">
                        <label class="auth-checkbox">
                            <input type="checkbox" name="remember_me" value="1">
                            Remember me
                        </label>
                        <a href="forgot_password.php" style="font-weight:700;color:var(--green-dark);">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn btn-emphasis btn-block" style="justify-content:center;gap:10px;">Log In <?= icon('arrow-right', 18) ?></button>
                </form>

                <div class="auth-divider">or continue with</div>
                <div class="auth-social-grid">
                    <a href="oauth_google.php" class="auth-social-btn"<?= oauth_google_configured() ? '' : ' aria-disabled="true" tabindex="-1" title="Google sign-in isn\'t set up on this server yet."' ?>><?= icon_google(20) ?> Google</a>
                    <a href="oauth_facebook.php" class="auth-social-btn"<?= oauth_facebook_configured() ? '' : ' aria-disabled="true" tabindex="-1" title="Facebook sign-in isn\'t set up on this server yet."' ?>><?= icon_facebook(20) ?> Facebook</a>
                    <a href="oauth_apple.php" class="auth-social-btn"<?= oauth_apple_configured() ? '' : ' aria-disabled="true" tabindex="-1" title="Apple sign-in isn\'t set up on this server yet."' ?>><?= icon_apple(20) ?> Apple</a>
                </div>

                <p class="auth-switch-link">Don't have an account? <a href="register.php">Sign Up →</a></p>
            </div>
        </div>
    </div>
</div>
</body>
</html>

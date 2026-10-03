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
// The (IP, email) layer below reuses both of these too - same 5-in-15
// policy, applied to a pairing the pre-existing users.failed_attempts/
// locked_until columns can't cover on their own (an email with no
// matching row never touches those columns at all - see
// sql/migrations.php's 2026_09_21_login_attempts for the full reasoning).
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
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        // Checked before the per-user lockout below, and before touching
        // password_hash at all - this is the layer that covers an email
        // with no matching account, which the per-user columns never see.
        // Cutoff computed in PHP, not MySQL's NOW() - the pre-existing
        // locked_until mechanism below never touches MySQL's own clock for
        // exactly this reason: this dev machine's MySQL runs with
        // time_zone=SYSTEM while config.php explicitly sets PHP to UTC, so
        // NOW() and strtotime() disagreed by the system's real UTC offset -
        // caught live, not from reading the code (a first version of this
        // showed "try again in 135 minutes" instead of 15). Every
        // attempted_at value this app writes and reads is now a PHP-
        // generated UTC string, never MySQL's CURRENT_TIMESTAMP, so the two
        // can't drift apart no matter what timezone MySQL's server happens
        // to be running in.
        $cutoff = (new DateTime())->modify('-' . LOCKOUT_MINUTES . ' minutes')->format('Y-m-d H:i:s');
        $ipAttemptStmt = db()->prepare(
            'SELECT COUNT(*), MAX(attempted_at) FROM login_attempts
             WHERE ip_address = ? AND email = ? AND attempted_at > ?'
        );
        $ipAttemptStmt->execute([$ip, $email, $cutoff]);
        [$recentAttempts, $lastAttemptAt] = $ipAttemptStmt->fetch(PDO::FETCH_NUM);
        $ipLocked = (int)$recentAttempts >= MAX_LOGIN_ATTEMPTS;

        $stmt = db()->prepare('SELECT id, name, password_hash, failed_attempts, locked_until, is_admin FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($ipLocked) {
            // Rolling, not a fixed timer from the first failure - each
            // further attempt while already over the threshold pushes the
            // window later, same as bumping into a "please wait" wall
            // repeatedly rather than one that quietly expires while an
            // attacker is still actively guessing.
            $minutesLeft = max(1, (int)ceil((strtotime($lastAttemptAt) + LOCKOUT_MINUTES * 60 - time()) / 60));
            $errors[] = "Too many attempts. Try again in $minutesLeft minute" . ($minutesLeft === 1 ? '' : 's') . '.';
        } elseif ($user && $user['locked_until'] && new DateTime($user['locked_until']) > new DateTime()) {
            $minutesLeft = max(1, (int)ceil((strtotime($user['locked_until']) - time()) / 60));
            $errors[] = "Too many failed attempts. Try again in $minutesLeft minute" . ($minutesLeft === 1 ? '' : 's') . ', or reset your password.';
        } elseif (!$user || !password_verify($password, $user['password_hash'])) {
            db()->prepare('INSERT INTO login_attempts (ip_address, email, attempted_at) VALUES (?, ?, ?)')
                ->execute([$ip, $email, (new DateTime())->format('Y-m-d H:i:s')]);
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
            db()->prepare('DELETE FROM login_attempts WHERE ip_address = ? AND email = ?')->execute([$ip, $email]);
            db()->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
            if (!empty($_POST['remember_me'])) {
                // The session is already open (config.php starts it) - the
                // new lifetime only takes effect on a freshly re-sent
                // cookie, so re-send it explicitly rather than the default
                // one. Both calls use the full options-array form and
                // repeat every flag config.php's own session_set_cookie_params()
                // set (httponly/secure/samesite) - the old single-argument
                // form used here previously silently reset all of those
                // back to PHP's defaults (secure=false, httponly=false, no
                // samesite) the moment "remember me" was checked, undoing
                // the hardening for exactly the sessions it's meant to
                // protect the longest.
                $cookieOptions = [
                    'expires' => time() + REMEMBER_ME_DAYS * 86400,
                    'path' => '/',
                    'domain' => '',
                    'secure' => is_https_request(),
                    'httponly' => true,
                    'samesite' => 'Lax',
                ];
                session_set_cookie_params($cookieOptions);
                setcookie(session_name(), session_id(), $cookieOptions);
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
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in · <?= APP_NAME ?></title>
<meta name="description" content="Log in to <?= APP_NAME ?> to get back to your saved recipes, meal plans, and pantry-based AI meal ideas.">
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=11">
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

                    <button type="submit" class="ring-pill ring-pill-block ring-pill-strong"><span class="ring-pill-disc"><?= icon('arrow-right', 16) ?></span><span class="ring-pill-label">Log in</span></button>
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

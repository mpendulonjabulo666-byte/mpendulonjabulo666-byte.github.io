<?php
require_once __DIR__ . '/../config/database.php';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT id, name, email, onboarded_at, is_admin, email_notifications, is_vendor, is_premium_member, pantry_free_uses_used, created_at FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
        if ($user && $user['is_premium_member']) {
            $user = premium_enforce_expiry($user);
        }
    }
    return $user;
}

// Closes CONTINUE.md §2.5's "stays premium forever" gap, lazily: called
// once per request, for any user currently flagged premium, from the one
// place (current_user()) every page already goes through. If their latest
// active subscription's paid-through date has passed, downgrades them
// immediately rather than waiting for a cancellation ITN that a silently
// failed renewal may never send - payfast_notify.php extends
// current_period_end by one billing period on every successful charge (see
// its subscription branch), so a lapsed one means the last real charge was
// over a month ago with no successful renewal since.
//
// A NULL period-end (no active subscription row, or one created before
// sql/migrations.php's 2026_09_15_premium_period_end ran and never
// renewed since) is left alone, not treated as expired - we don't know
// that subscription's real paid-through date, and guessing "expired"
// would downgrade someone who may still be legitimately paying. It starts
// being enforced from that subscription's next successful charge, same as
// any pre-migration row.
function premium_enforce_expiry(array $user): array
{
    $stmt = db()->prepare(
        "SELECT current_period_end FROM premium_subscriptions
         WHERE user_id = ? AND status = 'active'
         ORDER BY current_period_end DESC LIMIT 1"
    );
    $stmt->execute([$user['id']]);
    $periodEnd = $stmt->fetchColumn();

    if (!$periodEnd || strtotime($periodEnd) >= time()) {
        return $user;
    }

    db()->prepare('UPDATE users SET is_premium_member = 0 WHERE id = ?')->execute([$user['id']]);
    db()->prepare("UPDATE premium_subscriptions SET status = 'expired' WHERE user_id = ? AND status = 'active'")->execute([$user['id']]);
    $user['is_premium_member'] = 0;
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        header('Location: login.php');
        exit;
    }
    // A single choke point for every protected page in the app (recipes,
    // pantry, planner, marketplace, profile, admin's own require_admin()
    // included) rather than a call sprinkled into each one individually -
    // login.php/register.php aren't behind require_login(), so an admin
    // can always still sign in to turn maintenance mode back off.
    enforce_maintenance_mode();
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if (empty($user['is_admin'])) {
        http_response_code(403);
        die('Admins only.');
    }
    return $user;
}

// Backs admin_settings.php's four platform toggles. Cached per-request in
// a static array since several pages (nav.php included) check one of
// these on every load - one row fetched once, not once per check.
function platform_setting(string $key): bool
{
    static $settings = null;
    if ($settings === null) {
        $settings = db()->query('SELECT * FROM platform_settings WHERE id = 1')->fetch() ?: [];
    }
    return !empty($settings[$key]);
}

// Called from the top of any page that should be closed to non-admins
// while maintenance_mode is on. Deliberately not enforced globally from
// config.php: login.php/logout.php must keep working so an admin can
// still sign in to turn it back off.
function enforce_maintenance_mode(): void
{
    if (!platform_setting('maintenance_mode')) {
        return;
    }
    $user = current_user();
    if ($user && !empty($user['is_admin'])) {
        return;
    }
    http_response_code(503);
    require __DIR__ . '/../maintenance.php';
    exit;
}

function flash_set(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (empty($_SESSION['flash'][$key])) {
        return null;
    }
    $message = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);
    return $message;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

// Splits a gross sale amount into [platformFee, sellerAmount] using
// PLATFORM_COMMISSION_PCT, rounded to cents.
function platform_fee_split(float $amount): array
{
    $fee = round($amount * PLATFORM_COMMISSION_PCT / 100, 2);
    return [$fee, round($amount - $fee, 2)];
}

function app_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return $scheme . '://' . $_SERVER['HTTP_HOST'] . $dir . '/';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

// Standing safety notices. Each one belongs next to the thing it qualifies,
// not in a footer - see CONTINUE.md §2.3 for where each is placed.
const DISCLAIMERS = [
    'nutrition' => 'Nutrition figures are estimates and are not intended for medical use.',
    'allergens' => 'Allergen filtering is not a guarantee. Always check ingredient labels, especially for severe allergies.',
    'medical'   => 'NutriTale does not give medical advice. Speak to a doctor or registered dietitian before changing your diet for health reasons.',
];

function disclaimer(string $kind): string
{
    if (!isset(DISCLAIMERS[$kind])) {
        throw new InvalidArgumentException("Unknown disclaimer: $kind");
    }
    return '<p class="disclaimer" role="note">' . h(DISCLAIMERS[$kind]) . '</p>';
}

// CONTINUE.md §2.8 / Step 9: PHP's mail() (the old body of this function)
// is silently dropped by many hosts with no error to catch - real SMTP via
// PHPMailer, configured with real credentials (config.php's SMTP_*
// constants), actually gets a delivery attempt and a real error if one
// fails. SMTP_HOST left blank (the shipped default) falls back to mail()
// rather than refusing to send at all - the same "safe empty default,
// feature just doesn't fully work until configured" pattern as
// GEMINI_API_KEY and the OAuth credentials elsewhere in config.php, not a
// dead end that needs a code change to recover from.
function send_notification_email(string $to, string $subject, string $body): void
{
    if (SMTP_HOST === '') {
        @mail($to, $subject, $body, 'From: ' . APP_NAME . ' <no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>');
        return;
    }

    require_once __DIR__ . '/PHPMailer/Exception.php';
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->Port = SMTP_PORT;
        $mail->SMTPAutoTLS = false; // explicit SMTP_ENCRYPTION below decides this, not a guess based on the port
        if (SMTP_USERNAME !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
        }
        if (SMTP_ENCRYPTION !== '') {
            $mail->SMTPSecure = SMTP_ENCRYPTION;
        }
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        // Logged, not thrown further - a notification email failing to
        // send (e.g. a rating notice) should never break the page action
        // that triggered it. error_log() is always on regardless of
        // APP_DEBUG (config.php), so this is still visible to whoever's
        // checking the server's error log.
        error_log('send_notification_email: SMTP send to ' . $to . ' failed: ' . $mail->ErrorInfo);
    }
}

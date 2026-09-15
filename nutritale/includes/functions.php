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

function send_notification_email(string $to, string $subject, string $body): void
{
    // No SMTP is configured for this app by default. This is a best-effort
    // send via PHP's mail() function; wire a real mail service (or SMTP in
    // php.ini) in production for this to actually deliver.
    @mail($to, $subject, $body, 'From: ' . APP_NAME . ' <no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '>');
}

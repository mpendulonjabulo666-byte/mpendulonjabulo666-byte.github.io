<?php
// Pantry expiry dates + the "use these up" banner (pantry.php and index.php).
//
// Dates live in user_pantry_items.expires_on (added by migration
// 2026_09_30_pantry_expiry). Everything here fails soft: if the column
// isn't there yet (setup.php not re-run after an upload) there are simply no
// alerts and the pantry keeps working.

const PANTRY_EXPIRY_WARN_DAYS = 3;
const PANTRY_EXPIRY_MAX_LISTED = 6;

function pantry_expiry_tz(): DateTimeZone
{
    return new DateTimeZone('Africa/Johannesburg');
}

function pantry_expiry_today(): DateTimeImmutable
{
    return new DateTimeImmutable('today', pantry_expiry_tz());
}

// "2026-10-02" -> "2026-10-02"; anything else (blank, bad format, absurd year) -> null.
function pantry_parse_expiry(?string $raw): ?string
{
    $raw = trim((string)$raw);
    if ($raw === '') {
        return null;
    }
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, pantry_expiry_tz());
    if (!$d || $d->format('Y-m-d') !== $raw) {
        return null;
    }
    $today = pantry_expiry_today();
    if ($d < $today->modify('-1 year') || $d > $today->modify('+5 years')) {
        return null;
    }
    return $raw;
}

// [ingredient_name => 'YYYY-MM-DD'] for this user's dated items.
function pantry_expiry_map(int $userId): array
{
    try {
        $stmt = db()->prepare('SELECT ingredient_name, expires_on FROM user_pantry_items WHERE user_id = ? AND expires_on IS NOT NULL');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $e) {
        return [];
    }
}

// Whole days from today until $date (negative = already past).
function pantry_expiry_days(string $date, ?DateTimeImmutable $today = null): int
{
    $today = $today ?? pantry_expiry_today();
    $d = new DateTimeImmutable($date . ' 00:00:00', pantry_expiry_tz());
    return (int)$today->diff($d)->format('%r%a');
}

function pantry_expiry_state(int $days): string
{
    if ($days < 0) {
        return 'expired';
    }
    if ($days === 0) {
        return 'today';
    }
    return $days <= PANTRY_EXPIRY_WARN_DAYS ? 'soon' : 'ok';
}

function pantry_expiry_label(int $days): string
{
    if ($days < -1) {
        return 'expired ' . abs($days) . ' days ago';
    }
    if ($days === -1) {
        return 'expired yesterday';
    }
    if ($days === 0) {
        return 'expires today';
    }
    if ($days === 1) {
        return 'expires tomorrow';
    }
    return 'expires in ' . $days . ' days';
}

// Items that are expired or due within the warning window, soonest first.
// Each entry: ['name' => string, 'days' => int, 'state' => 'expired'|'today'|'soon'].
function pantry_expiry_alerts(array $map, ?DateTimeImmutable $today = null): array
{
    $alerts = [];
    foreach ($map as $name => $date) {
        $days = pantry_expiry_days((string)$date, $today);
        $state = pantry_expiry_state($days);
        if ($state !== 'ok') {
            $alerts[] = ['name' => (string)$name, 'days' => $days, 'state' => $state];
        }
    }
    usort($alerts, fn($a, $b) => [$a['days'], $a['name']] <=> [$b['days'], $b['name']]);
    return $alerts;
}

function render_pantry_expiry_banner(array $alerts, bool $linkToPantry = false): string
{
    if (!$alerts) {
        return '';
    }
    $hasExpired = (bool)array_filter($alerts, fn($a) => $a['state'] === 'expired');
    $shown = array_slice($alerts, 0, PANTRY_EXPIRY_MAX_LISTED);
    $more = count($alerts) - count($shown);

    $items = [];
    foreach ($shown as $a) {
        $items[] = '<span class="expiry-item expiry-' . h($a['state']) . '"><strong>' . h($a['name']) . '</strong> ('
            . h(pantry_expiry_label($a['days'])) . ')</span>';
    }
    $html = '<div class="expiry-banner' . ($hasExpired ? ' has-expired' : '') . '" role="status">'
        . icon('clock', 18)
        . '<p><strong>' . ($hasExpired ? 'Check your pantry:' : 'Use these up soon:') . '</strong> '
        . implode(', ', $items)
        . ($more > 0 ? ' and ' . (int)$more . ' more' : '')
        . '.</p>';
    if ($linkToPantry) {
        $html .= '<a href="pantry.php" class="btn btn-small">Open pantry</a>';
    }
    return $html . '</div>';
}

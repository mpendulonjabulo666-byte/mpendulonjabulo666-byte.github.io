<?php
require_once __DIR__ . '/../config/db_conn.php';

// Caching and cost-bounding for AI pantry suggestions (CONTINUE.md step 5).
// Kept separate from ai_pantry.php, which is deliberately DB-free and
// tested without a database, an API key, or a network call - see
// tests/ai_pantry_test.php. Everything in this file needs the ai_generations
// table from sql/db_migrations.php.
//
// A successful request (status 'ok') is stored under ai_pantry_hash() from
// includes/ai_pantry.php. An identical request within AI_PANTRY_CACHE_DAYS
// is served from here instead of calling Gemini again - which also means
// it costs nobody a free trial use or a daily-cap count, since nothing new
// was generated. A failed request (status 'error') is logged for
// visibility but never cached: retrying a transient failure should get a
// fresh attempt, not a replay of the same error.

// The cached result for an identical request made within the last
// $withinDays, or null if there isn't one.
function ai_cache_lookup(int $userId, string $pantryHash, int $withinDays): ?array
{
    $stmt = db()->prepare(
        "SELECT result_json FROM ai_generations
         WHERE user_id = ? AND pantry_hash = ? AND status = 'ok'
           AND created_at >= (NOW() - INTERVAL ? DAY)
         ORDER BY created_at DESC LIMIT 1"
    );
    $stmt->execute([$userId, $pantryHash, $withinDays]);
    $json = $stmt->fetchColumn();
    if ($json === false) {
        return null;
    }
    $result = json_decode($json, true);
    return is_array($result) ? $result : null;
}

// How many Gemini calls (attempts, not button presses - a regeneration
// triggered by an allergen violation still costs one) this user has made
// today. Compared against AI_PANTRY_DAILY_CAP for premium/admin accounts,
// which pantry.php's free-trial counter never touches - see config.php.
//
// $windowDays = 1 (default, premium/admin) counts since midnight today.
// A larger window (free accounts: AI_PANTRY_FREE_WINDOW_DAYS, 3) counts a
// rolling N x 24 hours instead, so "2 ideas per 3 days" is a real rolling
// limit rather than a calendar one.
function ai_daily_attempt_count(int $userId, int $windowDays = 1): int
{
    if ($windowDays <= 1) {
        $stmt = db()->prepare(
            'SELECT COALESCE(SUM(attempts), 0) FROM ai_generations WHERE user_id = ? AND created_at >= CURDATE()'
        );
        $stmt->execute([$userId]);
    } else {
        $stmt = db()->prepare(
            'SELECT COALESCE(SUM(attempts), 0) FROM ai_generations WHERE user_id = ? AND created_at >= (NOW() - INTERVAL ? DAY)'
        );
        $stmt->execute([$userId, $windowDays]);
    }
    return (int)$stmt->fetchColumn();
}

// Seconds since this user's last logged attempt (any status), or null if
// they've never made one. Checked against AI_PANTRY_COOLDOWN_SECONDS
// before a *new* Gemini call - never before a cache hit, which costs
// nothing and shouldn't be throttled. The diff is computed by MySQL
// itself (TIMESTAMPDIFF against its own NOW()), not PHP's time() against
// a fetched timestamp string - this dev machine's MySQL runs with
// time_zone=SYSTEM while PHP is set to UTC (see CONTINUE.md's login-
// lockout note for the same bug already caught there), so comparing a
// MySQL-written created_at against PHP's clock came back negative in
// testing. Keeping both sides of the comparison on MySQL's own clock,
// same fix shape as ai_cache_lookup()/ai_daily_attempt_count() below
// already use, avoids the mismatch entirely rather than trying to
// correct for an offset that varies by deployment.
function ai_seconds_since_last_attempt(int $userId): ?int
{
    $stmt = db()->prepare('SELECT TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) FROM ai_generations WHERE user_id = ?');
    $stmt->execute([$userId]);
    $seconds = $stmt->fetchColumn();
    return $seconds !== null ? (int)$seconds : null;
}

// Records one AI pantry request. Only called for a request that actually
// reached Gemini at least once - pantry.php skips this for a result whose
// 'attempts' is 0 (blocked before any call, e.g. no API key configured),
// since there is nothing to bound or make visible.
function ai_log_generation(int $userId, string $pantryHash, array $result): void
{
    $attempts = (int)($result['attempts'] ?? 1);
    $stmt = db()->prepare(
        'INSERT INTO ai_generations (user_id, pantry_hash, status, attempts, result_json) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $pantryHash,
        $result['ok'] ? 'ok' : 'error',
        max(1, $attempts),
        $result['ok'] ? json_encode([
            'ok' => true,
            'meals' => $result['meals'],
            'discarded' => $result['discarded'] ?? 0,
            'allergens_enforced' => $result['allergens_enforced'] ?? [],
        ]) : null,
    ]);
}

<?php
// Web-driven stand-in for scripts/fetch_recipe_images.php and
// scripts/apply_recipe_images.php, built because InfinityFree's free tier
// has no SSH/CLI and no cron jobs (both confirmed live, 2026-10-06) - so
// those two scripts, written to loop for 40+ minutes with internal sleep(),
// can't run there at all. This does the same job one recipe per PAGE LOAD
// instead, auto-refreshing itself so leaving the tab open reproduces the
// same pacing the CLI scripts used (RECIPE_PACE_SECONDS). Admin-gated
// rather than a secret-token URL since this app already has that auth and
// it's one less secret to manage.
//
// Keeps the same on-disk contract as the CLI scripts (same
// scripts/image_review/*.jpg review copies, same data/image_attribution.json)
// so whichever one runs next - this page, or the real CLI scripts once
// Varrick has SSH somewhere - picks up exactly where the other left off.
//
// Deliberately does NOT touch scripts/fetch_recipe_images.php or
// scripts/apply_recipe_images.php - those stay correct for a real CLI
// environment (local XAMPP, or a future host with shell access).

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_admin();

const WEB_RECIPE_PACE_SECONDS = 85; // matches scripts/fetch_recipe_images.php's RECIPE_PACE_SECONDS
const WEB_APPLY_PACE_SECONDS = 3;   // matches scripts/apply_recipe_images.php's sleep(2) between pings, +1 for page overhead

$reviewDir = __DIR__ . '/scripts/image_review';
$attrFile = __DIR__ . '/data/image_attribution.json';
$missFile = $reviewDir . '/misses.json';
if (!is_dir($reviewDir)) {
    @mkdir($reviewDir, 0755, true);
}

function wi_title_words(string $title): array
{
    $stop = ['with', 'and', 'the', 'style', 'side', 'dish', 'plate', 'for', 'from'];
    preg_match_all('/[a-z]{3,}/', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title), $m);
    return array_values(array_diff(array_unique($m[0]), $stop));
}

// Single attempt, short timeout - no internal sleep/retry loop, since this
// runs inside a web request. A rate limit just means "wait longer before
// the next page load" (handled by the caller setting a longer refresh delay).
function wi_unsplash_get(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Client-ID ' . UNSPLASH_ACCESS_KEY, 'Accept-Version: v1'],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_SSL_OPTIONS => defined('CURLSSLOPT_NATIVE_CA') ? CURLSSLOPT_NATIVE_CA : 16,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, $body !== false ? json_decode($body, true) : null, (string)$body];
}

$action = $_GET['action'] ?? '';
$result = null; // ['message' => string, 'done' => bool, 'wait' => int, 'photo_b64' => ?string]

if ($action === 'fetch' && UNSPLASH_ACCESS_KEY !== '') {
    $misses = is_file($missFile) ? (json_decode((string)file_get_contents($missFile), true) ?: []) : [];
    $attribution = is_file($attrFile) ? (json_decode((string)file_get_contents($attrFile), true) ?: []) : [];
    $skipIds = array_keys($misses);
    $recipe = null;
    $stmt = db()->query("SELECT id, title, cuisine FROM recipes WHERE (image_url IS NULL OR image_url = '') ORDER BY title");
    foreach ($stmt->fetchAll() as $r) {
        if (isset($misses[$r['id']])) continue;
        if (isset($attribution[$r['id']]) && is_file($reviewDir . '/' . $r['id'] . '.jpg')) continue; // already fetched, awaiting apply
        $recipe = $r;
        break;
    }

    if (!$recipe) {
        $result = ['message' => 'No recipes left to fetch - every recipe either has an image, a review copy waiting to be applied, or was marked as a miss.', 'done' => true];
    } else {
        $cuisine = in_array($recipe['cuisine'], [null, '', 'International'], true) ? '' : $recipe['cuisine'];
        preg_match('/\(([^)]+)\)/', $recipe['title'], $alt);
        $query = trim(preg_replace('/[()]/', '', $recipe['title']) . ' ' . $cuisine . ' food');
        [$status, $data, $raw] = wi_unsplash_get(
            'https://api.unsplash.com/search/photos?per_page=10&orientation=landscape&content_filter=high&query=' . urlencode($query)
        );
        $limited = $status === 429 || ($status === 403 && stripos($raw, 'rate limit') !== false);
        if ($limited) {
            $result = ['message' => "Rate limited (HTTP $status) on \"{$recipe['title']}\" - Unsplash's demo cap is 50 requests/hour. Waiting 10 minutes before retrying.", 'done' => false, 'wait' => 600];
        } elseif ($status !== 200 || empty($data['results'])) {
            $misses[$recipe['id']] = ['title' => $recipe['title'], 'queries' => [$query]];
            file_put_contents($missFile, json_encode($misses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $result = ['message' => "\"{$recipe['title']}\": " . ($status !== 200 ? "API error (HTTP $status)" : 'no results') . " - marked as a miss, moving on.", 'done' => false, 'wait' => WEB_RECIPE_PACE_SECONDS];
        } else {
            $words = wi_title_words($recipe['title']);
            $best = 0;
            $bestScore = -1;
            foreach ($data['results'] as $idx => $p) {
                $hay = strtolower(($p['alt_description'] ?? '') . ' ' . ($p['description'] ?? '') . ' '
                    . implode(' ', array_column($p['tags'] ?? [], 'title')));
                $score = count(array_filter($words, fn($w) => str_contains($hay, $w)));
                if ($score > $bestScore) { $bestScore = $score; $best = $idx; }
            }
            $photo = $data['results'][$best];
            $ch = curl_init($photo['urls']['regular']);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_OPTIONS => defined('CURLSSLOPT_NATIVE_CA') ? CURLSSLOPT_NATIVE_CA : 16]);
            $bytes = curl_exec($ch);
            $imgStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($bytes === false || $imgStatus !== 200) {
                $result = ['message' => "\"{$recipe['title']}\": review-copy download failed (HTTP $imgStatus) - will retry next load.", 'done' => false, 'wait' => WEB_RECIPE_PACE_SECONDS];
            } else {
                file_put_contents($reviewDir . '/' . $recipe['id'] . '.jpg', $bytes);
                $utm = 'utm_source=nutritale&utm_medium=referral';
                $profile = $photo['user']['links']['html'] ?? '';
                $attribution[$recipe['id']] = [
                    'title' => $recipe['title'],
                    'image_url' => $photo['urls']['regular'],
                    'download_location' => $photo['links']['download_location'] ?? '',
                    'photographer_name' => $photo['user']['name'] ?? '',
                    'photographer_url' => $profile . (str_contains($profile, '?') ? '&' : '?') . $utm,
                    'photo_page_url' => ($photo['links']['html'] ?? '') . '?' . $utm,
                    'alt_description' => $photo['alt_description'] ?? '',
                    'match_score' => $bestScore . '/' . count($words),
                    'search_query' => $query,
                ];
                file_put_contents($attrFile, json_encode($attribution, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                $result = [
                    'message' => "\"{$recipe['title']}\": fetched (match {$bestScore}/" . count($words) . ", \"" . ($photo['alt_description'] ?? '') . "\"). Review below - if it doesn't match, delete scripts/image_review/{$recipe['id']}.jpg via File Manager before running Apply.",
                    'done' => false,
                    'wait' => WEB_RECIPE_PACE_SECONDS,
                    'photo_b64' => base64_encode($bytes),
                ];
            }
        }
    }
} elseif ($action === 'apply') {
    $attribution = is_file($attrFile) ? (json_decode((string)file_get_contents($attrFile), true) ?: []) : [];
    $ids = db()->query("SELECT id FROM recipes WHERE image_url IS NULL OR image_url = ''")->fetchAll(PDO::FETCH_COLUMN);
    $target = null;
    foreach ($ids as $id) {
        $entry = $attribution[$id] ?? null;
        if ($entry && is_file($reviewDir . '/' . $id . '.jpg') && !empty($entry['image_url'])) {
            $target = ['id' => $id, 'entry' => $entry];
            break;
        }
    }
    if (!$target) {
        $result = ['message' => 'Nothing left to apply - every reviewed photo has already been applied (or none have been fetched yet).', 'done' => true];
    } else {
        $entry = $target['entry'];
        $status = 200;
        if (!empty($entry['download_location'])) {
            [$status, , $raw] = wi_unsplash_get($entry['download_location']);
        }
        $limited = $status === 429 || ($status === 403 && stripos($raw ?? '', 'rate limit') !== false);
        if ($limited) {
            $result = ['message' => "Rate limited (HTTP $status) applying \"{$entry['title']}\" - waiting 10 minutes.", 'done' => false, 'wait' => 600];
        } elseif ($status !== 200) {
            $result = ['message' => "\"{$entry['title']}\": download ping failed (HTTP $status) - not applying a photo we couldn't register with Unsplash. Will retry next load.", 'done' => false, 'wait' => WEB_APPLY_PACE_SECONDS];
        } else {
            db()->prepare('UPDATE recipes SET image_url = ? WHERE id = ?')->execute([$entry['image_url'], $target['id']]);
            $result = ['message' => "\"{$entry['title']}\": applied.", 'done' => false, 'wait' => WEB_APPLY_PACE_SECONDS];
        }
    }
}

// Counts for the dashboard view.
$missing = (int)db()->query("SELECT COUNT(*) FROM recipes WHERE image_url IS NULL OR image_url = ''")->fetchColumn();
$attribution = is_file($attrFile) ? (json_decode((string)file_get_contents($attrFile), true) ?: []) : [];
$awaitingApply = 0;
foreach ($attribution as $id => $e) {
    if (is_file($reviewDir . '/' . $id . '.jpg')) {
        $row = db()->prepare('SELECT image_url FROM recipes WHERE id = ?');
        $row->execute([$id]);
        $url = $row->fetchColumn();
        if ($url === '' || $url === false) $awaitingApply++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Import recipe images · <?= APP_NAME ?></title>
<?php if ($result && !$result['done'] && isset($result['wait'])): ?>
<meta http-equiv="refresh" content="<?= (int)$result['wait'] ?>;url=?action=<?= h($action) ?>">
<?php endif; ?>
<link rel="stylesheet" href="assets/css/style.css?v=22">
<style>
  body { max-width: 640px; margin: 40px auto; padding: 0 16px; font-family: sans-serif; }
  .wi-box { border: 1px solid #ccc; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
  .wi-photo { max-width: 100%; border-radius: 8px; margin-top: 12px; }
  .wi-count { font-size: 14px; opacity: 0.8; }
</style>
</head>
<body>
<h1>Import recipe images</h1>
<p class="wi-count">Recipes still missing an image: <strong><?= $missing ?></strong> &middot; fetched, awaiting apply: <strong><?= $awaitingApply ?></strong></p>

<?php if (UNSPLASH_ACCESS_KEY === ''): ?>
<div class="wi-box">UNSPLASH_ACCESS_KEY is blank in config/config.php - set it first.</div>
<?php endif; ?>

<?php if ($result): ?>
<div class="wi-box">
    <p><?= h($result['message']) ?></p>
    <?php if (!empty($result['photo_b64'])): ?>
        <img class="wi-photo" src="data:image/jpeg;base64,<?= $result['photo_b64'] ?>" alt="">
    <?php endif; ?>
    <?php if (!$result['done'] && isset($result['wait'])): ?>
        <p class="wi-count">Auto-continuing in <?= (int)$result['wait'] ?>s - leave this tab open. (Or wait and refresh manually.)</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<p>
    <a class="btn btn-primary" href="?action=fetch">Fetch next (one recipe, from Unsplash)</a>
    &nbsp;
    <a class="btn" href="?action=apply">Apply next (one reviewed photo &rarr; live)</a>
</p>
<p class="wi-count">
    Fetch finds a photo and saves a review copy to <code>scripts/image_review/</code> (not public - blocked by its own folder rules).
    Check the photo actually matches the dish before it's applied. Apply sends Unsplash's required usage ping and sets the recipe's live image.
    Paced the same as the CLI scripts (~85s between fetches, Unsplash's free-tier limit is 50 requests/hour) - click once and leave the tab open, it reloads itself.
</p>
</body>
</html>

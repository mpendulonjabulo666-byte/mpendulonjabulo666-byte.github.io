<?php
// Finds candidate food photos on Unsplash for recipes that have no image
// yet - via Unsplash's official API, never by scraping Google Images or a
// recipe blog (this app has been burned by that once, on the landing page
// hero photo).
//
// Unsplash API terms this script is built around
// (help.unsplash.com/en/articles/2511245):
//   - HOTLINK, don't self-host: the app must use the image URLs the API
//     returns (photo.urls.*, ixid parameter intact). So recipes.image_url
//     ends up holding an images.unsplash.com URL, not a local file. The
//     copies this script downloads are REVIEW COPIES ONLY, kept in
//     scripts/image_review/ (blocked from the web by .htaccess) so a human
//     can check each photo actually matches its dish.
//   - ATTRIBUTE: "Photo by {name} on Unsplash", both linked with
//     utm_source/utm_medium - recipe_photo_credit() in
//     includes/functions_core.php renders it from data/image_attribution.json.
//   - DOWNLOAD PING: required when a photo is actually chosen for use, so
//     it is sent by apply_recipe_images.php for approved photos only, not
//     here for every search result.
//
// Needs UNSPLASH_ACCESS_KEY in config/config.php (or the environment).
//
// Per recipe: one search ("{title} {cuisine} food", 10 landscape results),
// pick the result whose alt text/tags best overlap the dish name's words
// (falls back to Unsplash's own top result), save a review copy, and record
// the photographer + hotlink URL in data/image_attribution.json (written
// after every recipe, merged with anything already there, so it is safe to
// stop and re-run - already-fetched recipes are skipped).
//
// Rate limit: a demo app gets 50 API requests/hour. This uses 1 request per
// recipe and paces itself to one recipe every RECIPE_PACE_SECONDS (85s =>
// ~42/hour), and if Unsplash still answers 429/403-rate-limited it waits and
// retries the same recipe rather than skipping it.
//
// Usage: php scripts/fetch_recipe_images.php [--all]
//   default: published recipes only. --all: also unpublished ones.

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/db_conn.php';

const RECIPE_PACE_SECONDS = 85;

if (UNSPLASH_ACCESS_KEY === '') {
    fwrite(STDERR, "UNSPLASH_ACCESS_KEY is blank in config/config.php - nothing to do.\n");
    exit(1);
}

$all = in_array('--all', $argv ?? [], true);
$one = in_array('--one', $argv ?? [], true); // process ONE pending recipe, no internal sleeping; prints CALLS=n (caller paces)
$recipes = db()->query(
    "SELECT id, title, cuisine FROM recipes WHERE (image_url IS NULL OR image_url = '')"
    . ($all ? '' : ' AND is_published = 1') . ' ORDER BY title'
)->fetchAll();

if (!$recipes) {
    echo "Every recipe already has an image_url - nothing to do.\n";
    exit(0);
}

$reviewDir = __DIR__ . '/image_review';
$attrFile = __DIR__ . '/../data/image_attribution.json';
if (!is_dir($reviewDir)) {
    mkdir($reviewDir, 0755, true);
}
$missFile = $reviewDir . '/misses.json'; // recipes Unsplash had nothing for - not retried on every run
$misses = is_file($missFile) ? (json_decode((string)file_get_contents($missFile), true) ?: []) : [];
$attribution = is_file($attrFile) ? (json_decode((string)file_get_contents($attrFile), true) ?: []) : [];

function unsplash_get(string $url): array
{
    $remaining = null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Client-ID ' . UNSPLASH_ACCESS_KEY, 'Accept-Version: v1'],
        CURLOPT_TIMEOUT => 20,
        // Trust the OS certificate store: on this Windows dev machine an
        // antivirus HTTPS scanner re-signs TLS with its own root, which the
        // OS trusts but PHP's bundled CA list doesn't. Verification stays ON.
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_OPTIONS => defined('CURLSSLOPT_NATIVE_CA') ? CURLSSLOPT_NATIVE_CA : 16,
        CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$remaining) {
            if (stripos($line, 'X-Ratelimit-Remaining:') === 0) {
                $remaining = (int)trim(substr($line, 22));
            }
            return strlen($line);
        },
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException("cURL error: $err");
    }
    return [$status, json_decode($body, true), $remaining, $body];
}

function unsplash_get_patient(string $url): array
{
    for ($try = 0; $try < 6; $try++) {
        try {
            [$status, $data, $remaining, $raw] = unsplash_get($url);
        } catch (RuntimeException $e) {
            // Transient network failure (timeout, dropped connection) - not
            // worth losing a 55-minute run over.
            echo "  network error (" . $e->getMessage() . ") - retrying in 30s...
";
            sleep(30);
            $status = 0; $data = null; $remaining = null; $raw = '';
            continue;
        }
        $limited = $status === 429 || ($status === 403 && stripos($raw, 'rate limit') !== false);
        if (!$limited) {
            return [$status, $data, $remaining];
        }
        echo "  rate limited (HTTP $status) - waiting 10 minutes, then retrying this recipe...\n";
        sleep(600);
    }
    return [$status, $data, $remaining];
}

function title_words(string $title): array
{
    $stop = ['with', 'and', 'the', 'style', 'side', 'dish', 'plate', 'for', 'from'];
    preg_match_all('/[a-z]{3,}/', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title), $m);
    return array_values(array_diff(array_unique($m[0]), $stop));
}

$fetched = 0;
$skipped = [];
$last = count($recipes) - 1;
foreach ($recipes as $i => $r) {
    $id = $r['id'];
    if (isset($attribution[$id]) && is_file($reviewDir . '/' . $id . '.jpg')) {
        echo "[" . ($i + 1) . "/" . count($recipes) . "] {$r['title']}: already fetched - skipping.\n";
        continue;
    }

    if (isset($misses[$id])) {
        continue;
    }

    $cuisine = in_array($r['cuisine'], [null, '', 'International'], true) ? '' : $r['cuisine'];
    // "Amagwinya (Fat Cakes)": search the name with the brackets removed
    // first; if Unsplash has nothing, retry once with just the bracketed
    // alternative name ("Fat Cakes"), which is often the searchable one.
    preg_match('/\(([^)]+)\)/', $r['title'], $alt);
    $queries = [trim(preg_replace('/[()]/', '', $r['title']) . ' ' . $cuisine . ' food')];
    if (!empty($alt[1])) {
        $queries[] = trim($alt[1] . ' ' . $cuisine . ' food');
    }
    echo "[" . ($i + 1) . "/" . count($recipes) . "] ";
    $calls = 0;
    foreach ($queries as $query) {
        echo "\"$query\"... ";
        [$status, $data, $remaining] = unsplash_get_patient(
            'https://api.unsplash.com/search/photos?per_page=10&orientation=landscape&content_filter=high&query=' . urlencode($query)
        );
        $calls++;
        if ($status === 200 && !empty($data['results'])) {
            break;
        }
        echo "none. ";
        if (!$one && count($queries) > 1 && $calls < count($queries)) {
            sleep(RECIPE_PACE_SECONDS);
        }
    }
    if ($status !== 200 || empty($data['results'])) {
        echo $status !== 200 ? "API error (HTTP $status) - skipped.\n" : "no results - skipped.\n";
        $skipped[] = "$id ($status)";
        if ($status === 200) {
            $misses[$id] = ['title' => $r['title'], 'queries' => $queries];
            file_put_contents($missFile, json_encode($misses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    } else {
        $words = title_words($r['title']);
        $best = 0;
        $bestScore = -1;
        foreach ($data['results'] as $idx => $p) {
            $hay = strtolower(($p['alt_description'] ?? '') . ' ' . ($p['description'] ?? '') . ' '
                . implode(' ', array_column($p['tags'] ?? [], 'title')));
            $score = count(array_filter($words, fn($w) => str_contains($hay, $w)));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $idx;
            }
        }
        $photo = $data['results'][$best];

        $ch = curl_init($photo['urls']['regular']);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => true, CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_OPTIONS => defined('CURLSSLOPT_NATIVE_CA') ? CURLSSLOPT_NATIVE_CA : 16]);
        $bytes = curl_exec($ch);
        $imgStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($bytes === false || $imgStatus !== 200) {
            echo "review-copy download failed - skipped.\n";
            $skipped[] = "$id (download)";
        } else {
            file_put_contents($reviewDir . '/' . $id . '.jpg', $bytes);
            $utm = 'utm_source=nutritale&utm_medium=referral';
            $profile = $photo['user']['links']['html'] ?? '';
            $attribution[$id] = [
                'title' => $r['title'],
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
            $fetched++;
            echo "ok (match {$bestScore}/" . count($words) . ", \"" . ($photo['alt_description'] ?? '') . "\", API calls left: " . ($remaining ?? '?') . ")\n";
        }
    }

    if ($one) {
        echo "CALLS=" . ($remaining !== null && $remaining < 3 ? 15 : $calls) . "
";
        exit(0);
    }
    if ($i < $last) {
        // Two low-quota safety nets on top of the fixed pace.
        sleep($remaining !== null && $remaining < 3 ? 1200 : RECIPE_PACE_SECONDS);
    }
}

echo "\n== Done ==\nFetched: $fetched\nSkipped: " . count($skipped) . ($skipped ? ' (' . implode(', ', $skipped) . ')' : '') . "\n";
echo "\nNEXT STEPS (manual, deliberately not automated):\n";
echo "1. Look at scripts/image_review/*.jpg against each recipe title; delete any that\n";
echo "   don't match the dish.\n";
echo "2. php scripts/apply_recipe_images.php - for each review copy still present, sends\n";
echo "   Unsplash's required download ping and sets recipes.image_url to the hotlink URL.\n";

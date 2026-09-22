<?php
// Sources real, properly-licensed food photos for recipes that don't have
// one yet, via the Unsplash API - never by hotlinking/scraping Google
// Images or a random blog (this app has already been burned by that once,
// on the landing page hero photo - see CONTINUE.md's history of it).
//
// Needs UNSPLASH_ACCESS_KEY set in config/config.php (or the environment)
// - sign up for a free key at https://unsplash.com/developers, create an
// app, and use its "Access Key" (not the secret). Nothing else in this
// app reads that constant; this script is the only consumer.
//
// What it does, per recipe with a blank image_url:
//   1. Searches Unsplash by "{title} {cuisine} food" - a bare dish name
//      like "Bobotie" returns unrelated results for an obscure/regional
//      dish; adding the cuisine and the word "food" narrows it a lot.
//   2. Downloads the top result's image into assets/img/recipes/{id}.jpg
//      (this app's own domain serves it from then on - not a permanent
//      hotlink to Unsplash's CDN) and pings Unsplash's official
//      download-tracking endpoint for it, both required by Unsplash's API
//      Guidelines (https://help.unsplash.com/en/articles/2511245) whenever
//      a photo is actually used, not just displayed in search results.
//   3. Records the photographer's name/profile link and the photo's own
//      Unsplash page (both with utm_source/utm_medium=referral per the
//      same guidelines) in image_attribution.json alongside the image -
//      Unsplash requires visible attribution ("Photo by {name} on
//      Unsplash", both linked) wherever the photo is shown. Nothing in
//      this app currently renders that credit line anywhere recipe photos
//      appear (recipe cards, recipe detail) - that's a real, separate
//      follow-up needed before any image this script fetches goes live,
//      not just a nice-to-have.
//   4. Updates recipes.image_url to the new local path - but ONLY after a
//      human (or a future Claude session that can view the downloaded
//      file) has looked at assets/img/recipes/{id}.jpg and confirmed it
//      actually matches the dish. This script deliberately stops short of
//      that: it downloads and reports, it does not auto-apply. Run
//      apply_recipe_images.php separately afterward, once you've deleted
//      any mismatched files from assets/img/recipes/ - it only updates
//      recipes whose downloaded file still exists there.
//
// Usage: php scripts/fetch_recipe_images.php

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/db_conn.php';

if (UNSPLASH_ACCESS_KEY === '') {
    fwrite(STDERR, "UNSPLASH_ACCESS_KEY is blank in config/config.php - nothing to do.\n");
    fwrite(STDERR, "Sign up for a free key at https://unsplash.com/developers, create an app,\n");
    fwrite(STDERR, "and paste its Access Key into config/config.php before running this.\n");
    exit(1);
}

$pdo = db();
$recipes = $pdo->query("SELECT id, title, cuisine FROM recipes WHERE image_url IS NULL OR image_url = ''")->fetchAll();

if (!$recipes) {
    echo "Every recipe already has an image_url - nothing to do.\n";
    exit(0);
}

$outDir = __DIR__ . '/../assets/img/recipes';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

function unsplash_get(string $url): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Client-ID ' . UNSPLASH_ACCESS_KEY, 'Accept-Version: v1'],
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($body === false) {
        throw new RuntimeException("cURL error: $err");
    }
    return [$status, json_decode($body, true)];
}

$attribution = [];
$report = [];

foreach ($recipes as $r) {
    $query = trim($r['title'] . ' ' . ($r['cuisine'] ?: '') . ' food');
    echo "Searching: \"$query\" (" . $r['id'] . ")... ";

    [$status, $data] = unsplash_get('https://api.unsplash.com/search/photos?per_page=1&query=' . urlencode($query));
    if ($status !== 200) {
        echo "API error (HTTP $status) - skipped.\n";
        $report[] = ['id' => $r['id'], 'title' => $r['title'], 'status' => 'api_error', 'http_status' => $status];
        continue;
    }
    if (empty($data['results'])) {
        echo "no results - skipped.\n";
        $report[] = ['id' => $r['id'], 'title' => $r['title'], 'status' => 'no_results', 'query' => $query];
        continue;
    }

    $photo = $data['results'][0];
    $imageUrl = $photo['urls']['regular']; // Unsplash's own ~1080px-wide size, already compressed

    // Official "download" event - required by Unsplash's API Guidelines
    // whenever a photo is actually used (not just returned in search
    // results). Fire-and-forget: a failure here shouldn't block getting
    // the actual image.
    if (!empty($photo['links']['download_location'])) {
        try {
            unsplash_get($photo['links']['download_location']);
        } catch (Throwable $e) {
            // non-fatal
        }
    }

    $imageBytes = @file_get_contents($imageUrl);
    if ($imageBytes === false) {
        echo "download failed - skipped.\n";
        $report[] = ['id' => $r['id'], 'title' => $r['title'], 'status' => 'download_failed'];
        continue;
    }

    $localPath = $outDir . '/' . $r['id'] . '.jpg';
    file_put_contents($localPath, $imageBytes);

    $utm = 'utm_source=nutritale&utm_medium=referral';
    $attribution[$r['id']] = [
        'title' => $r['title'],
        'photographer_name' => $photo['user']['name'] ?? '',
        'photographer_url' => ($photo['user']['links']['html'] ?? '') . (str_contains($photo['user']['links']['html'] ?? '', '?') ? '&' : '?') . $utm,
        'photo_page_url' => ($photo['links']['html'] ?? '') . (str_contains($photo['links']['html'] ?? '', '?') ? '&' : '?') . $utm,
        'search_query' => $query,
        'local_file' => 'assets/img/recipes/' . $r['id'] . '.jpg',
    ];
    $report[] = ['id' => $r['id'], 'title' => $r['title'], 'status' => 'downloaded', 'local_file' => $localPath];
    echo "saved -> $localPath\n";

    // Unsplash's rate limit on the free/demo tier is 50 requests/hour -
    // two calls per recipe (search + download ping) means well under 40
    // recipes fits in one run, but a small pause is cheap insurance.
    usleep(300000);
}

file_put_contents($outDir . '/image_attribution.json', json_encode($attribution, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "\n== Done ==\n";
echo "Downloaded: " . count(array_filter($report, fn($r) => $r['status'] === 'downloaded')) . "\n";
echo "Skipped: " . count(array_filter($report, fn($r) => $r['status'] !== 'downloaded')) . "\n";
echo "\nNEXT STEPS (manual, deliberately not automated):\n";
echo "1. Open assets/img/recipes/ and look at each downloaded photo against its\n";
echo "   recipe title. Delete any file that doesn't actually match the dish.\n";
echo "2. Run: php scripts/apply_recipe_images.php\n";
echo "   This only updates recipes.image_url for files still present in\n";
echo "   assets/img/recipes/ after your review - anything you deleted in step 1\n";
echo "   stays unset, exactly like a recipe this script never found a photo for.\n";
echo "3. Add a visible \"Photo by {name} on Unsplash\" credit (data in\n";
echo "   assets/img/recipes/image_attribution.json) wherever these photos are\n";
echo "   shown - required by Unsplash's API terms, not yet built anywhere in\n";
echo "   this app's templates.\n";

<?php
// Second half of fetch_recipe_images.php's flow, deliberately separate.
// For every recipe that still has no image AND whose review copy is still
// present in scripts/image_review/ (delete the ones that don't match the
// dish first): sends Unsplash's required download-tracking ping for that
// photo (API terms: sent when a photo is actually chosen for use), then
// sets recipes.image_url to Unsplash's hotlink URL (API terms require
// hotlinking, not self-hosting). The photographer credit is rendered from
// data/image_attribution.json by recipe_photo_credit().
//
// One API request per applied photo (the ping), so it paces itself under
// the demo app's 50 requests/hour cap.
//
// Usage: php scripts/apply_recipe_images.php

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/db_conn.php';

$pdo = db();
$reviewDir = __DIR__ . '/image_review';
$attrFile = __DIR__ . '/../data/image_attribution.json';
$attribution = is_file($attrFile) ? (json_decode((string)file_get_contents($attrFile), true) ?: []) : [];

$ids = $pdo->query("SELECT id FROM recipes WHERE image_url IS NULL OR image_url = ''")->fetchAll(PDO::FETCH_COLUMN);
$update = $pdo->prepare('UPDATE recipes SET image_url = ? WHERE id = ?');
$applied = 0;
foreach ($ids as $id) {
    $entry = $attribution[$id] ?? null;
    if (!$entry || !is_file($reviewDir . '/' . $id . '.jpg') || empty($entry['image_url'])) {
        continue;
    }
    if (!empty($entry['download_location'])) {
        for ($try = 0; $try < 6; $try++) {
            $ch = curl_init($entry['download_location']);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Client-ID ' . UNSPLASH_ACCESS_KEY, 'Accept-Version: v1'],
                CURLOPT_TIMEOUT => 20,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_OPTIONS => defined('CURLSSLOPT_NATIVE_CA') ? CURLSSLOPT_NATIVE_CA : 16, // OS cert store (see fetch script)
            ]);
            $body = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($status !== 429 && !($status === 403 && stripos((string)$body, 'rate limit') !== false)) {
                break;
            }
            echo "  rate limited - waiting 10 minutes...\n";
            sleep(600);
        }
        if ($status !== 200) {
            echo "Skipped $id: download ping failed (HTTP $status) - not applying a photo we couldn't register.\n";
            continue;
        }
        sleep(75);
    }
    $update->execute([$entry['image_url'], $id]);
    $applied++;
    echo "Applied: $id\n";
}

echo "\nUpdated $applied recipe(s).\n";

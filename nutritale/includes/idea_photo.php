<?php
// Photos for the AI pantry ideas (pantry.php). AI ideas aren't saved recipes,
// so they have no image_url; this looks one up on Unsplash by meal title.
// Server-side only (the key never reaches the browser), cached per title in
// data/idea_photos.json so a repeat view costs no API call, and it fails
// quietly: no key / no network / no result = null = the plain fallback picture.

function idea_photo_cache_file(): string
{
    return __DIR__ . '/../data/idea_photos.json';
}

function idea_photo_key(string $title): string
{
    return substr(preg_replace('/[^a-z ]+/', '', strtolower(trim($title))) ?? '', 0, 80);
}

/** @return array{url:string,name:string,profile:string}|null */
function idea_photo(string $title): ?array
{
    $key = idea_photo_key($title);
    if ($key === '' || !defined('UNSPLASH_ACCESS_KEY') || UNSPLASH_ACCESS_KEY === '') {
        return null;
    }
    $file = idea_photo_cache_file();
    $cache = is_file($file) ? (json_decode((string)@file_get_contents($file), true) ?: []) : [];
    if (isset($cache[$key]['url'])) {
        return $cache[$key];
    }
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init('https://api.unsplash.com/search/photos?per_page=1&orientation=landscape&content_filter=high&query='
        . urlencode($key . ' food dish'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Client-ID ' . UNSPLASH_ACCESS_KEY, 'Accept-Version: v1'],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $hit = $status === 200 && $body ? (json_decode($body, true)['results'][0] ?? null) : null;
    $url = (string)($hit['urls']['small'] ?? '');
    $profile = (string)($hit['user']['links']['html'] ?? '');
    if (!str_starts_with($url, 'https://images.unsplash.com/') || !str_starts_with($profile, 'https://unsplash.com/')) {
        return null; // misses aren't cached, so a later try can still succeed
    }
    $photo = ['url' => $url, 'name' => (string)($hit['user']['name'] ?? 'Unsplash'), 'profile' => $profile];
    $cache[$key] = $photo;
    if (count($cache) > 500) {
        $cache = array_slice($cache, -500, null, true);
    }
    @file_put_contents($file, json_encode($cache), LOCK_EX);
    return $photo;
}

/** The picture block shown at the top of an AI idea card. */
function idea_photo_html(string $title): string
{
    $p = idea_photo($title);
    if (!$p) {
        return '<div class="idea-photo idea-photo-plain" role="img" aria-label="No photo"></div>';
    }
    return '<div class="idea-photo" role="img" aria-label="' . h($title) . '" style="background-image:url(\'' . h($p['url']) . '\')">'
        . '<span class="idea-photo-credit">Photo by <a href="' . h($p['profile']) . '?utm_source=nutritale&amp;utm_medium=referral" target="_blank" rel="noopener">' . h($p['name'])
        . '</a> on <a href="https://unsplash.com/?utm_source=nutritale&amp;utm_medium=referral" target="_blank" rel="noopener">Unsplash</a></span></div>';
}

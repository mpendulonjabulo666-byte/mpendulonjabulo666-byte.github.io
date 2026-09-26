<?php
// Second licensed photo source, for dishes Unsplash has no photos of
// (bobotie, koeksisters, vetkoek, bunny chow...). Wikimedia Commons only
// hosts freely licensed files (CC0 / public domain / CC BY / CC BY-SA), so
// every photo here is reusable - with attribution (author + licence),
// which recipe_photo_credit() renders from data/image_attribution.json.
//
// Writes up to 3 candidates per recipe to scripts/image_review/wm_candidates.json
// plus small review thumbnails ({id}__wm{n}.jpg). Nothing goes live until a
// human picks one (scripts/choose_recipe_image.php) and apply_recipe_images.php runs.
// Resumable: recipes already in the candidates file are skipped.
//
// Usage: php scripts/fetch_wikimedia_candidates.php

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/db_conn.php';

// Commons search terms per recipe - the plain dish name people actually
// photograph, not the app's full recipe title.
const WM_QUERIES = [
    'amagwinya-fat-cakes' => ['vetkoek', 'amagwinya'],
    'avocado-feta-toast' => ['avocado toast'],
    'banana-oat-pancakes' => ['banana pancakes'],
    'biltong-and-droewors-snack-board' => ['biltong', 'droewors'],
    'biltong-spiced-popcorn' => ['popcorn bowl'],
    'bobotie-with-yellow-rice' => ['bobotie'],
    'boerewors-breakfast-rolls' => ['boerewors roll', 'boerewors'],
    'bunny-chow-with-lamb-curry' => ['bunny chow'],
    'cape-malay-chicken-curry-wrap' => ['chicken curry wrap', 'cape malay curry'],
    'chakalaka-side-dish' => ['chakalaka'],
    'chakalaka-and-bean-stew' => ['chakalaka', 'bean stew'],
    'chicken-sweetcorn-samp-salad' => ['samp maize', 'chicken corn salad'],
    'classic-shakshuka' => ['shakshuka'],
    'denningvleis-cape-malay-lamb-curry' => ['lamb curry', 'denningvleis'],
    'durban-style-bean-curry' => ['durban curry', 'bean curry'],
    'falafel-pita-with-tahini' => ['falafel pita'],
    'greek-salad-with-grilled-halloumi' => ['halloumi salad', 'greek salad'],
    'hummus-with-roasted-veg-sticks' => ['hummus vegetables', 'hummus'],
    'koeksisters' => ['koeksister'],
    'lemon-herb-baked-hake' => ['baked hake', 'baked fish lemon'],
    'malva-pudding' => ['malva pudding'],
    'mango-mageu-smoothie-bowl' => ['mango smoothie bowl'],
    'melktert-milk-tart' => ['melktert', 'milk tart'],
    'mieliepap-with-sugar-beans-and-chakalaka' => ['pap sugar beans', 'mieliepap', 'pap maize porridge'],
    'no-bake-oat-and-honey-bites' => ['energy balls oats', 'oat balls'],
    'pap-wors-and-chakalaka-plate' => ['pap en vleis', 'boerewors pap'],
    'peppermint-crisp-tart' => ['peppermint crisp tart', 'peppermint tart'],
    'potjiekos-beef-and-vegetable-stew' => ['potjiekos', 'potjie'],
    'rooibos-overnight-oats' => ['overnight oats'],
    'rooibos-poached-pears' => ['poached pears'],
    'roosterkoek' => ['roosterkoek', 'braai bread'],
    'smoked-snoek-pate-sandwich' => ['snoek', 'fish pate sandwich'],
    'sosaties-on-the-braai' => ['sosatie', 'sosaties'],
    'spiced-peanuts-and-raisins-mix' => ['peanuts raisins', 'trail mix'],
    'sweetcorn-fritters' => ['corn fritters', 'sweetcorn fritters'],
    'tomato-bredie' => ['tomato bredie', 'bredie'],
    'umngqusho-samp-and-beans' => ['umngqusho', 'samp and beans'],
    'vegetable-and-chickpea-tagine' => ['vegetable tagine', 'chickpea tagine'],
    'vetkoek-with-curried-mince' => ['vetkoek mince', 'vetkoek'],
    'waterblommetjiebredie' => ['waterblommetjie', 'Aponogeton distachyos food'],
];

function wm_get(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_USERAGENT => 'NutriTale-recipe-images/1.0 (one-off low-volume fetch)',
        CURLOPT_SSL_OPTIONS => defined('CURLSSLOPT_NATIVE_CA') ? CURLSSLOPT_NATIVE_CA : 16, // OS cert store (see fetch_recipe_images.php)
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($body !== false && $status === 200) ? $body : null;
}

$reviewDir = __DIR__ . '/image_review';
$candFile = $reviewDir . '/wm_candidates.json';
if (!is_dir($reviewDir)) {
    mkdir($reviewDir, 0755, true);
}
$cands = is_file($candFile) ? (json_decode((string)file_get_contents($candFile), true) ?: []) : [];

$ids = db()->query("SELECT id FROM recipes WHERE (image_url IS NULL OR image_url = '') AND is_published = 1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

foreach ($ids as $id) {
    if (isset($cands[$id]) || !isset(WM_QUERIES[$id])) {
        continue;
    }
    $found = [];
    foreach (WM_QUERIES[$id] as $q) {
        $api = 'https://commons.wikimedia.org/w/api.php?' . http_build_query([
            'action' => 'query', 'format' => 'json', 'generator' => 'search',
            'gsrsearch' => $q . ' filetype:bitmap', 'gsrnamespace' => 6, 'gsrlimit' => 6,
            'prop' => 'imageinfo', 'iiprop' => 'url|extmetadata|mime', 'iiurlwidth' => 1080,
        ]);
        $json = wm_get($api);
        $pages = $json ? (json_decode($json, true)['query']['pages'] ?? []) : [];
        uasort($pages, fn($a, $b) => ($a['index'] ?? 99) <=> ($b['index'] ?? 99));
        foreach ($pages as $p) {
            $ii = $p['imageinfo'][0] ?? null;
            if (!$ii || !in_array($ii['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) {
                continue;
            }
            $m = $ii['extmetadata'] ?? [];
            $license = trim(strip_tags($m['LicenseShortName']['value'] ?? ''));
            // Commons only hosts free licences, but skip anything unclear/non-free anyway.
            if ($license === '' || preg_match('/\b(NC|ND|fair use|non-free)\b/i', $license)) {
                continue;
            }
            $found[] = [
                'source' => 'wikimedia',
                'file' => $p['title'],
                'image_url' => $ii['thumburl'] ?? $ii['url'],
                'photo_page_url' => $ii['descriptionurl'],
                'photographer_name' => trim(html_entity_decode(strip_tags($m['Artist']['value'] ?? 'Unknown author'))) ?: 'Unknown author',
                'license' => $license,
                'license_url' => $m['LicenseUrl']['value'] ?? '',
                'query' => $q,
            ];
            if (count($found) >= 3) {
                break 2;
            }
        }
    }
    foreach ($found as $n => $c) {
        $thumb = preg_replace('#/\d+px-#', '/330px-', $c['image_url']);
        $bytes = wm_get($thumb) ?? wm_get($c['image_url']);
        if ($bytes !== null) {
            file_put_contents("$reviewDir/{$id}__wm$n.jpg", $bytes);
        }
    }
    $cands[$id] = $found;
    file_put_contents($candFile, json_encode($cands, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo "$id: " . count($found) . " candidate(s)\n";
}
echo "DONE\n";

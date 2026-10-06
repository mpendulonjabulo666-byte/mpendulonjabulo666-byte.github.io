<?php
require_once __DIR__ . '/functions_core.php';
require_once __DIR__ . '/allergens.php';
require_once __DIR__ . '/ingredient_matching.php';

// External APIs used by Premium features: TheMealDB (extra "What Can I
// Make?" recipes) and Open Food Facts (barcode -> product). Neither app's
// content is copied into the recipe tables - responses are only cached
// (external_api_cache, EXTERNAL_CACHE_HOURS) so a busy pantry page doesn't
// re-request the same lists on every load.

const MEALDB_BASE = 'https://www.themealdb.com/api/json/v1/';
const EXTERNAL_CACHE_HOURS = 24;

// GETs several JSON URLs at once (curl_multi, so ~20 requests cost about as
// long as the slowest one). Returns [url => decoded array|null]. Cache keys
// are hashes, so an API key inside a URL is never stored.
function external_fetch_json(array $urls, array $headers = []): array
{
    $out = array_fill_keys($urls, null);
    if (!$urls) {
        return $out;
    }
    $keys = [];
    foreach ($urls as $u) {
        $keys[sha1($u)] = $u;
    }
    $in = implode(',', array_fill(0, count($keys), '?'));
    // Both sides of the age check use MySQL's own clock (see ai_cache.php).
    $stmt = db()->prepare("SELECT cache_key, body FROM external_api_cache
        WHERE cache_key IN ($in) AND fetched_at > NOW() - INTERVAL " . EXTERNAL_CACHE_HOURS . " HOUR");
    $stmt->execute(array_keys($keys));
    foreach ($stmt->fetchAll() as $row) {
        $out[$keys[$row['cache_key']]] = json_decode($row['body'], true);
        unset($keys[$row['cache_key']]);
    }
    if (!$keys) {
        return $out;
    }

    $save = db()->prepare('INSERT INTO external_api_cache (cache_key, body) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE body = VALUES(body), fetched_at = CURRENT_TIMESTAMP');

    // Some shared hosts (InfinityFree confirmed) disable the whole
    // curl_multi_* family - not just a config flag, the functions are
    // literally undefined, so even calling curl_multi_init() is a fatal
    // error. Detect that up front and fall back to one request at a time
    // rather than letting this whole feature 500 the pantry page.
    if (!function_exists('curl_multi_init')) {
        foreach ($keys as $key => $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, external_curl_base_opts($headers));
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $data = ($code === 200 && is_string($body)) ? json_decode($body, true) : null;
            if (is_array($data)) {
                $save->execute([$key, $body]);
                $out[$keys[$key]] = $data;
            }
        }
        return $out;
    }

    // A few connections per host, multiplexed over HTTP/2, instead of one
    // TLS handshake per request - 20 lookups take ~2s this way; 20 separate
    // connections timed out outright behind this dev machine's antivirus
    // HTTPS scanner.
    $mh = curl_multi_init();
    curl_multi_setopt($mh, CURLMOPT_MAX_HOST_CONNECTIONS, 3);
    if (defined('CURLPIPE_MULTIPLEX')) {
        curl_multi_setopt($mh, CURLMOPT_PIPELINING, CURLPIPE_MULTIPLEX);
    }
    $handles = [];
    foreach ($keys as $key => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array_merge(external_curl_base_opts($headers), [
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
            CURLOPT_PIPEWAIT => true,
        ]));
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 1.0);
        }
    } while ($running && $status === CURLM_OK);

    foreach ($handles as $key => $ch) {
        $body = curl_multi_getcontent($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        $data = ($code === 200 && is_string($body)) ? json_decode($body, true) : null;
        if (is_array($data)) {
            $save->execute([$key, $body]);
            $out[$keys[$key]] = $data;
        }
    }
    curl_multi_close($mh);
    return $out;
}

// Shared curl options between the multi-handle path and the sequential
// fallback (everything except the HTTP/2-pipelining-specific options,
// which only make sense when several handles run concurrently).
function external_curl_base_opts(array $headers): array
{
    return [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_USERAGENT => 'NutriTale/1.0',
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        // OS certificate store: also trusts roots an antivirus HTTPS
        // scanner installs (this dev machine). Verification stays on.
        CURLOPT_SSL_OPTIONS => defined('CURLSSLOPT_NATIVE_CA') ? CURLSSLOPT_NATIVE_CA : 16,
    ];
}

function mealdb_enabled(): bool
{
    return MEALDB_API_KEY !== '';
}

function mealdb_url(string $endpoint, array $params): string
{
    return MEALDB_BASE . rawurlencode(MEALDB_API_KEY) . '/' . $endpoint . '?' . http_build_query($params);
}

// Pantry text -> TheMealDB ingredient filter term ("Chicken breast " ->
// "chicken_breast").
function mealdb_term(string $pantryItem): string
{
    return trim(preg_replace('/[^a-z0-9]+/', '_', mb_strtolower(trim($pantryItem))), '_');
}

// [['name' => 'soy sauce', 'measure' => '3/4 cup'], ...] from a lookup.php meal.
function mealdb_meal_ingredients(array $meal): array
{
    $list = [];
    for ($n = 1; $n <= 20; $n++) {
        $name = trim((string)($meal['strIngredient' . $n] ?? ''));
        if ($name !== '') {
            $list[] = ['name' => $name, 'measure' => trim((string)($meal['strMeasure' . $n] ?? ''))];
        }
    }
    return $list;
}

// Everything in a meal that could name an allergen, flattened for text_allergen_hits().
function mealdb_meal_text(array $meal): string
{
    $parts = [$meal['strMeal'] ?? '', $meal['strInstructions'] ?? ''];
    foreach (mealdb_meal_ingredients($meal) as $i) {
        $parts[] = $i['name'];
    }
    return implode("\n", $parts);
}

// filter.php results per term -> meal ids ranked by how many of the user's
// pantry terms they turned up under (most-covered first, then first seen).
function mealdb_rank_candidates(array $resultsByTerm): array
{
    $score = [];
    $order = 0;
    foreach ($resultsByTerm as $meals) {
        foreach ($meals ?: [] as $m) {
            $id = (string)($m['idMeal'] ?? '');
            if ($id === '') {
                continue;
            }
            if (!isset($score[$id])) {
                $score[$id] = [0, $order++];
            }
            $score[$id][0]++;
        }
    }
    uasort($score, fn($a, $b) => $b[0] <=> $a[0] ?: $a[1] <=> $b[1]);
    return array_map('strval', array_keys($score));
}

// Diet filter for the diets TheMealDB can actually vouch for (its
// Vegetarian/Vegan categories). Other diets (keto, gluten-free...) can't be
// checked from its data, so they don't filter anything.
function mealdb_meal_fits_diet(array $meal, array $dietPrefs): bool
{
    $cat = (string)($meal['strCategory'] ?? '');
    if (in_array('vegan', $dietPrefs, true)) {
        return $cat === 'Vegan';
    }
    if (in_array('vegetarian', $dietPrefs, true)) {
        return in_array($cat, ['Vegetarian', 'Vegan'], true);
    }
    return true;
}

// Up to $limit TheMealDB recipes using what's in the pantry, minus anything
// that (by its ingredient/instruction text) contains one of the user's
// allergens or doesn't fit a vegetarian/vegan preference.
function external_pantry_recipes(array $pantry, array $pantryCanonical, array $aliasMap, array $userAllergens, array $dietPrefs, int $limit): array
{
    $result = ['recipes' => [], 'hidden_by_allergens' => 0, 'error' => null];
    if (!mealdb_enabled() || !$pantry || $limit <= 0) {
        return $result;
    }
    $terms = array_slice(array_values(array_unique(array_filter(array_map('mealdb_term', $pantry)))), 0, 8);
    $filterUrls = array_map(fn($t) => mealdb_url('filter.php', ['i' => $t]), $terms);
    $lists = external_fetch_json($filterUrls);
    if (!array_filter($lists, 'is_array')) {
        $result['error'] = 'The worldwide recipe library is not responding right now - try again shortly.';
        return $result;
    }
    $candidates = array_slice(mealdb_rank_candidates(array_map(fn($d) => $d["meals"] ?? [], $lists)), 0, $limit + 6);
    $details = external_fetch_json(array_map(fn($id) => mealdb_url('lookup.php', ['i' => $id]), $candidates));

    foreach ($candidates as $id) {
        $meal = $details[mealdb_url('lookup.php', ['i' => $id])]['meals'][0] ?? null;
        if (!$meal) {
            continue;
        }
        if (text_allergen_hits(mealdb_meal_text($meal), $userAllergens)) {
            $result['hidden_by_allergens']++;
            continue;
        }
        if (!mealdb_meal_fits_diet($meal, $dietPrefs)) {
            continue;
        }
        $have = [];
        $missing = [];
        foreach (mealdb_meal_ingredients($meal) as $ing) {
            if (pantry_has_ingredient($ing['name'], $pantryCanonical, $aliasMap)) {
                $have[] = $ing['name'];
            } else {
                $missing[] = $ing['name'];
            }
        }
        if (!$have) {
            continue;
        }
        $result['recipes'][] = [
            'id' => $id,
            'title' => (string)$meal['strMeal'],
            'image' => (string)($meal['strMealThumb'] ?? ''),
            'category' => (string)($meal['strCategory'] ?? ''),
            'area' => (string)($meal['strArea'] ?? ''),
            'have' => count($have),
            'total' => count($have) + count($missing),
            'missing' => $missing,
        ];
        if (count($result['recipes']) >= $limit) {
            break;
        }
    }
    return $result;
}

// Barcode -> a short pantry-ready name ("Clover Full Cream Milk 1L", brand
// "Clover" -> "Full Cream Milk"). The user edits it before adding anyway.
function product_pantry_name(string $productName, string $brands, string $genericName = ''): string
{
    $name = trim($productName) !== '' ? $productName : $genericName;
    foreach (array_filter(array_map('trim', explode(',', $brands))) as $brand) {
        $name = preg_replace('/\b' . preg_quote($brand, '/') . '\b/iu', ' ', $name);
    }
    $name = preg_replace('/\b\d+(?:[.,]\d+)?\s*(?:x\s*\d+\s*)?(?:kg|g|mg|l|ml|cl|oz|lb|pack|pk|s)\b/iu', ' ', $name);
    $name = trim(preg_replace('/\s+/', ' ', $name), " -,.");
    return mb_substr($name, 0, 80);
}

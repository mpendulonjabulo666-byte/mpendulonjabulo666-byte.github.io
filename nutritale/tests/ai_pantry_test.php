<?php
// Tests the second allergen layer: what gemini_pantry_ideas() does with
// what the model hands back. Uses a stubbed generator, so no API key,
// no network call and no cost.
//
//   php tests/ai_pantry_test.php
//
// The rule these all defend: a meal that trips the code-side check must
// never reach the caller, no matter what the model claimed.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/ai_pantry.php';

$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        return;
    }
    $fail++;
    echo "FAIL  $label\n";
}

function meal(string $title, array $shopping = []): array
{
    return [
        'title' => $title,
        'description' => 'A test meal.',
        'uses_from_pantry' => ['rice'],
        'shopping_list' => array_map(fn($i) => ['item' => $i, 'quantity' => '1'], $shopping),
    ];
}

// A generator that replays a fixed script of responses, one per attempt,
// and records how many times it was called.
function scripted(array $responses, ?int &$calls = null): callable
{
    $calls = 0;
    return function () use ($responses, &$calls) {
        $response = $responses[$calls] ?? end($responses);
        $calls++;
        return $response;
    };
}

// --- A violating meal is dropped, a clean one survives -------------------
$gen = scripted([
    ['ok' => true, 'meals' => [meal('Peanut satay chicken'), meal('Chicken and rice bowl')]],
    ['ok' => true, 'meals' => [meal('Grilled chicken salad'), meal('Vegetable rice bowl')]],
], $calls);
$result = gemini_pantry_ideas(['rice', 'chicken'], [], ['nuts'], $gen);

check('violating meal is filtered out', $result['ok'] === true);
$titles = array_column($result['meals'], 'title');
check('satay never reaches the caller', !in_array('Peanut satay chicken', $titles, true));
check('clean meal is kept', in_array('Chicken and rice bowl', $titles, true));
check('discarded count is reported', ($result['discarded'] ?? 0) === 1);

// --- A violation triggers a retry ---------------------------------------
check('a violation causes a second attempt', $calls >= 2);

// --- A clean first attempt does not retry (each attempt costs money) -----
$gen = scripted([
    ['ok' => true, 'meals' => [meal('Rice bowl'), meal('Chicken salad'), meal('Vegetable soup')]],
], $calls2);
$result = gemini_pantry_ideas(['rice'], [], ['nuts'], $gen);
check('clean first attempt makes exactly one call', $calls2 === 1);
check('clean first attempt returns its meals', count($result['meals']) === 3);

// --- Persistent violations fail closed, never open -----------------------
$gen = scripted([
    ['ok' => true, 'meals' => [meal('Peanut stew'), meal('Cashew curry')]],
], $calls3);
$result = gemini_pantry_ideas(['rice'], [], ['nuts'], $gen);
check('unfixable violations return an error, not a meal', $result['ok'] === false);
check('error names the allergens', str_contains($result['error'] ?? '', 'nuts'));
check('retries are capped', $calls3 === AI_PANTRY_MAX_ATTEMPTS);

// --- The allergen can hide in the shopping list --------------------------
$gen = scripted([
    ['ok' => true, 'meals' => [meal('Simple vegetable stir fry', ['peanut butter'])]],
    ['ok' => true, 'meals' => [meal('Simple vegetable stir fry', ['spring onions'])]],
], $calls4);
$result = gemini_pantry_ideas(['rice'], [], ['nuts'], $gen);
check('shopping-list allergen is caught', $result['ok'] === true && ($result['discarded'] ?? 0) === 1);
check('the clean replacement is returned', count($result['meals'] ?? []) === 1);

// --- A user with no allergens is unaffected ------------------------------
$gen = scripted([
    ['ok' => true, 'meals' => [meal('Peanut satay chicken'), meal('Rice bowl')]],
], $calls5);
$result = gemini_pantry_ideas(['rice'], [], [], $gen);
check('no allergens means nothing is filtered', $result['ok'] === true && count($result['meals']) === 2);
check('no allergens means one call', $calls5 === 1);

// --- API failures are passed through, not retried ------------------------
$gen = scripted([
    ['ok' => false, 'error' => 'The AI service is at its free-tier rate limit right now.'],
], $calls6);
$result = gemini_pantry_ideas(['rice'], [], ['nuts'], $gen);
check('API error is returned to the caller', $result['ok'] === false);
check('API error is not retried', $calls6 === 1);

// --- Empty pantry is rejected before any call ----------------------------
$gen = scripted([['ok' => true, 'meals' => [meal('Anything')]]], $calls7);
$result = gemini_pantry_ideas([], [], [], $gen);
check('empty pantry makes no call at all', $result['ok'] === false && $calls7 === 0);
check('rejected-before-any-call reports zero attempts', ($result['attempts'] ?? null) === 0);

// --- 'attempts' always matches the generator's actual call count --------
// Regression check for an off-by-one: the for-loop's own increment fires
// once more than the body runs, so a naive read of the loop variable
// overshoots by one on exactly this path (exhausted retries, never
// breaks early) - see the $callsMade clamp in gemini_pantry_ideas().
$gen = scripted([
    ['ok' => true, 'meals' => [meal('Peanut stew')]],
], $calls8);
$result = gemini_pantry_ideas(['rice'], [], ['nuts'], $gen);
check('exhausted retries report attempts == actual calls, not calls + 1', ($result['attempts'] ?? null) === $calls8);
check('sanity: that is 3', $calls8 === AI_PANTRY_MAX_ATTEMPTS);

$gen = scripted([
    ['ok' => true, 'meals' => [meal('Rice bowl'), meal('Chicken salad'), meal('Vegetable soup')]],
], $calls9);
$result = gemini_pantry_ideas(['rice'], [], [], $gen);
check('a clean first attempt reports attempts == 1', ($result['attempts'] ?? null) === 1);

$gen = scripted([
    ['ok' => false, 'error' => 'rate limited'],
], $calls10);
$result = gemini_pantry_ideas(['rice'], [], [], $gen);
check('an API error reports the one attempt it took', ($result['attempts'] ?? null) === 1);

// --- ai_pantry_hash(): the cache key behind CONTINUE.md step 5 ----------
// Order and case must not matter - a user re-typing the same pantry in a
// different order must be treated as the same request.
check('hash ignores item order', ai_pantry_hash(['rice', 'chicken'], [], []) === ai_pantry_hash(['chicken', 'rice'], [], []));
check('hash ignores case', ai_pantry_hash(['Rice'], [], []) === ai_pantry_hash(['rice'], [], []));
check('hash ignores surrounding whitespace', ai_pantry_hash([' rice '], [], []) === ai_pantry_hash(['rice'], [], []));

// A different pantry, different diet prefs, or - critically - a different
// allergen list must each produce a different key. See the comment on
// ai_pantry_hash() for why allergens are safety-relevant here, not just
// personalisation: a cache hit skips the code-side allergen check.
check('hash changes with a different pantry', ai_pantry_hash(['rice'], [], []) !== ai_pantry_hash(['rice', 'egg'], [], []));
check('hash changes with a different diet pref', ai_pantry_hash(['rice'], ['vegan'], []) !== ai_pantry_hash(['rice'], [], []));
check('hash changes when an allergen is added', ai_pantry_hash(['rice'], [], ['nuts']) !== ai_pantry_hash(['rice'], [], []));
check('hash changes between two different allergens', ai_pantry_hash(['rice'], [], ['nuts']) !== ai_pantry_hash(['rice'], [], ['dairy']));
check('hash is stable for identical input', ai_pantry_hash(['rice', 'egg'], ['vegan'], ['nuts']) === ai_pantry_hash(['rice', 'egg'], ['vegan'], ['nuts']));

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);

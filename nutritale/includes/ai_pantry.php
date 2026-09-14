<?php
require_once __DIR__ . '/allergens.php';

// Calls Google's Gemini API to turn a pantry list into a few meal ideas
// plus a shopping list for whatever's missing. Always fails soft — the
// rule-based "Recipes you can make" matcher on pantry.php never depends
// on this, so a slow/quota-limited/misconfigured key just means this one
// card shows a message instead of breaking the page.
//
// ALLERGEN SAFETY. Allergens are enforced in two independent layers:
//
//   Layer 1 — the prompt tells the model which allergens are forbidden.
//   Layer 2 — every meal it returns is scanned in code by
//             text_allergen_hits() and discarded if it trips.
//
// Layer 2 is the one that counts. The model is capable of cheerfully
// suggesting a satay to someone who told us they're allergic to peanuts,
// and a prompt instruction is a request, not a guarantee. Never remove
// the code-side check on the grounds that the prompt already says it.

const AI_PANTRY_MAX_ATTEMPTS = 3;

// A stable key for "this exact request" — same pantry, same diet prefs,
// same allergens — used by includes/ai_cache.php to serve a repeat
// request without calling Gemini again (CONTINUE.md step 5).
//
// Allergens are part of the key on purpose, even though they don't change
// the pantry itself: a cache hit skips gemini_pantry_ideas() entirely, so
// Layer 2 (text_allergen_hits()) never re-runs on it. If the key ignored
// allergens, adding a new allergy after an earlier generation could serve
// back meals that were only ever checked against the old, shorter list.
// Order and case don't matter to the user, so both are normalised out
// before hashing — "Rice, Chicken" and "chicken, rice" must collide.
function ai_pantry_hash(array $pantryItems, array $dietPrefs, array $allergens): string
{
    $canon = function (array $items): string {
        $items = array_unique(array_map(fn($i) => mb_strtolower(trim($i)), $items));
        sort($items);
        return implode(',', $items);
    };
    return hash('sha256', $canon($pantryItems) . '|' . $canon($dietPrefs) . '|' . $canon($allergens));
}

// $generator exists so the filtering and retry logic can be tested
// without a network call or an API key (see tests/allergen_test.php).
// Production callers leave it null and get gemini_generate_meals().
function gemini_pantry_ideas(array $pantryItems, array $dietPrefs, array $allergens = [], ?callable $generator = null): array
{
    $generator = $generator ?? 'gemini_generate_meals';

    if ($generator === 'gemini_generate_meals' && GEMINI_API_KEY === '') {
        return ['ok' => false, 'error' => 'AI suggestions aren\'t set up on this server yet.', 'attempts' => 0];
    }
    if (!$pantryItems) {
        return ['ok' => false, 'error' => 'Add a few ingredients first.', 'attempts' => 0];
    }

    $prompt = gemini_pantry_prompt($pantryItems, $dietPrefs, $allergens);

    $clean = [];
    $discarded = 0;

    // Each attempt is a billable API call, so the ceiling is low and the
    // loop exits as soon as an attempt comes back clean. When the daily
    // cap lands (CONTINUE.md step 5) it needs to count attempts, not
    // calls to this function.
    for ($attempt = 1; $attempt <= AI_PANTRY_MAX_ATTEMPTS; $attempt++) {
        $call = $generator($prompt);
        if (!$call['ok']) {
            // Config, network or quota problem — retrying won't fix it.
            return $call + ['attempts' => $attempt];
        }

        $attemptViolations = [];
        foreach ($call['meals'] as $meal) {
            if (!is_array($meal)) {
                continue;
            }
            $hits = text_allergen_hits(meal_text_for_scanning($meal), $allergens);
            if ($hits) {
                $attemptViolations = array_merge($attemptViolations, $hits);
                $discarded++;
                error_log('AI pantry: discarded meal "' . ($meal['title'] ?? '?')
                    . '" for allergens: ' . implode(', ', $hits));
                continue;
            }
            $clean[] = $meal;
        }

        if (!$attemptViolations || count($clean) >= 3) {
            break;
        }

        // Tell the next attempt exactly what it got wrong. Vague
        // "try again" retries tend to reproduce the same mistake.
        $prompt = gemini_pantry_prompt($pantryItems, $dietPrefs, $allergens)
            . "\n\nYour previous answer was rejected because it included "
            . implode(' and ', array_unique($attemptViolations))
            . '. Do not include those, or any ingredient derived from them, anywhere '
            . 'in the meal names, descriptions or shopping lists.';
    }

    // $attempt overshoots by one when the loop ran out rather than
    // breaking early (the for-loop's own increment fires before its
    // condition fails) - clamp so this always reports calls actually
    // made, matching what tests/ai_pantry_test.php counts on the stub.
    $callsMade = min($attempt, AI_PANTRY_MAX_ATTEMPTS);

    if (!$clean) {
        if ($allergens) {
            return ['ok' => false, 'error' => 'We couldn\'t come up with meal ideas that avoid your allergens ('
                . implode(', ', $allergens) . '). Try adding a few more ingredients to your pantry.', 'attempts' => $callsMade];
        }
        return ['ok' => false, 'error' => 'The AI service didn\'t return any usable ideas — try again.', 'attempts' => $callsMade];
    }

    return [
        'ok' => true,
        'meals' => array_slice($clean, 0, 3),
        'discarded' => $discarded,
        'allergens_enforced' => $allergens,
        'attempts' => $callsMade,
    ];
}

function gemini_pantry_prompt(array $pantryItems, array $dietPrefs, array $allergens): string
{
    $prompt = 'You are a friendly home-cooking assistant. The user has these ingredients on hand: '
        . implode(', ', $pantryItems) . '.';

    // Stated first and in absolute terms — this is a safety constraint,
    // not a preference, and it must not read like one.
    if ($allergens) {
        $prompt .= ' CRITICAL SAFETY REQUIREMENT: the user is allergic to '
            . implode(', ', $allergens) . '. Every suggestion must be completely free of these '
            . 'allergens and of all ingredients derived from them, including in the shopping list. '
            . 'This is a medical restriction, not a preference. If an ingredient in their pantry '
            . 'contains one of these allergens, do not use it.';
    }

    if ($dietPrefs) {
        $prompt .= ' Dietary preferences to respect: ' . implode(', ', $dietPrefs) . '.';
    }

    return $prompt
        . ' Suggest exactly 3 simple, realistic meal ideas that make good use of what they already have. '
        . 'For each: a short title, a one-sentence description, which of their pantry items it uses, '
        . 'and a short shopping list (with a rough quantity) of any extra ingredients they would need to buy. '
        . 'Keep quantities realistic for a single household grocery trip.';
}

// One round-trip to Gemini. Returns ['ok' => true, 'meals' => [...]] or
// ['ok' => false, 'error' => '...'] — no allergen logic lives here.
function gemini_generate_meals(string $prompt): array
{
    $body = [
        'contents' => [[
            'parts' => [['text' => $prompt]],
        ]],
        'generationConfig' => [
            'maxOutputTokens' => GEMINI_MAX_OUTPUT_TOKENS,
            'temperature' => 0.7,
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => [
                    'meals' => [
                        'type' => 'ARRAY',
                        'items' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'title' => ['type' => 'STRING'],
                                'description' => ['type' => 'STRING'],
                                'uses_from_pantry' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                                'shopping_list' => [
                                    'type' => 'ARRAY',
                                    'items' => [
                                        'type' => 'OBJECT',
                                        'properties' => [
                                            'item' => ['type' => 'STRING'],
                                            'quantity' => ['type' => 'STRING'],
                                        ],
                                        'required' => ['item', 'quantity'],
                                    ],
                                ],
                            ],
                            'required' => ['title', 'description', 'uses_from_pantry', 'shopping_list'],
                        ],
                    ],
                ],
                'required' => ['meals'],
            ],
        ],
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL
        . ':generateContent?key=' . urlencode(GEMINI_API_KEY);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'error' => 'Could not reach the AI service (' . $curlErr . ').'];
    }
    if ($httpCode === 429) {
        return ['ok' => false, 'error' => 'The AI service is at its free-tier rate limit right now — try again in a minute.'];
    }
    if ($httpCode !== 200) {
        return ['ok' => false, 'error' => 'The AI service returned an error (HTTP ' . $httpCode . ').'];
    }

    $data = json_decode($response, true);
    $finishReason = $data['candidates'][0]['finishReason'] ?? null;
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!$text) {
        return ['ok' => false, 'error' => 'The AI service returned an unexpected response.'];
    }

    $parsed = json_decode($text, true);
    if (!is_array($parsed) || empty($parsed['meals'])) {
        if ($finishReason === 'MAX_TOKENS') {
            return ['ok' => false, 'error' => 'The AI response got cut off before finishing — try again, or raise GEMINI_MAX_OUTPUT_TOKENS in config.php.'];
        }
        return ['ok' => false, 'error' => 'Could not understand the AI service\'s response.'];
    }

    return ['ok' => true, 'meals' => $parsed['meals']];
}

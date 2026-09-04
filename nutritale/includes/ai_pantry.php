<?php
// Calls Google's Gemini API to turn a pantry list into a few meal ideas
// plus a shopping list for whatever's missing. Always fails soft — the
// rule-based "Recipes you can make" matcher on pantry.php never depends
// on this, so a slow/quota-limited/misconfigured key just means this one
// card shows a message instead of breaking the page.
function gemini_pantry_ideas(array $pantryItems, array $dietPrefs): array
{
    if (GEMINI_API_KEY === '') {
        return ['ok' => false, 'error' => 'AI suggestions aren\'t set up on this server yet.'];
    }
    if (!$pantryItems) {
        return ['ok' => false, 'error' => 'Add a few ingredients first.'];
    }

    $prompt = 'You are a friendly home-cooking assistant. The user has these ingredients on hand: '
        . implode(', ', $pantryItems) . '.'
        . ($dietPrefs ? ' Dietary preferences to respect: ' . implode(', ', $dietPrefs) . '.' : '')
        . ' Suggest exactly 3 simple, realistic meal ideas that make good use of what they already have. '
        . 'For each: a short title, a one-sentence description, which of their pantry items it uses, '
        . 'and a short shopping list (with a rough quantity) of any extra ingredients they would need to buy. '
        . 'Keep quantities realistic for a single household grocery trip.';

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
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!$text) {
        return ['ok' => false, 'error' => 'The AI service returned an unexpected response.'];
    }

    $parsed = json_decode($text, true);
    if (!is_array($parsed) || empty($parsed['meals'])) {
        return ['ok' => false, 'error' => 'Could not understand the AI service\'s response.'];
    }

    return ['ok' => true, 'meals' => $parsed['meals']];
}

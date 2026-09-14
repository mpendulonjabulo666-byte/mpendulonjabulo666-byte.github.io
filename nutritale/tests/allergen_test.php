<?php
// Tests for the code-side half of the two-layer allergen check
// (includes/allergens.php). No database and no API key needed.
//
//   php tests/allergen_test.php
//
// Run this after touching allergen_keywords(), allergen_exceptions() or
// allergen_exception_patterns(). A false negative here is the failure
// mode that puts an allergen in front of someone who told us not to.
//
// When a real suggestion gets wrongly rejected in the wild, add it as a
// case before adjusting the keyword lists - that's what stops the fix
// from quietly reopening an older hole.

require_once __DIR__ . '/../includes/allergens.php';

$cases = [
    // [text, user's allergens, expected hits]

    // Straightforward detections.
    ['Peanut satay chicken skewers',        ['nuts'],              ['nuts']],
    ['Thai satay noodles',                  ['nuts'],              ['nuts']],
    ['Grilled hake with lemon',             ['fish'],              ['fish']],
    ['Hummus and pita platter',             ['sesame'],            ['sesame']],
    ['Prawn stir fry with tamari',          ['shellfish', 'soy'],  ['shellfish', 'soy']],
    ['Scrambled eggs on toast',             ['eggs'],              ['eggs']],
    ['Eggplant parmigiana',                 ['dairy'],             ['dairy']],
    ['Almond milk smoothie',                ['nuts'],              ['nuts']],
    ['Beef stir fry with soy sauce',        ['gluten'],            ['gluten']],

    // Compound words that contain an allergen keyword but not the allergen.
    ['Nutmeg-spiced pumpkin soup',          ['nuts'],              []],
    ['Creamy coconut milk curry',           ['nuts'],              []],
    ['Creamy coconut milk curry',           ['dairy'],             []],
    ['Roasted butternut squash',            ['nuts'],              []],
    ['Nutritional yeast pasta sauce',       ['nuts'],              []],
    ['Eggplant parmigiana',                 ['eggs'],              []],
    ['Almond milk smoothie',                ['dairy'],             []],
    ['Rice noodle stir fry',                ['gluten'],            []],

    // "X-free" claims cover the noun that follows them, but only that far.
    ['Gluten-free banana bread',            ['gluten'],            []],
    ['Dairy-free chocolate mousse',         ['dairy'],             []],

    // "creamy" is texture, not an ingredient - gluten fires on "pasta",
    // dairy does not. Real cream is caught in the shopping list below.
    ['Creamy mushroom pasta',               ['dairy', 'gluten'],   ['gluten']],
    ['Creamy mushroom pasta with cream',    ['dairy'],             ['dairy']],

    // Nothing to find.
    ['Chicken and rice bowl',               ['dairy', 'nuts'],     []],
    ['Vegetable soup',                      [],                    []],
];

$pass = 0;
$fail = 0;

foreach ($cases as [$text, $userAllergens, $expected]) {
    $got = text_allergen_hits($text, $userAllergens);
    sort($got);
    sort($expected);
    if ($got === $expected) {
        $pass++;
        continue;
    }
    $fail++;
    printf("FAIL  %-38s allergens=[%s]\n      expected=[%s] got=[%s]\n",
        $text, implode(',', $userAllergens), implode(',', $expected), implode(',', $got));
}

// The classic miss: a clean title with the allergen hiding in what to buy.
// meal_text_for_scanning() must reach every field of the response schema.
$shoppingListCases = [
    [[
        'title' => 'Simple vegetable stir fry',
        'description' => 'Quick and fresh.',
        'uses_from_pantry' => ['rice', 'carrots'],
        'shopping_list' => [['item' => 'peanut butter', 'quantity' => '2 tbsp']],
    ], ['nuts'], ['nuts']],
    [[
        'title' => 'Creamy mushroom pasta',
        'description' => 'Rich and comforting.',
        'uses_from_pantry' => ['mushrooms'],
        'shopping_list' => [['item' => 'double cream', 'quantity' => '200ml']],
    ], ['dairy'], ['dairy']],
    [[
        'title' => 'Chicken and vegetable rice',
        'description' => 'A simple weeknight bowl.',
        'uses_from_pantry' => ['rice', 'chicken'],
        'shopping_list' => [['item' => 'spring onions', 'quantity' => '1 bunch']],
    ], ['nuts', 'dairy'], []],
];

foreach ($shoppingListCases as [$meal, $userAllergens, $expected]) {
    $got = text_allergen_hits(meal_text_for_scanning($meal), $userAllergens);
    sort($got);
    sort($expected);
    if ($got === $expected) {
        $pass++;
        continue;
    }
    $fail++;
    printf("FAIL  meal scan %-26s expected=[%s] got=[%s]\n",
        $meal['title'], implode(',', $expected), implode(',', $got));
}

// Every allergen a user can tick must have keywords behind it, or
// text_allergen_hits() silently skips it.
$keywords = allergen_keywords();
foreach (ALLERGEN_OPTIONS as $allergen) {
    if (!empty($keywords[$allergen])) {
        $pass++;
        continue;
    }
    $fail++;
    echo "FAIL  allergen '$allergen' is selectable but has no keywords - it would never be detected\n";
}

echo "\n$pass passed, $fail failed\n";
exit($fail > 0 ? 1 : 0);

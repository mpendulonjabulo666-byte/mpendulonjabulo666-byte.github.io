<?php
// Tests the pure helpers in includes/external_recipes.php (TheMealDB
// matching + Open Food Facts naming). Network and cache code isn't
// exercised here - that was verified live in the browser.
//
//   php tests/external_recipes_test.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/external_recipes.php';

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

// mealdb_term
check('lowercases and underscores spaces', mealdb_term('Chicken Breast ') === 'chicken_breast');
check('strips punctuation', mealdb_term("  sweet-corn!! ") === 'sweet_corn');

// mealdb_meal_ingredients
$meal = ['strMeal' => 'Test', 'strIngredient1' => 'Rice', 'strMeasure1' => '1 cup', 'strIngredient2' => ' ', 'strIngredient3' => 'Egg', 'strMeasure3' => null];
$ings = mealdb_meal_ingredients($meal);
check('skips blank ingredient slots', count($ings) === 2);
check('keeps measure with name', $ings[0] === ['name' => 'Rice', 'measure' => '1 cup']);
check('null measure becomes empty string', $ings[1]['measure'] === '');

// mealdb_meal_text includes title, ingredients and instructions (allergen scanning)
$text = mealdb_meal_text($meal + ['strInstructions' => 'Stir in the peanuts.']);
check('scan text has instructions', str_contains($text, 'peanuts'));
check('scan text has ingredients', str_contains($text, 'Egg'));

// mealdb_rank_candidates: meals found under more pantry terms rank first; ties keep first-seen order
$ranked = mealdb_rank_candidates([
    'chicken' => [['idMeal' => '1'], ['idMeal' => '2']],
    'rice' => [['idMeal' => '2'], ['idMeal' => '3']],
    'eggs' => null,
]);
check('most-covered meal first', $ranked[0] === '2');
check('ties keep first-seen order', $ranked === ['2', '1', '3']);
check('null term result tolerated', mealdb_rank_candidates(['x' => null]) === []);

// mealdb_meal_fits_diet
check('vegan requires Vegan category', !mealdb_meal_fits_diet(['strCategory' => 'Vegetarian'], ['vegan']));
check('vegetarian accepts Vegan', mealdb_meal_fits_diet(['strCategory' => 'Vegan'], ['vegetarian']));
check('vegetarian rejects Chicken', !mealdb_meal_fits_diet(['strCategory' => 'Chicken'], ['vegetarian']));
check('unverifiable diets do not filter', mealdb_meal_fits_diet(['strCategory' => 'Beef'], ['keto']));

// product_pantry_name
check('drops brand and size', product_pantry_name('Clover Full Cream Milk 1L', 'Clover') === 'Full Cream Milk');
check('drops multipack size', product_pantry_name('Koo Baked Beans 6 x 410g', 'Koo') === 'Baked Beans');
check('falls back to generic name', product_pantry_name('', 'Acme', 'Tomato sauce') === 'Tomato sauce');
check('brand-only name keeps nothing', product_pantry_name('Nutella', 'Nutella') === '');
check('brand not listed leaves name alone', product_pantry_name('Nutella', 'Ferrero') === 'Nutella');

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

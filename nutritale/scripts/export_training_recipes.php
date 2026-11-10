<?php
// Exports the recipe library as JSON Lines: one recipe per line.
//
// Read-only: SELECT statements only, inside a read-only transaction, so it
// cannot change the database even by mistake.
//
// No user data: only NutriTale's own published recipes are exported
// (created_by IS NULL). Recipes written by users or vendors are skipped,
// and no user ids, names, emails, ratings, favourites, views, prices or
// purchases are read at all. Photo URLs are left out too (third-party
// photos with their own licences).
//
// Usage (command line only):
//   php scripts/export_training_recipes.php              -> writes recipes.jsonl
//   php scripts/export_training_recipes.php out/r.jsonl  -> writes that file
//   php scripts/export_training_recipes.php -            -> prints to the screen
//
// Do not write the file inside the web root on a live server.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Command line only.');
}

chdir(__DIR__ . '/..');
require 'config/config.php';
require 'config/db_conn.php';

$target = $argv[1] ?? 'recipes.jsonl';
$pdo = db();
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$pdo->beginTransaction();

$recipes = $pdo->query(
    'SELECT id, title, description, meal_type, cuisine, difficulty, cook_time_minutes, servings,
            calories, protein_g, carbs_g, fat_g, fiber_g
       FROM recipes
      WHERE is_published = 1 AND created_by IS NULL
      ORDER BY id'
)->fetchAll();
$skipped = (int)$pdo->query('SELECT COUNT(*) FROM recipes WHERE is_published = 1 AND created_by IS NOT NULL')->fetchColumn();

$ingQ = $pdo->prepare('SELECT name, quantity, unit, display_quantity FROM recipe_ingredients WHERE recipe_id = ? ORDER BY order_index, id');
$stepQ = $pdo->prepare('SELECT step_text FROM recipe_instructions WHERE recipe_id = ? ORDER BY step_number, id');
$dietQ = $pdo->prepare('SELECT diet_type FROM recipe_diet_tags WHERE recipe_id = ? ORDER BY diet_type');
$algQ = $pdo->prepare('SELECT allergen FROM recipe_allergens WHERE recipe_id = ? ORDER BY allergen');

$out = $target === '-' ? fopen('php://stdout', 'w') : fopen($target . '.tmp', 'w');
if (!$out) {
    fwrite(STDERR, "Cannot write to $target\n");
    exit(1);
}

$int = fn($v) => $v === null ? null : (int)$v;
$count = 0;
foreach ($recipes as $r) {
    $ingQ->execute([$r['id']]);
    $ingredients = array_map(fn($i) => [
        'name' => $i['name'],
        'quantity' => (float)$i['quantity'],
        'unit' => $i['unit'],
        'display_quantity' => $i['display_quantity'],
    ], $ingQ->fetchAll());
    $stepQ->execute([$r['id']]);
    $dietQ->execute([$r['id']]);
    $algQ->execute([$r['id']]);

    $line = json_encode([
        'id' => $r['id'],
        'title' => $r['title'],
        'description' => $r['description'],
        'meal_type' => $r['meal_type'],
        'cuisine' => $r['cuisine'],
        'difficulty' => $r['difficulty'],
        'time_minutes' => $int($r['cook_time_minutes']),
        'servings' => $int($r['servings']),
        'ingredients' => $ingredients,
        'steps' => $stepQ->fetchAll(PDO::FETCH_COLUMN),
        'diet_tags' => $dietQ->fetchAll(PDO::FETCH_COLUMN),
        'allergens' => $algQ->fetchAll(PDO::FETCH_COLUMN),
        'nutrition_per_serving' => [
            'calories' => $int($r['calories']),
            'protein_g' => $int($r['protein_g']),
            'carbs_g' => $int($r['carbs_g']),
            'fat_g' => $int($r['fat_g']),
            'fibre_g' => $int($r['fiber_g']),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        fwrite(STDERR, "Skipped {$r['id']}: " . json_last_error_msg() . "\n");
        continue;
    }
    fwrite($out, $line . "\n");
    $count++;
}
fclose($out);
$pdo->rollBack();

if ($target !== '-') {
    rename($target . '.tmp', $target);
}
fwrite(STDERR, "Exported $count recipes" . ($target === '-' ? '' : " to $target")
    . ($skipped ? "; skipped $skipped user-submitted recipe" . ($skipped === 1 ? '' : 's') : '') . ".\n");

<?php
// Exports the recipe library as JSON Lines: one recipe per line.
//
// Read-only: SELECT statements only, nothing in the database is changed.
// No user data: the export never reads the users table, favourites, ratings,
// views, pantries or purchases, and it leaves out created_by, price and
// image_url. Recipes written by a user account (created_by is set) are
// skipped by default, because a member's own recipe is their content;
// pass --include-user-recipes to export those too (still without the author).
// Unpublished recipes are always skipped.
//
// Usage (command line only):
//   php scripts/export_training_recipes.php                  writes recipes.jsonl in the current folder
//   php scripts/export_training_recipes.php path/to/out.jsonl
//   php scripts/export_training_recipes.php -                writes to the screen (stdout)
//   php scripts/export_training_recipes.php --include-user-recipes
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Command line only.');
}

$args = array_slice($argv, 1);
$includeUser = in_array('--include-user-recipes', $args, true);
$args = array_values(array_filter($args, fn($a) => $a !== '--include-user-recipes'));
$cwd = getcwd();
$outPath = $args[0] ?? 'recipes.jsonl';
if ($outPath !== '-' && $outPath[0] !== '/' && !preg_match('/^[A-Za-z]:[\\\\\/]/', $outPath)) {
    $outPath = $cwd . '/' . $outPath; // relative to where the command was run, not to the app folder
}

chdir(__DIR__ . '/..');
require 'config/config.php';
// config.php opens a session (stored in the database). Drop it unsaved, so
// this script does not even leave a session row behind.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_abort();
}
// Own connection instead of db(): db() applies pending schema migrations,
// and an export must never change the database.
$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$pdo->exec('SET SESSION TRANSACTION READ ONLY');
$pdo->beginTransaction(); // one consistent snapshot; the server rejects any write in it

$num = fn($v) => $v === null ? null : (int)$v;

// Children are loaded once each and grouped in memory (4 queries total,
// rather than 4 per recipe).
$group = function (string $sql) use ($pdo): array {
    $out = [];
    foreach ($pdo->query($sql) as $row) {
        $id = $row['recipe_id'];
        unset($row['recipe_id']);
        $out[$id][] = $row;
    }
    return $out;
};
$ingredients = $group('SELECT recipe_id, name, quantity, unit, display_quantity FROM recipe_ingredients ORDER BY recipe_id, order_index, id');
$steps = $group('SELECT recipe_id, step_text FROM recipe_instructions ORDER BY recipe_id, step_number, id');
$diets = $group('SELECT recipe_id, diet_type FROM recipe_diet_tags ORDER BY recipe_id, diet_type');
$allergens = $group('SELECT recipe_id, allergen FROM recipe_allergens ORDER BY recipe_id, allergen');

$recipes = $pdo->query(
    'SELECT id, title, description, meal_type, cuisine, difficulty, cook_time_minutes, servings,
            calories, protein_g, carbs_g, fat_g, fiber_g, created_by
       FROM recipes WHERE is_published = 1 ORDER BY id'
)->fetchAll();

$out = $outPath === '-' ? fopen('php://stdout', 'w') : fopen($outPath, 'w');
if (!$out) {
    fwrite(STDERR, "Could not open $outPath for writing.\n");
    exit(1);
}

$written = 0;
$skippedUser = 0;
foreach ($recipes as $r) {
    if ($r['created_by'] !== null && !$includeUser) {
        $skippedUser++;
        continue;
    }
    $id = $r['id'];
    $line = [
        'id' => $id,
        'title' => $r['title'],
        'description' => $r['description'],
        'meal_type' => $r['meal_type'],
        'cuisine' => $r['cuisine'],
        'difficulty' => $r['difficulty'],
        'time_minutes' => (int)$r['cook_time_minutes'],
        'servings' => (int)$r['servings'],
        'ingredients' => array_map(fn($i) => [
            'name' => $i['name'],
            'quantity' => (float)$i['quantity'],
            'unit' => $i['unit'],
            'display_quantity' => $i['display_quantity'],
        ], $ingredients[$id] ?? []),
        'steps' => array_column($steps[$id] ?? [], 'step_text'),
        'diet_tags' => array_column($diets[$id] ?? [], 'diet_type'),
        'allergens' => array_column($allergens[$id] ?? [], 'allergen'),
        'nutrition' => [
            'calories' => $num($r['calories']),
            'protein_g' => $num($r['protein_g']),
            'carbs_g' => $num($r['carbs_g']),
            'fat_g' => $num($r['fat_g']),
            'fibre_g' => $num($r['fiber_g']),
        ],
    ];
    fwrite($out, json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    $written++;
}
if ($outPath !== '-') {
    fclose($out);
}

fwrite(STDERR, "Wrote $written recipes to " . ($outPath === '-' ? 'stdout' : $outPath) . ".\n");
if ($skippedUser) {
    fwrite(STDERR, "Skipped $skippedUser recipes written by user accounts (use --include-user-recipes to export them).\n");
}

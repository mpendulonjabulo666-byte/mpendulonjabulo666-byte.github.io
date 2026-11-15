<?php
// Checks the 45 draft recipes (data/draft_recipes/) and the loader that writes
// them as hidden drafts. The database part runs inside a transaction that is
// always rolled back, so it leaves nothing behind.
chdir(__DIR__ . '/..');
require 'config/config.php';
if (session_status() === PHP_SESSION_ACTIVE) session_abort();
require 'config/db_conn.php';
require 'includes/draft_recipes.php';

$fail = 0;
$t = function (bool $ok, string $name) use (&$fail) { echo ($ok ? 'ok   ' : 'FAIL ') . $name . "\n"; if (!$ok) $fail++; };

$expectedIds = ['avocado-egg-power-bowl','balsamic-vinaigrette','beef-veggie-bowl','black-bean-avocado-bowl','blackberry-sage-refresher','buffalo-chicken-wings','caesar-dressing','cauliflower-rice-bowl','chicken-quinoa-bowl','chickpea-tahini-bowl','cottage-cheese-pineapple-bowl','creamy-tomato-pasta','dynamite-shrimp','edamame-brown-rice-bowl','farro-veggie-bowl','garlic-chicken-bowl','greek-dressing','greek-yogurt-berry-bowl','honey-mustard-dressing','lentil-veggie-bowl','mango-dragonfruit-refresher','miso-tofu-bowl','mixed-berry-nut-bowl','mozzarella-sticks','peanut-satay-bowl','pineapple-passionfruit-refresher','potato-croquettes','ranch-dressing','salmon-power-bowl','salmon-quinoa-bowl','sardine-avocado-bowl','shrimp-zoodle-bowl','spinach-mushroom-bowl','spring-rolls','steak-eggs-power-bowl','steak-sweet-potato-bowl','strawberry-aca-i-refresher','stuffed-mushrooms','tempeh-broccoli-bowl','thousand-island-dressing','tofu-veggie-stir-bowl','tuna-white-bean-bowl','turkey-avocado-bowl','turkey-meatball-bowl','turkey-sweet-potato-bowl'];

$recipes = nutritale_draft_recipes();
$ids = array_column($recipes, 'id');
sort($ids);
$t($ids === $expectedIds, 'exactly the 45 hidden catalogue ids, no duplicates');

$units = ['cup','tbsp','tsp','pieces','cloves','clove','bunch','can','slices','g','kg','ml','l','packet'];
$cats = ['grains','dairy','pantry','produce','protein'];
$diets = ['vegan','vegetarian','gluten-free','high-protein','keto'];
$meals = ['breakfast','lunch','dinner','snack','dessert'];
$meat = '/\b(beef|steak|sirloin|chicken|turkey|pork|bacon|sausage|fish|salmon|tuna|sardine|shrimp|prawn|anchov|gelatine|wings)\b/i';
$animal = "/\\b(egg|eggs|milk|butter|buttermilk|cream|cheese|parmesan|mozzarella|yoghurt|yogurt|honey|mayonnaise)\\b/i";
$bad = [];
foreach ($recipes as $r) {
    $id = $r['id'];
    $names = implode("\n", array_column($r['ingredients'], 'name'));
    if (count($r['ingredients']) < 3) $bad[] = "$id: fewer than 3 ingredients";
    if (count($r['steps']) < 3) $bad[] = "$id: fewer than 3 steps";
    if (!in_array($r['meal_type'], $meals, true)) $bad[] = "$id: meal_type";
    if (!in_array($r['difficulty'], ['easy','medium','hard'], true)) $bad[] = "$id: difficulty";
    if ($r['cook_time'] < 1 || $r['cook_time'] > 240 || $r['servings'] < 1) $bad[] = "$id: time/servings";
    if (strlen($r['description']) < 40) $bad[] = "$id: description too short";
    foreach ($r['ingredients'] as $i) {
        if ($i['quantity'] <= 0) $bad[] = "$id: {$i['name']} quantity";
        if (!in_array($i['unit'], $units, true)) $bad[] = "$id: {$i['name']} unit '{$i['unit']}'";
        if (!in_array($i['category'], $cats, true)) $bad[] = "$id: {$i['name']} category '{$i['category']}'";
        if ($i['display'] === '') $bad[] = "$id: {$i['name']} display";
    }
    foreach ($r['steps'] as $s) if (strlen($s) < 15) $bad[] = "$id: very short step";
    foreach ($r['diet_tags'] as $d) if (!in_array($d, $diets, true)) $bad[] = "$id: unknown diet tag $d";
    $isVeg = array_intersect(['vegan', 'vegetarian'], $r['diet_tags']);
    if ($isVeg && preg_match($meat, $names)) $bad[] = "$id: tagged vegetarian/vegan but has meat or fish";
    $plantNames = preg_replace('/\\b(peanut|almond|cashew|nut|seed|tahini|coconut|oat|soy|soya|almond) (butter|milk|cream)\\b/i', '', $names);
    if (in_array('vegan', $r['diet_tags'], true) && preg_match($animal, $plantNames)) $bad[] = "$id: tagged vegan but has animal products";
    if (in_array('gluten-free', $r['diet_tags'], true) && in_array('gluten', $r['allergens'], true)) $bad[] = "$id: gluten-free but contains gluten";
    if (in_array('vegan', $r['diet_tags'], true) && array_intersect(['dairy', 'eggs', 'fish', 'shellfish'], $r['allergens'])) $bad[] = "$id: vegan but allergens say animal";
    if (in_array('keto', $r['diet_tags'], true) && preg_match('/\b(rice|pasta|flour|bread|potato|quinoa|noodle)/i', $names)) $bad[] = "$id: keto with starch";
    if ($r['nutrition'] ?? false) $bad[] = "$id: has hand-typed nutrition";
}
$t($bad === [], 'every recipe valid (units, categories, tags, steps, diet-vs-ingredient checks)' . ($bad ? ': ' . implode('; ', $bad) : ''));

// Allergens: spot checks of the derived values.
$by = array_column($recipes, null, 'id');
$t(in_array('shellfish', $by['shrimp-zoodle-bowl']['allergens'], true), 'shrimp recipe derives shellfish');
$t(in_array('fish', $by['salmon-power-bowl']['allergens'], true), 'salmon recipe derives fish');
$t(in_array('nuts', $by['peanut-satay-bowl']['allergens'], true), 'peanut recipe derives nuts');
$t(in_array('gluten', $by['mozzarella-sticks']['allergens'], true) && in_array('dairy', $by['mozzarella-sticks']['allergens'], true) && in_array('eggs', $by['mozzarella-sticks']['allergens'], true), 'mozzarella sticks derive gluten, dairy, eggs');
$t(!in_array('gluten', $by['edamame-brown-rice-bowl']['allergens'], true), 'tamari recipe not flagged gluten');

// Database behaviour, rolled back at the end.
$pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$before = (int)$pdo->query("SELECT COUNT(*) FROM recipes WHERE id IN ('" . implode("','", $expectedIds) . "')")->fetchColumn();
$pdo->beginTransaction();
try {
    // The migration may already have filled this database; clear the 45 ids inside
    // the transaction (rolled back below) so the test starts from a known state.
    $in = "'" . implode("','", $expectedIds) . "'";
    foreach (['recipe_ingredients', 'recipe_instructions', 'recipe_diet_tags', 'recipe_allergens'] as $tbl) $pdo->exec("DELETE FROM $tbl WHERE recipe_id IN ($in)");
    $pdo->exec("DELETE FROM recipes WHERE id IN ($in)");
    // One row pre-exists empty and published-looking (hidden), one is already complete.
    $pdo->exec("INSERT INTO recipes (id, title, meal_type, difficulty, cook_time_minutes, servings, is_published, calories, tier) VALUES ('beef-veggie-bowl','Old Title','dinner','easy',10,1,0,321,'premium')");
    $pdo->exec("INSERT INTO recipes (id, title, meal_type, difficulty, cook_time_minutes, servings, is_published) VALUES ('spring-rolls','Kept As Is','snack','easy',10,1,0)");
    $pdo->exec("INSERT INTO recipe_ingredients (recipe_id, name, quantity, unit, display_quantity, category, order_index) VALUES ('spring-rolls','Original ingredient',1,'g','1 g','pantry',1)");
    $pdo->exec("INSERT INTO recipe_instructions (recipe_id, step_number, step_text) VALUES ('spring-rolls',1,'Original step text')");

    $res = nutritale_apply_draft_recipes($pdo);
    $t(count($res['inserted']) === 43 && $res['filled'] === ['beef-veggie-bowl'] && $res['skipped'] === ['spring-rolls'], 'insert 43, fill 1 empty row, skip 1 complete row');
    $t((int)$pdo->query("SELECT COUNT(*) FROM recipes WHERE is_published = 1 AND id IN ('" . implode("','", $expectedIds) . "')")->fetchColumn() === 0, 'nothing is published');
    $row = $pdo->query("SELECT title, tier, calories, is_published, cook_time_minutes FROM recipes WHERE id = 'beef-veggie-bowl'")->fetch();
    $t($row['title'] === 'Old Title' && $row['tier'] === 'premium' && (int)$row['calories'] === 321 && (int)$row['is_published'] === 0 && (int)$row['cook_time_minutes'] === 30, 'filled row keeps title, tier, calories, hidden; takes new time');
    $t($pdo->query("SELECT step_text FROM recipe_instructions WHERE recipe_id = 'spring-rolls'")->fetchColumn() === 'Original step text', 'complete recipe untouched');
    $t((int)$pdo->query("SELECT COUNT(*) FROM recipe_instructions WHERE recipe_id = 'avocado-egg-power-bowl'")->fetchColumn() === 5, 'steps stored in order');
    $t($pdo->query("SELECT step_text FROM recipe_instructions WHERE recipe_id = 'avocado-egg-power-bowl' AND step_number = 1")->fetchColumn() !== false, 'step numbering starts at 1');
    $again = nutritale_apply_draft_recipes($pdo);
    $t($again['inserted'] === [] && $again['filled'] === [] && count($again['skipped']) === 45, 'second run changes nothing');
} finally {
    $pdo->rollBack();
}
$t((int)$pdo->query("SELECT COUNT(*) FROM recipes WHERE id IN ('" . implode("','", $expectedIds) . "')")->fetchColumn() === $before, 'test left the database as it found it');
exit($fail ? 1 : 0);

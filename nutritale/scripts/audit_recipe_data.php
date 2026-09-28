<?php
// Checks every published recipe for details that would mislead users:
// calories that don't add up from the macros (>20% off 4P+4C+9F), missing
// calories/servings/steps/ingredients, implausible cook times, allergens
// named in the ingredients but not tagged (the dangerous one - untagged
// allergens let allergic users see a recipe as safe), and vegetarian/vegan
// tags contradicted by the ingredients. Read-only; prints a report.
//
// Usage: php scripts/audit_recipe_data.php
chdir(__DIR__ . '/..');
require 'config/config.php';
require 'config/db_conn.php';
require 'includes/allergens.php';
$pdo = db();
$allAllergens = array_keys(allergen_keywords());
$recipes = $pdo->query("SELECT * FROM recipes WHERE is_published = 1 ORDER BY title")->fetchAll();
$ingQ = $pdo->prepare('SELECT name FROM recipe_ingredients WHERE recipe_id = ?');
$stepQ = $pdo->prepare('SELECT COUNT(*) FROM recipe_instructions WHERE recipe_id = ?');
$algQ = $pdo->prepare('SELECT allergen FROM recipe_allergens WHERE recipe_id = ?');
$dietQ = $pdo->prepare('SELECT diet_type FROM recipe_diet_tags WHERE recipe_id = ?');
$meat = '/\b(beef|lamb|mutton|chicken|pork|bacon|boerewors|wors|sausage|mince|biltong|droewors|droëwors|fish|hake|snoek|salmon|tuna|sardine|prawn|shrimp|anchov|gelatine|steak|turkey|chorizo|ham)\b/iu';
$animal = '/\b(egg|eggs|milk|butter|cream|cheese|feta|halloumi|yoghurt|yogurt|honey|mayonnaise|mageu|buttermilk|condensed)\b/iu';
$issues = [];
foreach ($recipes as $r) {
    $ingQ->execute([$r['id']]); $ings = $ingQ->fetchAll(PDO::FETCH_COLUMN);
    $stepQ->execute([$r['id']]); $steps = (int)$stepQ->fetchColumn();
    $algQ->execute([$r['id']]); $tagged = $algQ->fetchAll(PDO::FETCH_COLUMN);
    $dietQ->execute([$r['id']]); $diets = $dietQ->fetchAll(PDO::FETCH_COLUMN);
    $text = implode("\n", $ings);
    $p = [];
    if (!$ings) $p[] = 'NO INGREDIENTS';
    if (!$steps) $p[] = 'NO STEPS';
    if ($r['protein_g'] !== null && $r['carbs_g'] !== null && $r['fat_g'] !== null && $r['calories']) {
        $calc = 4 * $r['protein_g'] + 4 * $r['carbs_g'] + 9 * $r['fat_g'];
        $dev = ($r['calories'] - $calc) / max(1, $calc);
        if (abs($dev) > 0.2) $p[] = sprintf('CALORIES %d vs macros %dP/%dC/%dF = %d kcal (%+d%%)', $r['calories'], $r['protein_g'], $r['carbs_g'], $r['fat_g'], $calc, round($dev * 100));
    } elseif (!$r['calories']) {
        $p[] = 'NO CALORIES';
    }
    if ($r['cook_time_minutes'] < 5 || $r['cook_time_minutes'] > 240) $p[] = 'COOK TIME ' . $r['cook_time_minutes'] . ' min';
    if (!$r['servings']) $p[] = 'NO SERVINGS';
    $hits = text_allergen_hits($text . "\n" . $r['title'], $allAllergens);
    $untagged = array_values(array_diff($hits, $tagged));
    if ($untagged) $p[] = 'ALLERGENS IN INGREDIENTS BUT NOT TAGGED: ' . implode(', ', $untagged);
    if (in_array('vegetarian', $diets, true) || in_array('vegan', $diets, true)) {
        if (preg_match_all($meat, $text, $m)) $p[] = 'TAGGED ' . implode('/', array_intersect($diets, ['vegetarian', 'vegan'])) . ' BUT HAS: ' . implode(', ', array_unique(array_map('strtolower', $m[0])));
    }
    if (in_array('vegan', $diets, true) && preg_match_all($animal, $text, $m)) $p[] = 'TAGGED vegan BUT HAS: ' . implode(', ', array_unique(array_map('strtolower', $m[0])));
    if ($p) $issues[$r['id']] = ['title' => $r['title'], 'tier' => $r['tier'], 'cal' => $r['calories'], 'time' => $r['cook_time_minutes'], 'serv' => $r['servings'], 'tagged_allergens' => $tagged, 'diets' => $diets, 'problems' => $p];
}
echo count($recipes) . " published recipes, " . count($issues) . " with issues\n\n";
foreach ($issues as $id => $i) {
    echo "$id ({$i['title']}) [tier {$i['tier']}, {$i['cal']} kcal, {$i['time']} min, serves {$i['serv']}; allergens tagged: " . implode(',', $i['tagged_allergens']) . "; diets: " . implode(',', $i['diets']) . "]\n";
    foreach ($i['problems'] as $pr) echo "   - $pr\n";
}

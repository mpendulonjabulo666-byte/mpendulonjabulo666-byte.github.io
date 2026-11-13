<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/recipe_card.php';
require_once __DIR__ . '/includes/hormone_foods.php';

$user = require_login();

$focus = hormone_focus_key($_GET['focus'] ?? '');
$focuses = hormone_focuses();
$groups = hormone_food_groups();
$active = $focuses[$focus];

// Built-in, published recipes the user's plan can open. Matching is done on
// ingredient names; nothing is stored about the person (no gender field).
[$planSql, $planParams] = recipe_plan_filter($user, 'r');
$sql = 'SELECT r.*,
        GROUP_CONCAT(DISTINCT dt.diet_type SEPARATOR ",") AS diet_tags,
        GROUP_CONCAT(DISTINCT al.allergen SEPARATOR ",") AS allergens
        FROM recipes r
        LEFT JOIN recipe_diet_tags dt ON dt.recipe_id = r.id
        LEFT JOIN recipe_allergens al ON al.recipe_id = r.id
        WHERE r.is_published = 1 AND r.is_generated = 0' . ($planSql ? ' AND ' . $planSql : '') . '
        GROUP BY r.id';
$stmt = db()->prepare($sql);
$stmt->execute($planParams);
$recipes = $stmt->fetchAll();

$ingStmt = db()->prepare('SELECT recipe_id, name FROM recipe_ingredients');
$ingStmt->execute();
$ingredientsByRecipe = [];
foreach ($ingStmt->fetchAll() as $row) {
    $ingredientsByRecipe[$row['recipe_id']][] = $row['name'];
}

$scored = [];
foreach ($recipes as $recipe) {
    $hit = hormone_groups_matched($ingredientsByRecipe[$recipe['id']] ?? [], $focus);
    if (count($hit) >= 2) {
        $scored[] = ['recipe' => $recipe, 'hit' => $hit];
    }
}
usort($scored, fn($a, $b) => count($b['hit']) <=> count($a['hit']) ?: strcmp($a['recipe']['title'], $b['recipe']['title']));
$scored = array_slice($scored, 0, 12);

$favStmt = db()->prepare('SELECT recipe_id FROM favorites WHERE user_id = ?');
$favStmt->execute([$user['id']]);
$favoriteIds = array_flip($favStmt->fetchAll(PDO::FETCH_COLUMN));
$userAllergenStmt = db()->prepare('SELECT allergen FROM user_allergens WHERE user_id = ?');
$userAllergenStmt->execute([$user['id']]);
$userAllergens = $userAllergenStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hormone-supportive eating · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#2fae66">
<script src="assets/js/theme-init.js?v=24"></script>
<link rel="stylesheet" href="assets/css/style.css?v=42">
<script src="assets/js/theme-toggle.js?v=24" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div class="page-hero-banner" style="background-image:url('assets/img/banners/pantry-vegetables.jpg');">
        <div>
            <h1><?= icon('leaf', 20) ?> Hormone-supportive eating</h1>
            <p>Everyday foods that support healthy hormone function, matched to recipes you can cook.</p>
        </div>
    </div>

    <div class="alert" role="note" style="margin-bottom:16px;">
        <strong>Please note.</strong> <?= h(hormone_doctor_note()) ?>
    </div>

    <div class="tag-row" style="margin-bottom:16px;" role="tablist" aria-label="Choose a focus">
        <?php foreach ($focuses as $key => $f): ?>
            <a class="btn btn-small <?= $key === $focus ? '' : 'btn-text' ?>" role="tab"
               aria-selected="<?= $key === $focus ? 'true' : 'false' ?>"
               href="hormone_guide.php?focus=<?= h($key) ?>"><?= h($f['label']) ?></a>
        <?php endforeach; ?>
    </div>

    <p class="muted"><?= h($active['intro']) ?></p>

    <div class="recipe-grid" style="margin-bottom:24px;">
        <?php foreach ($active['groups'] as $g): ?>
            <div class="recipe-card"><div class="recipe-card-body">
                <h3><?= h($groups[$g]['label']) ?></h3>
                <p class="muted"><?= h($groups[$g]['why']) ?></p>
                <p style="font-size:13px;"><strong>Try:</strong> <?= h(implode(', ', array_slice($groups[$g]['keywords'], 0, 6))) ?></p>
            </div></div>
        <?php endforeach; ?>
    </div>

    <h2>Recipes that cover these groups</h2>
    <?php if (!$scored): ?>
        <p class="muted">No recipes cover two or more of these groups yet. Browse all recipes or ask for AI ideas from your pantry.</p>
    <?php else: ?>
        <div class="recipe-grid">
            <?php foreach ($scored as $s): ?>
                <?php
                $recipe = $s['recipe'];
                $conflicts = array_intersect(array_filter(explode(',', $recipe['allergens'] ?? '')), $userAllergens);
                ?>
                <div>
                    <?php render_recipe_card($recipe, isset($favoriteIds[$recipe['id']]), $conflicts); ?>
                    <p class="muted" style="font-size:12px;margin:6px 4px 0;">Covers: <?= h(implode(', ', array_map(fn($g) => $groups[$g]['label'], $s['hit']))) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="muted" style="font-size:12px;margin-top:24px;">Sleep, movement, stress and a healthy weight shape your hormones as much as food does. <?= h(hormone_doctor_note()) ?></p>
</main>
</body>
</html>

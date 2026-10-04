<?php
// A TheMealDB recipe, shown in the app's own recipe layout (Premium extra
// from "What Can I Make?"). Fetched live (cached 24h), never copied into
// the recipe tables. Checked against the user's allergens by ingredient and
// instruction text - flagged, with the standard allergen disclaimer.
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/external_recipes.php';

$user = require_login();
$id = (string)($_GET['id'] ?? '');
$locked = !user_has_full_library($user);
$meal = null;

if (!$locked && preg_match('/^\d{1,8}$/', $id) && mealdb_enabled()) {
    $url = mealdb_url('lookup.php', ['i' => $id]);
    $meal = external_fetch_json([$url])[$url]['meals'][0] ?? null;
}
if (!$locked && !$meal) {
    http_response_code(404);
}

$ingredients = $meal ? mealdb_meal_ingredients($meal) : [];
$steps = [];
if ($meal) {
    foreach (preg_split('/\r\n|\r|\n/', (string)($meal['strInstructions'] ?? '')) as $line) {
        $line = trim(preg_replace('/^(step\s*\d+[:.)-]?|\d+[.)])\s*/i', '', trim($line)));
        if ($line !== '' && !preg_match('/^step\s*\d+$/i', $line)) {
            $steps[] = $line;
        }
    }
}
$allergenHits = $meal ? text_allergen_hits(mealdb_meal_text($meal), user_allergens((int)$user['id'])) : [];
$source = (string)($meal['strSource'] ?? '');
$youtube = (string)($meal['strYoutube'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $meal ? h($meal['strMeal']) : 'Worldwide recipe' ?> · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#2fae66">
<script src="assets/js/theme-init.js?v=21"></script>
<link rel="stylesheet" href="assets/css/style.css?v=21">
<script src="assets/js/theme-toggle.js?v=21" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div class="mb-16">
        <a href="pantry.php" class="btn-back"><span class="btn-back-disc"><?= icon('chevron-left', 18) ?></span><span class="btn-back-label">Back to What Can I Make?</span></a>
    </div>

    <?php if ($locked): ?>
        <div class="card center-text" style="padding:32px;">
            <?= icon('wand', 28) ?>
            <h1 style="margin:10px 0 6px;font-size:22px;">Worldwide recipes are a Premium extra</h1>
            <p class="muted" style="margin:0 0 16px;">Premium adds up to <?= EXTERNAL_RECIPE_LIMIT ?> matching recipes from around the world to What Can I Make?</p>
            <a class="btn btn-primary" href="premium.php">Go Premium</a>
        </div>
    <?php elseif (!$meal): ?>
        <div class="card center-text" style="padding:32px;">
            <h1 style="margin:0 0 6px;font-size:22px;">Recipe not available</h1>
            <p class="muted" style="margin:0;">This recipe couldn't be loaded right now. Please try again shortly.</p>
        </div>
    <?php else: ?>
        <div class="recipe-detail">
            <div class="recipe-detail-image" style="background-image:url('<?= h((string)$meal['strMealThumb']) ?>')"></div>
            <span class="recipe-photo-credit">Recipe and photo from <a href="https://www.themealdb.com" target="_blank" rel="noopener">TheMealDB</a></span>
            <div class="recipe-detail-body">
                <div class="recipe-detail-header">
                    <h1><?= h($meal['strMeal']) ?></h1>
                </div>
                <div class="tag-row mb-16">
                    <span class="tag">Worldwide</span>
                    <?php if (!empty($meal['strArea'])): ?><span class="tag"><?= h($meal['strArea']) ?></span><?php endif; ?>
                    <?php if (!empty($meal['strCategory'])): ?><span class="tag"><?= h($meal['strCategory']) ?></span><?php endif; ?>
                </div>
                <?php if ($allergenHits): ?>
                    <p class="allergen-warning"><?= icon('alert-triangle', 14) ?> Contains <?= h(implode(', ', $allergenHits)) ?>, which you've asked to avoid.</p>
                <?php endif; ?>
                <div class="mb-16"><?= disclaimer('allergens') ?></div>
                <p class="muted" style="font-size:12.5px;">From an external recipe library: cooking times and nutrition aren't provided, and quantities are as the original lists them.</p>
                <div class="recipe-columns">
                    <div>
                        <h2>Ingredients</h2>
                        <ul class="ingredient-list">
                            <?php foreach ($ingredients as $ing): ?>
                                <li><?= h($ing['measure']) ?> <?= h($ing['name']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div>
                        <h2>Instructions</h2>
                        <ol class="step-list">
                            <?php foreach ($steps as $step): ?>
                                <li><?= h($step) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
                <?php if (preg_match('#^https?://#', $source) || preg_match('#^https://www\.youtube\.com/#', $youtube)): ?>
                    <p class="muted" style="font-size:13px;margin-top:16px;">
                        <?php if (preg_match('#^https?://#', $source)): ?><a href="<?= h($source) ?>" target="_blank" rel="noopener nofollow">Original recipe</a><?php endif; ?>
                        <?php if (preg_match('#^https://www\.youtube\.com/#', $youtube)): ?> · <a href="<?= h($youtube) ?>" target="_blank" rel="noopener nofollow">Video</a><?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

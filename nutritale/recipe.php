<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

$id = $_GET['id'] ?? '';

$stmt = db()->prepare('SELECT * FROM recipes WHERE id = ?');
$stmt->execute([$id]);
$recipe = $stmt->fetch();

if (!$recipe) {
    http_response_code(404);
    die('Recipe not found.');
}

$dietStmt = db()->prepare('SELECT diet_type FROM recipe_diet_tags WHERE recipe_id = ?');
$dietStmt->execute([$id]);
$dietTags = $dietStmt->fetchAll(PDO::FETCH_COLUMN);

$allergenStmt = db()->prepare('SELECT allergen FROM recipe_allergens WHERE recipe_id = ?');
$allergenStmt->execute([$id]);
$allergens = $allergenStmt->fetchAll(PDO::FETCH_COLUMN);

$ingredientStmt = db()->prepare('SELECT * FROM recipe_ingredients WHERE recipe_id = ? ORDER BY order_index');
$ingredientStmt->execute([$id]);
$ingredients = $ingredientStmt->fetchAll();

$stepStmt = db()->prepare('SELECT * FROM recipe_instructions WHERE recipe_id = ? ORDER BY step_number');
$stepStmt->execute([$id]);
$steps = $stepStmt->fetchAll();

$favStmt = db()->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND recipe_id = ?');
$favStmt->execute([$user['id'], $id]);
$isFavorite = (bool)$favStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($recipe['title']) ?> · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <a href="index.php" class="btn btn-text btn-small mb-16"><?= icon('chevron-left', 16) ?> Back to recipes</a>

    <div class="recipe-detail">
        <div class="recipe-detail-image" style="background-image:url('<?= h($recipe['image_url']) ?>')"></div>

        <div class="recipe-detail-body">
            <div class="recipe-detail-header">
                <h1><?= h($recipe['title']) ?></h1>
                <form method="post" action="favorite_toggle.php">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="recipe_id" value="<?= h($recipe['id']) ?>">
                    <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI']) ?>">
                    <button type="submit" class="fav-btn <?= $isFavorite ? 'is-active' : '' ?>" aria-label="Toggle favorite">
                        <?= icon('heart', 22) ?>
                    </button>
                </form>
            </div>
            <p class="muted"><?= h($recipe['description']) ?></p>

            <div class="recipe-stats">
                <div><?= icon('clock', 16) ?> <?= (int)$recipe['cook_time_minutes'] ?> min</div>
                <div><?= icon('users', 16) ?> Serves <?= (int)$recipe['servings'] ?></div>
                <div><?= icon('flame', 16) ?> <?= (int)$recipe['calories'] ?> cal</div>
                <div>Difficulty: <?= h(ucfirst($recipe['difficulty'])) ?></div>
            </div>

            <div class="macro-row">
                <div class="macro-pill"><strong><?= (int)$recipe['protein_g'] ?>g</strong><span>Protein</span></div>
                <div class="macro-pill"><strong><?= (int)$recipe['carbs_g'] ?>g</strong><span>Carbs</span></div>
                <div class="macro-pill"><strong><?= (int)$recipe['fat_g'] ?>g</strong><span>Fat</span></div>
                <div class="macro-pill"><strong><?= (int)$recipe['fiber_g'] ?>g</strong><span>Fiber</span></div>
            </div>

            <?php if ($dietTags): ?>
                <div class="tag-row mb-16">
                    <?php foreach ($dietTags as $tag): ?><span class="tag"><?= h($tag) ?></span><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($allergens): ?>
                <p class="muted">Contains: <?= h(implode(', ', $allergens)) ?></p>
            <?php endif; ?>

            <div class="recipe-columns">
                <div>
                    <h2>Ingredients</h2>
                    <ul class="ingredient-list">
                        <?php foreach ($ingredients as $ing): ?>
                            <li><?= h($ing['display_quantity']) ?> <?= h($ing['name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h2>Instructions</h2>
                    <ol class="step-list">
                        <?php foreach ($steps as $step): ?>
                            <li><?= h($step['step_text']) ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>

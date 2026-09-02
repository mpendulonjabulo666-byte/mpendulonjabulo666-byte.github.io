<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/recipe_card.php';

$user = require_login();

$stmt = db()->prepare(
    'SELECT r.*, GROUP_CONCAT(dt.diet_type SEPARATOR ",") AS diet_tags
     FROM favorites f
     JOIN recipes r ON r.id = f.recipe_id
     LEFT JOIN recipe_diet_tags dt ON dt.recipe_id = r.id
     WHERE f.user_id = ?
     GROUP BY r.id
     ORDER BY f.created_at DESC'
);
$stmt->execute([$user['id']]);
$recipes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Favorites · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <h1 class="mb-16">Your favorites</h1>
    <?php if (!$recipes): ?>
        <p class="muted">You haven't favorited any recipes yet. <a href="index.php">Browse recipes</a>.</p>
    <?php else: ?>
        <div class="recipe-grid">
            <?php foreach ($recipes as $recipe): ?>
                <?php render_recipe_card($recipe, true); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

$stmt = db()->prepare('SELECT * FROM recipes WHERE created_by = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$recipes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Recipes · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <?php if ($success = flash_get('success')): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <div class="planner-header">
        <h1>My recipes</h1>
        <a class="btn btn-primary" href="add_recipe.php"><?= icon('plus', 16) ?> Add a recipe</a>
    </div>

    <?php if (!$recipes): ?>
        <p class="muted">You haven't added any recipes yet.</p>
    <?php else: ?>
        <div class="recipe-grid">
            <?php foreach ($recipes as $recipe): ?>
                <div class="recipe-card">
                    <a href="recipe.php?id=<?= urlencode($recipe['id']) ?>">
                        <div class="recipe-card-image" style="background-image:url('<?= h($recipe['image_url']) ?>')"></div>
                        <div class="recipe-card-body">
                            <h3><?= h($recipe['title']) ?></h3>
                            <p class="muted recipe-card-desc"><?= h($recipe['description']) ?></p>
                        </div>
                    </a>
                    <div class="recipe-owner-actions" style="padding:0 16px 16px;">
                        <a class="btn btn-text btn-small" href="add_recipe.php?id=<?= urlencode($recipe['id']) ?>">Edit</a>
                        <form method="post" action="recipe_delete.php" onsubmit="return confirm('Delete this recipe?');">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="recipe_id" value="<?= h($recipe['id']) ?>">
                            <button type="submit" class="btn btn-text btn-small" style="color:var(--error);"><?= icon('trash', 14) ?> Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

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
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
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
        <div class="search-field mb-16">
            <?= icon('search', 16) ?>
            <input type="text" id="mineSearch" placeholder="Search your recipes...">
        </div>
        <div class="recipe-grid" id="mineGrid">
            <?php foreach ($recipes as $recipe): ?>
                <div class="recipe-card" data-title="<?= h(mb_strtolower($recipe['title'])) ?>">
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
        <p class="muted center-text mt-16" id="mineEmpty" hidden>No recipes match your search.</p>
        <script>
        document.getElementById('mineSearch').addEventListener('input', function (e) {
            var q = e.target.value.trim().toLowerCase();
            var cards = document.querySelectorAll('#mineGrid .recipe-card');
            var visible = 0;
            cards.forEach(function (card) {
                var match = card.getAttribute('data-title').indexOf(q) !== -1;
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.getElementById('mineEmpty').hidden = visible > 0;
        });
        </script>
    <?php endif; ?>
</main>
</body>
</html>

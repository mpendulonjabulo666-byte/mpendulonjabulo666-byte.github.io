<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();
$canSubmit = !empty($user['is_admin']) || platform_setting('allow_recipe_submissions');
$isCurrentlyPremium = !empty($user['is_admin']) || !empty($user['is_premium_member']);

$stmt = db()->prepare('SELECT * FROM recipes WHERE created_by = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$recipes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Recipes · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=16">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <?php if ($success = flash_get('success')): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash_get('error')): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="planner-header">
        <h1>My recipes</h1>
        <?php if ($canSubmit): ?>
            <a class="btn btn-primary" href="add_recipe.php"><?= icon('plus', 16) ?> Add a recipe</a>
        <?php else: ?>
            <span class="tag"><?= icon('alert-triangle', 14) ?> New submissions paused</span>
        <?php endif; ?>
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
                            <?php if ($recipe['is_premium'] && !$isCurrentlyPremium): ?>
                                <p class="allergen-warning"><?= icon('alert-triangle', 12) ?> Unpublished - renew <a href="premium.php">Premium</a> to relist</p>
                            <?php endif; ?>
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

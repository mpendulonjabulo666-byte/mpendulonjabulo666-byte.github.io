<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/recipe_card.php';

$user = require_login();

$stmt = db()->prepare(
    'SELECT r.*,
     GROUP_CONCAT(DISTINCT dt.diet_type SEPARATOR ",") AS diet_tags,
     GROUP_CONCAT(DISTINCT al.allergen SEPARATOR ",") AS allergens,
     rt.avg_rating, rt.rating_count
     FROM favorites f
     JOIN recipes r ON r.id = f.recipe_id
     LEFT JOIN recipe_diet_tags dt ON dt.recipe_id = r.id
     LEFT JOIN recipe_allergens al ON al.recipe_id = r.id
     LEFT JOIN (SELECT recipe_id, AVG(rating) avg_rating, COUNT(*) rating_count FROM recipe_ratings GROUP BY recipe_id) rt ON rt.recipe_id = r.id
     WHERE f.user_id = ?
     GROUP BY r.id
     ORDER BY f.created_at DESC'
);
$stmt->execute([$user['id']]);
$recipes = $stmt->fetchAll();

$userAllergenStmt = db()->prepare('SELECT allergen FROM user_allergens WHERE user_id = ?');
$userAllergenStmt->execute([$user['id']]);
$userAllergens = $userAllergenStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Favorites · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <h1 class="mb-16">Your favorites</h1>
    <?php if (!$recipes): ?>
        <p class="muted">You haven't favorited any recipes yet. <a href="index.php">Browse recipes</a>.</p>
    <?php else: ?>
        <div class="search-field mb-16">
            <?= icon('search', 16) ?>
            <input type="text" id="favSearch" placeholder="Search your favorites...">
        </div>
        <div class="recipe-grid" id="favGrid">
            <?php foreach ($recipes as $recipe): ?>
                <?php
                $recipeAllergens = array_filter(explode(',', $recipe['allergens'] ?? ''));
                $conflicts = array_intersect($recipeAllergens, $userAllergens);
                render_recipe_card($recipe, true, $conflicts);
                ?>
            <?php endforeach; ?>
        </div>
        <p class="muted center-text mt-16" id="favEmpty" hidden>No favorites match your search.</p>
        <script>
        document.getElementById('favSearch').addEventListener('input', function (e) {
            var q = e.target.value.trim().toLowerCase();
            var cards = document.querySelectorAll('#favGrid .recipe-card');
            var visible = 0;
            cards.forEach(function (card) {
                var match = card.getAttribute('data-title').indexOf(q) !== -1;
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.getElementById('favEmpty').hidden = visible > 0;
        });
        </script>
    <?php endif; ?>
</main>
</body>
</html>

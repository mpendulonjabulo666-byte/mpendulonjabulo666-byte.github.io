<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/recipe_card.php';

$user = require_login();

$q = trim($_GET['q'] ?? '');
$mealType = $_GET['meal_type'] ?? '';
$diet = $_GET['diet'] ?? '';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = 'r.title LIKE ?';
    $params[] = '%' . $q . '%';
}
if ($mealType !== '') {
    $where[] = 'r.meal_type = ?';
    $params[] = $mealType;
}
if ($diet !== '') {
    $where[] = 'EXISTS (SELECT 1 FROM recipe_diet_tags dt WHERE dt.recipe_id = r.id AND dt.diet_type = ?)';
    $params[] = $diet;
}

$sql = 'SELECT r.*, GROUP_CONCAT(dt.diet_type SEPARATOR ",") AS diet_tags
        FROM recipes r
        LEFT JOIN recipe_diet_tags dt ON dt.recipe_id = r.id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' GROUP BY r.id ORDER BY r.title';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$recipes = $stmt->fetchAll();

$favStmt = db()->prepare('SELECT recipe_id FROM favorites WHERE user_id = ?');
$favStmt->execute([$user['id']]);
$favoriteIds = array_flip($favStmt->fetchAll(PDO::FETCH_COLUMN));

$dietOptions = ['vegetarian', 'vegan', 'gluten-free', 'high-protein', 'keto'];
$mealTypes = ['breakfast', 'lunch', 'dinner', 'snack'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recipes · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <?php if ($success = flash_get('success')): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <form method="get" class="filter-bar">
        <div class="search-field">
            <?= icon('search', 16) ?>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search recipes...">
        </div>
        <select name="meal_type" onchange="this.form.submit()">
            <option value="">All meals</option>
            <?php foreach ($mealTypes as $mt): ?>
                <option value="<?= h($mt) ?>" <?= $mealType === $mt ? 'selected' : '' ?>><?= ucfirst($mt) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="diet" onchange="this.form.submit()">
            <option value="">Any diet</option>
            <?php foreach ($dietOptions as $d): ?>
                <option value="<?= h($d) ?>" <?= $diet === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>

    <?php if (!$recipes): ?>
        <p class="muted center-text mt-16">No recipes match your filters.</p>
    <?php else: ?>
        <div class="recipe-grid">
            <?php foreach ($recipes as $recipe): ?>
                <?php render_recipe_card($recipe, isset($favoriteIds[$recipe['id']])); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_admin();

$mealTypes = ['breakfast', 'lunch', 'dinner', 'snack'];
$quickAddErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'quick_add') {
    if (!csrf_check()) {
        $quickAddErrors[] = 'Your session expired. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $imageUrl = trim($_POST['image_url'] ?? '');
        $mealType = $_POST['meal_type'] ?? 'dinner';
        $cookTime = (int)($_POST['cook_time'] ?? 20) ?: 20;
        $servings = (int)($_POST['servings'] ?? 2) ?: 2;
        $calories = (int)($_POST['calories'] ?? 0);
        $ingredientLines = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $_POST['ingredients'] ?? '')))));
        $stepLines = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $_POST['instructions'] ?? '')))));

        if ($title === '') $quickAddErrors[] = 'Title is required.';
        if (!in_array($mealType, $mealTypes, true)) $quickAddErrors[] = 'Invalid meal type.';
        if (!$ingredientLines) $quickAddErrors[] = 'Add at least one ingredient (one per line).';
        if (!$stepLines) $quickAddErrors[] = 'Add at least one instruction step (one per line).';

        if (!$quickAddErrors) {
            $recipeId = 'r-' . preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', strtolower($title))) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
            db()->prepare(
                'INSERT INTO recipes (id, title, description, image_url, meal_type, cuisine, difficulty, cook_time_minutes, servings, calories, protein_g, carbs_g, fat_g, fiber_g, is_generated, is_premium, price, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, 0, 1, 0, NULL, ?)'
            )->execute([$recipeId, $title, '', $imageUrl, $mealType, '', 'easy', $cookTime, $servings, $calories, $user['id']]);

            $insIng = db()->prepare('INSERT INTO recipe_ingredients (recipe_id, name, quantity, unit, display_quantity, category, order_index) VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($ingredientLines as $i => $line) $insIng->execute([$recipeId, $line, 0, '', $line, 'other', $i]);

            $insStep = db()->prepare('INSERT INTO recipe_instructions (recipe_id, step_number, step_text) VALUES (?, ?, ?)');
            foreach ($stepLines as $i => $line) $insStep->execute([$recipeId, $i + 1, $line]);

            flash_set('success', 'Recipe added — it\'s free for everyone right away.');
            redirect('admin_recipes.php');
        }
    }
}

$search = trim($_GET['q'] ?? '');
$filterMealType = $_GET['meal_type'] ?? '';

// Was WHERE r.is_generated = 1 - every recipe in the database regardless
// of type now, per CONTINUE.md §2.19: this page is the one place an admin
// can see and manage everything, including the seed catalog and the 45
// hidden rows §2.18 couldn't explain (still visible here on purpose, so
// there's somewhere to actually deal with them later). LEFT JOIN, not the
// original INNER JOIN - a seed-catalog recipe's created_by is NULL, which
// an INNER JOIN would have silently excluded even after dropping the
// is_generated filter.
$where = [];
$params = [];
if ($search !== '') {
    $where[] = 'r.title LIKE ?';
    $params[] = '%' . $search . '%';
}
if ($filterMealType !== '' && in_array($filterMealType, $mealTypes, true)) {
    $where[] = 'r.meal_type = ?';
    $params[] = $filterMealType;
}

$sql = 'SELECT r.*, u.name AS author_name, u.email AS author_email
     FROM recipes r LEFT JOIN users u ON u.id = r.created_by';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY r.created_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$recipes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recipes · Admin · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js?v=18"></script>
<link rel="stylesheet" href="assets/css/style.css?v=20">
<script src="assets/js/theme-toggle.js?v=18" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div class="admin-page-head mb-16">
        <h1 style="margin:0;"><?= icon('shield', 20) ?> Admin portal</h1>
        <a href="admin_profile.php" class="btn btn-text btn-small"><?= icon('user', 16) ?> My profile</a>
    </div>

    <?php $adminCurrent = 'admin_recipes.php'; include __DIR__ . '/includes/admin_nav.php'; ?>

    <?php if ($success = flash_get('success')): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>
    <?php if ($error = flash_get('error')): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="card mb-16">
        <h2 style="margin-top:0;font-size:17px;"><?= icon('plus', 18) ?> Quick add recipe</h2>
        <p class="muted" style="font-size:13px;margin:0 0 16px;">
            The fastest way to add a recipe — it's published free for everyone immediately, no review needed.
        </p>

        <?php foreach ($quickAddErrors as $error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endforeach; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="quick_add">

            <label class="field">
                <span>Title</span>
                <input type="text" name="title" required>
            </label>
            <label class="field">
                <span>Image URL</span>
                <input type="url" name="image_url" placeholder="https://...">
                <span class="muted" style="font-size:12px;">Use a real food photo (e.g. from Unsplash) — not an AI-generated image.</span>
            </label>

            <div class="form-grid mb-16">
                <label class="field">
                    <span>Meal type</span>
                    <select name="meal_type">
                        <?php foreach ($mealTypes as $mt): ?>
                            <option value="<?= h($mt) ?>"><?= ucfirst($mt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field"><span>Cook time (min)</span><input type="number" name="cook_time" value="20" min="1"></label>
                <label class="field"><span>Servings</span><input type="number" name="servings" value="2" min="1"></label>
                <label class="field"><span>Calories</span><input type="number" name="calories" min="0"></label>
            </div>

            <label class="field">
                <span>Ingredients (one per line)</span>
                <textarea name="ingredients" rows="5" placeholder="2 cups rice&#10;500g chicken breast&#10;1 onion, diced" required></textarea>
            </label>
            <label class="field">
                <span>Instructions (one step per line)</span>
                <textarea name="instructions" rows="5" placeholder="Rinse the rice.&#10;Season and sear the chicken.&#10;Simmer everything together for 20 minutes." required></textarea>
            </label>

            <button type="submit" class="btn btn-primary">Add recipe</button>
        </form>
    </div>

    <h1 class="mb-16" style="font-size:20px;"><?= icon('list', 20) ?> All recipes</h1>

    <form method="get" class="toolbar mb-16" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search by title..." class="field" style="max-width:260px;">
        <select name="meal_type" class="field" style="max-width:180px;">
            <option value="">All meal types</option>
            <?php foreach ($mealTypes as $mt): ?>
                <option value="<?= h($mt) ?>" <?= $filterMealType === $mt ? 'selected' : '' ?>><?= ucfirst($mt) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-text btn-small"><?= icon('search', 14) ?> Filter</button>
        <a href="admin_export.php?type=recipes" class="btn btn-text btn-small" style="margin-left:auto;"><?= icon('download', 14) ?> Export CSV</a>
    </form>

    <?php if (!$recipes): ?>
        <p class="muted">No recipes match.</p>
    <?php else: ?>
        <div class="card" style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Meal</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recipes as $recipe): ?>
                        <?php
                        // Three real types, three different correct actions -
                        // see CONTINUE.md §2.19 for why each is what it is.
                        $isUserSubmitted = (bool)$recipe['is_generated'];
                        $isHidden = !$isUserSubmitted && !$recipe['is_published'];
                        ?>
                        <tr>
                            <td>
                                <a href="recipe.php?id=<?= urlencode($recipe['id']) ?>"><?= h($recipe['title']) ?></a>
                            </td>
                            <td>
                                <?php if ($isUserSubmitted): ?>
                                    <span class="tag">User-submitted</span><br>
                                    <span class="muted" style="font-size:12px;"><?= h($recipe['author_name'] ?? '') ?> (<?= h($recipe['author_email'] ?? '') ?>)</span>
                                <?php elseif ($isHidden): ?>
                                    <span class="tag" style="background:var(--error-bg);color:var(--error);">Hidden</span>
                                <?php else: ?>
                                    <span class="tag">Built-in catalog</span>
                                <?php endif; ?>
                            </td>
                            <td><?= h(ucfirst($recipe['meal_type'])) ?></td>
                            <td><?= h((new DateTime($recipe['created_at']))->format('M j, Y')) ?></td>
                            <td style="display:flex;gap:6px;flex-wrap:wrap;">
                                <?php if ($isUserSubmitted): ?>
                                    <a href="add_recipe.php?id=<?= urlencode($recipe['id']) ?>" class="btn btn-text btn-small">Edit</a>
                                    <form method="post" action="admin_recipe_delete.php" onsubmit="return confirm('Remove this recipe?');">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="recipe_id" value="<?= h($recipe['id']) ?>">
                                        <button type="submit" class="btn btn-text btn-small" style="color:var(--error);"><?= icon('trash', 14) ?> Remove</button>
                                    </form>
                                <?php else: ?>
                                    <span class="btn btn-text btn-small" style="opacity:0.5;cursor:not-allowed;" title="Built-in catalog recipe - not editable here">Edit</span>
                                    <form method="post" action="admin_recipe_toggle_publish.php">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="recipe_id" value="<?= h($recipe['id']) ?>">
                                        <?php if ($isHidden): ?>
                                            <button type="submit" class="btn btn-text btn-small"><?= icon('eye', 14) ?> Unhide</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-text btn-small" onclick="return confirm('Hide this recipe from browse/search?');"><?= icon('eye-off', 14) ?> Hide</button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

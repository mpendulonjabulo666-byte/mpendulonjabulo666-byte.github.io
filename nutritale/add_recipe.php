<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

$dietOptions = ['vegetarian', 'vegan', 'gluten-free', 'high-protein', 'keto'];
$allergenOptions = ['dairy', 'eggs', 'gluten', 'nuts', 'soy', 'fish', 'sesame', 'shellfish'];
$mealTypes = ['breakfast', 'lunch', 'dinner', 'snack'];
$difficulties = ['easy', 'medium', 'hard'];
$categories = ['produce', 'protein', 'dairy', 'grains', 'pantry', 'other'];

$editId = $_GET['id'] ?? null;
$recipe = [
    'title' => '', 'description' => '', 'image_url' => '', 'meal_type' => 'dinner',
    'cuisine' => '', 'difficulty' => 'easy', 'cook_time' => 20, 'servings' => 2,
    'calories' => '', 'protein' => '', 'carbs' => '', 'fat' => '', 'fiber' => '',
    'is_premium' => false, 'price' => '',
];
$selectedDiets = [];
$selectedAllergens = [];
$ingredients = [['', '', '', '', 'other']];
$steps = [''];
$errors = [];

if ($editId) {
    $stmt = db()->prepare('SELECT * FROM recipes WHERE id = ? AND created_by = ?');
    $stmt->execute([$editId, $user['id']]);
    $existing = $stmt->fetch();
    if (!$existing) {
        http_response_code(404);
        die('Recipe not found or you do not have permission to edit it.');
    }
    $recipe = [
        'title' => $existing['title'], 'description' => $existing['description'], 'image_url' => $existing['image_url'],
        'meal_type' => $existing['meal_type'], 'cuisine' => $existing['cuisine'], 'difficulty' => $existing['difficulty'],
        'cook_time' => $existing['cook_time_minutes'], 'servings' => $existing['servings'], 'calories' => $existing['calories'],
        'protein' => $existing['protein_g'], 'carbs' => $existing['carbs_g'], 'fat' => $existing['fat_g'], 'fiber' => $existing['fiber_g'],
        'is_premium' => (bool)$existing['is_premium'], 'price' => $existing['price'],
    ];
    $selectedDiets = db()->prepare('SELECT diet_type FROM recipe_diet_tags WHERE recipe_id = ?');
    $selectedDiets->execute([$editId]);
    $selectedDiets = $selectedDiets->fetchAll(PDO::FETCH_COLUMN);
    $selectedAllergens = db()->prepare('SELECT allergen FROM recipe_allergens WHERE recipe_id = ?');
    $selectedAllergens->execute([$editId]);
    $selectedAllergens = $selectedAllergens->fetchAll(PDO::FETCH_COLUMN);
    $ingStmt = db()->prepare('SELECT name, quantity, unit, display_quantity, category FROM recipe_ingredients WHERE recipe_id = ? ORDER BY order_index');
    $ingStmt->execute([$editId]);
    $ingredients = array_map(fn($r) => [$r['name'], $r['quantity'], $r['unit'], $r['display_quantity'], $r['category']], $ingStmt->fetchAll());
    $stepStmt = db()->prepare('SELECT step_text FROM recipe_instructions WHERE recipe_id = ? ORDER BY step_number');
    $stepStmt->execute([$editId]);
    $steps = $stepStmt->fetchAll(PDO::FETCH_COLUMN);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $recipe['title'] = trim($_POST['title'] ?? '');
        $recipe['description'] = trim($_POST['description'] ?? '');
        $recipe['image_url'] = trim($_POST['image_url'] ?? '');
        $recipe['meal_type'] = $_POST['meal_type'] ?? 'dinner';
        $recipe['cuisine'] = trim($_POST['cuisine'] ?? '');
        $recipe['difficulty'] = $_POST['difficulty'] ?? 'easy';
        $recipe['cook_time'] = (int)($_POST['cook_time'] ?? 0);
        $recipe['servings'] = (int)($_POST['servings'] ?? 0);
        $recipe['calories'] = (int)($_POST['calories'] ?? 0);
        $recipe['protein'] = (int)($_POST['protein'] ?? 0);
        $recipe['carbs'] = (int)($_POST['carbs'] ?? 0);
        $recipe['fat'] = (int)($_POST['fat'] ?? 0);
        $recipe['fiber'] = (int)($_POST['fiber'] ?? 0);
        $recipe['is_premium'] = $user['is_vendor'] && isset($_POST['is_premium']);
        $recipe['price'] = $recipe['is_premium'] ? (float)($_POST['price'] ?? 0) : null;

        $selectedDiets = array_intersect($_POST['diet_types'] ?? [], $dietOptions);
        $selectedAllergens = array_intersect($_POST['allergens'] ?? [], $allergenOptions);

        $ingNames = $_POST['ing_name'] ?? [];
        $ingQty = $_POST['ing_qty'] ?? [];
        $ingUnit = $_POST['ing_unit'] ?? [];
        $ingDisplay = $_POST['ing_display'] ?? [];
        $ingCategory = $_POST['ing_category'] ?? [];
        $ingredients = [];
        foreach ($ingNames as $i => $name) {
            $name = trim($name);
            if ($name === '') continue;
            $ingredients[] = [$name, (float)($ingQty[$i] ?? 0), trim($ingUnit[$i] ?? ''), trim($ingDisplay[$i] ?? ''), $ingCategory[$i] ?? 'other'];
        }

        $steps = array_values(array_filter(array_map('trim', $_POST['step_text'] ?? [])));

        if ($recipe['title'] === '') $errors[] = 'Please enter a title.';
        if (!in_array($recipe['meal_type'], $mealTypes, true)) $errors[] = 'Invalid meal type.';
        if ($recipe['cook_time'] <= 0) $errors[] = 'Cook time must be greater than 0.';
        if ($recipe['servings'] <= 0) $errors[] = 'Servings must be greater than 0.';
        if (!$ingredients) $errors[] = 'Add at least one ingredient.';
        if (!$steps) $errors[] = 'Add at least one instruction step.';
        if ($recipe['is_premium'] && $recipe['price'] <= 0) $errors[] = 'Set a price greater than R0 for a premium recipe.';

        if (!$errors) {
            if ($editId) {
                $recipeId = $editId;
                $upd = db()->prepare(
                    'UPDATE recipes SET title=?, description=?, image_url=?, meal_type=?, cuisine=?, difficulty=?,
                     cook_time_minutes=?, servings=?, calories=?, protein_g=?, carbs_g=?, fat_g=?, fiber_g=?,
                     is_premium=?, price=? WHERE id=?'
                );
                $upd->execute([
                    $recipe['title'], $recipe['description'], $recipe['image_url'], $recipe['meal_type'], $recipe['cuisine'],
                    $recipe['difficulty'], $recipe['cook_time'], $recipe['servings'], $recipe['calories'], $recipe['protein'],
                    $recipe['carbs'], $recipe['fat'], $recipe['fiber'], $recipe['is_premium'] ? 1 : 0, $recipe['price'], $recipeId,
                ]);
                db()->prepare('DELETE FROM recipe_diet_tags WHERE recipe_id = ?')->execute([$recipeId]);
                db()->prepare('DELETE FROM recipe_allergens WHERE recipe_id = ?')->execute([$recipeId]);
                db()->prepare('DELETE FROM recipe_ingredients WHERE recipe_id = ?')->execute([$recipeId]);
                db()->prepare('DELETE FROM recipe_instructions WHERE recipe_id = ?')->execute([$recipeId]);
            } else {
                $recipeId = 'r-' . preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', strtolower($recipe['title']))) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
                $ins = db()->prepare(
                    'INSERT INTO recipes (id, title, description, image_url, meal_type, cuisine, difficulty, cook_time_minutes, servings, calories, protein_g, carbs_g, fat_g, fiber_g, is_generated, is_premium, price, created_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)'
                );
                $ins->execute([
                    $recipeId, $recipe['title'], $recipe['description'], $recipe['image_url'], $recipe['meal_type'], $recipe['cuisine'],
                    $recipe['difficulty'], $recipe['cook_time'], $recipe['servings'], $recipe['calories'], $recipe['protein'],
                    $recipe['carbs'], $recipe['fat'], $recipe['fiber'], $recipe['is_premium'] ? 1 : 0, $recipe['price'], $user['id'],
                ]);
            }

            $insDiet = db()->prepare('INSERT INTO recipe_diet_tags (recipe_id, diet_type) VALUES (?, ?)');
            foreach ($selectedDiets as $d) $insDiet->execute([$recipeId, $d]);
            $insAllergen = db()->prepare('INSERT INTO recipe_allergens (recipe_id, allergen) VALUES (?, ?)');
            foreach ($selectedAllergens as $a) $insAllergen->execute([$recipeId, $a]);
            $insIng = db()->prepare('INSERT INTO recipe_ingredients (recipe_id, name, quantity, unit, display_quantity, category, order_index) VALUES (?, ?, ?, ?, ?, ?, ?)');
            foreach ($ingredients as $i => $ing) {
                $display = $ing[3] !== '' ? $ing[3] : trim($ing[1] . ' ' . $ing[2]);
                $insIng->execute([$recipeId, $ing[0], $ing[1], $ing[2], $display, $ing[4], $i]);
            }
            $insStep = db()->prepare('INSERT INTO recipe_instructions (recipe_id, step_number, step_text) VALUES (?, ?, ?)');
            foreach ($steps as $i => $step) $insStep->execute([$recipeId, $i + 1, $step]);

            flash_set('success', $editId ? 'Recipe updated.' : 'Recipe created.');
            redirect('recipe.php?id=' . urlencode($recipeId));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $editId ? 'Edit Recipe' : 'New Recipe' ?> · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main" style="max-width:760px;">
    <h1 class="mb-16"><?= $editId ? 'Edit recipe' : 'Add a recipe' ?></h1>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <form method="post" class="card" id="recipe-form">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

        <label class="field">
            <span>Title</span>
            <input type="text" name="title" value="<?= h($recipe['title']) ?>" required>
        </label>
        <label class="field">
            <span>Description</span>
            <input type="text" name="description" value="<?= h($recipe['description']) ?>">
        </label>
        <label class="field">
            <span>Image URL</span>
            <input type="url" name="image_url" value="<?= h($recipe['image_url']) ?>" placeholder="https://...">
        </label>

        <div class="form-grid mb-16">
            <label class="field">
                <span>Meal type</span>
                <select name="meal_type">
                    <?php foreach ($mealTypes as $mt): ?>
                        <option value="<?= h($mt) ?>" <?= $recipe['meal_type'] === $mt ? 'selected' : '' ?>><?= ucfirst($mt) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Cuisine</span>
                <input type="text" name="cuisine" value="<?= h($recipe['cuisine']) ?>">
            </label>
            <label class="field">
                <span>Difficulty</span>
                <select name="difficulty">
                    <?php foreach ($difficulties as $diff): ?>
                        <option value="<?= h($diff) ?>" <?= $recipe['difficulty'] === $diff ? 'selected' : '' ?>><?= ucfirst($diff) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Cook time (min)</span>
                <input type="number" name="cook_time" value="<?= h($recipe['cook_time']) ?>" min="1" required>
            </label>
            <label class="field">
                <span>Servings</span>
                <input type="number" name="servings" value="<?= h($recipe['servings']) ?>" min="1" required>
            </label>
        </div>

        <div class="form-grid mb-16">
            <label class="field"><span>Calories</span><input type="number" name="calories" value="<?= h($recipe['calories']) ?>" min="0"></label>
            <label class="field"><span>Protein (g)</span><input type="number" name="protein" value="<?= h($recipe['protein']) ?>" min="0"></label>
            <label class="field"><span>Carbs (g)</span><input type="number" name="carbs" value="<?= h($recipe['carbs']) ?>" min="0"></label>
            <label class="field"><span>Fat (g)</span><input type="number" name="fat" value="<?= h($recipe['fat']) ?>" min="0"></label>
            <label class="field"><span>Fiber (g)</span><input type="number" name="fiber" value="<?= h($recipe['fiber']) ?>" min="0"></label>
        </div>

        <?php if ($user['is_vendor']): ?>
            <div class="pref-group card" style="background:var(--green-light);border-color:var(--green);">
                <h3 style="margin-top:0;">Sell this recipe</h3>
                <label class="pref-chip <?= $recipe['is_premium'] ? 'is-active' : '' ?>" style="display:inline-flex;background:var(--white);">
                    <input type="checkbox" name="is_premium" id="premium-toggle" <?= $recipe['is_premium'] ? 'checked' : '' ?>>
                    Make this a premium recipe
                </label>
                <label class="field mt-16" id="price-field" style="<?= $recipe['is_premium'] ? '' : 'display:none;' ?>max-width:200px;">
                    <span>Price (ZAR)</span>
                    <input type="number" name="price" value="<?= h($recipe['price']) ?>" min="1" step="0.01" placeholder="e.g. 49.00">
                </label>
            </div>
            <script>
            document.getElementById('premium-toggle').addEventListener('change', function (e) {
                document.getElementById('price-field').style.display = e.target.checked ? '' : 'none';
            });
            </script>
        <?php endif; ?>

        <div class="pref-group">
            <h3>Diet tags</h3>
            <div class="pref-options">
                <?php foreach ($dietOptions as $d): ?>
                    <label class="pref-chip <?= in_array($d, $selectedDiets, true) ? 'is-active' : '' ?>">
                        <input type="checkbox" name="diet_types[]" value="<?= h($d) ?>" <?= in_array($d, $selectedDiets, true) ? 'checked' : '' ?>>
                        <?= h(ucfirst($d)) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pref-group">
            <h3>Allergens present</h3>
            <div class="pref-options">
                <?php foreach ($allergenOptions as $a): ?>
                    <label class="pref-chip <?= in_array($a, $selectedAllergens, true) ? 'is-active' : '' ?>">
                        <input type="checkbox" name="allergens[]" value="<?= h($a) ?>" <?= in_array($a, $selectedAllergens, true) ? 'checked' : '' ?>>
                        <?= h(ucfirst($a)) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pref-group">
            <h3>Ingredients</h3>
            <div class="dynamic-rows" id="ingredient-rows">
                <?php foreach ($ingredients as $ing): ?>
                    <div class="dynamic-row">
                        <input type="text" name="ing_name[]" placeholder="Name" value="<?= h($ing[0]) ?>">
                        <input type="text" name="ing_qty[]" placeholder="Qty" value="<?= h($ing[1]) ?>" style="max-width:70px;">
                        <input type="text" name="ing_unit[]" placeholder="Unit" value="<?= h($ing[2]) ?>" style="max-width:80px;">
                        <input type="text" name="ing_display[]" placeholder="Display (e.g. 1/2 cup)" value="<?= h($ing[3]) ?>">
                        <select name="ing_category[]" style="max-width:110px;">
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= h($c) ?>" <?= ($ing[4] ?? 'other') === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="remove-row"><?= icon('x', 14) ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-text btn-small" id="add-ingredient"><?= icon('plus', 14) ?> Add ingredient</button>
        </div>

        <div class="pref-group">
            <h3>Instructions</h3>
            <div class="dynamic-rows" id="step-rows">
                <?php foreach ($steps as $step): ?>
                    <div class="dynamic-row">
                        <input type="text" name="step_text[]" placeholder="Step" value="<?= h($step) ?>">
                        <button type="button" class="remove-row"><?= icon('x', 14) ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-text btn-small" id="add-step"><?= icon('plus', 14) ?> Add step</button>
        </div>

        <button type="submit" class="btn btn-primary"><?= $editId ? 'Save changes' : 'Create recipe' ?></button>
    </form>
</main>

<template id="ingredient-row-template">
    <div class="dynamic-row">
        <input type="text" name="ing_name[]" placeholder="Name">
        <input type="text" name="ing_qty[]" placeholder="Qty" style="max-width:70px;">
        <input type="text" name="ing_unit[]" placeholder="Unit" style="max-width:80px;">
        <input type="text" name="ing_display[]" placeholder="Display (e.g. 1/2 cup)">
        <select name="ing_category[]" style="max-width:110px;">
            <?php foreach ($categories as $c): ?>
                <option value="<?= h($c) ?>"><?= ucfirst($c) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="remove-row"><?= icon('x', 14) ?></button>
    </div>
</template>
<template id="step-row-template">
    <div class="dynamic-row">
        <input type="text" name="step_text[]" placeholder="Step">
        <button type="button" class="remove-row"><?= icon('x', 14) ?></button>
    </div>
</template>

<script>
function wireRemoveButtons(container) {
    container.querySelectorAll('.remove-row').forEach(function (btn) {
        btn.onclick = function () {
            if (container.children.length > 1) btn.closest('.dynamic-row').remove();
        };
    });
}
var ingredientRows = document.getElementById('ingredient-rows');
var stepRows = document.getElementById('step-rows');
wireRemoveButtons(ingredientRows);
wireRemoveButtons(stepRows);

document.getElementById('add-ingredient').onclick = function () {
    var tpl = document.getElementById('ingredient-row-template');
    ingredientRows.appendChild(tpl.content.cloneNode(true));
    wireRemoveButtons(ingredientRows);
};
document.getElementById('add-step').onclick = function () {
    var tpl = document.getElementById('step-row-template');
    stepRows.appendChild(tpl.content.cloneNode(true));
    wireRemoveButtons(stepRows);
};
</script>
</body>
</html>

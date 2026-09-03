<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$log = [];
$error = null;

try {
    $root = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $root->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $log[] = 'Database "' . DB_NAME . '" ready.';

    require_once __DIR__ . '/config/database.php';
    $pdo = db();

    $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
    $sql = preg_replace('/^--.*$/m', '', $sql); // strip full-line comments before splitting on ';'
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement === '') continue;
        $pdo->exec($statement);
    }
    $log[] = 'Tables created (or already existed).';

    $count = (int)$pdo->query('SELECT COUNT(*) FROM recipes')->fetchColumn();
    if ($count === 0) {
        require_once __DIR__ . '/data/seed_recipes.php';
        $recipes = nutritale_seed_recipes();

        $insertRecipe = $pdo->prepare('INSERT INTO recipes (id, title, description, image_url, meal_type, cuisine, difficulty, cook_time_minutes, servings, calories, protein_g, carbs_g, fat_g, fiber_g, is_generated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');
        $insertDiet = $pdo->prepare('INSERT INTO recipe_diet_tags (recipe_id, diet_type) VALUES (?, ?)');
        $insertAllergen = $pdo->prepare('INSERT INTO recipe_allergens (recipe_id, allergen) VALUES (?, ?)');
        $insertIngredient = $pdo->prepare('INSERT INTO recipe_ingredients (recipe_id, name, quantity, unit, display_quantity, category, order_index) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insertStep = $pdo->prepare('INSERT INTO recipe_instructions (recipe_id, step_number, step_text) VALUES (?, ?, ?)');

        $pdo->beginTransaction();
        foreach ($recipes as $r) {
            $insertRecipe->execute([
                $r['id'], $r['title'], $r['description'], $r['image_url'], $r['meal_type'], $r['cuisine'],
                $r['difficulty'], $r['cook_time'], $r['servings'], $r['calories'], $r['protein'], $r['carbs'], $r['fat'], $r['fiber'],
            ]);
            foreach ($r['diet_tags'] as $d) $insertDiet->execute([$r['id'], $d]);
            foreach ($r['allergens'] as $a) $insertAllergen->execute([$r['id'], $a]);
            foreach ($r['ingredients'] as $i => $ing) {
                $insertIngredient->execute([$r['id'], $ing[0], $ing[1], $ing[2], $ing[3], $ing[4], $i]);
            }
            foreach ($r['steps'] as $i => $step) {
                $insertStep->execute([$r['id'], $i + 1, $step]);
            }
        }
        $pdo->commit();
        $log[] = 'Seeded ' . count($recipes) . ' starter recipes.';
    } else {
        $log[] = "Recipes table already has $count rows — skipped seeding.";
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<div class="auth-shell">
<?= render_theme_toggle() ?>
    <div class="auth-card">
        <div class="center-text mb-16"><?= nutritale_logo_svg(56) ?></div>
        <h1 class="center-text">NutriTale Setup</h1>
        <div class="card">
            <?php if ($error): ?>
                <div class="alert alert-error">Setup failed: <?= h($error) ?></div>
                <p class="muted">Check that MySQL is running and that <code>config/config.php</code> has the right DB credentials.</p>
            <?php else: ?>
                <div class="alert alert-success">Setup complete.</div>
                <ul style="margin-bottom:16px;">
                    <?php foreach ($log as $line): ?>
                        <li style="padding:6px 0;display:flex;gap:8px;align-items:center;"><?= icon('check', 16) ?> <?= h($line) ?></li>
                    <?php endforeach; ?>
                </ul>
                <a class="btn btn-primary btn-block" href="register.php">Create your account</a>
                <a class="btn btn-text btn-block" href="login.php">I already have an account</a>
            <?php endif; ?>
        </div>
        <p class="muted mt-16" style="font-size:12.5px;">You can re-run install.php any time — it only creates what's missing.</p>
    </div>
</div>
</body>
</html>

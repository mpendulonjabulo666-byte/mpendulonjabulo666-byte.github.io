<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['ingredient_name'] ?? '');
        if ($name !== '') {
            $stmt = db()->prepare('INSERT IGNORE INTO user_pantry_items (user_id, ingredient_name) VALUES (?, ?)');
            $stmt->execute([$user['id'], $name]);
        }
    } elseif ($action === 'remove') {
        $name = $_POST['ingredient_name'] ?? '';
        db()->prepare('DELETE FROM user_pantry_items WHERE user_id = ? AND ingredient_name = ?')->execute([$user['id'], $name]);
    } elseif ($action === 'clear') {
        db()->prepare('DELETE FROM user_pantry_items WHERE user_id = ?')->execute([$user['id']]);
    }
    redirect('pantry.php');
}

$pantryStmt = db()->prepare('SELECT ingredient_name FROM user_pantry_items WHERE user_id = ? ORDER BY ingredient_name');
$pantryStmt->execute([$user['id']]);
$pantry = $pantryStmt->fetchAll(PDO::FETCH_COLUMN);
$pantryNorm = array_map(fn($p) => mb_strtolower(trim($p)), $pantry);

$matches = [];
if ($pantry) {
    $recipeStmt = db()->query('SELECT id, title, description, image_url, cook_time_minutes, calories FROM recipes ORDER BY title');
    $recipes = $recipeStmt->fetchAll();

    $ingStmt = db()->prepare('SELECT name FROM recipe_ingredients WHERE recipe_id = ? ORDER BY order_index');
    foreach ($recipes as $recipe) {
        $ingStmt->execute([$recipe['id']]);
        $ingredients = $ingStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$ingredients) continue;

        $have = [];
        $missing = [];
        foreach ($ingredients as $ing) {
            $ingNorm = mb_strtolower(trim($ing));
            $found = false;
            foreach ($pantryNorm as $p) {
                if ($p !== '' && (str_contains($ingNorm, $p) || str_contains($p, $ingNorm))) {
                    $found = true;
                    break;
                }
            }
            if ($found) $have[] = $ing; else $missing[] = $ing;
        }

        if (!$have) continue;
        $matches[] = [
            'recipe' => $recipe,
            'have' => count($have),
            'total' => count($ingredients),
            'missing' => $missing,
            'pct' => count($have) / count($ingredients),
        ];
    }
    usort($matches, fn($a, $b) => $b['pct'] <=> $a['pct'] ?: $b['have'] <=> $a['have']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>What Can I Make? · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <h1 class="mb-16"><?= icon('wand', 20) ?> What Can I Make?</h1>
    <p class="muted" style="margin-top:-10px;">Add the ingredients you have on hand and we'll find recipes that use them.</p>

    <div class="card mb-16">
        <form method="post" style="display:flex;gap:8px;">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="add">
            <input type="text" name="ingredient_name" placeholder="e.g. chicken, spinach, rice..." style="flex:1;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--ink);" required>
            <button type="submit" class="btn btn-primary"><?= icon('plus', 16) ?> Add</button>
        </form>

        <?php if ($pantry): ?>
            <div class="tag-row mt-16">
                <?php foreach ($pantry as $item): ?>
                    <form method="post" style="display:inline-flex;">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="ingredient_name" value="<?= h($item) ?>">
                        <button type="submit" class="pantry-chip"><?= h($item) ?> <?= icon('x', 12) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
            <form method="post" class="mt-16">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="btn btn-text btn-small" style="color:var(--error);"><?= icon('trash', 14) ?> Clear all</button>
            </form>
        <?php else: ?>
            <p class="muted mt-16" style="font-size:13px;">Your pantry is empty — add a few ingredients to get recipe ideas.</p>
        <?php endif; ?>
    </div>

    <?php if ($pantry): ?>
        <h2 class="mb-16">Recipes you can make</h2>
        <?php if (!$matches): ?>
            <p class="muted">No recipes match what's in your pantry yet — try adding a few more ingredients.</p>
        <?php else: ?>
            <div class="recipe-grid">
                <?php foreach ($matches as $m): $recipe = $m['recipe']; ?>
                    <a class="recipe-card" href="recipe.php?id=<?= urlencode($recipe['id']) ?>">
                        <div class="recipe-card-image" style="background-image:url('<?= h($recipe['image_url']) ?>')">
                            <span class="pantry-match-badge <?= $m['pct'] >= 1 ? 'is-full' : '' ?>"><?= $m['have'] ?>/<?= $m['total'] ?> ingredients</span>
                        </div>
                        <div class="recipe-card-body">
                            <h3><?= h($recipe['title']) ?></h3>
                            <p class="muted recipe-card-desc"><?= h($recipe['description']) ?></p>
                            <div class="recipe-card-meta">
                                <span><?= icon('clock', 14) ?> <?= (int)$recipe['cook_time_minutes'] ?> min</span>
                                <span><?= icon('flame', 14) ?> <?= (int)$recipe['calories'] ?> cal</span>
                            </div>
                            <?php if ($m['missing']): ?>
                                <p class="muted" style="font-size:11.5px;margin:6px 0 0;">Missing: <?= h(implode(', ', $m['missing'])) ?></p>
                            <?php else: ?>
                                <p style="font-size:11.5px;margin:6px 0 0;color:var(--green-dark);font-weight:600;">You have everything!</p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>

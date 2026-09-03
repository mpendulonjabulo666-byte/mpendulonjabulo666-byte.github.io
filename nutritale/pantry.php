<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

$usesLeft = max(0, PANTRY_FREE_USES - (int)$user['pantry_free_uses_used']);
$isBlocked = !$user['is_premium_member'] && $usesLeft <= 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['ingredient_name'] ?? '');
        if ($name !== '' && !$isBlocked) {
            $stmt = db()->prepare('INSERT IGNORE INTO user_pantry_items (user_id, ingredient_name) VALUES (?, ?)');
            $stmt->execute([$user['id'], $name]);
            if ($stmt->rowCount() > 0 && !$user['is_premium_member']) {
                db()->prepare('UPDATE users SET pantry_free_uses_used = pantry_free_uses_used + 1 WHERE id = ?')->execute([$user['id']]);
            }
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

$dietPrefStmt = db()->prepare('SELECT diet_type FROM user_diet_preferences WHERE user_id = ?');
$dietPrefStmt->execute([$user['id']]);
$dietPrefs = $dietPrefStmt->fetchAll(PDO::FETCH_COLUMN);

$matches = [];
if ($pantry) {
    $recipeStmt = db()->query(
        'SELECT r.id, r.title, r.description, r.image_url, r.cook_time_minutes, r.calories,
         GROUP_CONCAT(DISTINCT dt.diet_type SEPARATOR ",") AS diet_tags
         FROM recipes r LEFT JOIN recipe_diet_tags dt ON dt.recipe_id = r.id
         GROUP BY r.id ORDER BY r.title'
    );
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
        $recipeDietTags = array_filter(explode(',', $recipe['diet_tags'] ?? ''));
        $matches[] = [
            'recipe' => $recipe,
            'have' => count($have),
            'total' => count($ingredients),
            'missing' => $missing,
            'pct' => count($have) / count($ingredients),
            'diet_match' => $dietPrefs ? (bool)array_intersect($dietPrefs, $recipeDietTags) : false,
        ];
    }
    usort($matches, fn($a, $b) => $b['diet_match'] <=> $a['diet_match'] ?: $b['pct'] <=> $a['pct'] ?: $b['have'] <=> $a['have']);
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
    <div class="page-hero-banner" style="background-image:url('assets/img/banners/pantry-vegetables.jpg');">
        <div>
            <h1><?= icon('wand', 20) ?> What Can I Make?</h1>
            <p>Add the ingredients you have on hand and we'll find recipes that use them.</p>
        </div>
    </div>

    <?php if (!$user['is_premium_member']): ?>
        <p class="muted mb-16" style="font-size:13px;">
            <?= $usesLeft > 0 ? "$usesLeft free ingredient" . ($usesLeft === 1 ? '' : 's') . ' left on your trial.' : 'Your free trial is used up.' ?>
            <a href="premium.php" style="color:var(--green-dark);font-weight:600;">Go Premium</a> for unlimited use.
        </p>
    <?php endif; ?>

    <div class="card mb-16">
        <?php if ($isBlocked): ?>
            <div class="paywall" style="padding:20px;">
                <?= icon('wand', 24) ?>
                <h2 style="margin:8px 0 4px;font-size:17px;">You've used your <?= PANTRY_FREE_USES ?> free trials</h2>
                <p class="muted" style="margin:0 0 14px;font-size:13.5px;">Upgrade to Premium for unlimited ingredient lookups and diet-matched recommendations.</p>
                <a href="premium.php" class="btn btn-primary">Go Premium — R<?= number_format(PREMIUM_MONTHLY_PRICE, 2) ?>/month</a>
            </div>
        <?php else: ?>
            <form method="post" style="display:flex;gap:8px;">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="add">
                <input type="text" name="ingredient_name" placeholder="e.g. chicken, spinach, rice..." style="flex:1;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--ink);" required>
                <button type="submit" class="btn btn-primary"><?= icon('plus', 16) ?> Add</button>
            </form>
        <?php endif; ?>

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
                            <?php if ($m['diet_match']): ?>
                                <span class="tag" style="position:absolute;top:8px;right:8px;">Matches your diet</span>
                            <?php endif; ?>
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

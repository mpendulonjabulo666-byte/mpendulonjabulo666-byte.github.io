<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/ai_pantry.php';
require_once __DIR__ . '/includes/ai_cache.php';

$user = require_login();

$isPremiumOrAdmin = $user['is_premium_member'] || $user['is_admin'];
$usesLeft = max(0, PANTRY_FREE_USES - (int)$user['pantry_free_uses_used']);
$isBlocked = !$isPremiumOrAdmin && $usesLeft <= 0;

$pantryStmt = db()->prepare('SELECT ingredient_name FROM user_pantry_items WHERE user_id = ? ORDER BY ingredient_name');
$pantryStmt->execute([$user['id']]);
$pantry = $pantryStmt->fetchAll(PDO::FETCH_COLUMN);

$dietPrefStmt = db()->prepare('SELECT diet_type FROM user_diet_preferences WHERE user_id = ?');
$dietPrefStmt->execute([$user['id']]);
$dietPrefs = $dietPrefStmt->fetchAll(PDO::FETCH_COLUMN);

// Drives both the AI prompt and the code-side check on what it returns,
// and filters the rule-based matcher below.
$userAllergens = user_allergens((int)$user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['ingredient_name'] ?? '');
        if ($name !== '' && !$isBlocked) {
            $stmt = db()->prepare('INSERT IGNORE INTO user_pantry_items (user_id, ingredient_name) VALUES (?, ?)');
            // Adding ingredients is free. Only an AI generation spends a trial use.
            $stmt->execute([$user['id'], $name]);
        }
    } elseif ($action === 'remove') {
        $name = $_POST['ingredient_name'] ?? '';
        db()->prepare('DELETE FROM user_pantry_items WHERE user_id = ? AND ingredient_name = ?')->execute([$user['id'], $name]);
    } elseif ($action === 'clear') {
        db()->prepare('DELETE FROM user_pantry_items WHERE user_id = ?')->execute([$user['id']]);
    } elseif ($action === 'ai_suggest' && !$isBlocked && $pantry) {
        $pantryHash = ai_pantry_hash($pantry, $dietPrefs, $userAllergens);
        $cached = ai_cache_lookup((int)$user['id'], $pantryHash, AI_PANTRY_CACHE_DAYS);
        if ($cached !== null) {
            // Nothing new was generated, so nothing new is charged for:
            // no trial use spent, no daily-cap count added.
            $_SESSION['ai_pantry_ideas'] = $cached + ['from_cache' => true];
        } elseif ($isPremiumOrAdmin && ai_daily_attempt_count((int)$user['id']) >= AI_PANTRY_DAILY_CAP) {
            $_SESSION['ai_pantry_ideas'] = ['ok' => false, 'error' => "You've reached today's AI suggestion limit ("
                . AI_PANTRY_DAILY_CAP . '). Try again tomorrow.'];
        } else {
            $result = gemini_pantry_ideas($pantry, $dietPrefs, $userAllergens);
            if (($result['attempts'] ?? 0) > 0) {
                ai_log_generation((int)$user['id'], $pantryHash, $result);
            }
            $_SESSION['ai_pantry_ideas'] = $result;
            if (!$isPremiumOrAdmin) {
                db()->prepare('UPDATE users SET pantry_free_uses_used = pantry_free_uses_used + 1 WHERE id = ?')->execute([$user['id']]);
            }
        }
    }
    redirect('pantry.php');
}

$pantryNorm = array_map(fn($p) => mb_strtolower(trim($p)), $pantry);
$aiResult = $_SESSION['ai_pantry_ideas'] ?? null;
unset($_SESSION['ai_pantry_ideas']);

$matches = [];
$hiddenByAllergens = 0;
if ($pantry) {
    $recipeStmt = db()->query(
        'SELECT r.id, r.title, r.description, r.image_url, r.cook_time_minutes, r.calories,
         GROUP_CONCAT(DISTINCT dt.diet_type SEPARATOR ",") AS diet_tags,
         GROUP_CONCAT(DISTINCT al.allergen SEPARATOR ",") AS allergens
         FROM recipes r
         LEFT JOIN recipe_diet_tags dt ON dt.recipe_id = r.id
         LEFT JOIN recipe_allergens al ON al.recipe_id = r.id
         GROUP BY r.id ORDER BY r.title'
    );
    $recipes = $recipeStmt->fetchAll();

    $ingStmt = db()->prepare('SELECT name FROM recipe_ingredients WHERE recipe_id = ? ORDER BY order_index');
    foreach ($recipes as $recipe) {
        // This page answers "what can I make?", so a recipe the user is
        // allergic to isn't an answer — it's dropped rather than badged.
        // (index.php and favorites.php badge instead, because there the
        // user asked to see the recipe.) The count is surfaced in the UI
        // so the omission is visible rather than silent.
        $recipeAllergens = array_filter(explode(',', $recipe['allergens'] ?? ''));
        if (array_intersect($recipeAllergens, $userAllergens)) {
            $hiddenByAllergens++;
            continue;
        }

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
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=4">
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

    <?php if (!$isPremiumOrAdmin): ?>
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
        <div class="card mb-16">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
                <div>
                    <h2 style="margin:0 0 2px;font-size:16px;"><?= icon('wand', 16) ?> AI meal ideas & shopping list</h2>
                    <p class="muted" style="margin:0;font-size:12.5px;">3 fresh meal ideas built around what's in your pantry, plus what to buy for each.</p>
                    <?php if ($userAllergens): ?>
                        <p style="margin:4px 0 0;font-size:12.5px;color:var(--green-dark);font-weight:600;">
                            <?= icon('check', 12) ?> Avoiding <?= h(implode(', ', $userAllergens)) ?>
                        </p>
                        <?= disclaimer('allergens') ?>
                    <?php endif; ?>
                </div>
                <?php if (!$isBlocked): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="ai_suggest">
                        <button type="submit" class="btn btn-primary btn-small"><?= icon('wand', 14) ?> Get AI ideas</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if ($aiResult && !$aiResult['ok']): ?>
                <p class="muted mt-16" style="font-size:13px;"><?= h($aiResult['error']) ?></p>
            <?php elseif ($aiResult): ?>
                <?php if (!empty($aiResult['from_cache'])): ?>
                    <p class="muted mt-16" style="font-size:12.5px;">
                        <?= icon('check', 12) ?> Showing your saved ideas for this exact pantry
                        (generated within the last <?= (int)AI_PANTRY_CACHE_DAYS ?> days).
                    </p>
                <?php endif; ?>
                <?php if (!empty($aiResult['discarded'])): ?>
                    <p class="muted mt-16" style="font-size:12.5px;">
                        <?= icon('shield', 12) ?>
                        <?= (int)$aiResult['discarded'] ?> suggestion<?= (int)$aiResult['discarded'] === 1 ? '' : 's' ?>
                        removed for containing your allergens.
                    </p>
                <?php endif; ?>
                <div class="mt-16" style="display:grid;gap:14px;">
                    <?php foreach ($aiResult['meals'] as $meal): ?>
                        <div class="shopping-list card" style="box-shadow:none;border:1px solid var(--border);margin:0;">
                            <h3 style="margin-top:0;font-size:15px;"><?= h($meal['title'] ?? '') ?></h3>
                            <p class="muted" style="font-size:13px;margin:0 0 8px;"><?= h($meal['description'] ?? '') ?></p>
                            <?php if (!empty($meal['uses_from_pantry'])): ?>
                                <p style="font-size:12.5px;margin:0;color:var(--green-dark);font-weight:600;">From your pantry: <?= h(implode(', ', $meal['uses_from_pantry'])) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($meal['shopping_list'])): ?>
                                <div class="shopping-category mt-16">
                                    <h3>Shopping list</h3>
                                    <?php foreach ($meal['shopping_list'] as $item): ?>
                                        <div class="shopping-item">
                                            <?= icon('plus', 12) ?>
                                            <span><?= h($item['quantity'] ?? '') ?> <?= h($item['item'] ?? '') ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <h2 class="mb-16">Recipes you can make</h2>
        <?php if ($hiddenByAllergens): ?>
            <p class="muted" style="font-size:12.5px;margin-bottom:0;">
                <?= icon('shield', 12) ?>
                <?= $hiddenByAllergens ?> recipe<?= $hiddenByAllergens === 1 ? '' : 's' ?>
                hidden because <?= $hiddenByAllergens === 1 ? 'it contains' : 'they contain' ?>
                allergens you've asked to avoid.
                <a href="profile.php" style="color:var(--green-dark);font-weight:600;">Change</a>
            </p>
            <div class="mb-16"><?= disclaimer('allergens') ?></div>
        <?php endif; ?>
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

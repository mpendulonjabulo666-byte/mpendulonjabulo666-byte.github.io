<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/ai_pantry.php';
require_once __DIR__ . '/includes/ai_cache.php';
require_once __DIR__ . '/includes/ingredient_matching.php';
require_once __DIR__ . '/includes/external_recipes.php';
require_once __DIR__ . '/includes/pantry_expiry.php';

$user = require_login();

$isPremiumOrAdmin = $user['is_premium_member'] || $user['is_admin'];
// Ingredient add/remove/clear is always free and never gated - only an
// actual AI generation (the ai_suggest handler below) is capped, by a
// per-day count (AI_PANTRY_FREE_DAILY_CAP / AI_PANTRY_DAILY_CAP) rather
// than the old lifetime "3 free trials ever" counter - see config.php.
$aiDailyCap = $isPremiumOrAdmin ? AI_PANTRY_DAILY_CAP : AI_PANTRY_FREE_DAILY_CAP;
// Free accounts: AI_PANTRY_FREE_DAILY_CAP generations per rolling
// AI_PANTRY_FREE_WINDOW_DAYS days (default 3 if the constant is missing from
// a host's older config.php - never a fatal). Premium/admin: per day.
$aiWindowDays = $isPremiumOrAdmin ? 1 : (defined('AI_PANTRY_FREE_WINDOW_DAYS') ? max(1, (int)AI_PANTRY_FREE_WINDOW_DAYS) : 3);
$aiUsedToday = ai_daily_attempt_count((int)$user['id'], $aiWindowDays);
$aiCapReached = $aiUsedToday >= $aiDailyCap;
$aiPeriodLabel = $aiWindowDays > 1 ? "in any $aiWindowDays days" : 'per day';

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
        $raw = trim($_POST['ingredient_name'] ?? '');
        if ($raw !== '') {
            // "chicken, spinach, rice" (the field's own placeholder text)
            // becomes three rows, not one - see split_pantry_entry().
            $stmt = db()->prepare('INSERT IGNORE INTO user_pantry_items (user_id, ingredient_name) VALUES (?, ?)');
            $names = split_pantry_entry($raw);
            foreach ($names as $name) {
                $stmt->execute([$user['id'], $name]);
            }
            // Optional expiry date; adding an item again with a new date updates it.
            $expires = pantry_parse_expiry($_POST['expires_on'] ?? '');
            if ($expires !== null && $names) {
                try {
                    $dateStmt = db()->prepare('UPDATE user_pantry_items SET expires_on = ? WHERE user_id = ? AND ingredient_name = ?');
                    foreach ($names as $name) {
                        $dateStmt->execute([$expires, $user['id'], $name]);
                    }
                } catch (Throwable $e) {
                    // expires_on column not there yet (setup.php not re-run) - item is still added.
                }
            }
        }
    } elseif ($action === 'remove') {
        $name = $_POST['ingredient_name'] ?? '';
        db()->prepare('DELETE FROM user_pantry_items WHERE user_id = ? AND ingredient_name = ?')->execute([$user['id'], $name]);
    } elseif ($action === 'clear') {
        db()->prepare('DELETE FROM user_pantry_items WHERE user_id = ?')->execute([$user['id']]);
    } elseif ($action === 'ai_suggest' && $pantry && empty($user['is_admin']) && !platform_setting('enable_ai_matching')) {
        $_SESSION['ai_pantry_ideas'] = ['ok' => false, 'error' => 'AI recipe matching is temporarily turned off by the site admin.'];
    } elseif ($action === 'ai_suggest' && $pantry) {
        $pantryHash = ai_pantry_hash($pantry, $dietPrefs, $userAllergens);
        $cached = ai_cache_lookup((int)$user['id'], $pantryHash, AI_PANTRY_CACHE_DAYS);
        $secondsSinceLast = ai_seconds_since_last_attempt((int)$user['id']);
        if ($cached !== null) {
            // Nothing new was generated, so nothing new is charged for:
            // no daily-cap count added, no cooldown started.
            $_SESSION['ai_pantry_ideas'] = $cached + ['from_cache' => true];
        } elseif ($aiCapReached) {
            $_SESSION['ai_pantry_ideas'] = ['ok' => false, 'error' => "You've reached the AI suggestion limit ($aiDailyCap $aiPeriodLabel). "
                . ($isPremiumOrAdmin ? 'Try again tomorrow.' : 'Try again in a day or two, or go Premium for up to ' . (int)AI_PANTRY_DAILY_CAP . ' a day.')];
        } elseif ($secondsSinceLast !== null && $secondsSinceLast < AI_PANTRY_COOLDOWN_SECONDS) {
            $wait = AI_PANTRY_COOLDOWN_SECONDS - $secondsSinceLast;
            $_SESSION['ai_pantry_ideas'] = ['ok' => false, 'error' => "Please wait $wait more second" . ($wait === 1 ? '' : 's') . ' before requesting new ideas.'];
        } else {
            $result = gemini_pantry_ideas($pantry, $dietPrefs, $userAllergens);
            if (($result['attempts'] ?? 0) > 0) {
                ai_log_generation((int)$user['id'], $pantryHash, $result);
            }
            $_SESSION['ai_pantry_ideas'] = $result;
        }
    }
    redirect('pantry.php');
}

$expiryMap = pantry_expiry_map((int)$user['id']);
$expiryAlerts = pantry_expiry_alerts($expiryMap);
$aiResult = $_SESSION['ai_pantry_ideas'] ?? null;
unset($_SESSION['ai_pantry_ideas']);

$matches = [];
$hiddenByAllergens = 0;
if ($pantry) {
    // See includes/ingredient_matching.php for why this replaced a
    // substring test (CONTINUE.md §2.2) - pantry "ice" matching recipe
    // "rice" is the canonical example. $pantryCanonical is the set of
    // canonical ingredient identities across the *whole* pantry - a
    // pantry row can resolve to more than one (a real example already in
    // this app's data is a single row literally saved as "rice and
    // chicken"), so it's built once here rather than per pantry item.
    $aliasMap = load_ingredient_alias_map();
    $pantryCanonical = [];
    foreach ($pantry as $item) {
        $pantryCanonical = array_merge($pantryCanonical, canonical_ingredient_set($item, $aliasMap));
    }
    $pantryCanonical = array_values(array_unique($pantryCanonical));

    $recipeStmt = db()->query(
        'SELECT r.id, r.title, r.description, r.image_url, r.cook_time_minutes, r.calories, r.is_premium, r.created_by,
         GROUP_CONCAT(DISTINCT dt.diet_type SEPARATOR ",") AS diet_tags,
         GROUP_CONCAT(DISTINCT al.allergen SEPARATOR ",") AS allergens
         FROM recipes r
         LEFT JOIN recipe_diet_tags dt ON dt.recipe_id = r.id
         LEFT JOIN recipe_allergens al ON al.recipe_id = r.id
         WHERE r.is_published = 1
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
            if (pantry_has_ingredient($ing, $pantryCanonical, $aliasMap)) {
                $have[] = $ing;
            } else {
                $missing[] = $ing;
            }
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

// Free: the top FREE_PANTRY_MATCH_LIMIT matches among the recipes their plan
// can open. Premium: every match, plus up to EXTERNAL_RECIPE_LIMIT more from
// TheMealDB. $totalMatchCount (whole library) drives the upsell line.
$hasFullLibrary = user_has_full_library($user);
$totalMatchCount = count($matches);
if (!$hasFullLibrary) {
    $matches = array_slice(array_values(array_filter($matches, fn($m) => !recipe_plan_locked($user, $m['recipe']))), 0, FREE_PANTRY_MATCH_LIMIT);
}
$moreWithPremium = $totalMatchCount - count($matches);
// Loaded after render by assets/js/world-recipes.js (external_matches.php).
$showWorld = $hasFullLibrary && $pantry && mealdb_enabled();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>What Can I Make? · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js?v=24"></script>
<link rel="stylesheet" href="assets/css/style.css?v=24">
<script src="assets/js/theme-toggle.js?v=24" defer></script>
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
            <?= $aiCapReached ? "You've used your $aiDailyCap free AI idea generations ($aiPeriodLabel)." : max(0, $aiDailyCap - $aiUsedToday) . ' free AI idea generation' . ((max(0, $aiDailyCap - $aiUsedToday)) === 1 ? '' : 's') . ' left (' . $aiDailyCap . ' ' . $aiPeriodLabel . ').' ?>
            <a href="premium.php" style="color:var(--green-dark);font-weight:600;">Go Premium</a> for up to <?= (int)AI_PANTRY_DAILY_CAP ?> a day.
        </p>
    <?php endif; ?>

    <?= render_pantry_expiry_banner($expiryAlerts) ?>

    <div class="card mb-16">
        <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="add">
            <input type="text" name="ingredient_name" placeholder="e.g. chicken, spinach, rice..." aria-label="Add an ingredient" style="flex:1;min-width:180px;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--ink);" required>
            <input type="date" name="expires_on" class="pantry-date-input" aria-label="Expiry date (optional)" title="Expiry date (optional)">
            <button type="submit" class="btn btn-primary"><?= icon('plus', 16) ?> Add</button>
        </form>
        <div class="pantry-scan-row">
            <?php if ($isPremiumOrAdmin): ?>
                <button type="button" class="btn btn-text btn-small" id="scan-open"><?= icon('search', 14) ?> Scan a barcode</button>
            <?php else: ?>
                <a class="btn btn-text btn-small" href="premium.php"><?= icon('lock', 14) ?> Scan a barcode (Premium)</a>
            <?php endif; ?>
        </div>
        <?php if ($isPremiumOrAdmin): ?>
            <div class="pantry-scan-panel" id="scan-panel" hidden>
                <div id="scan-reader" class="pantry-scan-reader"></div>
                <p class="muted pantry-scan-status" id="scan-status" role="status">Point your camera at the barcode on the pack.</p>
                <form class="pantry-scan-manual" id="scan-manual">
                    <input type="text" id="scan-code" inputmode="numeric" pattern="[0-9]{6,14}" placeholder="...or type the barcode number" aria-label="Barcode number">
                    <button type="submit" class="btn btn-small">Look up</button>
                </form>
                <form method="post" class="pantry-scan-result" id="scan-result" hidden>
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="add">
                    <label for="scan-name" class="muted" style="font-size:12.5px;">Found it. Edit the name if needed, then add it:</label>
                    <div class="pantry-scan-result-row">
                        <input type="text" name="ingredient_name" id="scan-name" required>
                        <input type="date" name="expires_on" class="pantry-date-input" aria-label="Expiry date (optional)" title="Expiry date (optional)">
                        <button type="submit" class="btn btn-primary btn-small"><?= icon('plus', 14) ?> Add</button>
                    </div>
                </form>
                <p class="muted" style="font-size:11.5px;margin:10px 0 0;">Product data from <a href="https://world.openfoodfacts.org" target="_blank" rel="noopener">Open Food Facts</a> (ODbL).
                    <button type="button" class="btn btn-text btn-small" id="scan-close">Close</button></p>
            </div>
        <?php endif; ?>

        <?php if ($pantry): ?>
            <div class="tag-row mt-16">
                <?php foreach ($pantry as $item): ?>
                    <form method="post" style="display:inline-flex;">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="ingredient_name" value="<?= h($item) ?>">
                        <?php $itemExpiry = isset($expiryMap[$item]) ? pantry_expiry_days((string)$expiryMap[$item]) : null; ?>
                        <button type="submit" class="pantry-chip"><?= h($item) ?><?php if ($itemExpiry !== null): ?> <span class="pantry-expiry expiry-<?= h(pantry_expiry_state($itemExpiry)) ?>"><?= h(pantry_expiry_label($itemExpiry)) ?></span><?php endif; ?> <?= icon('x', 12) ?></button>
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
                <?php if (!$aiCapReached): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="ai_suggest">
                        <button type="submit" class="btn btn-primary btn-small"><?= icon('wand', 14) ?> Get AI ideas</button>
                    </form>
                <?php else: ?>
                    <p class="muted" style="font-size:12.5px;margin:0;">Today's limit reached<?= $isPremiumOrAdmin ? '' : ' — ' ?><?= $isPremiumOrAdmin ? '.' : '<a href="premium.php" style="color:var(--green-dark);font-weight:600;">go Premium</a> for more.' ?></p>
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
                    <div class="recipe-card">
                        <div class="recipe-card-image" style="background-image:url('<?= h($recipe['image_url']) ?>')">
                            <?= recipe_photo_credit($recipe['id'], $recipe['image_url'] ?? null, 'recipe-card-credit') ?>
                            <span class="pantry-match-badge <?= $m['pct'] >= 1 ? 'is-full' : '' ?>"><?= $m['have'] ?>/<?= $m['total'] ?> ingredients</span>
                            <?php if ($m['diet_match']): ?>
                                <span class="tag" style="position:absolute;top:8px;right:8px;">Matches your diet</span>
                            <?php endif; ?>
                        </div>
                        <div class="recipe-card-body">
                            <h3><a class="recipe-card-link" href="recipe.php?id=<?= urlencode($recipe['id']) ?>"><?= h($recipe['title']) ?></a></h3>
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
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$hasFullLibrary && $moreWithPremium > 0): ?>
            <div class="plan-limit-banner mt-16">
                <?= icon('wand', 18) ?>
                <p>Your pantry matches <strong><?= $totalMatchCount ?> recipe<?= $totalMatchCount === 1 ? '' : 's' ?></strong>.
                    The free plan shows <?= FREE_PANTRY_MATCH_LIMIT ?>; Premium shows every match, plus up to <?= EXTERNAL_RECIPE_LIMIT ?> more from around the world.</p>
                <a href="premium.php" class="btn btn-primary btn-small">Go Premium</a>
            </div>
        <?php endif; ?>

        <?php if ($showWorld): ?>
            <section id="world-recipes" data-src="external_matches.php" aria-busy="true" aria-live="polite">
                <h2 class="mb-16" style="margin-top:28px;">More from around the world</h2>
                <p class="muted world-recipes-status">Finding recipes from around the world that use your ingredients...</p>
                <div class="recipe-grid" aria-hidden="true">
                    <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="recipe-card skeleton-card">
                        <div class="recipe-card-image skeleton-block"></div>
                        <div class="recipe-card-body">
                            <div class="skeleton-block skeleton-title"></div>
                            <div class="skeleton-block skeleton-line"></div>
                            <div class="skeleton-block skeleton-line short"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php if ($isPremiumOrAdmin): ?>
<script src="assets/js/vendor/html5-qrcode.min.js?v=18" defer></script>
<script src="assets/js/pantry-scan.js?v=23" defer></script>
<script src="assets/js/world-recipes.js?v=18" defer></script>
<?php endif; ?>
</body>
</html>

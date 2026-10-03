<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/recipe_card.php';
require_once __DIR__ . '/includes/pantry_expiry.php';

enforce_maintenance_mode();

$user = current_user();
if (!$user) {
    redirect('landing.php');
}

if (empty($user['onboarded_at'])) {
    redirect('onboarding.php');
}

$q = trim($_GET['q'] ?? '');
$mealType = $_GET['meal_type'] ?? '';
$diet = $_GET['diet'] ?? '';

$where = [];
$params = [];

// Hidden recipes (recipes.is_published - CONTINUE.md §2.19) never show in
// browse/search, same as an unpublished vendor recipe below, just for a
// different reason (no per-recipe premium/lapse logic involved - an admin
// just turned this one off).
$where[] = 'r.is_published = 1';

// Free accounts browse FREE_RECIPE_LIMIT recipes; Premium sees them all.
[$planSql, $planParams] = recipe_plan_filter($user);
if ($planSql !== '') {
    $where[] = $planSql;
    array_push($params, ...$planParams);
}
$hasFullLibrary = user_has_full_library($user);
$libraryTotal = (int)db()->query('SELECT COUNT(*) FROM recipes WHERE is_published = 1')->fetchColumn();

// A vendor recipe unpublishes from browse/search if its seller's own
// Premium lapses (selling requires Premium - profile.php's vendor toggle).
// First cut of this used users.is_premium_member directly, accepting the
// same lazy-refresh tradeoff (only re-checked when that vendor's own
// account next logs in) already used elsewhere in this app - but verified
// live that this leaves a dangling listing: still browsable and
// clickable, 404ing only once you actually open it (recipe.php/
// checkout.php both check in real time already). That's a worse result
// than just being consistent, so this mirrors user_is_currently_premium()'s
// exact rule (includes/functions_core.php) in pure SQL instead - an active
// subscription row with a future or NULL period_end publishes it; an
// active row whose period_end has passed unpublishes it regardless of
// what the flag still says; no subscription row at all (e.g. an
// admin-comped account) falls back to trusting the flag. Non-vendor
// recipes (the whole seed catalog, admin quick-adds) never reach any of
// this - is_premium = 0 short-circuits it for all of them.
$where[] = "(r.is_premium = 0
    OR EXISTS (
        SELECT 1 FROM premium_subscriptions ps
        WHERE ps.user_id = r.created_by AND ps.status = 'active'
          AND (ps.current_period_end IS NULL OR ps.current_period_end >= NOW())
    )
    OR (
        NOT EXISTS (SELECT 1 FROM premium_subscriptions ps2 WHERE ps2.user_id = r.created_by AND ps2.status = 'active')
        AND EXISTS (SELECT 1 FROM users vu WHERE vu.id = r.created_by AND vu.is_premium_member = 1)
    ))";

if ($q !== '') {
    $where[] = '(r.title LIKE ? OR EXISTS (SELECT 1 FROM recipe_ingredients ri WHERE ri.recipe_id = r.id AND ri.name LIKE ?))';
    $params[] = '%' . $q . '%';
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

$countSql = 'SELECT COUNT(*) FROM recipes r';
if ($where) {
    $countSql .= ' WHERE ' . implode(' AND ', $where);
}
$countStmt = db()->prepare($countSql);
$countStmt->execute($params);
$totalRecipes = (int)$countStmt->fetchColumn();

$perPage = 12;
$totalPages = max(1, (int)ceil($totalRecipes / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;

$sql = 'SELECT r.*,
        GROUP_CONCAT(DISTINCT dt.diet_type SEPARATOR ",") AS diet_tags,
        GROUP_CONCAT(DISTINCT al.allergen SEPARATOR ",") AS allergens,
        rt.avg_rating, rt.rating_count
        FROM recipes r
        LEFT JOIN recipe_diet_tags dt ON dt.recipe_id = r.id
        LEFT JOIN recipe_allergens al ON al.recipe_id = r.id
        LEFT JOIN (SELECT recipe_id, AVG(rating) avg_rating, COUNT(*) rating_count FROM recipe_ratings GROUP BY recipe_id) rt ON rt.recipe_id = r.id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' GROUP BY r.id ORDER BY r.title LIMIT ' . $perPage . ' OFFSET ' . $offset;

$stmt = db()->prepare($sql);
$stmt->execute($params);
$recipes = $stmt->fetchAll();

function paginate_url(int $targetPage, string $q, string $mealType, string $diet): string
{
    return 'index.php?' . http_build_query(array_filter([
        'q' => $q, 'meal_type' => $mealType, 'diet' => $diet, 'page' => $targetPage,
    ], fn($v) => $v !== '' && $v !== 1));
}

$favStmt = db()->prepare('SELECT recipe_id FROM favorites WHERE user_id = ?');
$favStmt->execute([$user['id']]);
$favoriteIds = array_flip($favStmt->fetchAll(PDO::FETCH_COLUMN));

$userAllergenStmt = db()->prepare('SELECT allergen FROM user_allergens WHERE user_id = ?');
$userAllergenStmt->execute([$user['id']]);
$userAllergens = $userAllergenStmt->fetchAll(PDO::FETCH_COLUMN);

$userDietStmt = db()->prepare('SELECT diet_type FROM user_diet_preferences WHERE user_id = ?');
$userDietStmt->execute([$user['id']]);
$userDiets = $userDietStmt->fetchAll(PDO::FETCH_COLUMN);

$dietOptions = ['vegetarian', 'vegan', 'gluten-free', 'high-protein', 'keto'];
$mealTypes = ['breakfast', 'lunch', 'dinner', 'snack'];

$todayStmt = db()->prepare(
    'SELECT r.title, r.calories, r.protein_g, r.carbs_g, r.fat_g, mpi.meal_type
     FROM meal_plan_items mpi JOIN recipes r ON r.id = mpi.recipe_id
     WHERE mpi.user_id = ? AND mpi.plan_date = CURDATE()
     ORDER BY FIELD(mpi.meal_type, "breakfast", "lunch", "dinner", "snack")'
);
$todayStmt->execute([$user['id']]);
$todayMeals = $todayStmt->fetchAll();
$todayTotals = [
    'calories' => array_sum(array_column($todayMeals, 'calories')),
    'protein_g' => array_sum(array_column($todayMeals, 'protein_g')),
    'carbs_g' => array_sum(array_column($todayMeals, 'carbs_g')),
    'fat_g' => array_sum(array_column($todayMeals, 'fat_g')),
];

$goalStmt = db()->prepare('SELECT * FROM user_goals WHERE user_id = ?');
$goalStmt->execute([$user['id']]);
$goals = $goalStmt->fetch() ?: [];

function render_goal_progress(string $label, int $value, ?int $goal): string
{
    if (!$goal) return '';
    $pct = min(100, round(($value / $goal) * 100));
    $over = $value > $goal;
    return '<div class="goal-progress">
        <div class="goal-progress-label"><span>' . h($label) . '</span><span>' . $value . ' / ' . $goal . '</span></div>
        <div class="goal-progress-bar"><div class="goal-progress-fill' . ($over ? ' is-over' : '') . '" style="width:' . $pct . '%"></div></div>
    </div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recipes · <?= APP_NAME ?></title>
<meta name="description" content="Browse <?= APP_NAME ?>'s recipe library filtered by diet and allergy needs, or head to your pantry for AI-generated meal ideas from what you already have.">
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=15">
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

    <?= render_pantry_expiry_banner(pantry_expiry_alerts(pantry_expiry_map((int)$user['id'])), true) ?>

    <?php if ($todayMeals): ?>
        <div class="card mb-16 today-widget">
            <div class="today-widget-head">
                <strong>Today's plan</strong>
                <span class="muted"><?= icon('flame', 14) ?> <?= (int)$todayTotals['calories'] ?> cal</span>
            </div>
            <div class="tag-row mb-16">
                <?php foreach ($todayMeals as $m): ?>
                    <span class="tag"><?= h(ucfirst($m['meal_type'])) ?>: <?= h($m['title']) ?></span>
                <?php endforeach; ?>
            </div>
            <?php if ($goals): ?>
                <?= render_goal_progress('Calories', $todayTotals['calories'], $goals['daily_calories'] ?? null) ?>
                <?= render_goal_progress('Protein (g)', $todayTotals['protein_g'], $goals['daily_protein_g'] ?? null) ?>
                <?= render_goal_progress('Carbs (g)', $todayTotals['carbs_g'], $goals['daily_carbs_g'] ?? null) ?>
                <?= render_goal_progress('Fat (g)', $todayTotals['fat_g'], $goals['daily_fat_g'] ?? null) ?>
            <?php else: ?>
                <p class="muted" style="font-size:12.5px;">Set daily goals in your <a href="profile.php">profile</a> to see progress bars here.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($userDiets && $q === '' && $mealType === '' && $diet === ''): ?>
        <div class="tag-row mb-16">
            <span class="muted" style="font-size:13px;">Recommended for you:</span>
            <?php foreach ($userDiets as $d): ?>
                <a class="tag" href="index.php?diet=<?= urlencode($d) ?>"><?= h(ucfirst($d)) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="get" class="filter-bar">
        <div class="search-field">
            <?= icon('search', 16) ?>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search by title or ingredient..." aria-label="Search recipes">
        </div>
        <select name="meal_type" onchange="this.form.submit()" aria-label="Filter by meal type">
            <option value="">All meals</option>
            <?php foreach ($mealTypes as $mt): ?>
                <option value="<?= h($mt) ?>" <?= $mealType === $mt ? 'selected' : '' ?>><?= ucfirst($mt) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="diet" onchange="this.form.submit()" aria-label="Filter by diet">
            <option value="">Any diet</option>
            <?php foreach ($dietOptions as $d): ?>
                <option value="<?= h($d) ?>" <?= $diet === $d ? 'selected' : '' ?>><?= ucfirst($d) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>

    <?php if (!$hasFullLibrary && $libraryTotal > FREE_RECIPE_LIMIT): ?>
        <div class="plan-limit-banner">
            <?= icon('wand', 18) ?>
            <p>You're on the free plan: <strong><?= count(free_recipe_ids()) ?> of <?= $libraryTotal ?> recipes</strong>.
                Premium unlocks every recipe, plus extra matches from around the world in What Can I Make?</p>
            <a href="premium.php" class="btn btn-primary btn-small">Go Premium</a>
        </div>
    <?php endif; ?>

    <?php if (!$recipes): ?>
        <p class="muted center-text mt-16">No recipes match your filters.</p>
    <?php else: ?>
        <div class="recipe-grid">
            <?php foreach ($recipes as $recipe): ?>
                <?php
                $recipeAllergens = array_filter(explode(',', $recipe['allergens'] ?? ''));
                $conflicts = array_intersect($recipeAllergens, $userAllergens);
                render_recipe_card($recipe, isset($favoriteIds[$recipe['id']]), $conflicts);
                ?>
            <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?= h(paginate_url($page - 1, $q, $mealType, $diet)) ?>" aria-label="Previous page"><?= icon('chevron-left', 14) ?></a>
                <?php endif; ?>
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <?php if ($p === $page): ?>
                        <span class="is-current"><?= $p ?></span>
                    <?php else: ?>
                        <a href="<?= h(paginate_url($p, $q, $mealType, $diet)) ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= h(paginate_url($page + 1, $q, $mealType, $diet)) ?>" aria-label="Next page"><?= icon('chevron-right', 14) ?></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>

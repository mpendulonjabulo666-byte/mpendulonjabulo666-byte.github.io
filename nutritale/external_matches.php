<?php
// "More from around the world" for What Can I Make? (Premium). Returned as
// an HTML fragment that assets/js/world-recipes.js drops into pantry.php
// after the page has rendered, so the first (uncached) TheMealDB lookup
// never holds up the pantry page itself.
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/allergens.php';
require_once __DIR__ . '/includes/ingredient_matching.php';
require_once __DIR__ . '/includes/external_recipes.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$user = current_user();
if (!$user || !user_has_full_library($user)) {
    http_response_code(403);
    exit;
}

$pantryStmt = db()->prepare('SELECT ingredient_name FROM user_pantry_items WHERE user_id = ? ORDER BY ingredient_name');
$pantryStmt->execute([$user['id']]);
$pantry = $pantryStmt->fetchAll(PDO::FETCH_COLUMN);

$dietStmt = db()->prepare('SELECT diet_type FROM user_diet_preferences WHERE user_id = ?');
$dietStmt->execute([$user['id']]);
$dietPrefs = $dietStmt->fetchAll(PDO::FETCH_COLUMN);

$aliasMap = load_ingredient_alias_map();
$pantryCanonical = [];
foreach ($pantry as $item) {
    $pantryCanonical = array_merge($pantryCanonical, canonical_ingredient_set($item, $aliasMap));
}
$pantryCanonical = array_values(array_unique($pantryCanonical));

$external = external_pantry_recipes($pantry, $pantryCanonical, $aliasMap, user_allergens((int)$user['id']), $dietPrefs, EXTERNAL_RECIPE_LIMIT);
?>
<h2 class="mb-16" style="margin-top:28px;">More from around the world</h2>
<?php if ($external['error']): ?>
    <p class="muted"><?= h($external['error']) ?></p>
<?php elseif (!$external['recipes']): ?>
    <p class="muted">No worldwide recipes match your pantry yet. Try adding a main ingredient like chicken, beef, rice or eggs.</p>
<?php else: ?>
    <p class="muted" style="font-size:12.5px;margin-top:-8px;">Up to <?= EXTERNAL_RECIPE_LIMIT ?> extra ideas from <a href="https://www.themealdb.com" target="_blank" rel="noopener">TheMealDB</a>, a Premium extra.
        <?php if ($external['hidden_by_allergens']): ?><?= (int)$external['hidden_by_allergens'] ?> hidden for your allergies.<?php endif; ?></p>
    <div class="recipe-grid">
        <?php foreach ($external['recipes'] as $x): ?>
            <div class="recipe-card">
                <div class="recipe-card-image" style="background-image:url('<?= h($x['image']) ?>')">
                    <span class="recipe-card-credit">Photo: TheMealDB</span>
                    <span class="pantry-match-badge <?= $x['have'] >= $x['total'] ? 'is-full' : '' ?>"><?= $x['have'] ?>/<?= $x['total'] ?> ingredients</span>
                    <span class="tag" style="position:absolute;top:8px;right:8px;">Worldwide</span>
                </div>
                <div class="recipe-card-body">
                    <h3><a class="recipe-card-link" href="external_recipe.php?id=<?= urlencode($x['id']) ?>"><?= h($x['title']) ?></a></h3>
                    <p class="muted recipe-card-desc"><?= h(trim($x['area'] . ' ' . strtolower($x['category']))) ?></p>
                    <?php if ($x['missing']): ?>
                        <p class="muted" style="font-size:11.5px;margin:6px 0 0;">Missing: <?= h(implode(', ', array_slice($x['missing'], 0, 6))) ?><?= count($x['missing']) > 6 ? '...' : '' ?></p>
                    <?php else: ?>
                        <p style="font-size:11.5px;margin:6px 0 0;color:var(--green-dark);font-weight:600;">You have everything!</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-16"><?= disclaimer('allergens') ?></div>
<?php endif; ?>

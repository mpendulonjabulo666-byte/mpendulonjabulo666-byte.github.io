<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/recipe_card.php';
require_once __DIR__ . '/includes/ingredient_matching.php';
require_once __DIR__ . '/includes/store_links.php';
require_once __DIR__ . '/includes/hormone_foods.php';

$user = require_login();

$focus = hormone_focus_key($_GET['focus'] ?? '');
$group = hormone_group_key($_GET['group'] ?? '', $focus);
$have = hormone_have_list((string)($_GET['have'] ?? ''));
$focuses = hormone_focuses();
$groups = hormone_food_groups();
$active = $focuses[$focus];
$shortLabels = hormone_focus_short_labels();
$unlocked = user_has_full_library($user);

$hour = (int)(new DateTime('now', new DateTimeZone('Africa/Johannesburg')))->format('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$firstName = trim(explode(' ', trim((string)($user['name'] ?? '')))[0] ?? '');

function hg_url(array $over = []): string
{
    global $focus, $group, $have;
    $q = array_merge(['focus' => $focus, 'group' => $group, 'have' => implode(', ', $have)], $over);
    return 'hormone_guide.php?' . http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== null));
}

// Built-in, published recipes this plan can open. Matching is on ingredient
// names only; nothing is stored about the person (no gender field).
[$planSql, $planParams] = recipe_plan_filter($user, 'r');
$stmt = db()->prepare(
    'SELECT r.*,
        GROUP_CONCAT(DISTINCT dt.diet_type SEPARATOR ",") AS diet_tags,
        GROUP_CONCAT(DISTINCT al.allergen SEPARATOR ",") AS allergens
     FROM recipes r
     LEFT JOIN recipe_diet_tags dt ON dt.recipe_id = r.id
     LEFT JOIN recipe_allergens al ON al.recipe_id = r.id
     WHERE r.is_published = 1 AND r.is_generated = 0' . ($planSql ? ' AND ' . $planSql : '') . '
     GROUP BY r.id'
);
$stmt->execute($planParams);
$recipes = $stmt->fetchAll();

$ingredientsByRecipe = [];
foreach (db()->query('SELECT recipe_id, name FROM recipe_ingredients')->fetchAll() as $row) {
    $ingredientsByRecipe[$row['recipe_id']][] = $row['name'];
}

$aliasMap = $have ? load_ingredient_alias_map() : [];
$haveSet = [];
foreach ($have as $h) {
    $haveSet = array_merge($haveSet, canonical_ingredient_set($h, $aliasMap));
}
$haveSet = array_values(array_unique($haveSet));

$scored = [];
foreach ($recipes as $recipe) {
    $names = $ingredientsByRecipe[$recipe['id']] ?? [];
    $hit = hormone_groups_matched($names, $focus);
    if ($group !== '' ? !in_array($group, $hit, true) : count($hit) < 2) {
        continue;
    }
    $missing = [];
    $haveCount = 0;
    foreach ($names as $n) {
        if ($haveSet && pantry_has_ingredient($n, $haveSet, $aliasMap)) {
            $haveCount++;
        } else {
            $missing[] = $n;
        }
    }
    if ($have && $haveCount === 0) {
        continue;
    }
    $scored[] = ['recipe' => $recipe, 'hit' => $hit, 'have' => $haveCount, 'missing' => $missing, 'total' => count($names)];
}
if ($have) {
    usort($scored, fn($a, $b) => count($a['missing']) <=> count($b['missing']) ?: count($b['hit']) <=> count($a['hit']) ?: strcmp($a['recipe']['title'], $b['recipe']['title']));
} else {
    usort($scored, fn($a, $b) => count($b['hit']) <=> count($a['hit']) ?: strcmp($a['recipe']['title'], $b['recipe']['title']));
}
$scored = array_slice($scored, 0, 12);

$pickup = hormone_pickup_foods($focus, $group, $have);

$favStmt = db()->prepare('SELECT recipe_id FROM favorites WHERE user_id = ?');
$favStmt->execute([$user['id']]);
$favoriteIds = array_flip($favStmt->fetchAll(PDO::FETCH_COLUMN));
$userAllergenStmt = db()->prepare('SELECT allergen FROM user_allergens WHERE user_id = ?');
$userAllergenStmt->execute([$user['id']]);
$userAllergens = $userAllergenStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hormone-supportive eating · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#2fae66">
<script src="assets/js/theme-init.js?v=24"></script>
<link rel="stylesheet" href="assets/css/style.css?v=42">
<script src="assets/js/theme-toggle.js?v=24" defer></script>
<style>
    .hg-hero { border-radius: 28px; padding: 24px 20px 20px; margin-bottom: 20px;
        background: radial-gradient(circle at 70% 0%, #ead7ff 0%, #f6eefe 45%, #fdfaff 100%); color: #2a2233; }
    [data-theme="dark"] .hg-hero { background: radial-gradient(circle at 70% 0%, #3b2a55 0%, #241c33 55%, #191522 100%); color: #f0e9fa; }
    .hg-hello { font-size: 14px; opacity: .75; margin: 0 0 6px; }
    .hg-title { font-size: clamp(28px, 7vw, 40px); line-height: 1.1; margin: 0 0 18px; font-weight: 500; }
    .hg-options { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px; }
    .hg-opt { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 14px 10px; border-radius: 999px;
        background: rgba(255,255,255,.85); color: inherit; text-decoration: none; font-weight: 600; border: 2px solid transparent; }
    [data-theme="dark"] .hg-opt { background: rgba(255,255,255,.1); }
    .hg-opt.is-active { border-color: #b86fe0; background: #fff; color: #2a2233; }
    [data-theme="dark"] .hg-opt.is-active { background: #b86fe0; color: #fff; }
    .hg-other { display: inline-block; font-size: 13px; padding: 6px 14px; border-radius: 999px; text-decoration: none; color: inherit;
        background: rgba(255,255,255,.55); border: 1px solid transparent; }
    [data-theme="dark"] .hg-other { background: rgba(255,255,255,.08); }
    .hg-other.is-active { border-color: #b86fe0; font-weight: 700; }
    .hg-search { display: flex; gap: 8px; margin: 16px 0 0; }
    .hg-search input[type=search] { flex: 1; min-width: 0; padding: 13px 16px; border-radius: 999px; border: 1px solid rgba(0,0,0,.12);
        font-size: 16px; background: rgba(255,255,255,.9); color: #2a2233; }
    .hg-search button { padding: 0 20px; border-radius: 999px; border: 0; background: #b86fe0; color: #fff; font-weight: 700; cursor: pointer; }
    .hg-row { display: flex; justify-content: space-between; align-items: center; margin: 22px 0 10px; }
    .hg-row h2 { margin: 0; font-size: 18px; }
    .hg-see { font-size: 13px; padding: 5px 14px; border-radius: 999px; text-decoration: none; color: inherit; background: rgba(184,111,224,.14); }
    .hg-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    @media (min-width: 700px) { .hg-grid { grid-template-columns: repeat(3, 1fr); } }
    .hg-card { display: flex; flex-direction: column; border-radius: 22px; padding: 16px 12px 12px; text-align: center; background: var(--white, #fff);
        border: 2px solid transparent; box-shadow: 0 2px 10px rgba(80,40,110,.07); }
    .hg-card.is-active { border-color: #b86fe0; }
    .hg-card h3 { margin: 8px 0 4px; font-size: 15px; }
    .hg-card p { margin: 0 0 10px; font-size: 12px; opacity: .75; flex: 1; }
    .hg-ico { width: 54px; height: 54px; margin: 0 auto; border-radius: 50%; display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, #f3c9ff, #c9a7ff); color: #4a2b6b; }
    .hg-view { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 8px 12px; border-radius: 999px;
        background: rgba(184,111,224,.14); color: inherit; text-decoration: none; font-weight: 600; font-size: 14px; }
    .hg-play { width: 26px; height: 26px; border-radius: 50%; background: #d98be8; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; }
    .hg-pick { display: flex; flex-wrap: wrap; gap: 8px; }
    .hg-pick a { padding: 7px 14px; border-radius: 999px; background: rgba(184,111,224,.14); color: inherit; text-decoration: none; font-size: 14px; }
    .hg-pick span.hg-chip { padding: 7px 14px; border-radius: 999px; background: rgba(184,111,224,.08); font-size: 14px; }
    .hg-need { font-size: 12px; margin: 6px 4px 0; }
    .hg-note { font-size: 12px; opacity: .8; margin-top: 24px; }
</style>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <section class="hg-hero">
        <p class="hg-hello"><?= h($greeting . ($firstName !== '' ? ', ' . $firstName : '')) ?></p>
        <h1 class="hg-title">Eat to support your hormones, stay in control</h1>

        <div class="hg-options" role="tablist" aria-label="Choose a focus">
            <a class="hg-opt <?= $focus === 'men' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $focus === 'men' ? 'true' : 'false' ?>"
               href="<?= h(hg_url(['focus' => 'men', 'group' => ''])) ?>"><?= icon('flame', 18) ?> Men</a>
            <a class="hg-opt <?= $focus === 'women' ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $focus === 'women' ? 'true' : 'false' ?>"
               href="<?= h(hg_url(['focus' => 'women', 'group' => ''])) ?>"><?= icon('heart', 18) ?> Women</a>
        </div>
        <a class="hg-other <?= $focus === 'both' ? 'is-active' : '' ?>" href="<?= h(hg_url(['focus' => 'both', 'group' => ''])) ?>"
           aria-current="<?= $focus === 'both' ? 'true' : 'false' ?>">Neither of these? Show everyone's guide</a>

        <form class="hg-search" method="get" action="hormone_guide.php" role="search">
            <input type="hidden" name="focus" value="<?= h($focus) ?>">
            <?php if ($group !== ''): ?><input type="hidden" name="group" value="<?= h($group) ?>"><?php endif; ?>
            <input type="search" name="have" value="<?= h(implode(', ', $have)) ?>" maxlength="200"
                   placeholder="What ingredients do you have? e.g. chicken, spinach" aria-label="Ingredients you have">
            <button type="submit">Find</button>
        </form>
    </section>

    <div class="alert" role="note" style="margin-bottom:16px;">
        <strong>Please note.</strong> <?= h(hormone_doctor_note()) ?>
    </div>

    <p class="muted"><?= h($active['intro']) ?></p>

    <div class="hg-row">
        <h2>Food groups</h2>
        <?php if ($group !== ''): ?><a class="hg-see" href="<?= h(hg_url(['group' => ''])) ?>">See all</a><?php endif; ?>
    </div>
    <div class="hg-grid">
        <?php foreach ($active['groups'] as $g): ?>
            <div class="hg-card <?= $group === $g ? 'is-active' : '' ?>">
                <div class="hg-ico"><?= icon('leaf', 26) ?></div>
                <h3><?= h($groups[$g]['label']) ?></h3>
                <p><?= h($groups[$g]['why']) ?></p>
                <a class="hg-view" href="<?= h(hg_url(['group' => $g])) ?>"><span class="hg-play">▶</span> View</a>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="hg-row">
        <h2><?= $have ? 'Recipes you can make' : 'Recipes that fit' ?><?= $group !== '' ? ' · ' . h($groups[$group]['label']) : '' ?></h2>
    </div>
    <?php if ($have): ?>
        <p class="muted" style="margin-top:0;">Using: <?= h(implode(', ', $have)) ?>. <a href="<?= h(hg_url(['have' => ''])) ?>">Clear</a></p>
    <?php endif; ?>

    <?php if (!$scored): ?>
        <p class="muted"><?= $have ? 'None of the recipes in this guide use those ingredients yet. See what to pick up below.' : 'No recipes fit these groups yet.' ?></p>
    <?php else: ?>
        <?php if ($have && !$unlocked): ?>
            <?= store_links_block('', false) ?>
        <?php endif; ?>
        <div class="recipe-grid">
            <?php foreach ($scored as $s): ?>
                <?php
                $recipe = $s['recipe'];
                $conflicts = array_intersect(array_filter(explode(',', $recipe['allergens'] ?? '')), $userAllergens);
                ?>
                <div>
                    <?php render_recipe_card($recipe, isset($favoriteIds[$recipe['id']]), $conflicts); ?>
                    <p class="muted" style="font-size:12px;margin:6px 4px 0;">Covers: <?= h(implode(', ', array_map(fn($g) => $groups[$g]['label'], $s['hit']))) ?></p>
                    <?php if ($have): ?>
                        <p class="hg-need"><strong>You have <?= (int)$s['have'] ?> of <?= (int)$s['total'] ?>.</strong>
                            <?php if ($s['missing']): ?>
                                Still need:
                                <?php foreach (array_slice($s['missing'], 0, 6) as $m): ?>
                                    <?= h($m) ?><?= $unlocked ? ' ' . pnp_link_html($m) : '' ?><?= $m === end($s['missing']) ? '' : ', ' ?>
                                <?php endforeach; ?>
                                <?= count($s['missing']) > 6 ? '…' : '' ?>
                            <?php else: ?>
                                You have everything.
                            <?php endif; ?>
                            <?php if ($unlocked && dish_on_uber_eats($recipe['title'])): ?>
                                <a class="store-link" href="<?= h(uber_eats_search_link($recipe['title'])) ?>" target="_blank" rel="noopener noreferrer">Order on Uber Eats</a>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($pickup): ?>
        <div class="hg-row"><h2>Foods to pick up</h2></div>
        <p class="muted" style="margin-top:0;">From this guide<?= $have ? ', leaving out what you already have' : '' ?>.
            <?= $unlocked ? 'Tap one to find it at Pick n Pay.' : '' ?></p>
        <?php if (!$unlocked): ?><?= store_links_block('', false) ?><?php endif; ?>
        <div class="hg-pick">
            <?php foreach ($pickup as $food): ?>
                <?php if ($unlocked): ?>
                    <a href="<?= h(pnp_search_link($food)) ?>" target="_blank" rel="noopener noreferrer"><?= h(ucfirst($food)) ?></a>
                <?php else: ?>
                    <span class="hg-chip"><?= h(ucfirst($food)) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php if ($unlocked): ?>
            <p style="margin-top:12px;"><a class="btn btn-small" href="https://www.ubereats.com/za/near-me" target="_blank" rel="noopener noreferrer">Browse Uber Eats</a>
                <span class="muted" style="font-size:12px;">Links open in a new tab; prices and stock are theirs.</span></p>
        <?php endif; ?>
    <?php endif; ?>

    <p class="hg-note">Sleep, movement, stress and a healthy weight shape your hormones as much as food does. <?= h(hormone_doctor_note()) ?></p>
</main>
</body>
</html>

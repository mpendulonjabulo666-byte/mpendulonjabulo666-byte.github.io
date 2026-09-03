<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

const MEAL_TYPES = ['breakfast', 'lunch', 'dinner', 'snack'];
const DAY_NAMES = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

function week_start(string $anchor): DateTime
{
    $dt = new DateTime($anchor);
    $dt->modify('monday this week');
    return $dt;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash_set('error', 'Your session expired. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $recipeId = $_POST['recipe_id'] ?? '';
            $date = $_POST['plan_date'] ?? '';
            $mealType = $_POST['meal_type'] ?? '';
            if ($recipeId !== '' && in_array($mealType, MEAL_TYPES, true) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $exists = db()->prepare('SELECT 1 FROM recipes WHERE id = ?');
                $exists->execute([$recipeId]);
                if ($exists->fetch()) {
                    $ins = db()->prepare('INSERT INTO meal_plan_items (user_id, plan_date, meal_type, recipe_id) VALUES (?, ?, ?, ?)');
                    $ins->execute([$user['id'], $date, $mealType, $recipeId]);
                }
            }
        } elseif ($action === 'remove') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $del = db()->prepare('DELETE FROM meal_plan_items WHERE id = ? AND user_id = ?');
            $del->execute([$itemId, $user['id']]);
        }
    }
    redirect('planner.php?week=' . urlencode($_POST['week_anchor'] ?? date('Y-m-d')));
}

$weekAnchor = $_GET['week'] ?? date('Y-m-d');
try {
    $monday = week_start($weekAnchor);
} catch (Exception $e) {
    $monday = week_start('now');
}
$sunday = (clone $monday)->modify('+6 days');

$prevWeek = (clone $monday)->modify('-7 days')->format('Y-m-d');
$nextWeek = (clone $monday)->modify('+7 days')->format('Y-m-d');

$stmt = db()->prepare(
    'SELECT mpi.*, r.title, r.calories, r.cook_time_minutes
     FROM meal_plan_items mpi
     JOIN recipes r ON r.id = mpi.recipe_id
     WHERE mpi.user_id = ? AND mpi.plan_date BETWEEN ? AND ?
     ORDER BY mpi.plan_date, FIELD(mpi.meal_type, "breakfast", "lunch", "dinner", "snack")'
);
$stmt->execute([$user['id'], $monday->format('Y-m-d'), $sunday->format('Y-m-d')]);

$plan = [];
$dayTotals = [];
foreach ($stmt->fetchAll() as $row) {
    $plan[$row['plan_date']][$row['meal_type']][] = $row;
    $dayTotals[$row['plan_date']] = ($dayTotals[$row['plan_date']] ?? 0) + (int)$row['calories'];
}

$recipes = db()->query('SELECT id, title FROM recipes ORDER BY title')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Meal Planner · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <?php if ($error = flash_get('error')): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="planner-header">
        <h1>Meal Planner</h1>
        <div class="week-nav">
            <a class="btn btn-text btn-small" href="planner.php?week=<?= h($prevWeek) ?>"><?= icon('chevron-left', 16) ?> Prev</a>
            <span class="muted"><?= $monday->format('M j') ?> – <?= $sunday->format('M j, Y') ?></span>
            <a class="btn btn-text btn-small" href="planner.php?week=<?= h($nextWeek) ?>">Next</a>
        </div>
        <a class="btn btn-text btn-small" href="planner_export.php?week=<?= h($monday->format('Y-m-d')) ?>"><?= icon('download', 16) ?> Export CSV</a>
        <a class="btn btn-primary btn-small" href="shopping_list.php?week=<?= h($monday->format('Y-m-d')) ?>">Shopping list for this week</a>
    </div>

    <div class="planner-grid">
        <?php
        $cursor = clone $monday;
        for ($d = 0; $d < 7; $d++):
            $dateKey = $cursor->format('Y-m-d');
        ?>
            <div class="planner-day">
                <div class="planner-day-head">
                    <strong><?= DAY_NAMES[$d] ?></strong> <span class="muted"><?= $cursor->format('M j') ?></span>
                    <?php if (!empty($dayTotals[$dateKey])): ?>
                        <span class="muted planner-day-cal"><?= icon('flame', 12) ?> <?= (int)$dayTotals[$dateKey] ?> cal</span>
                    <?php endif; ?>
                </div>
                <?php foreach (MEAL_TYPES as $mealType): ?>
                    <div class="planner-slot">
                        <div class="planner-slot-label"><?= ucfirst($mealType) ?></div>
                        <?php foreach ($plan[$dateKey][$mealType] ?? [] as $item): ?>
                            <div class="planner-chip">
                                <a href="recipe.php?id=<?= urlencode($item['recipe_id']) ?>"><?= h($item['title']) ?></a>
                                <form method="post" class="planner-remove">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                    <input type="hidden" name="week_anchor" value="<?= h($monday->format('Y-m-d')) ?>">
                                    <button type="submit" aria-label="Remove"><?= icon('x', 12) ?></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <form method="post" class="planner-add">
                            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="plan_date" value="<?= h($dateKey) ?>">
                            <input type="hidden" name="meal_type" value="<?= h($mealType) ?>">
                            <input type="hidden" name="week_anchor" value="<?= h($monday->format('Y-m-d')) ?>">
                            <select name="recipe_id" onchange="this.form.submit()">
                                <option value="">+ Add recipe</option>
                                <?php foreach ($recipes as $r): ?>
                                    <option value="<?= h($r['id']) ?>"><?= h($r['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php
            $cursor->modify('+1 day');
        endfor;
        ?>
    </div>
</main>
</body>
</html>

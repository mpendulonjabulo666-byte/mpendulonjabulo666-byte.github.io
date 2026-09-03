<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_check()) {
        $itemKey = $_POST['item_key'] ?? '';
        $checked = ($_POST['checked'] ?? '0') === '1' ? 1 : 0;
        if ($itemKey !== '') {
            $stmt = db()->prepare(
                'INSERT INTO shopping_list_checks (user_id, item_key, checked) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE checked = VALUES(checked)'
            );
            $stmt->execute([$user['id'], $itemKey, $checked]);
        }
    }
    redirect('shopping_list.php?week=' . urlencode($_POST['week_anchor'] ?? date('Y-m-d')));
}

$weekAnchor = $_GET['week'] ?? date('Y-m-d');
try {
    $monday = new DateTime($weekAnchor);
    $monday->modify('monday this week');
} catch (Exception $e) {
    $monday = new DateTime('monday this week');
}
$sunday = (clone $monday)->modify('+6 days');

$stmt = db()->prepare(
    'SELECT ri.name, ri.unit, ri.display_quantity, ri.quantity, ri.category
     FROM meal_plan_items mpi
     JOIN recipe_ingredients ri ON ri.recipe_id = mpi.recipe_id
     WHERE mpi.user_id = ? AND mpi.plan_date BETWEEN ? AND ?'
);
$stmt->execute([$user['id'], $monday->format('Y-m-d'), $sunday->format('Y-m-d')]);

$items = [];
foreach ($stmt->fetchAll() as $row) {
    $key = strtolower(trim($row['name'])) . '|' . strtolower(trim($row['unit']));
    if (!isset($items[$key])) {
        $items[$key] = [
            'name' => $row['name'],
            'unit' => $row['unit'],
            'category' => $row['category'] ?: 'other',
            'quantity' => 0,
            'display_quantities' => [],
        ];
    }
    $items[$key]['quantity'] += (float)$row['quantity'];
    $items[$key]['display_quantities'][] = $row['display_quantity'];
}

$checkedStmt = db()->prepare('SELECT item_key, checked FROM shopping_list_checks WHERE user_id = ?');
$checkedStmt->execute([$user['id']]);
$checkedMap = [];
foreach ($checkedStmt->fetchAll() as $row) {
    $checkedMap[$row['item_key']] = (bool)$row['checked'];
}

$grouped = [];
foreach ($items as $key => $item) {
    $grouped[$item['category']][$key] = $item;
}
ksort($grouped);

function format_qty(float $qty): string
{
    return rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Shopping List · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div class="mb-16" style="display:flex;justify-content:space-between;align-items:center;">
        <a href="planner.php?week=<?= h($monday->format('Y-m-d')) ?>" class="btn btn-text btn-small"><?= icon('chevron-left', 16) ?> Back to planner</a>
        <div style="display:flex;gap:8px;">
            <a class="btn btn-text btn-small" href="shopping_list_export.php?week=<?= h($monday->format('Y-m-d')) ?>"><?= icon('download', 16) ?> Export CSV</a>
            <button type="button" class="btn btn-text btn-small" onclick="window.print()"><?= icon('printer', 16) ?> Print</button>
        </div>
    </div>
    <h1 class="mb-16"><?= icon('shopping-cart', 20) ?> Shopping list — <?= $monday->format('M j') ?> to <?= $sunday->format('M j, Y') ?></h1>

    <?php if (!$items): ?>
        <p class="muted">No recipes planned for this week yet. Add some from the <a href="planner.php?week=<?= h($monday->format('Y-m-d')) ?>">planner</a>.</p>
    <?php else: ?>
        <div class="shopping-list card">
            <?php foreach ($grouped as $category => $categoryItems): ?>
                <div class="shopping-category">
                    <h3><?= h(ucfirst($category)) ?></h3>
                    <?php foreach ($categoryItems as $key => $item): ?>
                        <?php $isChecked = $checkedMap[$key] ?? false; ?>
                        <div class="shopping-item <?= $isChecked ? 'is-checked' : '' ?>">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                <input type="hidden" name="item_key" value="<?= h($key) ?>">
                                <input type="hidden" name="checked" value="<?= $isChecked ? '0' : '1' ?>">
                                <input type="hidden" name="week_anchor" value="<?= h($monday->format('Y-m-d')) ?>">
                                <input type="checkbox" onchange="this.form.submit()" <?= $isChecked ? 'checked' : '' ?>>
                            </form>
                            <span>
                                <?php if ($item['unit'] !== ''): ?>
                                    <?= h(format_qty($item['quantity'])) ?> <?= h($item['unit']) ?> <?= h($item['name']) ?>
                                <?php else: ?>
                                    <?= h(implode(' + ', array_unique($item['display_quantities']))) ?> <?= h($item['name']) ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

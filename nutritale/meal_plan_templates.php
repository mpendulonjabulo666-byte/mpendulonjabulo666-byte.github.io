<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

const ADOPT_MEAL_TYPES = ['breakfast', 'lunch', 'dinner', 'snack'];
const ADOPT_DAY_NAMES = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'adopt' && csrf_check()) {
    $templateId = (int)($_POST['template_id'] ?? 0);
    $startDate = $_POST['start_date'] ?? '';

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
        $errors[] = 'Choose a valid start date.';
    } else {
        $itemsStmt = db()->prepare('SELECT * FROM meal_plan_template_items WHERE template_id = ?');
        $itemsStmt->execute([$templateId]);
        $items = $itemsStmt->fetchAll();

        if (!$items) {
            $errors[] = 'That plan has no recipes to add.';
        } else {
            $ins = db()->prepare('INSERT INTO meal_plan_items (user_id, plan_date, meal_type, recipe_id) VALUES (?, ?, ?, ?)');
            $start = new DateTime($startDate);
            foreach ($items as $item) {
                $date = (clone $start)->modify('+' . (int)$item['day_offset'] . ' days')->format('Y-m-d');
                $ins->execute([$user['id'], $date, $item['meal_type'], $item['recipe_id']]);
            }
            flash_set('success', 'Plan added to your planner starting ' . (new DateTime($startDate))->format('j F Y') . '.');
            redirect('planner.php?week=' . urlencode($startDate));
        }
    }
}

$templates = db()->query(
    'SELECT t.*, COUNT(i.id) AS item_count
     FROM meal_plan_templates t
     LEFT JOIN meal_plan_template_items i ON i.template_id = t.id
     GROUP BY t.id
     ORDER BY t.created_at DESC'
)->fetchAll();

$itemsByTemplate = [];
if ($templates) {
    $ids = array_column($templates, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT i.*, r.title FROM meal_plan_template_items i JOIN recipes r ON r.id = i.recipe_id
         WHERE i.template_id IN ($placeholders) ORDER BY i.day_offset, FIELD(i.meal_type, 'breakfast', 'lunch', 'dinner', 'snack')"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $itemsByTemplate[$row['template_id']][$row['day_offset']][$row['meal_type']] = $row['title'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Meal Plan Templates · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=9">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <a href="planner.php" class="btn btn-text btn-small mb-16"><?= icon('chevron-left', 16) ?> Back to planner</a>
    <h1 class="mb-16"><?= icon('calendar', 20) ?> Meal plan templates</h1>
    <p class="muted" style="margin-top:-8px;">Ready-made weekly plans — adopt one to fill your planner starting from any date.</p>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <?php if (!$templates): ?>
        <p class="muted">No plan templates have been published yet — check back soon.</p>
    <?php else: ?>
        <?php foreach ($templates as $t): ?>
            <details class="card mb-16">
                <summary style="cursor:pointer;font-weight:700;font-size:16px;">
                    <?= h($t['title']) ?> <span class="muted" style="font-weight:400;font-size:13px;">(<?= (int)$t['item_count'] ?> meals)</span>
                </summary>
                <?php if ($t['description']): ?><p class="muted" style="font-size:13px;"><?= h($t['description']) ?></p><?php endif; ?>

                <div style="overflow-x:auto;">
                    <table class="admin-table">
                        <thead><tr><th>Day</th><?php foreach (ADOPT_MEAL_TYPES as $mt): ?><th><?= ucfirst($mt) ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                            <?php for ($d = 0; $d < 7; $d++): ?>
                                <tr>
                                    <td><strong><?= ADOPT_DAY_NAMES[$d] ?></strong></td>
                                    <?php foreach (ADOPT_MEAL_TYPES as $mt): ?>
                                        <td><?= h($itemsByTemplate[$t['id']][$d][$mt] ?? '—') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>

                <form method="post" class="mt-16" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="adopt">
                    <input type="hidden" name="template_id" value="<?= (int)$t['id'] ?>">
                    <label class="field" style="margin:0;">
                        <span>Start this plan on</span>
                        <input type="date" name="start_date" value="<?= h(date('Y-m-d')) ?>" required>
                    </label>
                    <button type="submit" class="btn btn-primary">Add to my planner</button>
                </form>
            </details>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>

<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_admin();

const TEMPLATE_MEAL_TYPES = ['breakfast', 'lunch', 'dinner', 'snack'];
const TEMPLATE_DAY_NAMES = ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $items = $_POST['items'] ?? [];

        if ($title === '') $errors[] = 'Title is required.';

        $validItems = [];
        if (is_array($items)) {
            foreach ($items as $dayOffset => $meals) {
                if (!is_array($meals) || $dayOffset < 0 || $dayOffset > 6) continue;
                foreach ($meals as $mealType => $recipeId) {
                    $recipeId = trim((string)$recipeId);
                    if ($recipeId !== '' && in_array($mealType, TEMPLATE_MEAL_TYPES, true)) {
                        $validItems[] = [(int)$dayOffset, $mealType, $recipeId];
                    }
                }
            }
        }
        if (!$validItems) $errors[] = 'Add at least one recipe to the plan.';

        if (!$errors) {
            $pdo = db();
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO meal_plan_templates (title, description, created_by) VALUES (?, ?, ?)')
                ->execute([$title, $description !== '' ? $description : null, $user['id']]);
            $templateId = (int)$pdo->lastInsertId();
            $insItem = $pdo->prepare('INSERT INTO meal_plan_template_items (template_id, day_offset, meal_type, recipe_id) VALUES (?, ?, ?, ?)');
            foreach ($validItems as [$dayOffset, $mealType, $recipeId]) {
                $insItem->execute([$templateId, $dayOffset, $mealType, $recipeId]);
            }
            $pdo->commit();
            flash_set('success', 'Meal plan template created.');
            redirect('admin_meal_plans.php');
        }
    } elseif ($action === 'delete') {
        $templateId = (int)($_POST['template_id'] ?? 0);
        db()->prepare('DELETE FROM meal_plan_templates WHERE id = ?')->execute([$templateId]);
        flash_set('success', 'Template removed.');
        redirect('admin_meal_plans.php');
    }
}

$templates = db()->query(
    'SELECT t.*, u.name AS author_name, COUNT(i.id) AS item_count
     FROM meal_plan_templates t
     LEFT JOIN meal_plan_template_items i ON i.template_id = t.id
     JOIN users u ON u.id = t.created_by
     GROUP BY t.id
     ORDER BY t.created_at DESC'
)->fetchAll();

$recipes = db()->query('SELECT id, title FROM recipes ORDER BY title')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Meal Plans · Admin · <?= APP_NAME ?></title>
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
    <div class="admin-page-head mb-16">
        <h1 style="margin:0;"><?= icon('shield', 20) ?> Admin portal</h1>
        <a href="admin_profile.php" class="btn btn-text btn-small"><?= icon('user', 16) ?> My profile</a>
    </div>

    <?php $adminCurrent = 'admin_meal_plans.php'; include __DIR__ . '/includes/admin_nav.php'; ?>

    <?php if ($success = flash_get('success')): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <div class="card mb-16">
        <h2 style="margin-top:0;font-size:17px;"><?= icon('calendar', 18) ?> Published templates</h2>
        <p class="muted" style="font-size:13px;margin:0 0 16px;">Members can browse and adopt these from Planner → Browse meal plans.</p>
        <?php if (!$templates): ?>
            <p class="muted">No templates yet — create one below.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead><tr><th>Title</th><th>Recipes</th><th>Created by</th><th>Date</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($templates as $t): ?>
                        <tr>
                            <td><strong><?= h($t['title']) ?></strong><?php if ($t['description']): ?><div class="muted" style="font-size:12px;"><?= h($t['description']) ?></div><?php endif; ?></td>
                            <td><?= (int)$t['item_count'] ?></td>
                            <td><?= h($t['author_name']) ?></td>
                            <td><?= h((new DateTime($t['created_at']))->format('M j, Y')) ?></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Delete this template?');">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="template_id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit" class="btn btn-text btn-small" style="color:var(--error);"><?= icon('trash', 14) ?> Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 style="margin-top:0;font-size:17px;"><?= icon('plus', 18) ?> Create a meal plan template</h2>
        <p class="muted" style="font-size:13px;margin:0 0 16px;">
            Days are relative (Day 1, Day 2…) — a member picks their own start date when they adopt it, so one
            template can be reused for any week.
        </p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">

            <label class="field"><span>Title</span><input type="text" name="title" required></label>
            <label class="field"><span>Description (optional)</span><textarea name="description" rows="2"></textarea></label>

            <div style="overflow-x:auto;">
                <table class="admin-table" id="templateGrid">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <?php foreach (TEMPLATE_MEAL_TYPES as $mt): ?><th><?= ucfirst($mt) ?></th><?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (TEMPLATE_DAY_NAMES as $dayIndex => $dayLabel): ?>
                            <tr>
                                <td><strong><?= $dayLabel ?></strong></td>
                                <?php foreach (TEMPLATE_MEAL_TYPES as $mt): ?>
                                    <td>
                                        <input type="hidden" name="items[<?= $dayIndex ?>][<?= $mt ?>]" class="combobox-value">
                                        <div class="combobox">
                                            <input type="text" class="combobox-input" placeholder="—" autocomplete="off" style="width:140px;">
                                            <div class="combobox-list" hidden></div>
                                        </div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button type="submit" class="btn btn-primary mt-16">Create template</button>
        </form>
    </div>
</main>

<script>
var TEMPLATE_RECIPES = <?= json_encode(array_map(fn($r) => ['id' => $r['id'], 'title' => $r['title']], $recipes), JSON_HEX_TAG | JSON_HEX_APOS) ?>;

(function () {
    function closeList(list) { list.hidden = true; list.innerHTML = ''; }

    function openList(list, query) {
        var q = query.trim().toLowerCase();
        var matches = TEMPLATE_RECIPES.filter(function (r) {
            return !q || r.title.toLowerCase().indexOf(q) !== -1;
        }).slice(0, 8);

        list.innerHTML = matches.length
            ? matches.map(function (r) {
                return '<button type="button" class="combobox-option" data-id="' + r.id.replace(/"/g, '&quot;') + '" data-title="' + r.title.replace(/"/g, '&quot;') + '">' + r.title.replace(/</g, '&lt;') + '</button>';
            }).join('')
            : '<div class="combobox-empty">No recipes match</div>';
        list.hidden = false;
    }

    Array.prototype.forEach.call(document.querySelectorAll('#templateGrid .combobox'), function (box) {
        var input = box.querySelector('.combobox-input');
        var list = box.querySelector('.combobox-list');
        var hiddenValue = box.parentElement.querySelector('.combobox-value');

        input.addEventListener('focus', function () { openList(list, input.value); });
        input.addEventListener('input', function () { openList(list, input.value); if (!input.value) hiddenValue.value = ''; });
        input.addEventListener('blur', function () { setTimeout(function () { closeList(list); }, 150); });

        list.addEventListener('mousedown', function (e) {
            var opt = e.target.closest('.combobox-option');
            if (!opt) return;
            hiddenValue.value = opt.getAttribute('data-id');
            input.value = opt.getAttribute('data-title');
            closeList(list);
        });
    });
})();
</script>
</body>
</html>

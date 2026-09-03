<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_admin();

$stmt = db()->prepare(
    'SELECT r.*, u.name AS author_name, u.email AS author_email
     FROM recipes r JOIN users u ON u.id = r.created_by
     WHERE r.is_generated = 1
     ORDER BY r.created_at DESC'
);
$stmt->execute();
$recipes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <?php if ($success = flash_get('success')): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <h1 class="mb-16"><?= icon('shield', 20) ?> User-submitted recipes</h1>

    <?php if (!$recipes): ?>
        <p class="muted">No user-submitted recipes yet.</p>
    <?php else: ?>
        <div class="card" style="overflow-x:auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Meal</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recipes as $recipe): ?>
                        <tr>
                            <td><a href="recipe.php?id=<?= urlencode($recipe['id']) ?>"><?= h($recipe['title']) ?></a></td>
                            <td><?= h($recipe['author_name']) ?> <span class="muted">(<?= h($recipe['author_email']) ?>)</span></td>
                            <td><?= h(ucfirst($recipe['meal_type'])) ?></td>
                            <td><?= h((new DateTime($recipe['created_at']))->format('M j, Y')) ?></td>
                            <td>
                                <form method="post" action="admin_recipe_delete.php" onsubmit="return confirm('Remove this recipe?');">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="recipe_id" value="<?= h($recipe['id']) ?>">
                                    <button type="submit" class="btn btn-text btn-small" style="color:var(--error);"><?= icon('trash', 14) ?> Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

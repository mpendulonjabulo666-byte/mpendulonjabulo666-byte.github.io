<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_listing') {
        $name = trim($_POST['ingredient_name'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        if ($name !== '' && $quantity !== '' && $price > 0) {
            db()->prepare(
                'INSERT INTO ingredient_listings (seller_id, ingredient_name, quantity, price, description) VALUES (?, ?, ?, ?, ?)'
            )->execute([$user['id'], $name, $quantity, number_format($price, 2, '.', ''), $description ?: null]);
        }
    } elseif ($action === 'delete_listing') {
        $id = (int)($_POST['listing_id'] ?? 0);
        db()->prepare("DELETE FROM ingredient_listings WHERE id = ? AND seller_id = ? AND status = 'available'")
            ->execute([$id, $user['id']]);
    }
    redirect('marketplace.php');
}

$search = trim($_GET['q'] ?? '');

$sql = "SELECT l.*, u.name AS seller_name FROM ingredient_listings l JOIN users u ON u.id = l.seller_id
        WHERE l.status = 'available' AND l.seller_id != ?";
$params = [$user['id']];
if ($search !== '') {
    $sql .= ' AND l.ingredient_name LIKE ?';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY l.created_at DESC';
$listingsStmt = db()->prepare($sql);
$listingsStmt->execute($params);
$listings = $listingsStmt->fetchAll();

$mineStmt = db()->prepare(
    'SELECT l.*, (SELECT o.seller_amount FROM ingredient_orders o WHERE o.listing_id = l.id AND o.status = "paid" LIMIT 1) AS seller_amount
     FROM ingredient_listings l WHERE l.seller_id = ? ORDER BY l.created_at DESC'
);
$mineStmt->execute([$user['id']]);
$mine = $mineStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingredient Marketplace · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <h1 class="mb-16"><?= icon('shopping-cart', 20) ?> Ingredient Marketplace</h1>
    <p class="muted" style="margin-top:-10px;">Buy and sell surplus ingredients with other <?= APP_NAME ?> members. <?= APP_NAME ?> keeps <?= PLATFORM_COMMISSION_PCT ?>% of each sale.</p>

    <div class="card mb-16">
        <h2 style="margin:0 0 12px;font-size:16px;">List an ingredient for sale</h2>
        <form method="post" style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));align-items:end;">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_listing">
            <label style="display:flex;flex-direction:column;gap:4px;font-size:12.5px;">Ingredient
                <input type="text" name="ingredient_name" placeholder="e.g. Fresh basil" style="padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--ink);" required>
            </label>
            <label style="display:flex;flex-direction:column;gap:4px;font-size:12.5px;">Quantity
                <input type="text" name="quantity" placeholder="e.g. 500g" style="padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--ink);" required>
            </label>
            <label style="display:flex;flex-direction:column;gap:4px;font-size:12.5px;">Price (R)
                <input type="number" name="price" min="1" step="0.01" placeholder="30.00" style="padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--ink);" required>
            </label>
            <button type="submit" class="btn btn-primary"><?= icon('plus', 16) ?> List it</button>
            <input type="text" name="description" placeholder="Optional note (freshness, pickup, etc.)" style="grid-column:1 / -1;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--ink);">
        </form>
    </div>

    <?php if ($mine): ?>
        <h2 class="mb-16">Your listings</h2>
        <div class="card mb-16" style="overflow-x:auto;">
            <table class="admin-table">
                <thead><tr><th>Ingredient</th><th>Quantity</th><th>Price</th><th>Status</th><th>Your earnings</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($mine as $l): ?>
                        <tr>
                            <td><a href="listing.php?id=<?= (int)$l['id'] ?>"><?= h($l['ingredient_name']) ?></a></td>
                            <td><?= h($l['quantity']) ?></td>
                            <td>R<?= number_format((float)$l['price'], 2) ?></td>
                            <td><?= $l['status'] === 'sold' ? 'Sold' : 'Available' ?></td>
                            <td><?= $l['seller_amount'] !== null ? 'R' . number_format((float)$l['seller_amount'], 2) : '—' ?></td>
                            <td>
                                <?php if ($l['status'] === 'available'): ?>
                                    <form method="post" onsubmit="return confirm('Remove this listing?');">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_listing">
                                        <input type="hidden" name="listing_id" value="<?= (int)$l['id'] ?>">
                                        <button type="submit" class="btn btn-text btn-small" style="color:var(--error);"><?= icon('trash', 14) ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="planner-header">
        <h2>Available now</h2>
        <form method="get" style="display:flex;gap:8px;">
            <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search ingredients..." style="padding:8px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--ink);">
            <button type="submit" class="btn btn-text"><?= icon('search', 16) ?></button>
        </form>
    </div>

    <?php if (!$listings): ?>
        <p class="muted"><?= $search !== '' ? 'No ingredients match your search.' : 'No ingredients listed yet — be the first to sell your surplus.' ?></p>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;">
            <?php foreach ($listings as $l): ?>
                <a class="card" href="listing.php?id=<?= (int)$l['id'] ?>" style="text-decoration:none;color:inherit;display:block;">
                    <h3 style="margin:0 0 4px;font-size:16px;"><?= h($l['ingredient_name']) ?></h3>
                    <p class="muted" style="margin:0 0 10px;font-size:13px;"><?= h($l['quantity']) ?></p>
                    <div class="macro-pill" style="text-align:center;max-width:none;">
                        <strong>R<?= number_format((float)$l['price'], 2) ?></strong>
                        <span>from <?= h($l['seller_name']) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>

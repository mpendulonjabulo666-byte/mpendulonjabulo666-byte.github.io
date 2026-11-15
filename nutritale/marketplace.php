<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

if (empty($user['is_admin']) && !platform_setting('show_marketplace')) {
    flash_set('error', 'The marketplace is temporarily unavailable.');
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_listing') {
        $name = trim($_POST['ingredient_name'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $description = mb_substr(trim($_POST['description'] ?? ''), 0, 500);
        $contact = mb_substr(preg_replace('/[^0-9+ ()-]/', '', (string)($_POST['contact'] ?? '')), 0, 30);
        $isPremiumListing = !empty($_POST['is_premium']) ? 1 : 0;
        $name = mb_substr($name, 0, 150);
        $quantity = mb_substr($quantity, 0, 50);
        if ($name !== '' && $quantity !== '' && $price > 0 && $price < 100000 && strlen(preg_replace('/\D/', '', $contact)) >= 9) {
            db()->prepare(
                'INSERT INTO ingredient_listings (seller_id, ingredient_name, quantity, price, description, contact, is_premium) VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$user['id'], $name, $quantity, number_format($price, 2, '.', ''), $description ?: null, $contact, $isPremiumListing]);
            flash_set('success', 'Listed. Members will contact you directly.');
        } else {
            flash_set('error', 'Please fill in the ingredient, quantity, price and a contact number (at least 9 digits).');
        }
    } elseif ($action === 'delete_listing') {
        $id = (int)($_POST['listing_id'] ?? 0);
        db()->prepare("DELETE FROM ingredient_listings WHERE id = ? AND seller_id = ? AND status = 'available'")
            ->execute([$id, $user['id']]);
    }
    redirect('marketplace.php');
}

$search = trim($_GET['q'] ?? '');

$canSeePremium = !empty($user['is_admin']) || !empty($user['is_premium_member']);
$sql = "SELECT l.*, u.name AS seller_name FROM ingredient_listings l JOIN users u ON u.id = l.seller_id
        WHERE l.status = 'available' AND l.seller_id != ?";
$params = [$user['id']];
if (!$canSeePremium) {
    $sql .= ' AND l.is_premium = 0';
}
if ($search !== '') {
    $sql .= ' AND l.ingredient_name LIKE ?';
    $params[] = '%' . $search . '%';
}
$sql .= ' ORDER BY l.created_at DESC';
$listingsStmt = db()->prepare($sql);
$listingsStmt->execute($params);
$listings = $listingsStmt->fetchAll();

$premiumHidden = 0;
if (!$canSeePremium) {
    $ph = db()->prepare("SELECT COUNT(*) FROM ingredient_listings WHERE status = 'available' AND is_premium = 1 AND seller_id != ?");
    $ph->execute([$user['id']]);
    $premiumHidden = (int)$ph->fetchColumn();
}

$mineStmt = db()->prepare(
    'SELECT l.* FROM ingredient_listings l WHERE l.seller_id = ? ORDER BY l.created_at DESC'
);
$mineStmt->execute([$user['id']]);
$mine = $mineStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ingredient Marketplace · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js?v=24"></script>
<link rel="stylesheet" href="assets/css/style.css?v=44">
<script src="assets/js/theme-toggle.js?v=24" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div class="page-hero-banner" style="background-image:url('assets/img/banners/marketplace-herbs.jpg');">
        <div>
            <h1><?= icon('shopping-cart', 20) ?> Ingredient Marketplace</h1>
            <p>Swap or sell surplus ingredients with other <?= APP_NAME ?> members.</p>
        </div>
    </div>

    <div class="card mb-16" style="border-left:4px solid var(--green, #375534);">
        <strong>Coming soon: paying inside the app.</strong>
        <p class="muted" style="margin:6px 0 0;font-size:13.5px;">For now you can list ingredients and contact other members directly. You agree the price, pickup or delivery between you, and pay each other yourselves. <?= APP_NAME ?> does not take payment or hold any money for these sales, and is not a party to them.</p>
    </div>

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
            <label style="display:flex;flex-direction:column;gap:4px;font-size:12.5px;">Your contact number
                <input type="tel" name="contact" placeholder="e.g. 082 000 0000" maxlength="30" style="padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--ink);" required>
            </label>
            <label style="display:flex;align-items:center;gap:8px;font-size:12.5px;">
                <input type="checkbox" name="is_premium" value="1"> Premium-only (only Premium members can see this)
            </label>
            <button type="submit" class="btn btn-primary"><?= icon('plus', 16) ?> List it</button>
            <input type="text" name="description" placeholder="Optional note (freshness, pickup, etc.)" style="grid-column:1 / -1;padding:10px 12px;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--ink);">
        </form>
    </div>

    <?php if ($mine): ?>
        <h2 class="mb-16">Your listings</h2>
        <div class="card mb-16" style="overflow-x:auto;">
            <table class="admin-table">
                <thead><tr><th>Ingredient</th><th>Quantity</th><th>Price</th><th>Status</th><th>Who sees it</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($mine as $l): ?>
                        <tr>
                            <td><a href="listing.php?id=<?= (int)$l['id'] ?>"><?= h($l['ingredient_name']) ?></a></td>
                            <td><?= h($l['quantity']) ?></td>
                            <td>R<?= number_format((float)$l['price'], 2) ?></td>
                            <td><?= $l['status'] === 'sold' ? 'Sold' : 'Available' ?></td>
                            <td><?= !empty($l['is_premium']) ? 'Premium members' : 'Everyone' ?></td>
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
        <p class="muted"><?= $search !== '' ? 'No ingredients match your search.' : 'No ingredients listed yet — be the first to list your surplus.' ?></p>
    <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;">
            <?php foreach ($listings as $l): ?>
                <a class="card" href="listing.php?id=<?= (int)$l['id'] ?>" style="text-decoration:none;color:inherit;display:block;">
                    <h3 style="margin:0 0 4px;font-size:16px;"><?= h($l['ingredient_name']) ?><?= !empty($l['is_premium']) ? ' <span class="muted" style="font-size:11px;">· Premium</span>' : '' ?></h3>
                    <p class="muted" style="margin:0 0 10px;font-size:13px;"><?= h($l['quantity']) ?></p>
                    <div class="macro-pill" style="text-align:center;max-width:none;">
                        <strong>R<?= number_format((float)$l['price'], 2) ?></strong>
                        <span>from <?= h($l['seller_name']) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($premiumHidden > 0): ?>
        <p class="muted mt-16" style="font-size:13.5px;"><?= $premiumHidden ?> more listing<?= $premiumHidden === 1 ? ' is' : 's are' ?> for Premium members. <a href="premium.php">See Premium</a></p>
    <?php endif; ?>
</main>
</body>
</html>

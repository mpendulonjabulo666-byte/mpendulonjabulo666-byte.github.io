<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/vendor_payouts.php';

$user = require_admin();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_paid' && csrf_check()) {
    $vendorId = (int)($_POST['vendor_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    // Recomputed fresh here rather than trusting whatever amount the page
    // showed when it was loaded - a sale could have landed in the gap
    // between then and this submission, and the payout row must reflect
    // what's actually owed right now, not a stale snapshot.
    $owed = calculate_vendor_owed(db(), $vendorId);

    if ($owed['amount'] <= 0) {
        $errors[] = "Nothing is currently owed to that vendor — it may have just been paid out from another tab.";
    } else {
        db()->prepare(
            'INSERT INTO vendor_payouts (vendor_id, amount, status, period_start, period_end, paid_at, paid_by_admin_id, notes)
             VALUES (?, ?, ?, ?, NOW(), NOW(), ?, ?)'
        )->execute([
            $vendorId, $owed['amount'], 'paid', $owed['period_start'], $user['id'],
            $notes !== '' ? $notes : null,
        ]);
        flash_set('success', 'Payout recorded — remember this only logs that you paid the vendor by EFT; no money moves through NutriTale itself.');
        redirect('admin_payouts.php');
    }
}

$owedByVendor = vendors_with_owed_balance(db());
$vendors = [];
if ($owedByVendor) {
    $placeholders = implode(',', array_fill(0, count($owedByVendor), '?'));
    $stmt = db()->prepare("SELECT id, name, email FROM users WHERE id IN ($placeholders) ORDER BY name");
    $stmt->execute(array_keys($owedByVendor));
    $vendors = $stmt->fetchAll();
}

$history = db()->query(
    'SELECT vp.*, u.name AS vendor_name, u.email AS vendor_email, a.name AS admin_name
     FROM vendor_payouts vp
     JOIN users u ON u.id = vp.vendor_id
     LEFT JOIN users a ON a.id = vp.paid_by_admin_id
     ORDER BY vp.created_at DESC
     LIMIT 50'
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Payouts · Admin · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=4">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;" class="mb-16">
        <h1 style="margin:0;"><?= icon('shield', 20) ?> Admin portal</h1>
        <a href="admin_profile.php" class="btn btn-text btn-small"><?= icon('user', 16) ?> My profile</a>
    </div>

    <?php $adminCurrent = 'admin_payouts.php'; include __DIR__ . '/includes/admin_nav.php'; ?>

    <?php if ($success = flash_get('success')): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <div class="card mb-16">
        <h2 style="margin-top:0;font-size:17px;">Vendors owed money</h2>
        <p class="muted" style="font-size:13px;margin:0 0 16px;">
            All sale money currently sits in the platform's own PayFast account. "Mark as paid" only records that
            you paid the vendor manually (e.g. by EFT) — it doesn't move any money itself.
        </p>
        <?php if (!$vendors): ?>
            <p class="muted">No vendor currently has an outstanding balance.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead><tr><th>Vendor</th><th>Owed since</th><th>Amount</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($vendors as $v): $owed = $owedByVendor[$v['id']]; ?>
                        <tr>
                            <td><strong><?= h($v['name']) ?></strong> <span class="muted"><?= h($v['email']) ?></span></td>
                            <td><?= $owed['period_start'] ? h((new DateTime($owed['period_start']))->format('M j, Y')) : '—' ?></td>
                            <td>R<?= number_format($owed['amount'], 2) ?> <span class="muted" style="font-size:12px;">(<?= (int)$owed['sales_count'] ?> sale<?= $owed['sales_count'] === 1 ? '' : 's' ?>)</span></td>
                            <td>
                                <form method="post" onsubmit="return confirm('Confirm you have paid <?= h(addslashes($v['name'])) ?> R<?= number_format($owed['amount'], 2) ?> by EFT? This only records it here.');">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="mark_paid">
                                    <input type="hidden" name="vendor_id" value="<?= (int)$v['id'] ?>">
                                    <input type="text" name="notes" placeholder="Bank ref (optional)" style="width:140px;display:inline-block;padding:8px 10px;border-radius:10px;border:1px solid var(--border);font-size:13px;">
                                    <button type="submit" class="btn btn-primary btn-small"><?= icon('check', 14) ?> Mark as paid</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 style="margin-top:0;font-size:17px;">Payout history</h2>
        <?php if (!$history): ?>
            <p class="muted">No payouts recorded yet.</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
            <table class="admin-table">
                <thead><tr><th>Vendor</th><th>Period</th><th>Amount</th><th>Status</th><th>Paid by</th><th>Notes</th></tr></thead>
                <tbody>
                    <?php foreach ($history as $p): ?>
                        <tr>
                            <td><?= h($p['vendor_name']) ?></td>
                            <td>
                                <?= $p['period_start'] ? h((new DateTime($p['period_start']))->format('M j')) : '—' ?>
                                – <?= h((new DateTime($p['period_end']))->format('M j, Y')) ?>
                            </td>
                            <td>R<?= number_format((float)$p['amount'], 2) ?></td>
                            <td><span class="pill pill-<?= $p['status'] === 'paid' ? 'resolved' : 'open' ?>"><?= h(ucfirst($p['status'])) ?></span></td>
                            <td><?= h($p['admin_name'] ?? '—') ?></td>
                            <td class="muted"><?= h($p['notes'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>

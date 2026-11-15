<?php
// Tests account_delete() (in-app account deletion) and the App Review demo
// account against a REAL MySQL/MariaDB database - the deletion rules live in
// foreign keys and SQL, so fixture arrays could not prove anything.
//
//   php tests/account_deletion_test.php
//
// Uses whatever database config.local.php points at. NEVER point it at the
// live database: it creates and deletes its own test users. If no database
// can be reached it prints SKIPPED and exits 0.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_conn.php';
require_once __DIR__ . '/../includes/functions_core.php';
require_once __DIR__ . '/../includes/account_deletion.php';
require_once __DIR__ . '/../includes/demo_account.php';

try {
    $pdo = db();
} catch (Throwable $e) {
    echo "SKIPPED (no database: " . $e->getMessage() . ")\n";
    exit(0);
}
if (stripos(DB_NAME, 'test') === false) {
    echo "REFUSED: DB_NAME '" . DB_NAME . "' does not look like a test database.\n";
    exit(1);
}

$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        return;
    }
    $fail++;
    echo "FAIL  $label\n";
}

$tag = bin2hex(random_bytes(4));

function make_user(PDO $pdo, string $name, int $isAdmin = 0): int
{
    global $tag;
    $pdo->prepare('INSERT INTO users (name, email, password_hash, is_admin) VALUES (?, ?, ?, ?)')
        ->execute([$name, strtolower($name) . "-$tag@test.invalid", password_hash('Secret123', PASSWORD_DEFAULT), $isAdmin]);
    return (int)$pdo->lastInsertId();
}

function make_recipe(PDO $pdo, int $authorId, string $title): string
{
    global $tag;
    $id = 'test-' . $tag . '-' . bin2hex(random_bytes(3));
    $pdo->prepare("INSERT INTO recipes (id, title, meal_type, difficulty, cook_time_minutes, servings, created_by, is_published)
                   VALUES (?, ?, 'dinner', 'easy', 20, 2, ?, 1)")
        ->execute([$id, $title, $authorId]);
    return $id;
}

function count_rows(PDO $pdo, string $sql, array $params): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

$neverCalled = function (string $token): array {
    throw new RuntimeException('cancel should not be called');
};
$cancelOk = function (string $token): array {
    return ['ok' => true];
};
$cancelFails = function (string $token): array {
    return ['ok' => false, 'error' => 'PayFast timed out'];
};

// 1. An account with no payments is fully removed.
$a = make_user($pdo, 'Alice');
$pdo->prepare('INSERT INTO user_pantry_items (user_id, ingredient_name) VALUES (?, ?)')->execute([$a, 'eggs']);
$pdo->prepare('INSERT INTO user_allergens (user_id, allergen) VALUES (?, ?)')->execute([$a, 'peanuts']);
$aRecipe = make_recipe($pdo, $a, 'Alice soup');
$pdo->prepare('INSERT INTO nutri_sessions (id, data, last_activity) VALUES (?, ?, ?)')
    ->execute(["sessA$tag", "csrf_token|s:4:\"abcd\";user_id|i:$a;", time()]);
$r = account_delete($pdo, $a, $neverCalled);
check('no-payment account: ok', $r['ok'] === true && ($r['mode'] ?? '') === 'deleted');
check('no-payment account: users row gone', count_rows($pdo, 'SELECT COUNT(*) FROM users WHERE id = ?', [$a]) === 0);
check('no-payment account: pantry gone', count_rows($pdo, 'SELECT COUNT(*) FROM user_pantry_items WHERE user_id = ?', [$a]) === 0);
check('no-payment account: allergies gone', count_rows($pdo, 'SELECT COUNT(*) FROM user_allergens WHERE user_id = ?', [$a]) === 0);
check('no-payment account: unsold recipe deleted', count_rows($pdo, 'SELECT COUNT(*) FROM recipes WHERE id = ?', [$aRecipe]) === 0);
check('no-payment account: signed out on every device', count_rows($pdo, 'SELECT COUNT(*) FROM nutri_sessions WHERE id = ?', ["sessA$tag"]) === 0);

// 2. A vendor who sold a recipe, and the buyer, are anonymised and the sale survives.
$vendor = make_user($pdo, 'Vendor');
$buyer = make_user($pdo, 'Buyer');
$sold = make_recipe($pdo, $vendor, 'Sold stew');
$unsold = make_recipe($pdo, $vendor, 'Unsold cake');
$pdo->prepare("INSERT INTO recipe_purchases (m_payment_id, buyer_id, recipe_id, vendor_id, amount, status) VALUES (?, ?, ?, ?, 25.00, 'paid')")
    ->execute(["mp-$tag", $buyer, $sold, $vendor]);
$pdo->prepare('INSERT INTO user_pantry_items (user_id, ingredient_name) VALUES (?, ?)')->execute([$vendor, 'rice']);
$pdo->prepare('INSERT INTO favorites (user_id, recipe_id) VALUES (?, ?)')->execute([$vendor, $sold]);
$pdo->prepare('INSERT INTO nutri_sessions (id, data, last_activity) VALUES (?, ?, ?)')
    ->execute(["sessB$tag", "user_id|i:$buyer;", time()]);

$r = account_delete($pdo, $vendor, $neverCalled);
check('vendor with a sale: anonymised', $r['ok'] === true && ($r['mode'] ?? '') === 'anonymised');
$row = $pdo->prepare('SELECT name, email, deleted_at, oauth_id, is_vendor FROM users WHERE id = ?');
$row->execute([$vendor]);
$v = $row->fetch(PDO::FETCH_ASSOC);
check('vendor: name replaced', $v['name'] === 'Deleted user');
check('vendor: email replaced with an unusable one', str_ends_with($v['email'], '@deleted.invalid') && !str_contains($v['email'], 'vendor-'));
check('vendor: marked deleted', $v['deleted_at'] !== null);
check('vendor: old password no longer works', !password_verify('Secret123', (string)$pdo->query("SELECT password_hash FROM users WHERE id = $vendor")->fetchColumn()));
check('vendor: personal data gone', count_rows($pdo, 'SELECT COUNT(*) FROM user_pantry_items WHERE user_id = ?', [$vendor]) === 0
    && count_rows($pdo, 'SELECT COUNT(*) FROM favorites WHERE user_id = ?', [$vendor]) === 0);
check('vendor: the sale record is kept', count_rows($pdo, 'SELECT COUNT(*) FROM recipe_purchases WHERE m_payment_id = ?', ["mp-$tag"]) === 1);
check('vendor: sold recipe kept but hidden', count_rows($pdo, 'SELECT COUNT(*) FROM recipes WHERE id = ? AND is_published = 0', [$sold]) === 1);
check('vendor: unsold recipe deleted', count_rows($pdo, 'SELECT COUNT(*) FROM recipes WHERE id = ?', [$unsold]) === 0);
check("vendor: buyer's session untouched", count_rows($pdo, 'SELECT COUNT(*) FROM nutri_sessions WHERE id = ?', ["sessB$tag"]) === 1);

// current_user() must treat the deleted account as logged out.
$_SESSION['user_id'] = $vendor;
check('deleted account cannot be "logged in" from an old session', current_user() === null);
unset($_SESSION['user_id']);

$r = account_delete($pdo, $vendor, $neverCalled);
check('deleting an already-deleted account is refused', $r['ok'] === false);

// 3. Active Premium: PayFast must confirm the cancel first.
$sub = make_user($pdo, 'Subscriber');
$pdo->prepare("INSERT INTO premium_subscriptions (user_id, m_payment_id, amount, status, pf_token) VALUES (?, ?, 99.00, 'active', ?)")
    ->execute([$sub, "sub-$tag", "tok-$tag"]);
$r = account_delete($pdo, $sub, $cancelFails);
check('PayFast cancel fails: refused', $r['ok'] === false && str_contains($r['error'], 'PayFast timed out'));
check('PayFast cancel fails: account untouched', count_rows($pdo, 'SELECT COUNT(*) FROM users WHERE id = ? AND deleted_at IS NULL', [$sub]) === 1);
check('PayFast cancel fails: subscription still active', count_rows($pdo, "SELECT COUNT(*) FROM premium_subscriptions WHERE user_id = ? AND status = 'active'", [$sub]) === 1);

$calledWith = null;
$r = account_delete($pdo, $sub, function (string $token) use (&$calledWith): array {
    $calledWith = $token;
    return ['ok' => true];
});
check('PayFast cancel ok: the right subscription was cancelled', $calledWith === "tok-$tag");
check('PayFast cancel ok: account anonymised (has billing history)', $r['ok'] === true && ($r['mode'] ?? '') === 'anonymised');
check('PayFast cancel ok: subscription marked cancelled', count_rows($pdo, "SELECT COUNT(*) FROM premium_subscriptions WHERE user_id = ? AND status = 'cancelled'", [$sub]) === 1);

// 4. The only admin cannot delete themselves; with a second admin they can.
$adminCountBefore = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1 AND deleted_at IS NULL')->fetchColumn();
$pdo->exec('UPDATE users SET is_admin = 0 WHERE is_admin = 1'); // isolate: no other admins
$admin1 = make_user($pdo, 'AdminOne', 1);
$r = account_delete($pdo, $admin1, $neverCalled);
check('only admin: refused', $r['ok'] === false && str_contains($r['error'], 'only admin'));
$admin2 = make_user($pdo, 'AdminTwo', 1);
$r = account_delete($pdo, $admin1, $neverCalled);
check('second admin exists: allowed', $r['ok'] === true);

// 5. App Review demo account.
for ($i = 0, $okAll = true; $i < 200; $i++) {
    $p = demo_account_password();
    $okAll = $okAll && strlen($p) >= 8 && preg_match('/[A-Za-z]/', $p) && preg_match('/[0-9]/', $p);
}
check('demo password always meets the sign-up password rule', $okAll);
check('demo email uses the site host', demo_account_email('nutritale.freedev.app:443') === 'appreview@nutritale.freedev.app');
check('demo email falls back for a junk Host header', demo_account_email('<script>') === 'appreview@nutritale.freedev.app');

$demoEmail = "appreview-$tag@test.invalid";
$first = demo_account_upsert($pdo, $demoEmail);
$hash1 = (string)$pdo->query('SELECT password_hash FROM users WHERE email = ' . $pdo->quote($demoEmail))->fetchColumn();
check('demo account created and its password works', password_verify($first['password'], $hash1));
check('demo account is Premium, not admin', count_rows($pdo, 'SELECT COUNT(*) FROM users WHERE email = ? AND is_premium_member = 1 AND is_admin = 0', [$demoEmail]) === 1);
check('demo account has pantry items to show', count_rows($pdo, 'SELECT COUNT(*) FROM user_pantry_items p JOIN users u ON u.id = p.user_id WHERE u.email = ?', [$demoEmail]) === count(DEMO_PANTRY_ITEMS));
$second = demo_account_upsert($pdo, $demoEmail);
$hash2 = (string)$pdo->query('SELECT password_hash FROM users WHERE email = ' . $pdo->quote($demoEmail))->fetchColumn();
check('reset gives a new password and the old one stops working', password_verify($second['password'], $hash2) && !password_verify($first['password'], $hash2));
check('reset does not create a second account', count_rows($pdo, 'SELECT COUNT(*) FROM users WHERE email = ?', [$demoEmail]) === 1);

// Clean up this run's rows (anonymised users keep their tag-free emails, so match by id).
$pdo->exec("DELETE FROM recipe_purchases WHERE m_payment_id = 'mp-$tag'");
$pdo->exec("DELETE FROM recipes WHERE id LIKE 'test-$tag-%'");
$pdo->exec("DELETE FROM nutri_sessions WHERE id LIKE 'sess_$tag'");
foreach ([$vendor, $buyer, $sub, $admin1, $admin2] as $id) {
    $pdo->exec('DELETE FROM users WHERE id = ' . (int)$id);
}
$pdo->exec('DELETE FROM users WHERE email = ' . $pdo->quote($demoEmail));
if ($adminCountBefore > 0) {
    echo "NOTE: this run cleared is_admin on existing test-database users.\n";
}

echo "$pass passed, $fail failed\n";
exit($fail ? 1 : 0);

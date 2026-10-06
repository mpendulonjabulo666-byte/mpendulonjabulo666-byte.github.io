<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/allergens.php';
require_once __DIR__ . '/includes/payfast_gateway.php';
require_once __DIR__ . '/includes/avatars.php';

$user = require_admin();

$dietOptions = ['vegetarian', 'vegan', 'gluten-free', 'high-protein', 'keto'];
$allergenOptions = ALLERGEN_OPTIONS;

$errors = [];

// Same guard as profile.php: a POST over post_max_size arrives with $_POST
// (and the CSRF token) emptied by PHP, which would otherwise fail silently.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $errors[] = 'That photo is too large to upload. Pick one under 5MB.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $form = $_POST['form'] ?? '';

    if ($form === 'avatar') {
        [$ok, $result] = avatar_store($_FILES['avatar'] ?? [], (int)$user['id']);
        if (!$ok) {
            $errors[] = $result;
        } else {
            avatar_delete($user['avatar_path'] ?? null);
            db()->prepare('UPDATE users SET avatar_path = ? WHERE id = ?')->execute([$result, $user['id']]);
            flash_set('success', 'Profile photo updated.');
            redirect('admin_profile.php');
        }
    } elseif ($form === 'avatar_remove') {
        avatar_delete($user['avatar_path'] ?? null);
        db()->prepare('UPDATE users SET avatar_path = NULL WHERE id = ?')->execute([$user['id']]);
        flash_set('success', 'Profile photo removed.');
        redirect('admin_profile.php');
    } elseif ($form === 'details') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
        if ($name === '') $errors[] = 'Please enter your name.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

        if (!$errors) {
            $dupe = db()->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
            $dupe->execute([$email, $user['id']]);
            if ($dupe->fetch()) $errors[] = 'Another account already uses that email.';
        }

        if (!$errors) {
            $upd = db()->prepare('UPDATE users SET name = ?, email = ?, email_notifications = ? WHERE id = ?');
            $upd->execute([$name, $email, $emailNotifications, $user['id']]);
            flash_set('success', 'Profile updated.');
            redirect('admin_profile.php');
        }
    } elseif ($form === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$user['id']]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($current, $hash)) $errors[] = 'Current password is incorrect.';
        if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
        if ($new !== $confirm) $errors[] = 'New passwords do not match.';

        if (!$errors) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash_set('success', 'Password changed.');
            redirect('admin_profile.php');
        }
    } elseif ($form === 'preferences') {
        $diets = array_intersect($_POST['diet_types'] ?? [], $dietOptions);
        $allergens = array_intersect($_POST['allergens'] ?? [], $allergenOptions);

        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM user_diet_preferences WHERE user_id = ?')->execute([$user['id']]);
        $pdo->prepare('DELETE FROM user_allergens WHERE user_id = ?')->execute([$user['id']]);
        $insDiet = $pdo->prepare('INSERT INTO user_diet_preferences (user_id, diet_type) VALUES (?, ?)');
        foreach ($diets as $d) $insDiet->execute([$user['id'], $d]);
        $insAllergen = $pdo->prepare('INSERT INTO user_allergens (user_id, allergen) VALUES (?, ?)');
        foreach ($allergens as $a) $insAllergen->execute([$user['id'], $a]);
        $pdo->commit();

        flash_set('success', 'Preferences saved.');
        redirect('admin_profile.php');
    } elseif ($form === 'goals') {
        $calories = $_POST['daily_calories'] !== '' ? (int)$_POST['daily_calories'] : null;
        $protein = $_POST['daily_protein_g'] !== '' ? (int)$_POST['daily_protein_g'] : null;
        $carbs = $_POST['daily_carbs_g'] !== '' ? (int)$_POST['daily_carbs_g'] : null;
        $fat = $_POST['daily_fat_g'] !== '' ? (int)$_POST['daily_fat_g'] : null;

        $stmt = db()->prepare(
            'INSERT INTO user_goals (user_id, daily_calories, daily_protein_g, daily_carbs_g, daily_fat_g) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE daily_calories = VALUES(daily_calories), daily_protein_g = VALUES(daily_protein_g),
             daily_carbs_g = VALUES(daily_carbs_g), daily_fat_g = VALUES(daily_fat_g)'
        );
        $stmt->execute([$user['id'], $calories, $protein, $carbs, $fat]);
        flash_set('success', 'Nutrition goals saved.');
        redirect('admin_profile.php');
    } elseif ($form === 'vendor') {
        $isVendor = isset($_POST['is_vendor']) ? 1 : 0;
        db()->prepare('UPDATE users SET is_vendor = ? WHERE id = ?')->execute([$isVendor, $user['id']]);
        flash_set('success', $isVendor ? 'Selling is now on — mark a recipe as premium from My Recipes to list it.' : 'Selling turned off.');
        redirect('admin_profile.php');
    } elseif ($form === 'cancel_subscription') {
        $subStmt = db()->prepare("SELECT * FROM premium_subscriptions WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
        $subStmt->execute([$user['id']]);
        $sub = $subStmt->fetch();

        if (!$sub || !$sub['pf_token']) {
            $errors[] = 'No active subscription was found to cancel.';
        } else {
            $result = payfast_cancel_subscription($sub['pf_token']);
            if ($result['ok']) {
                db()->prepare("UPDATE premium_subscriptions SET status = 'cancelled' WHERE id = ?")->execute([$sub['id']]);
                db()->prepare('UPDATE users SET is_premium_member = 0 WHERE id = ?')->execute([$user['id']]);
                flash_set('success', 'Your Premium subscription has been cancelled. No further payments will be taken.');
                redirect('admin_profile.php');
            } else {
                $errors[] = 'Could not cancel your subscription right now (' . $result['error'] . '). Please try again, or contact support.';
            }
        }
    }
}

$subStmt = db()->prepare("SELECT * FROM premium_subscriptions WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
$subStmt->execute([$user['id']]);
$activeSubscription = $subStmt->fetch() ?: null;

$dietStmt = db()->prepare('SELECT diet_type FROM user_diet_preferences WHERE user_id = ?');
$dietStmt->execute([$user['id']]);
$selectedDiets = $dietStmt->fetchAll(PDO::FETCH_COLUMN);

$allergenStmt = db()->prepare('SELECT allergen FROM user_allergens WHERE user_id = ?');
$allergenStmt->execute([$user['id']]);
$selectedAllergens = $allergenStmt->fetchAll(PDO::FETCH_COLUMN);

$goalStmt = db()->prepare('SELECT * FROM user_goals WHERE user_id = ?');
$goalStmt->execute([$user['id']]);
$goals = $goalStmt->fetch() ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Profile · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js?v=24"></script>
<link rel="stylesheet" href="assets/css/style.css?v=24">
<script src="assets/js/theme-toggle.js?v=24" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main" style="max-width:640px;">
    <a href="admin.php" class="btn-back mb-16"><span class="btn-back-disc"><?= icon('chevron-left', 18) ?></span><span class="btn-back-label">Back to admin</span></a>

    <section class="profile-hero mb-16">
        <form method="post" enctype="multipart/form-data" class="profile-hero-photo" id="avatar-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="form" value="avatar">
            <label class="profile-avatar-wrap" for="avatar-input">
                <?= user_avatar($user['name'], 96, $user['avatar_path'] ?? null) ?>
                <span class="profile-avatar-badge" aria-hidden="true"><?= icon('camera', 15) ?></span>
                <span class="profile-avatar-label">
                    <?= !empty($user['avatar_path']) ? 'Change photo' : 'Add a photo' ?>
                </span>
            </label>
            <input type="file" id="avatar-input" name="avatar" class="profile-avatar-input"
                   accept="image/jpeg,image/png,image/webp">
            <button type="submit" class="btn btn-small profile-avatar-submit">Upload photo</button>
        </form>

        <div class="profile-hero-id">
            <h2 class="profile-hero-name"><?= h($user['name']) ?></h2>
            <p class="profile-hero-email muted">Administrator since <?= h((new DateTime($user['created_at']))->format('j F Y')) ?></p>
            <div class="profile-hero-actions">
                <span class="tag">Full access</span>
                <a href="index.php?tour=1" class="btn btn-text btn-small profile-tour-link">Take the app tour</a>
                <?php if (!empty($user['avatar_path'])): ?>
                    <form method="post" class="profile-hero-remove">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="form" value="avatar_remove">
                        <button type="submit" class="btn btn-text btn-small">Remove photo</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($success = flash_get('success')): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <div class="card mb-16">
        <h2 style="font-size:16px;margin-top:0;">Account details</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="form" value="details">
            <label class="field">
                <span>Name</span>
                <input type="text" name="name" value="<?= h($user['name']) ?>" required>
            </label>
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" value="<?= h($user['email']) ?>" required>
            </label>
            <label class="pref-chip <?= $user['email_notifications'] ? 'is-active' : '' ?> mb-16" style="display:inline-flex;">
                <input type="checkbox" name="email_notifications" <?= $user['email_notifications'] ? 'checked' : '' ?>>
                Email me when someone rates or reviews my recipes
            </label>
            <button type="submit" class="btn btn-primary">Save details</button>
        </form>
    </div>

    <?php if ($activeSubscription): ?>
        <div class="card mb-16">
            <h2 style="font-size:16px;margin-top:0;">Premium subscription</h2>
            <p class="muted" style="margin-top:0;font-size:13px;">
                R<?= number_format((float)$activeSubscription['amount'], 2) ?>/month via PayFast.
                <?php if ($activeSubscription['current_period_end']): ?>
                    Renews <?= h(date('j F Y', strtotime($activeSubscription['current_period_end']))) ?>.
                <?php endif; ?>
            </p>
            <form method="post" onsubmit="return confirm('Cancel your Premium subscription? No further payments will be taken.');">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="form" value="cancel_subscription">
                <button type="submit" class="btn btn-text btn-small" style="color:var(--error);">Cancel subscription</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="card mb-16">
        <h2 style="font-size:16px;margin-top:0;">Change password</h2>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="form" value="password">
            <label class="field">
                <span>Current password</span>
                <input type="password" name="current_password" required>
            </label>
            <label class="field">
                <span>New password</span>
                <input type="password" name="new_password" required minlength="8">
            </label>
            <label class="field">
                <span>Confirm new password</span>
                <input type="password" name="confirm_password" required minlength="8">
            </label>
            <button type="submit" class="btn btn-primary">Change password</button>
        </form>
    </div>

    <div class="card mb-16">
        <h2 style="font-size:16px;margin-top:0;">Dietary preferences</h2>
        <p class="muted" style="margin-top:0;font-size:13px;">Used to personalize your recipe feed and flag recipes that contain something you avoid.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="form" value="preferences">

            <div class="pref-group">
                <h3>Diet preference</h3>
                <div class="pref-options">
                    <?php foreach ($dietOptions as $d): ?>
                        <label class="pref-chip <?= in_array($d, $selectedDiets, true) ? 'is-active' : '' ?>">
                            <input type="checkbox" name="diet_types[]" value="<?= h($d) ?>" <?= in_array($d, $selectedDiets, true) ? 'checked' : '' ?>>
                            <?= h(ucfirst($d)) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pref-group">
                <h3>Allergens to avoid</h3>
                <div class="pref-options">
                    <?php foreach ($allergenOptions as $a): ?>
                        <label class="pref-chip <?= in_array($a, $selectedAllergens, true) ? 'is-active' : '' ?>">
                            <input type="checkbox" name="allergens[]" value="<?= h($a) ?>" <?= in_array($a, $selectedAllergens, true) ? 'checked' : '' ?>>
                            <?= h(ucfirst($a)) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?= disclaimer('allergens') ?>
            </div>

            <button type="submit" class="btn btn-primary">Save preferences</button>
        </form>
    </div>

    <div class="card mb-16">
        <h2 style="font-size:16px;margin-top:0;">Daily nutrition goals</h2>
        <p class="muted" style="margin-top:0;font-size:13px;">Optional targets used to show progress bars against your planned meals for today.</p>
        <?= disclaimer('medical') ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="form" value="goals">
            <div class="form-grid">
                <label class="field"><span>Calories</span><input type="number" name="daily_calories" value="<?= h($goals['daily_calories'] ?? '') ?>" min="0"></label>
                <label class="field"><span>Protein (g)</span><input type="number" name="daily_protein_g" value="<?= h($goals['daily_protein_g'] ?? '') ?>" min="0"></label>
                <label class="field"><span>Carbs (g)</span><input type="number" name="daily_carbs_g" value="<?= h($goals['daily_carbs_g'] ?? '') ?>" min="0"></label>
                <label class="field"><span>Fat (g)</span><input type="number" name="daily_fat_g" value="<?= h($goals['daily_fat_g'] ?? '') ?>" min="0"></label>
            </div>
            <button type="submit" class="btn btn-primary mt-16">Save goals</button>
        </form>
    </div>

    <div class="card mb-16">
        <h2 style="font-size:16px;margin-top:0;">Sell your recipes</h2>
        <p class="muted" style="margin-top:0;font-size:13px;">Turn this on to mark your own recipes as premium with a price. Buyers pay through PayFast to unlock the full recipe.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="form" value="vendor">
            <label class="pref-chip <?= $user['is_vendor'] ? 'is-active' : '' ?>" style="display:inline-flex;">
                <input type="checkbox" name="is_vendor" <?= $user['is_vendor'] ? 'checked' : '' ?>>
                I want to sell recipes
            </label>
            <button type="submit" class="btn btn-primary mt-16" style="display:block;">Save</button>
        </form>
        <?php if ($user['is_vendor']): ?>
            <a class="btn btn-text btn-small mt-16" href="vendor.php"><?= icon('flame', 14) ?> Go to your vendor dashboard</a>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 style="font-size:16px;margin-top:0;">Administrator access</h2>
        <p class="muted" style="margin-top:0;font-size:13px;">What your account can do on NutriTale.</p>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 0;border-bottom:1px solid var(--border);">
            <span>Add, edit and remove any recipe</span>
            <span class="tag">Full access</span>
        </div>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 0;">
            <span>Everything a regular member can do</span>
            <span class="tag">Full access</span>
        </div>
    </div>
<?php include __DIR__ . '/includes/profile_more.php'; ?>
</main>
<script src="assets/js/avatar-upload.js?v=18" defer></script>
</body>
</html>

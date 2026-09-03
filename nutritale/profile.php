<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

$dietOptions = ['vegetarian', 'vegan', 'gluten-free', 'high-protein', 'keto'];
$allergenOptions = ['dairy', 'eggs', 'gluten', 'nuts', 'soy', 'fish', 'sesame', 'shellfish'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $form = $_POST['form'] ?? '';

    if ($form === 'details') {
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
            redirect('profile.php');
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
            $upd = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $upd->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash_set('success', 'Password changed.');
            redirect('profile.php');
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
        redirect('profile.php');
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
        redirect('profile.php');
    }
}

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
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Profile · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main" style="max-width:640px;">
    <h1 class="mb-16">Your profile</h1>

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

    <div class="card">
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
            </div>

            <button type="submit" class="btn btn-primary">Save preferences</button>
        </form>
    </div>

    <div class="card mt-16">
        <h2 style="font-size:16px;margin-top:0;">Daily nutrition goals</h2>
        <p class="muted" style="margin-top:0;font-size:13px;">Optional targets used to show progress bars against your planned meals for today.</p>
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
</main>
</body>
</html>

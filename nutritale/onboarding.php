<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

$dietOptions = ['vegetarian', 'vegan', 'gluten-free', 'high-protein', 'keto'];
$allergenOptions = ['dairy', 'eggs', 'gluten', 'nuts', 'soy', 'fish', 'sesame', 'shellfish'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
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
        $pdo->prepare('UPDATE users SET onboarded_at = NOW() WHERE id = ?')->execute([$user['id']]);
        $pdo->commit();
    } else {
        db()->prepare('UPDATE users SET onboarded_at = NOW() WHERE id = ?')->execute([$user['id']]);
    }

    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Welcome · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<div class="auth-shell">
<?= render_theme_toggle() ?>
    <div class="auth-card" style="max-width:480px;">
        <div class="center-text mb-16"><?= nutritale_logo_svg(56) ?></div>
        <h1 class="center-text">Welcome, <?= h($user['name']) ?>!</h1>
        <p class="muted center-text" style="margin-top:-8px;">Tell us your preferences so we can tailor your recipe feed. You can change these anytime in your profile.</p>
        <div class="card">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">

                <div class="pref-group">
                    <h3>Diet preference</h3>
                    <div class="pref-options">
                        <?php foreach ($dietOptions as $d): ?>
                            <label class="pref-chip">
                                <input type="checkbox" name="diet_types[]" value="<?= h($d) ?>">
                                <?= h(ucfirst($d)) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="pref-group">
                    <h3>Allergens to avoid</h3>
                    <div class="pref-options">
                        <?php foreach ($allergenOptions as $a): ?>
                            <label class="pref-chip">
                                <input type="checkbox" name="allergens[]" value="<?= h($a) ?>">
                                <?= h(ucfirst($a)) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Save and continue</button>
            </form>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="skip">
                <button type="submit" class="btn btn-text btn-block">Skip for now</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>

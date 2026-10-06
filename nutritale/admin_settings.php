<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_admin();

$toggleFields = ['allow_recipe_submissions', 'enable_ai_matching', 'show_marketplace', 'maintenance_mode'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $values = [];
    foreach ($toggleFields as $field) {
        $values[$field] = isset($_POST[$field]) ? 1 : 0;
    }
    db()->prepare(
        'UPDATE platform_settings SET allow_recipe_submissions = ?, enable_ai_matching = ?, show_marketplace = ?, maintenance_mode = ? WHERE id = 1'
    )->execute([$values['allow_recipe_submissions'], $values['enable_ai_matching'], $values['show_marketplace'], $values['maintenance_mode']]);
    flash_set('success', 'Settings saved.');
    redirect('admin_settings.php');
}

$settings = db()->query('SELECT * FROM platform_settings WHERE id = 1')->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings · Admin · <?= APP_NAME ?></title>
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
<main class="app-main">
    <div class="admin-page-head mb-16">
        <h1 style="margin:0;"><?= icon('shield', 20) ?> Admin portal</h1>
        <a href="admin_profile.php" class="btn btn-text btn-small"><?= icon('user', 16) ?> My profile</a>
    </div>

    <?php $adminCurrent = 'admin_settings.php'; include __DIR__ . '/includes/admin_nav.php'; ?>

    <?php if ($success = flash_get('success')): ?>
        <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2 style="margin-top:0;font-size:16px;">Platform settings</h2>
        <p class="muted" style="font-size:13px;margin:0 0 8px;">
            These are real, enforced switches — turning one off actually changes what non-admin members
            can do right now, not just a label. Your own admin account always keeps full access.
        </p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <div class="settings-row">
                <div class="settings-row-text">
                    <strong>Recipe submissions</strong>
                    <small>Let members submit their own new recipes via "My Recipes."</small>
                </div>
                <label class="pref-chip <?= $settings['allow_recipe_submissions'] ? 'is-active' : '' ?>" style="display:inline-flex;">
                    <input type="checkbox" name="allow_recipe_submissions" <?= $settings['allow_recipe_submissions'] ? 'checked' : '' ?>>
                    <?= $settings['allow_recipe_submissions'] ? 'On' : 'Off' ?>
                </label>
            </div>

            <div class="settings-row">
                <div class="settings-row-text">
                    <strong>AI recipe matching</strong>
                    <small>Enable the "Get AI ideas" button on the pantry page (still requires a Gemini API key to be configured).</small>
                </div>
                <label class="pref-chip <?= $settings['enable_ai_matching'] ? 'is-active' : '' ?>" style="display:inline-flex;">
                    <input type="checkbox" name="enable_ai_matching" <?= $settings['enable_ai_matching'] ? 'checked' : '' ?>>
                    <?= $settings['enable_ai_matching'] ? 'On' : 'Off' ?>
                </label>
            </div>

            <div class="settings-row">
                <div class="settings-row-text">
                    <strong>Public marketplace</strong>
                    <small>Show the ingredient marketplace nav link and page to members.</small>
                </div>
                <label class="pref-chip <?= $settings['show_marketplace'] ? 'is-active' : '' ?>" style="display:inline-flex;">
                    <input type="checkbox" name="show_marketplace" <?= $settings['show_marketplace'] ? 'checked' : '' ?>>
                    <?= $settings['show_marketplace'] ? 'On' : 'Off' ?>
                </label>
            </div>

            <div class="settings-row">
                <div class="settings-row-text">
                    <strong>Maintenance mode</strong>
                    <small>Block every non-admin page behind a "down for maintenance" notice. Admins keep full access, including this page.</small>
                </div>
                <label class="pref-chip <?= $settings['maintenance_mode'] ? 'is-active' : '' ?>" style="display:inline-flex;">
                    <input type="checkbox" name="maintenance_mode" <?= $settings['maintenance_mode'] ? 'checked' : '' ?>>
                    <?= $settings['maintenance_mode'] ? 'On' : 'Off' ?>
                </label>
            </div>

            <button type="submit" class="btn btn-primary mt-16">Save settings</button>
        </form>
    </div>
</main>
</body>
</html>

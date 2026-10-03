<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Privacy Policy · <?= APP_NAME ?></title>
<meta name="description" content="<?= APP_NAME ?>'s Privacy Policy.">
<meta name="robots" content="noindex">
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=16">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body class="landing-body">
<header class="app-nav landing-nav">
    <a class="app-nav-brand" href="landing.php"><?= nutritale_logo_svg(28) ?> <?= brand_wordmark_html() ?></a>
    <div class="app-nav-user">
        <?= render_theme_toggle() ?>
        <a href="landing.php" class="btn btn-text btn-small">Back home</a>
    </div>
</header>
<main class="landing-main" style="padding:40px 20px;">
    <div class="card" style="max-width:640px;margin:0 auto;">
        <h1 style="margin-top:0;">Privacy Policy</h1>
        <p class="muted">
            This page isn't published yet — <?= APP_NAME ?>'s full Privacy Policy
            (what account and pantry data we collect, how it's used, and your
            rights over it) is being finalized before launch.
        </p>
        <p class="muted">
            Questions in the meantime? Email
            <a href="mailto:hello@nutritale.co.za" style="color:var(--green-dark);font-weight:600;">hello@nutritale.co.za</a>.
        </p>
    </div>
</main>
</body>
</html>

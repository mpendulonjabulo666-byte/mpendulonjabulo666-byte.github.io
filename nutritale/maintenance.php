<?php
// Rendered by enforce_maintenance_mode() (includes/functions_core.php) - never
// requested directly, so it assumes config.php/functions_core.php/icons.php
// are already loaded and just prints a response for the current request.
//
// Requested directly, none of that is loaded: config.php never runs, so its
// ini_set('display_errors', '0') never runs either, and the undefined
// ga4_script() below fatals straight into the response - printing the
// server's absolute path on any host whose php.ini defaults display_errors
// on. A direct hit is never a real maintenance render, so hand it to the
// app, which runs the actual maintenance check itself.
if (!function_exists('ga4_script')) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Down for maintenance · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=13">
</head>
<body>
<div style="min-height:100vh;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:24px;text-align:center;">
    <div class="card" style="max-width:420px;padding:32px;">
        <div class="center-text mb-16"><?= nutritale_logo_svg(48) ?></div>
        <h1 style="font-size:20px;">Down for maintenance</h1>
        <p class="muted">We're making some improvements and will be back shortly. Thanks for your patience.</p>
        <a href="login.php" class="btn btn-text btn-small mt-16">Admin login</a>
    </div>
</div>
</body>
</html>

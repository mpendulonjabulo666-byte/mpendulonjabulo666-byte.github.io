<?php
// Rendered by enforce_maintenance_mode() (includes/functions.php) - never
// requested directly, so it assumes config.php/functions.php/icons.php
// are already loaded and just prints a response for the current request.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Down for maintenance · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=4">
</head>
<body>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;text-align:center;">
    <div class="card" style="max-width:420px;padding:32px;">
        <div class="center-text mb-16"><?= nutritale_logo_svg(48) ?></div>
        <h1 style="font-size:20px;">Down for maintenance</h1>
        <p class="muted">We're making some improvements and will be back shortly. Thanks for your patience.</p>
        <a href="login.php" class="btn btn-text btn-small mt-16">Admin login</a>
    </div>
</div>
</body>
</html>

<?php
// Shared frame for the Terms, Privacy and Refund pages: same head, header
// and card as before, so the three policy pages only hold their own text.

const LEGAL_LAST_UPDATED = '7 November 2026';

function legal_page_start(string $title, string $description): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($title) ?> · <?= APP_NAME ?></title>
<meta name="description" content="<?= h($description) ?>">
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<script src="assets/js/theme-init.js?v=24"></script>
<link rel="stylesheet" href="assets/css/style.css?v=24">
<script src="assets/js/theme-toggle.js?v=24" defer></script>
<style>
    .legal-doc h2 { font-size: 17px; margin: 28px 0 8px; }
    .legal-doc p, .legal-doc li { font-size: 14.5px; line-height: 1.65; }
    .legal-doc ul { padding-left: 20px; }
    .legal-doc a { color: var(--green-dark); font-weight: 600; }
</style>
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
    <article class="card legal-doc" style="max-width:720px;margin:0 auto;">
        <h1 style="margin-top:0;"><?= h($title) ?></h1>
        <p class="muted">Last updated: <?= LEGAL_LAST_UPDATED ?></p>
    <?php
}

function legal_contact_html(): string
{
    $email = h(SUPPORT_EMAIL);
    $html = h(LEGAL_OPERATOR_NAME) . ', email <a href="mailto:' . $email . '">' . $email . '</a>';
    if (defined('LEGAL_ADDRESS') && LEGAL_ADDRESS !== '') {
        $html .= ', ' . h(LEGAL_ADDRESS);
    }
    return $html;
}

function legal_page_end(): void
{
    ?>
        <p class="muted" style="margin-top:28px;">
            <a href="terms.php">Terms of Service</a> ·
            <a href="privacy.php">Privacy Policy</a> ·
            <a href="refund.php">Refund Policy</a>
        </p>
    </article>
</main>
</body>
</html>
    <?php
}

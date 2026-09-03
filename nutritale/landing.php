<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

if (current_user()) {
    redirect('index.php');
}

$recipeCountStmt = db()->query('SELECT COUNT(*) FROM recipes');
$recipeCount = (int)$recipeCountStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= APP_NAME ?> — Cook what you have, plan what you need</title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<header class="app-nav">
    <a class="app-nav-brand" href="landing.php"><?= nutritale_logo_svg(28) ?> <span><?= APP_NAME ?></span></a>
    <div class="app-nav-user">
        <?= render_theme_toggle() ?>
        <a href="login.php" class="btn btn-text btn-small">Log in</a>
        <a href="register.php" class="btn btn-primary btn-small">Get started</a>
    </div>
</header>

<main class="landing-main">
    <section class="landing-hero">
        <div class="landing-hero-grid">
            <div class="landing-hero-copy">
                <h1>Cook what you have.<br>Plan what you need.</h1>
                <p class="muted landing-hero-sub">
                    <?= APP_NAME ?> turns your pantry into recipe ideas, your week into a meal plan, and your plan into a
                    shopping list — with nutrition goals and ratings built in.
                </p>
                <div class="landing-cta">
                    <a href="register.php" class="btn btn-primary">Get started free</a>
                    <a href="login.php" class="btn btn-text">I already have an account</a>
                </div>
                <p class="muted" style="font-size:12.5px;"><?= $recipeCount ?>+ recipes ready to browse today</p>
            </div>
            <div class="landing-hero-photo" style="background-image:url('assets/img/banners/landing-hero-breakfast.jpg');"></div>
        </div>
    </section>

    <section class="feature-grid">
        <div class="feature-card">
            <?= icon('wand', 24) ?>
            <h3>What Can I Make?</h3>
            <p class="muted">Add the ingredients sitting in your kitchen and get ranked recipe matches — matched to your diet first, missing items called out. 3 free tries, then Premium.</p>
        </div>
        <div class="feature-card">
            <?= icon('shopping-cart', 24) ?>
            <h3>Ingredient marketplace</h3>
            <p class="muted">Got surplus ingredients? List them for other members to buy, or pick up what you're missing from someone nearby.</p>
        </div>
        <div class="feature-card">
            <?= icon('calendar', 24) ?>
            <h3>Meal planner</h3>
            <p class="muted">Drag recipes onto a weekly grid by meal, then export or print a shopping list built from what you planned.</p>
        </div>
        <div class="feature-card">
            <?= icon('flame', 24) ?>
            <h3>Nutrition goals</h3>
            <p class="muted">Set daily calorie and macro targets and watch progress bars fill in as you plan your day.</p>
        </div>
        <div class="feature-card">
            <?= icon('star', 24) ?>
            <h3>Ratings &amp; reviews</h3>
            <p class="muted">Every recipe carries real ratings from people who've cooked it — no guessing if it's any good.</p>
        </div>
    </section>

    <section class="landing-vendor">
        <div class="landing-vendor-text">
            <span class="tag mb-16"><?= icon('download', 12) ?> Free download</span>
            <h2>The starter recipe book</h2>
            <p class="muted">
                8 balanced breakfasts, lunches and dinners — with ingredients, macros and step-by-step
                method — bundled into one PDF. No account needed, yours free.
            </p>
            <a href="assets/downloads/nutritale-recipe-book.pdf" class="btn btn-primary" download>Download Now</a>
        </div>
        <div class="book-mockup">
            <div class="book-mockup-inner">
                <img src="assets/img/logo/book-mark.png" alt="The NutriTale starter recipe book" width="700" height="455">
                <span class="tag premium-tag book-badge">8 Free Recipes</span>
            </div>
        </div>
    </section>

    <section class="landing-vendor">
        <div class="landing-vendor-text">
            <span class="tag premium-tag mb-16"><?= icon('wand', 12) ?> Premium</span>
            <h2>Unlimited "What Can I Make?"</h2>
            <p class="muted">
                Everyone gets 3 free ingredient lookups. Go Premium for R<?= number_format(PREMIUM_MONTHLY_PRICE, 2) ?>/month
                and get unlimited AI-matched recipe recommendations, ranked to your diet preferences first — cancel
                any time.
            </p>
            <a href="register.php" class="btn btn-primary">Try it free</a>
        </div>
        <div class="landing-vendor-card card">
            <div class="center-text mb-16"><?= icon('wand', 28) ?></div>
            <span class="tag premium-tag">R<?= number_format(PREMIUM_MONTHLY_PRICE, 2) ?>/month</span>
            <p class="mt-16 muted" style="font-size:13px;">Unlimited lookups &middot; diet-matched ranking &middot; cancel anytime</p>
        </div>
    </section>

    <section class="landing-vendor">
        <div class="landing-vendor-text">
            <span class="tag premium-tag mb-16"><?= icon('wand', 12) ?> For creators</span>
            <h2>Sell your recipes</h2>
            <p class="muted">
                Got recipes worth paying for? Turn on selling in your profile, price any recipe you create, and
                buyers unlock the full ingredients and instructions after a secure PayFast checkout. You keep
                a running ledger of every sale in your vendor dashboard.
            </p>
            <a href="register.php" class="btn btn-primary">Start selling</a>
        </div>
        <div class="landing-vendor-card page-hero-banner" style="background-image:url('assets/img/banners/landing-ribeye.jpg');min-height:220px;">
            <div>
                <div class="recipe-card-meta mb-16" style="color:rgba(255,255,255,0.85);"><span><?= icon('clock', 14) ?> 35 min</span><span><?= icon('flame', 14) ?> 520 cal</span></div>
                <span class="tag premium-tag">R49.00</span>
                <p class="mt-16" style="font-size:13px;">Ingredients &amp; instructions unlock after purchase</p>
            </div>
        </div>
    </section>

    <section class="landing-final-cta">
        <h2>Ready to stop wondering what's for dinner?</h2>
        <a href="register.php" class="btn btn-primary">Create your free account</a>
    </section>
</main>

<footer class="landing-footer muted">
    <?= APP_NAME ?> · <a href="login.php">Log in</a> · <a href="register.php">Sign up</a>
</footer>
</body>
</html>

<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

enforce_maintenance_mode();

if (current_user()) {
    redirect('index.php');
}

$recipeCountStmt = db()->query('SELECT COUNT(*) FROM recipes');
$recipeCount = (int)$recipeCountStmt->fetchColumn();

// The one CTA label/style repeated down the page (hero, after benefits,
// final CTA) - kept as one constant specifically so it can never drift
// into three slightly different asks ("Get started free" / "Try it free" /
// "Create your free account", as the previous version of this page had).
const LANDING_CTA_LABEL = 'Get Started Free';

// REAL testimonials go here once the client has some - each one:
//   ['photo' => 'assets/img/testimonials/whoever.jpg' (or '' for the
//    placeholder avatar below), 'quote' => '...', 'name' => 'First name,
//    context (e.g. "home cook" or "Cape Town")'].
// Deliberately not fabricated - the review section below renders nothing
// at all while this stays empty, rather than shipping fake social proof.
$testimonials = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= APP_NAME ?> — Cook what you have, plan what you need</title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=5">
<script src="assets/js/theme-toggle.js" defer></script>
<script src="assets/js/hero-photo-motion.js" defer></script>
</head>
<body class="landing-body">
<header class="app-nav landing-nav">
    <a class="app-nav-brand" href="landing.php"><?= nutritale_logo_svg(28) ?> <?= brand_wordmark_html() ?></a>
    <div class="app-nav-user">
        <?= render_theme_toggle() ?>
        <a href="login.php" class="btn btn-text btn-small">Log in</a>
        <a href="register.php" class="btn btn-primary btn-small">Get started</a>
    </div>
</header>

<main class="landing-main">
    <div class="landing-glow-field">
        <div class="landing-blob landing-blob-1"></div>
        <div class="landing-blob landing-blob-2"></div>
        <div class="landing-blob landing-blob-3"></div>

        <!-- 1. HERO -->
        <section class="landing-hero">
            <div class="landing-hero-grid">
                <div class="landing-hero-copy">
                    <h1>Never Wonder What<br>to Cook Again</h1>
                    <p class="muted landing-hero-sub">
                        Tell <?= APP_NAME ?> what's in your kitchen and get real recipes back — matched to your
                        diet and checked against your allergies, with a shopping list for whatever's still missing.
                    </p>
                    <div class="landing-cta">
                        <a href="register.php" class="btn btn-primary"><?= LANDING_CTA_LABEL ?></a>
                        <a href="login.php" class="btn btn-text">I already have an account</a>
                    </div>
                    <p class="muted" style="font-size:12.5px;"><?= $recipeCount ?>+ recipes ready to browse today</p>
                </div>
                <div class="landing-hero-photo-wrap">
                    <div class="landing-hero-photo-disc"></div>
                    <div class="landing-hero-photo" style="background-image:url('assets/img/hero/overnight-oats-bowl.jpg');"></div>
                    <span class="landing-float-badge landing-float-badge-cal"><?= icon('flame', 14) ?> 340 cal <span class="muted" style="font-weight:400;">Overnight oats</span></span>
                    <span class="landing-float-badge landing-float-badge-match"><?= icon('wand', 14) ?> Pantry-matched</span>
                </div>
            </div>
        </section>
    </div>

    <!-- 2. TRUST BAR — real, true-today signals, not fabricated logos -->
    <section class="landing-trustbar">
        <div class="landing-trust-item"><?= icon('shield', 20) ?> Secure payments via PayFast</div>
        <div class="landing-trust-item"><?= icon('lock', 20) ?> POPIA-aligned privacy</div>
        <div class="landing-trust-item"><?= icon('wand', 20) ?> AI-powered allergen checking</div>
        <div class="landing-trust-item"><?= icon('leaf', 20) ?> Built for South African kitchens</div>
    </section>

    <!-- 3. BENEFITS — value first, feature second -->
    <section class="landing-section-heading">
        <h2>Everything you need, nothing you don't</h2>
    </section>
    <section class="feature-grid">
        <div class="feature-card landing-glass-card">
            <?= icon('wand', 26) ?>
            <h3>Cook with what you already have</h3>
            <p class="muted">Stop staring into the fridge. List what's on hand and get ranked recipes back in
                seconds — no last-minute grocery run required.</p>
        </div>
        <div class="feature-card landing-glass-card">
            <?= icon('calendar', 26) ?>
            <h3>Your week, planned in minutes</h3>
            <p class="muted">Drop meals onto a weekly planner and get a shopping list built automatically —
                one trip, nothing forgotten.</p>
        </div>
        <div class="feature-card landing-glass-card">
            <?= icon('shield', 26) ?>
            <h3>Recipes that respect your allergies</h3>
            <p class="muted">Every AI suggestion is checked twice — once when we ask it to avoid your allergens,
                again after, when we scan the result and throw out anything that slipped through.</p>
        </div>
    </section>

    <!-- CTA repeat #2: after benefits -->
    <section class="landing-cta-strip">
        <p>Ready to see what you can make tonight?</p>
        <a href="register.php" class="btn btn-primary"><?= LANDING_CTA_LABEL ?></a>
    </section>

    <!-- 4. REVIEWS — structure only; stays hidden entirely until the
         client has real testimonials to put in $testimonials above. No
         fabricated quotes, ever. -->
    <?php if ($testimonials): ?>
        <section class="landing-section-heading">
            <h2>What people are cooking up</h2>
        </section>
        <section class="landing-reviews-grid">
            <?php foreach ($testimonials as $t): ?>
                <div class="review-card landing-glass-card">
                    <div class="review-avatar" style="<?= $t['photo'] !== '' ? "background-image:url('" . h($t['photo']) . "');" : '' ?>">
                        <?= $t['photo'] === '' ? icon('user', 22) : '' ?>
                    </div>
                    <p class="review-quote">&ldquo;<?= h($t['quote']) ?>&rdquo;</p>
                    <p class="review-name"><?= h($t['name']) ?></p>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <!-- 5. FAQ — native <details>/<summary>: expand/collapse with no JS,
         and correct keyboard/screen-reader behaviour for free. -->
    <section class="landing-section-heading">
        <h2>Questions, answered</h2>
    </section>
    <section class="landing-faq">
        <details class="faq-item">
            <summary>Is it free to start?<?= icon('chevron-right', 16) ?></summary>
            <p>Yes. Creating an account, browsing every recipe, and building meal plans costs nothing.
                The AI-powered pantry matcher gives you 3 free tries before Premium.</p>
        </details>
        <details class="faq-item">
            <summary>How does the AI know about my allergies?<?= icon('chevron-right', 16) ?></summary>
            <p>You tell us what to avoid when you join. Every AI suggestion is then checked twice: once when
                we instruct the AI to avoid it, and again afterward when we scan its answer in code and
                discard anything that slipped through — the code check is what actually decides, not the AI's word.</p>
        </details>
        <details class="faq-item">
            <summary>What happens after my free trial?<?= icon('chevron-right', 16) ?></summary>
            <p>You keep everything else. Recipe browsing, meal planning, ratings, and the free rule-based
                pantry matcher all keep working — only unlimited AI-generated ideas need Premium
                (R<?= number_format(PREMIUM_MONTHLY_PRICE, 2) ?>/month).</p>
        </details>
        <details class="faq-item">
            <summary>Is payment secure?<?= icon('chevron-right', 16) ?></summary>
            <p>Yes. Upgrades go through PayFast, a licensed South African payment gateway —
                <?= APP_NAME ?> never sees or stores your card details.</p>
        </details>
        <details class="faq-item">
            <summary>Can I cancel anytime?<?= icon('chevron-right', 16) ?></summary>
            <p>Yes, any time from your profile. No phone call, no waiting period — cancellation takes
                effect immediately.</p>
        </details>
    </section>

    <!-- 6. FINAL CTA -->
    <section class="landing-final-cta">
        <p class="muted" style="margin:0 0 8px;">Stop wondering what's for dinner.</p>
        <h2>Get started with <?= APP_NAME ?> today</h2>
        <a href="register.php" class="btn btn-primary"><?= LANDING_CTA_LABEL ?></a>
    </section>
</main>

<!-- 7. FOOTER — essential links only. -->
<footer class="landing-footer muted">
    <div class="landing-footer-links">
        <!--
            Privacy Policy / Terms of Service / Refund Policy don't exist
            as real pages yet anywhere in this app - these point at where
            they should live once written (same top-level, page-per-file
            convention as login.php/register.php/etc.), not at content
            that exists today.
        -->
        <a href="privacy.php">Privacy Policy</a>
        <a href="terms.php">Terms of Service</a>
        <a href="refund.php">Refund Policy</a>
        <!--
            Placeholder - no real support inbox has been set up for this
            app yet. Replace with a real, monitored address before launch.
        -->
        <a href="mailto:hello@nutritale.co.za">hello@nutritale.co.za</a>
    </div>
    <!-- Social follow icons go here once there are real NutriTale accounts
         to link - none exist yet, so nothing is shown rather than linking
         to a placeholder/guessed handle. -->
    <p style="margin:14px 0 0;">&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
</footer>
</body>
</html>

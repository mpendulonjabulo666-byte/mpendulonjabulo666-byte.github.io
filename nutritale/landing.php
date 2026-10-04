<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';

enforce_maintenance_mode();

if (current_user()) {
    redirect('index.php');
}

$recipeCountStmt = db()->query('SELECT COUNT(*) FROM recipes WHERE is_published = 1');
$recipeCount = (int)$recipeCountStmt->fetchColumn();

// Recipe ring: up to 22 published recipes whose photo has licence data, so
// every photo shown can carry its credit. Random each visit for variety.
// Images are requested at card size (~500px) rather than the 1080px the
// recipe pages use - 22 full-size photos would be several MB.
$attribution = photo_attribution_data();
$ringRecipes = array_values(array_filter(
    db()->query("SELECT id, title, image_url FROM recipes WHERE is_published = 1 AND image_url <> ''")->fetchAll(),
    fn($r) => ($attribution[$r['id']]['image_url'] ?? null) === $r['image_url']
));
shuffle($ringRecipes);
$ringRecipes = array_slice($ringRecipes, 0, 22);
function ring_thumb(string $url): string
{
    if (str_contains($url, 'images.unsplash.com')) {
        return preg_replace('/([?&])w=\d+/', '${1}w=480', $url);
    }
    return preg_replace('#/thumb/(.+)/\d+px-#', '/thumb/$1/500px-', $url);
}

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
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= APP_NAME ?> — Cook what you have, plan what you need</title>
<meta name="description" content="Turn what's already in your kitchen into real meals. NutriTale gives you AI-powered recipe ideas from your pantry, allergy-safe filtering, meal planning, and shopping lists that build themselves.">
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json" crossorigin="use-credentials">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js?v=21"></script>
<link rel="stylesheet" href="assets/css/style.css?v=21">
<script src="assets/js/theme-toggle.js?v=21" defer></script>
<script src="assets/js/hero-photo-motion.js?v=18" defer></script>
<script src="assets/js/recipe-ring.js?v=18" defer></script>
</head>
<body class="landing-body">
<header class="app-nav landing-nav">
    <a class="app-nav-brand" href="landing.php"><?= nutritale_logo_svg(28) ?> <?= brand_wordmark_html() ?></a>
    <div class="app-nav-user">
        <?= render_theme_toggle() ?>
        <a href="login.php" class="ring-pill"><span class="ring-pill-disc"><?= icon('arrow-right', 16) ?></span><span class="ring-pill-label">Log in</span></a>
        <a href="register.php" class="ring-pill ring-pill-strong"><span class="ring-pill-disc"><?= icon('arrow-right', 16) ?></span><span class="ring-pill-label"><span class="lbl-full">Create account</span><span class="lbl-short">Sign up</span></span></a>
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

    <?php if (count($ringRecipes) >= 6): ?>
    <!-- 3b. RECIPE RING — real recipe photos on a tipped, spinning wheel
         (assets/js/recipe-ring.js). Without JS it falls back to a plain
         scrollable row. -->
    <section class="recipe-ring-section" aria-labelledby="recipe-ring-heading">
        <div class="landing-section-heading">
            <h2 id="recipe-ring-heading">A taste of what's inside</h2>
            <p class="muted recipe-ring-sub">From braai-day classics to weeknight bowls. Drag to spin.</p>
        </div>
        <div class="recipe-ring-stage" tabindex="0" role="group" aria-roledescription="carousel"
             aria-label="Featured recipes. Drag, or use the left and right arrow keys, to spin.">
            <?php foreach ($ringRecipes as $i => $r): ?>
                <figure class="recipe-ring-card">
                    <img src="<?= h(ring_thumb($r['image_url'])) ?>" alt="<?= h($r['title']) ?>" loading="lazy" decoding="async" draggable="false">
                    <figcaption><?= h($r['title']) ?></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
        <p class="recipe-ring-caption">
            <strong class="recipe-ring-caption-title"><?= h($ringRecipes[0]['title']) ?></strong>
            <span class="recipe-ring-caption-credit"><?= recipe_photo_credit($ringRecipes[0]['id'], $ringRecipes[0]['image_url'], 'recipe-ring-credit') ?></span>
        </p>
        <details class="recipe-ring-credits">
            <summary>Photo credits</summary>
            <ol>
                <?php foreach ($ringRecipes as $r): ?>
                    <li><?= h($r['title']) ?> — <?= recipe_photo_credit($r['id'], $r['image_url'], 'recipe-ring-credit') ?></li>
                <?php endforeach; ?>
            </ol>
        </details>
    </section>
    <?php endif; ?>

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
                Free accounts also get <?= AI_PANTRY_FREE_DAILY_CAP ?> AI-generated pantry ideas every day,
                on top of the always-free rule-based pantry matcher.</p>
        </details>
        <details class="faq-item">
            <summary>How does the AI know about my allergies?<?= icon('chevron-right', 16) ?></summary>
            <p>You tell us what to avoid when you join. Every AI suggestion is then checked twice: once when
                we instruct the AI to avoid it, and again afterward when we scan its answer in code and
                discard anything that slipped through — the code check is what actually decides, not the AI's word.</p>
        </details>
        <details class="faq-item">
            <summary>What does Premium actually add?<?= icon('chevron-right', 16) ?></summary>
            <p>Every recipe in the library (free accounts can open <?= FREE_RECIPE_LIMIT ?> to start), and a
                higher AI pantry-idea cap — <?= AI_PANTRY_DAILY_CAP ?> a day instead of <?= AI_PANTRY_FREE_DAILY_CAP ?> —
                for R<?= number_format(PREMIUM_MONTHLY_PRICE, 2) ?>/month. Nothing you already have on the free
                plan goes away or expires.</p>
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
            Stub pages (privacy.php/terms.php/refund.php) - each just says
            "not published yet" plus a contact email, noindex'd, so these
            links don't 404 from the public landing page while the real
            policies (Premium billing, marketplace terms, data collection -
            legal/business content, not something to fabricate here) are
            still being written.
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

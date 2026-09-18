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

// Top List: real recipes, real (honest) ratings - render_stars() already
// shows "No reviews yet" rather than a fabricated-looking "0.0 (0)" for a
// recipe nobody's rated yet, same as everywhere else in the app. Ordered
// so a recipe that does have real reviews surfaces first, rather than
// implying a popularity ranking this fresh a site doesn't have yet.
$topListStmt = db()->query(
    'SELECT r.id, r.title, r.description, r.image_url, r.cook_time_minutes, r.calories,
     COALESCE(AVG(rr.rating), 0) AS avg_rating, COUNT(rr.rating) AS rating_count
     FROM recipes r LEFT JOIN recipe_ratings rr ON rr.recipe_id = r.id
     GROUP BY r.id
     ORDER BY rating_count DESC, avg_rating DESC, r.id ASC
     LIMIT 3'
);
$topList = $topListStmt->fetchAll();
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
<link rel="stylesheet" href="assets/css/style.css?v=4">
<script src="assets/js/theme-toggle.js" defer></script>
<!-- Pinned exactly (version + integrity hashes) for the hero's animated
     overnight-oats jar below - three.js loads only on landing.php, not
     anywhere else in the app. -->
<script type="importmap">
{
  "imports": {
    "three": "https://unpkg.com/three@0.184.0/build/three.module.js"
  },
  "integrity": {
    "https://unpkg.com/three@0.184.0/build/three.module.js": "sha384-8FCZ1eVO6it4+pbec2aDtnTrwjWXZLJRC+MAGCIPDgsYnUrl/E0A2YlF8ioMKI/J",
    "https://unpkg.com/three@0.184.0/build/three.core.js": "sha384-dw2ooPewaEIrAgl6oFDBmmBWCE9oW9LxRGcfwZ0hLvEprzo202wXl7vCYHRlSnOT"
  }
}
</script>
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

        <section class="landing-hero">
            <div class="landing-hero-grid">
                <div class="landing-hero-copy">
                    <h1>Cook What You<br>Already Have</h1>
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
                <div class="landing-hero-photo-wrap">
                    <div class="landing-hero-photo-disc"></div>
                    <!-- Falls back to the static photo (same file used before)
                         if WebGL/three.js can't load - it sits behind the
                         canvas, which is transparent wherever nothing's drawn. -->
                    <oats-jar-stage class="landing-hero-photo" style="background-image:url('assets/img/banners/landing-hero-breakfast.jpg');background-size:cover;background-position:85% center;"></oats-jar-stage>
                    <script src="assets/js/oats-jar-stage.js"></script>
                    <span class="landing-float-badge landing-float-badge-cal"><?= icon('flame', 14) ?> 340 cal <span class="muted" style="font-weight:400;">Overnight oats</span></span>
                    <span class="landing-float-badge landing-float-badge-match"><?= icon('wand', 14) ?> Pantry-matched</span>
                </div>
            </div>
        </section>

        <?php if ($topList): ?>
            <section class="top-list-heading">
                <h2>Top List</h2>
                <p class="muted">A few of our recipes to get you started</p>
            </section>
            <section class="top-list-grid mb-16" style="margin-bottom:76px;">
                <?php foreach ($topList as $r): ?>
                    <div class="top-list-card landing-glass-card">
                        <div class="top-list-photo" style="background-image:url('<?= h($r['image_url']) ?>');"></div>
                        <?= render_stars((float)$r['avg_rating'], (int)$r['rating_count'], 15) ?>
                        <h3><?= h($r['title']) ?></h3>
                        <p><?= h($r['description']) ?></p>
                        <div class="top-list-meta">
                            <span><span><?= icon('clock', 14) ?> <?= (int)$r['cook_time_minutes'] ?> min</span><span><?= icon('flame', 14) ?> <?= (int)$r['calories'] ?> cal</span></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>

    <section class="feature-grid">
        <div class="feature-card landing-glass-card">
            <?= icon('wand', 24) ?>
            <h3>What Can I Make?</h3>
            <p class="muted">Add the ingredients sitting in your kitchen and get ranked recipe matches — matched to your diet first, missing items called out. 3 free tries, then Premium.</p>
        </div>
        <div class="feature-card landing-glass-card">
            <?= icon('shopping-cart', 24) ?>
            <h3>Ingredient marketplace</h3>
            <p class="muted">Got surplus ingredients? List them for other members to buy, or pick up what you're missing from someone nearby.</p>
        </div>
        <div class="feature-card landing-glass-card">
            <?= icon('calendar', 24) ?>
            <h3>Meal planner</h3>
            <p class="muted">Drag recipes onto a weekly grid by meal, then export or print a shopping list built from what you planned.</p>
        </div>
        <div class="feature-card landing-glass-card">
            <?= icon('flame', 24) ?>
            <h3>Nutrition goals</h3>
            <p class="muted">Set daily calorie and macro targets and watch progress bars fill in as you plan your day.</p>
        </div>
        <div class="feature-card landing-glass-card">
            <?= icon('star', 24) ?>
            <h3>Ratings &amp; reviews</h3>
            <p class="muted">Every recipe carries real ratings from people who've cooked it — no guessing if it's any good.</p>
        </div>
    </section>

    <section class="landing-vendor landing-glass-card">
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
                <img src="assets/img/logo/book-mark.png" alt="The NutriTale starter recipe book" width="1024" height="525">
                <span class="tag premium-tag book-badge">8 Free Recipes</span>
            </div>
        </div>
    </section>

    <section class="landing-vendor landing-glass-card">
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

    <section class="landing-vendor landing-glass-card">
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
<script type="module">
// Builds the hero's overnight-oats jar - the same procedural model (lathe
// jar profile, layered contents, scattered chia/blueberries/oats/almonds,
// honey drizzle) from the animation concept, adapted only to call
// oats-jar-stage's setObject() instead of the original full-viewport
// three-d-stage's. Geometry/material values are unchanged from the
// tested source.
import * as THREE from 'three';

const stage = document.querySelector('oats-jar-stage');
if (stage) {
    stage.ready.then(() => {
        const model = new THREE.Group();

        const glass = new THREE.MeshStandardMaterial({
            color: 0xdfece9, roughness: 0.06, metalness: 0.0,
            transparent: true, opacity: 0.26, side: THREE.DoubleSide, depthWrite: false
        });
        const lidMat = new THREE.MeshStandardMaterial({ color: 0xcfd3d6, roughness: 0.34, metalness: 0.35 });
        const yogurt = new THREE.MeshStandardMaterial({ color: 0xfaf6ec, roughness: 0.62, metalness: 0 });
        const oatMat = new THREE.MeshStandardMaterial({ color: 0xd9c29a, roughness: 0.85, metalness: 0 });
        const chiaMat = new THREE.MeshStandardMaterial({ color: 0x241f1a, roughness: 0.72, metalness: 0 });
        const berryMat = new THREE.MeshStandardMaterial({ color: 0x2f3f66, roughness: 0.34, metalness: 0 });
        const almondMat = new THREE.MeshStandardMaterial({ color: 0xf0e2c8, roughness: 0.6, metalness: 0 });
        const honeyMat = new THREE.MeshStandardMaterial({ color: 0xd79a2b, roughness: 0.18, metalness: 0, transparent: true, opacity: 0.88 });

        const R = 0.043, WALL = 0.0022;
        const prof = [];
        const push = (x, y) => prof.push(new THREE.Vector2(x, y));
        push(0.0, 0.0);
        push(R - 0.004, 0.0);
        push(R, 0.005);
        push(R, 0.112);
        push(R - 0.004, 0.120);
        push(R - 0.010, 0.131);
        push(0.0335, 0.140);
        push(0.0335, 0.152);
        push(0.0335 - WALL, 0.152);
        push(0.0335 - WALL, 0.139);
        push(R - 0.0125, 0.130);
        push(R - WALL, 0.118);
        push(R - WALL, 0.006);
        push(0.0, 0.006);
        const jar = new THREE.Mesh(new THREE.LatheGeometry(prof, 64), glass);
        model.add(jar);

        const ring = new THREE.Mesh(new THREE.TorusGeometry(0.0342, 0.0018, 12, 48), glass);
        ring.rotation.x = Math.PI / 2;
        ring.position.y = 0.1445;
        model.add(ring);

        const lid = new THREE.Group();
        const band = new THREE.Mesh(new THREE.CylinderGeometry(0.0368, 0.0368, 0.011, 48, 1, true), lidMat);
        const top = new THREE.Mesh(new THREE.CylinderGeometry(0.0368, 0.0368, 0.0016, 48), lidMat);
        top.position.y = 0.0055;
        lid.add(band, top);
        lid.position.set(0.082, 0.0055, 0.03);
        lid.rotation.z = 0.06;
        model.add(lid);

        const inner = R - WALL - 0.0008;
        function layer(mat, y0, y1, r = inner) {
            const m = new THREE.Mesh(new THREE.CylinderGeometry(r, r, y1 - y0, 48), mat);
            m.position.y = (y0 + y1) / 2;
            return m;
        }
        model.add(
            layer(oatMat, 0.0065, 0.026),
            layer(chiaMat, 0.026, 0.040),
            layer(yogurt, 0.040, 0.072),
            layer(berryMat, 0.072, 0.088),
            layer(oatMat, 0.088, 0.107)
        );

        const seedGeo = new THREE.SphereGeometry(0.0016, 8, 6);
        const seeds = new THREE.Group();
        for (let i = 0; i < 90; i++) {
            const s = new THREE.Mesh(seedGeo, chiaMat);
            const a = Math.random() * Math.PI * 2;
            const rr = inner - 0.0006;
            s.position.set(Math.cos(a) * rr, 0.008 + Math.random() * 0.031, Math.sin(a) * rr);
            s.scale.set(1, 0.62, 1.25);
            seeds.add(s);
        }
        model.add(seeds);

        const berryGeo = new THREE.SphereGeometry(0.0085, 20, 16);
        const berries = new THREE.Group();
        for (let i = 0; i < 18; i++) {
            const b = new THREE.Mesh(berryGeo, berryMat);
            const row = i % 2;
            const a = (i / 18) * Math.PI * 4 + Math.random() * 0.3;
            const rr = inner - 0.0062;
            b.position.set(Math.cos(a) * rr, (row ? 0.0775 : 0.0855) + Math.random() * 0.004, Math.sin(a) * rr);
            b.scale.setScalar(0.85 + Math.random() * 0.3);
            b.rotation.set(Math.random(), Math.random(), Math.random());
            berries.add(b);
        }
        model.add(berries);

        const oatGeo = new THREE.CylinderGeometry(0.0046, 0.0046, 0.0009, 12);
        const oats = new THREE.Group();
        for (let i = 0; i < 120; i++) {
            const o = new THREE.Mesh(oatGeo, oatMat);
            const a = Math.random() * Math.PI * 2;
            const rr = Math.sqrt(Math.random()) * (inner - 0.004);
            const onTop = i < 46;
            o.position.set(
                Math.cos(a) * (onTop ? rr : inner - 0.0035),
                onTop ? 0.1065 + Math.random() * 0.002 : 0.089 + Math.random() * 0.018,
                Math.sin(a) * (onTop ? rr : inner - 0.0035)
            );
            o.rotation.set((Math.random() - 0.5) * 1.1, Math.random() * Math.PI, (Math.random() - 0.5) * 1.1);
            o.scale.set(0.8 + Math.random() * 0.5, 1, 0.8 + Math.random() * 0.5);
            oats.add(o);
        }
        model.add(oats);

        const almondGeo = new THREE.SphereGeometry(0.0098, 16, 10);
        const almonds = new THREE.Group();
        for (let i = 0; i < 20; i++) {
            const al = new THREE.Mesh(almondGeo, almondMat);
            const a = Math.random() * Math.PI * 2;
            const rr = Math.sqrt(Math.random()) * (inner - 0.006);
            al.position.set(Math.cos(a) * rr, 0.1088 + Math.random() * 0.004, Math.sin(a) * rr);
            al.scale.set(0.58, 0.12, 1);
            al.rotation.set((Math.random() - 0.5) * 0.5, Math.random() * Math.PI, (Math.random() - 0.5) * 0.5);
            almonds.add(al);
        }
        model.add(almonds);

        const honeyPool = new THREE.Mesh(new THREE.CylinderGeometry(0.019, 0.023, 0.0035, 40), honeyMat);
        honeyPool.position.set(-0.004, 0.1105, 0.002);
        model.add(honeyPool);

        const drizzlePts = [];
        for (let i = 0; i <= 24; i++) {
            const t = i / 24;
            const a = t * Math.PI * 3.2;
            const rr = 0.0175 * (1 - t * 0.7);
            drizzlePts.push(new THREE.Vector3(Math.cos(a) * rr - 0.004, 0.1125 + t * 0.006, Math.sin(a) * rr + 0.002));
        }
        const drizzle = new THREE.Mesh(
            new THREE.TubeGeometry(new THREE.CatmullRomCurve3(drizzlePts), 72, 0.0022, 10, false),
            honeyMat
        );
        model.add(drizzle);

        const drop = new THREE.Mesh(new THREE.SphereGeometry(0.0036, 16, 12), honeyMat);
        drop.position.set(-0.0045, 0.1215, 0.0025);
        drop.scale.set(1, 1.35, 1);
        model.add(drop);

        stage.setObject(model);
    }).catch(() => {
        // WebGL/three.js unavailable - the static background-image on the
        // <oats-jar-stage> element (set in the markup above) is already
        // showing, so there's nothing further to do here.
    });
}
</script>
</body>
</html>

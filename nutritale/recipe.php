<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/icons.php';

$user = require_login();

$id = $_GET['id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rate' && csrf_check()) {
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 0)));
    $review = trim($_POST['review'] ?? '');
    $recipeId = $_POST['recipe_id'] ?? '';
    if ($recipeId !== '' && $rating >= 1) {
        $stmt = db()->prepare(
            'INSERT INTO recipe_ratings (recipe_id, user_id, rating, review) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE rating = VALUES(rating), review = VALUES(review)'
        );
        $stmt->execute([$recipeId, $user['id'], $rating, $review !== '' ? $review : null]);
        flash_set('success', 'Thanks for your rating!');

        $ownerStmt = db()->prepare(
            'SELECT u.id, u.email, u.name, u.email_notifications, r.title
             FROM recipes r JOIN users u ON u.id = r.created_by
             WHERE r.id = ?'
        );
        $ownerStmt->execute([$recipeId]);
        $owner = $ownerStmt->fetch();
        if ($owner && $owner['email_notifications'] && (int)$owner['id'] !== (int)$user['id']) {
            $body = $user['name'] . ' rated your recipe "' . $owner['title'] . '" ' . $rating . '/5.'
                . ($review !== '' ? "\n\nReview: " . $review : '');
            send_notification_email($owner['email'], 'New rating on ' . $owner['title'], $body);
        }
    }
    redirect('recipe.php?id=' . urlencode($recipeId));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'report' && csrf_check()) {
    $recipeId = $_POST['recipe_id'] ?? '';
    $reportReasons = ['incorrect_info', 'inappropriate', 'duplicate', 'other'];
    $reason = in_array($_POST['reason'] ?? '', $reportReasons, true) ? $_POST['reason'] : 'other';
    $details = trim($_POST['details'] ?? '');

    $dupe = db()->prepare('SELECT 1 FROM recipe_reports WHERE recipe_id = ? AND reporter_user_id = ?');
    $dupe->execute([$recipeId, $user['id']]);
    if ($dupe->fetch()) {
        flash_set('error', "You've already reported this recipe — our team will review it.");
    } else {
        db()->prepare('INSERT INTO recipe_reports (recipe_id, reporter_user_id, reason, details) VALUES (?, ?, ?, ?)')
            ->execute([$recipeId, $user['id'], $reason, $details !== '' ? $details : null]);
        flash_set('success', 'Thanks — this recipe has been reported for review.');
    }
    redirect('recipe.php?id=' . urlencode($recipeId));
}

$stmt = db()->prepare('SELECT * FROM recipes WHERE id = ?');
$stmt->execute([$id]);
$recipe = $stmt->fetch();

if (!$recipe) {
    http_response_code(404);
    die('Recipe not found.');
}

// A hidden recipe (recipes.is_published - CONTINUE.md §2.19) behaves as
// not found to everyone except admins, who still need to reach it -
// that's the whole point of admin_recipes.php now listing it, to unhide/
// complete/delete it later. Same "not found," not a locked/paywall state -
// nothing about a hidden recipe implies there's anything to unlock.
if (!$recipe['is_published'] && empty($user['is_admin'])) {
    http_response_code(404);
    die('Recipe not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    db()->prepare('INSERT INTO recipe_views (recipe_id, user_id) VALUES (?, ?)')->execute([$id, $user['id']]);
}

$dietStmt = db()->prepare('SELECT diet_type FROM recipe_diet_tags WHERE recipe_id = ?');
$dietStmt->execute([$id]);
$dietTags = $dietStmt->fetchAll(PDO::FETCH_COLUMN);

$allergenStmt = db()->prepare('SELECT allergen FROM recipe_allergens WHERE recipe_id = ?');
$allergenStmt->execute([$id]);
$allergens = $allergenStmt->fetchAll(PDO::FETCH_COLUMN);

$ingredientStmt = db()->prepare('SELECT * FROM recipe_ingredients WHERE recipe_id = ? ORDER BY order_index');
$ingredientStmt->execute([$id]);
$ingredients = $ingredientStmt->fetchAll();

$stepStmt = db()->prepare('SELECT * FROM recipe_instructions WHERE recipe_id = ? ORDER BY step_number');
$stepStmt->execute([$id]);
$steps = $stepStmt->fetchAll();

$favStmt = db()->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND recipe_id = ?');
$favStmt->execute([$user['id'], $id]);
$isFavorite = (bool)$favStmt->fetch();

$userAllergenStmt = db()->prepare('SELECT allergen FROM user_allergens WHERE user_id = ?');
$userAllergenStmt->execute([$user['id']]);
$userAllergens = $userAllergenStmt->fetchAll(PDO::FETCH_COLUMN);
$allergenConflicts = array_intersect($allergens, $userAllergens);

$ratingStatsStmt = db()->prepare('SELECT AVG(rating) AS avg_rating, COUNT(*) AS rating_count FROM recipe_ratings WHERE recipe_id = ?');
$ratingStatsStmt->execute([$id]);
$ratingStats = $ratingStatsStmt->fetch();
$avgRating = (float)($ratingStats['avg_rating'] ?? 0);
$ratingCount = (int)($ratingStats['rating_count'] ?? 0);

$myRatingStmt = db()->prepare('SELECT rating, review FROM recipe_ratings WHERE recipe_id = ? AND user_id = ?');
$myRatingStmt->execute([$id, $user['id']]);
$myRating = $myRatingStmt->fetch();

$reviewsStmt = db()->prepare(
    'SELECT rr.rating, rr.review, rr.created_at, u.name
     FROM recipe_ratings rr JOIN users u ON u.id = rr.user_id
     WHERE rr.recipe_id = ? AND rr.review IS NOT NULL AND rr.review != ""
     ORDER BY rr.created_at DESC'
);
$reviewsStmt->execute([$id]);
$reviews = $reviewsStmt->fetchAll();

$isOwner = (int)$recipe['created_by'] === (int)$user['id'];
$hasPurchased = false;
if ($recipe['is_premium'] && !$isOwner) {
    $purchaseStmt = db()->prepare("SELECT 1 FROM recipe_purchases WHERE buyer_id = ? AND recipe_id = ? AND status = 'paid'");
    $purchaseStmt->execute([$user['id'], $id]);
    $hasPurchased = (bool)$purchaseStmt->fetch();
}

// A vendor recipe unpublishes if its seller's own Premium lapses (selling
// requires Premium - profile.php) - checked here, not just at the point of
// a new purchase (checkout.php), so someone can't reach the full recipe
// by simply not buying it. Grandfathered: the vendor's own view (so they
// can see their delisted content and know to renew), anyone who already
// paid (revoking access from an existing customer over something the
// *seller* let lapse would be unfair to the buyer), and admins, same as
// every other gate in this app.
if ($recipe['is_premium'] && !$isOwner && !$hasPurchased && empty($user['is_admin'])
    && $recipe['created_by'] !== null && !user_is_currently_premium((int)$recipe['created_by'])) {
    http_response_code(404);
    die('This recipe is not currently available.');
}

$isPurchaseLocked = $recipe['is_premium'] && !$isOwner && !$hasPurchased && empty($user['is_admin']);

// Recipe-library Premium gate, separate from the vendor marketplace
// pay-per-recipe lock above (is_premium/price/recipe_purchases) and from
// the AI pantry matcher's own trial-count gate (pantry.php) - neither of
// those is touched here. Checked only when the purchase lock doesn't
// already apply, so a recipe never shows two different "go pay" messages
// at once (not a real scenario in this app's own data today - vendor
// recipes and platform seed-catalog recipes are disjoint - but kept
// explicit rather than assumed). Admins bypass both, same as everywhere
// else in this app.
// Also covers free accounts opening a free-tier recipe outside their
// FREE_RECIPE_LIMIT set by URL - see recipe_plan_locked().
$isTierLocked = !$isPurchaseLocked && recipe_plan_locked($user, $recipe);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?= ga4_script() ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($recipe['title']) ?> · <?= APP_NAME ?></title>
<link rel="icon" type="image/png" href="assets/img/logo/favicon-64.png">
<link rel="apple-touch-icon" href="assets/img/logo/apple-touch-icon.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2fae66">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="NutriTale">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css?v=10">
<script src="assets/js/theme-toggle.js" defer></script>
<script src="assets/js/photo-credit.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div class="mb-16" style="display:flex;justify-content:space-between;align-items:center;">
        <a href="index.php" class="btn btn-text btn-small"><?= icon('chevron-left', 16) ?> Back to recipes</a>
        <button type="button" class="btn btn-text btn-small" onclick="window.print()"><?= icon('printer', 16) ?> Print</button>
    </div>

    <div class="recipe-detail">
        <div class="recipe-detail-image" title="Click the photo for its credit" style="background-image:url('<?= h($recipe['image_url']) ?>')"></div>
        <?= recipe_photo_credit($recipe['id'], $recipe['image_url'] ?? null, 'recipe-photo-credit') ?>

        <div class="recipe-detail-body">
            <div class="recipe-detail-header">
                <h1><?= h($recipe['title']) ?></h1>
                <div style="display:flex;gap:8px;">
                    <div class="share-wrap print-hide">
                        <button type="button" id="share-btn" class="fav-btn" aria-label="Share recipe"
                            aria-haspopup="true" aria-expanded="false"
                            data-title="<?= h($recipe['title']) ?>" data-text="<?= h($recipe['description']) ?>"
                            data-image="<?= h((string)$recipe['image_url']) ?>">
                            <?= icon('share', 20) ?>
                        </button>
                        <div id="share-menu" class="share-menu" role="menu" hidden>
                            <a role="menuitem" tabindex="-1" id="share-whatsapp" class="share-menu-item" href="#" target="_blank" rel="noopener"><?= icon_whatsapp(18) ?> WhatsApp</a>
                            <a role="menuitem" tabindex="-1" id="share-facebook" class="share-menu-item" href="#" target="_blank" rel="noopener"><?= icon_facebook(18) ?> Facebook</a>
                            <!-- Instagram and TikTok have no public, unauthenticated web
                                 endpoint for posting someone else's link/image the way
                                 WhatsApp's wa.me and Facebook's sharer.php do - both only
                                 accept content through their own native-app share sheet,
                                 which is exactly what navigator.share() above already
                                 reaches on a phone where those apps are installed. There's
                                 nothing a browser-side link could hand them here. -->
                            <button type="button" role="menuitem" tabindex="-1" id="share-copy" class="share-menu-item">Copy link</button>
                        </div>
                    </div>
                    <form method="post" action="favorite_toggle.php" class="print-hide">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="recipe_id" value="<?= h($recipe['id']) ?>">
                        <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI']) ?>">
                        <button type="submit" class="fav-btn <?= $isFavorite ? 'is-active' : '' ?>" aria-label="Toggle favorite">
                            <?= icon('heart', 22) ?>
                        </button>
                    </form>
                </div>
            </div>
            <div id="share-toast" class="share-toast" hidden>Link copied!</div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                <?= render_stars($avgRating, $ratingCount, 16) ?>
                <?php if ($recipe['is_premium']): ?>
                    <span class="tag premium-tag"><?= icon('wand', 12) ?> Premium · R<?= number_format((float)$recipe['price'], 2) ?></span>
                <?php endif; ?>
            </div>
            <p class="muted"><?= h($recipe['description']) ?></p>

            <?php if ($success = flash_get('success')): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>
            <?php if ($reportError = flash_get('error')): ?>
                <div class="alert alert-error"><?= h($reportError) ?></div>
            <?php endif; ?>

            <?php if ($allergenConflicts): ?>
                <div class="alert alert-error">This recipe contains <?= h(implode(', ', $allergenConflicts)) ?>, which you've marked as an allergen to avoid.</div>
            <?php endif; ?>

            <div class="recipe-stats">
                <div><?= icon('clock', 16) ?> <?= (int)$recipe['cook_time_minutes'] ?> min</div>
                <div class="servings-scaler">
                    <?= icon('users', 16) ?> Serves
                    <button type="button" id="servings-minus" aria-label="Fewer servings"><?= icon('minus', 12) ?></button>
                    <span id="servings-value"><?= (int)$recipe['servings'] ?></span>
                    <button type="button" id="servings-plus" aria-label="More servings"><?= icon('plus', 12) ?></button>
                </div>
                <div><?= icon('flame', 16) ?> <?= $recipe['calories'] !== null ? (int)$recipe['calories'] . ' cal' : 'Calories unknown' ?></div>
                <div>Difficulty: <?= h(ucfirst($recipe['difficulty'])) ?></div>
            </div>

            <div class="macro-row" id="nutrition">
                <div class="macro-pill"><strong><?= $recipe['protein_g'] !== null ? (int)$recipe['protein_g'] . 'g' : '—' ?></strong><span>Protein</span></div>
                <div class="macro-pill"><strong><?= $recipe['carbs_g'] !== null ? (int)$recipe['carbs_g'] . 'g' : '—' ?></strong><span>Carbs</span></div>
                <div class="macro-pill"><strong><?= $recipe['fat_g'] !== null ? (int)$recipe['fat_g'] . 'g' : '—' ?></strong><span>Fat</span></div>
                <div class="macro-pill"><strong><?= $recipe['fiber_g'] !== null ? (int)$recipe['fiber_g'] . 'g' : '—' ?></strong><span>Fiber</span></div>
            </div>
            <?= disclaimer('nutrition') ?>

            <?php if ($dietTags): ?>
                <div class="tag-row mb-16">
                    <?php foreach ($dietTags as $tag): ?><span class="tag"><?= h($tag) ?></span><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($allergens): ?>
                <p class="muted">Contains: <?= h(implode(', ', $allergens)) ?></p>
            <?php endif; ?>
            <?php if ($allergens || $allergenConflicts): ?>
                <?= disclaimer('allergens') ?>
            <?php endif; ?>

            <?php if ((int)$recipe['created_by'] === (int)$user['id']): ?>
                <div class="recipe-owner-actions mb-16">
                    <a class="btn btn-text btn-small" href="add_recipe.php?id=<?= urlencode($recipe['id']) ?>"><?= icon('settings', 14) ?> Edit recipe</a>
                    <form method="post" action="recipe_delete.php" onsubmit="return confirm('Delete this recipe?');">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="recipe_id" value="<?= h($recipe['id']) ?>">
                        <button type="submit" class="btn btn-text btn-small" style="color:var(--error);"><?= icon('trash', 14) ?> Delete</button>
                    </form>
                </div>
            <?php else: ?>
                <details class="recipe-owner-actions mb-16">
                    <summary class="btn btn-text btn-small" style="display:inline-flex;cursor:pointer;"><?= icon('alert-triangle', 14) ?> Report this recipe</summary>
                    <form method="post" class="mt-16" style="max-width:360px;">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="action" value="report">
                        <input type="hidden" name="recipe_id" value="<?= h($recipe['id']) ?>">
                        <label class="field">
                            <span>Reason</span>
                            <select name="reason">
                                <option value="incorrect_info">Incorrect information</option>
                                <option value="inappropriate">Inappropriate content</option>
                                <option value="duplicate">Duplicate of another recipe</option>
                                <option value="other">Other</option>
                            </select>
                        </label>
                        <label class="field">
                            <span>Details (optional)</span>
                            <textarea name="details" rows="2" maxlength="255"></textarea>
                        </label>
                        <button type="submit" class="btn btn-text btn-small" style="color:var(--error);">Submit report</button>
                    </form>
                </details>
            <?php endif; ?>

            <?php if (!$isPurchaseLocked): ?>
                <?php
                // Folder-card rail: jumps to the three sections. Face = SVG rect
                // with an SVG mask notching a folder tab out of its top edge.
                $folders = [
                    ['href' => '#ingredients', 'icon' => 'list', 'label' => count($ingredients) . ' ingredients', 'title' => 'Ingredients', 'a' => '#2fae66', 'b' => '#1a6b3e'],
                    ['href' => '#method', 'icon' => 'clock', 'label' => count($steps) . ' steps · ' . (int)$recipe['cook_time_minutes'] . ' min', 'title' => 'Method', 'a' => '#f0b24a', 'b' => '#c9741f'],
                    ['href' => '#nutrition', 'icon' => 'flame', 'label' => ($recipe['calories'] !== null ? (int)$recipe['calories'] . ' kcal' : 'Nutrition'), 'title' => 'Nutrition', 'a' => '#e8667f', 'b' => '#a83a53'],
                ];
                ?>
                <nav class="folder-rail" aria-label="Jump to a section">
                    <?php foreach ($folders as $n => $f): ?>
                        <a class="folder-card" href="<?= $f['href'] ?>" aria-label="<?= h($f['title'] . ': ' . $f['label']) ?>">
                            <span class="folder-sheen" aria-hidden="true"></span>
                            <span class="folder-object" aria-hidden="true" style="--obj-a:<?= $f['b'] ?>;"><?= icon($f['icon'], 26) ?></span>
                            <svg class="folder-face" viewBox="0 0 240 150" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                                <defs>
                                    <linearGradient id="folder-grad-<?= $n ?>" x1="0" y1="0" x2="1" y2="1">
                                        <stop offset="0" stop-color="<?= $f['a'] ?>"/>
                                        <stop offset="1" stop-color="<?= $f['b'] ?>"/>
                                    </linearGradient>
                                    <mask id="folder-mask-<?= $n ?>" maskUnits="userSpaceOnUse" x="0" y="0" width="240" height="150">
                                        <rect width="240" height="150" fill="#fff"/>
                                        <path fill="#000" d="M0 0 H37 C30 0 31 16 26 16 H14 C6 16 0 22 0 30 Z M117 0 C124 0 123 16 128 16 H226 C234 16 240 22 240 30 V0 Z"/>
                                    </mask>
                                </defs>
                                <rect width="240" height="150" rx="16" fill="url(#folder-grad-<?= $n ?>)" mask="url(#folder-mask-<?= $n ?>)"/>
                                <text x="20" y="54" class="folder-face-title"><?= h($f['title']) ?></text>
                            </svg>
                            <span class="folder-label" aria-hidden="true"><?= h($f['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>

            <?php if ($isPurchaseLocked): ?>
                <div class="paywall card">
                    <?= icon('wand', 28) ?>
                    <h2 style="margin:10px 0 4px;">Unlock the full recipe</h2>
                    <p class="muted" style="margin:0 0 16px;">Ingredients and step-by-step instructions for this premium recipe unlock after purchase.</p>
                    <a class="btn btn-primary" href="checkout.php?recipe_id=<?= urlencode($recipe['id']) ?>">Buy for R<?= number_format((float)$recipe['price'], 2) ?></a>
                </div>
            <?php elseif ($isTierLocked): ?>
                <!-- Real teaser content (first 2 ingredients, first step),
                     not the full lists blurred over with CSS - a CSS blur
                     still ships the underlying text to the browser, plainly
                     readable via view-source, which would make this no real
                     gate at all. Only this small, deliberately-safe slice
                     is ever sent when tier-locked. -->
                <div class="recipe-columns recipe-tier-locked">
                    <div class="recipe-tier-teaser" aria-hidden="true">
                        <div>
                            <h2 id="ingredients">Ingredients</h2>
                            <ul class="ingredient-list">
                                <?php foreach (array_slice($ingredients, 0, 2) as $ing): ?>
                                    <li><?= h($ing['display_quantity']) ?> <?= h($ing['name']) ?></li>
                                <?php endforeach; ?>
                                <?php if (count($ingredients) > 2): ?><li>&hellip;</li><?php endif; ?>
                            </ul>
                        </div>
                        <div>
                            <h2 id="method">Instructions</h2>
                            <ol class="step-list">
                                <?php if ($steps): ?><li><?= h($steps[0]['step_text']) ?></li><?php endif; ?>
                                <?php if (count($steps) > 1): ?><li>&hellip;</li><?php endif; ?>
                            </ol>
                        </div>
                    </div>
                    <div class="recipe-tier-lock-overlay">
                        <?= icon('wand', 28) ?>
                        <h2 style="margin:10px 0 4px;">Premium recipe</h2>
                        <p class="muted" style="margin:0 0 16px;">Upgrade to NutriTale Premium to see the full ingredients and step-by-step instructions.</p>
                        <a class="btn btn-primary" href="premium.php">Upgrade to view</a>
                    </div>
                </div>
            <?php else: ?>
                <?php if ($recipe['is_premium'] && $hasPurchased): ?>
                    <p class="alert alert-success">You've purchased this recipe — enjoy!</p>
                <?php endif; ?>
                <div class="recipe-columns">
                    <div>
                        <h2 id="ingredients">Ingredients</h2>
                        <ul class="ingredient-list" id="ingredient-list">
                            <?php foreach ($ingredients as $ing): ?>
                                <li
                                    data-base-qty="<?= h($ing['quantity']) ?>"
                                    data-unit="<?= h($ing['unit']) ?>"
                                    data-display="<?= h($ing['display_quantity']) ?>"
                                    data-name="<?= h($ing['name']) ?>"
                                ><span class="ing-qty"><?= h($ing['display_quantity']) ?></span> <?= h($ing['name']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div>
                        <h2 id="method">Instructions</h2>
                        <ol class="step-list">
                            <?php foreach ($steps as $step): ?>
                                <li><?= h($step['step_text']) ?></li>
                            <?php endforeach; ?>
                        </ol>
                    </div>
                </div>
            <?php endif; ?>

            <div class="reviews-section">
                <h2>Ratings &amp; reviews</h2>
                <form method="post" class="card mb-16 print-hide">
                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="rate">
                    <input type="hidden" name="recipe_id" value="<?= h($recipe['id']) ?>">
                    <div class="rate-input mb-16">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <label class="rate-star">
                                <input type="radio" name="rating" value="<?= $i ?>" <?= ($myRating['rating'] ?? 0) == $i ? 'checked' : '' ?> required>
                                <?= icon('star', 22) ?>
                            </label>
                        <?php endfor; ?>
                    </div>
                    <label class="field">
                        <span>Review (optional)</span>
                        <input type="text" name="review" value="<?= h($myRating['review'] ?? '') ?>" placeholder="What did you think?">
                    </label>
                    <button type="submit" class="btn btn-primary btn-small"><?= $myRating ? 'Update rating' : 'Submit rating' ?></button>
                </form>

                <?php if (!$reviews): ?>
                    <p class="muted">No written reviews yet.</p>
                <?php else: ?>
                    <?php foreach ($reviews as $rev): ?>
                        <div class="review-item">
                            <div class="review-item-head">
                                <strong><?= h($rev['name']) ?></strong>
                                <?= render_stars((float)$rev['rating'], null, 12) ?>
                                <span class="muted"><?= h((new DateTime($rev['created_at']))->format('M j, Y')) ?></span>
                            </div>
                            <p><?= h($rev['review']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
(function () {
    var baseServings = <?= (int)$recipe['servings'] ?>;
    var servings = baseServings;
    var valueEl = document.getElementById('servings-value');
    var items = document.querySelectorAll('#ingredient-list li');

    function render() {
        valueEl.textContent = servings;
        var factor = servings / baseServings;
        items.forEach(function (li) {
            var baseQty = parseFloat(li.getAttribute('data-base-qty'));
            var unit = li.getAttribute('data-unit');
            var qtyEl = li.querySelector('.ing-qty');
            if (!baseQty || !unit) return; // no numeric quantity to scale, keep original display text
            var scaled = baseQty * factor;
            var rounded = Math.round(scaled * 100) / 100;
            qtyEl.textContent = (rounded % 1 === 0 ? rounded : rounded.toFixed(2)) + ' ' + unit;
        });
    }

    document.getElementById('servings-minus').addEventListener('click', function () {
        if (servings > 1) { servings--; render(); }
    });
    document.getElementById('servings-plus').addEventListener('click', function () {
        servings++; render();
    });
})();

</script>
<script src="assets/js/recipe-share.js" defer></script>
</body>
</html>

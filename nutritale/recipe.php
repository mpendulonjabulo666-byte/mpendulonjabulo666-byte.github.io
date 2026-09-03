<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
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

$stmt = db()->prepare('SELECT * FROM recipes WHERE id = ?');
$stmt->execute([$id]);
$recipe = $stmt->fetch();

if (!$recipe) {
    http_response_code(404);
    die('Recipe not found.');
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
$isLocked = $recipe['is_premium'] && !$isOwner && !$hasPurchased && empty($user['is_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($recipe['title']) ?> · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<script src="assets/js/theme-init.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
<script src="assets/js/theme-toggle.js" defer></script>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>
<main class="app-main">
    <div class="mb-16" style="display:flex;justify-content:space-between;align-items:center;">
        <a href="index.php" class="btn btn-text btn-small"><?= icon('chevron-left', 16) ?> Back to recipes</a>
        <button type="button" class="btn btn-text btn-small" onclick="window.print()"><?= icon('printer', 16) ?> Print</button>
    </div>

    <div class="recipe-detail">
        <div class="recipe-detail-image" style="background-image:url('<?= h($recipe['image_url']) ?>')"></div>

        <div class="recipe-detail-body">
            <div class="recipe-detail-header">
                <h1><?= h($recipe['title']) ?></h1>
                <div style="display:flex;gap:8px;">
                    <button type="button" id="share-btn" class="fav-btn print-hide" aria-label="Share recipe"
                        data-title="<?= h($recipe['title']) ?>" data-text="<?= h($recipe['description']) ?>">
                        <?= icon('share', 20) ?>
                    </button>
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
                <div><?= icon('flame', 16) ?> <?= (int)$recipe['calories'] ?> cal</div>
                <div>Difficulty: <?= h(ucfirst($recipe['difficulty'])) ?></div>
            </div>

            <div class="macro-row">
                <div class="macro-pill"><strong><?= (int)$recipe['protein_g'] ?>g</strong><span>Protein</span></div>
                <div class="macro-pill"><strong><?= (int)$recipe['carbs_g'] ?>g</strong><span>Carbs</span></div>
                <div class="macro-pill"><strong><?= (int)$recipe['fat_g'] ?>g</strong><span>Fat</span></div>
                <div class="macro-pill"><strong><?= (int)$recipe['fiber_g'] ?>g</strong><span>Fiber</span></div>
            </div>

            <?php if ($dietTags): ?>
                <div class="tag-row mb-16">
                    <?php foreach ($dietTags as $tag): ?><span class="tag"><?= h($tag) ?></span><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($allergens): ?>
                <p class="muted">Contains: <?= h(implode(', ', $allergens)) ?></p>
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
            <?php endif; ?>

            <?php if ($isLocked): ?>
                <div class="paywall card">
                    <?= icon('wand', 28) ?>
                    <h2 style="margin:10px 0 4px;">Unlock the full recipe</h2>
                    <p class="muted" style="margin:0 0 16px;">Ingredients and step-by-step instructions for this premium recipe unlock after purchase.</p>
                    <a class="btn btn-primary" href="checkout.php?recipe_id=<?= urlencode($recipe['id']) ?>">Buy for R<?= number_format((float)$recipe['price'], 2) ?></a>
                </div>
            <?php else: ?>
                <?php if ($recipe['is_premium'] && $hasPurchased): ?>
                    <p class="alert alert-success">You've purchased this recipe — enjoy!</p>
                <?php endif; ?>
                <div class="recipe-columns">
                    <div>
                        <h2>Ingredients</h2>
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
                        <h2>Instructions</h2>
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

(function () {
    var shareBtn = document.getElementById('share-btn');
    var toast = document.getElementById('share-toast');

    function showToast(text) {
        toast.textContent = text;
        toast.hidden = false;
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function () { toast.hidden = true; }, 2500);
    }

    shareBtn.addEventListener('click', function () {
        var shareData = {
            title: shareBtn.getAttribute('data-title'),
            text: shareBtn.getAttribute('data-text'),
            url: window.location.href,
        };
        if (navigator.share) {
            navigator.share(shareData).catch(function () { /* user cancelled - no-op */ });
            return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(shareData.url)
                .then(function () { showToast('Link copied!'); })
                .catch(function () { showToast(shareData.url); });
        } else {
            showToast(shareData.url);
        }
    });
})();
</script>
</body>
</html>

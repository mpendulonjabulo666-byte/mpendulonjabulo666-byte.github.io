<?php
function render_recipe_card(array $recipe, bool $isFavorite, array $allergenConflicts = []): void
{
    $dietTags = array_filter(explode(',', $recipe['diet_tags'] ?? ''));
    ?>
    <a class="recipe-card" href="recipe.php?id=<?= urlencode($recipe['id']) ?>" data-title="<?= h(mb_strtolower($recipe['title'])) ?>">
        <div class="recipe-card-image" style="background-image:url('<?= h($recipe['image_url']) ?>')">
            <form method="post" action="favorite_toggle.php" class="recipe-card-fav" onclick="event.stopPropagation()">
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="recipe_id" value="<?= h($recipe['id']) ?>">
                <input type="hidden" name="redirect" value="<?= h($_SERVER['REQUEST_URI']) ?>">
                <button type="submit" class="fav-btn <?= $isFavorite ? 'is-active' : '' ?>" aria-label="Toggle favorite">
                    <?= icon('heart', 18) ?>
                </button>
            </form>
        </div>
        <div class="recipe-card-body">
            <h3><?= h($recipe['title']) ?></h3>
            <?= render_stars((float)($recipe['avg_rating'] ?? 0), (int)($recipe['rating_count'] ?? 0), 12) ?>
            <p class="muted recipe-card-desc"><?= h($recipe['description']) ?></p>
            <div class="recipe-card-meta">
                <span><?= icon('clock', 14) ?> <?= (int)$recipe['cook_time_minutes'] ?> min</span>
                <span><?= icon('flame', 14) ?> <?= (int)$recipe['calories'] ?> cal</span>
            </div>
            <?php if ($dietTags): ?>
                <div class="tag-row">
                    <?php foreach ($dietTags as $tag): ?>
                        <span class="tag"><?= h($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($allergenConflicts): ?>
                <p class="allergen-warning"><?= icon('flame', 12) ?> Contains <?= h(implode(', ', $allergenConflicts)) ?></p>
            <?php endif; ?>
        </div>
    </a>
    <?php
}

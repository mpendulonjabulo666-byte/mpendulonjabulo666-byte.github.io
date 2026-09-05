<?php
/** @var array $user Expects $user to be set by the including page. */
?>
<header class="app-nav">
    <a class="app-nav-brand" href="index.php"><?= nutritale_logo_svg(28) ?> <span><?= APP_NAME ?></span></a>

    <input type="checkbox" id="nav-toggle" class="app-nav-toggle-input">
    <label for="nav-toggle" class="app-nav-toggle" aria-label="Toggle menu"><?= icon('list', 20) ?></label>
    <label for="nav-toggle" class="app-nav-backdrop" aria-hidden="true"></label>

    <div class="app-nav-collapsible">
        <label for="nav-toggle" class="app-nav-close" aria-label="Close menu"><?= icon('x', 18) ?></label>
        <nav class="app-nav-links">
            <a href="index.php"><?= icon('list', 18) ?> Recipes</a>
            <a href="pantry.php"><?= icon('wand', 18) ?> What Can I Make?</a>
            <a href="favorites.php"><?= icon('heart', 18) ?> Favorites</a>
            <a href="planner.php"><?= icon('calendar', 18) ?> Planner</a>
            <a href="my_recipes.php"><?= icon('plus', 18) ?> My Recipes</a>
            <a href="marketplace.php"><?= icon('shopping-cart', 18) ?> Marketplace</a>
            <?php if (!empty($user['is_admin'])): ?>
                <a href="admin.php"><?= icon('shield', 18) ?> Admin</a>
            <?php endif; ?>
        </nav>
        <div class="app-nav-user">
            <?php if (empty($user['is_premium_member'])): ?>
                <a href="premium.php" class="btn btn-text btn-small" style="color:var(--green-dark);font-weight:600;"><?= icon('wand', 14) ?> Go Premium</a>
            <?php endif; ?>
            <?= render_theme_toggle() ?>
            <a href="profile.php" class="muted"><?= icon('settings', 16) ?> <?= h($user['name']) ?></a>
            <a href="logout.php" class="btn btn-text btn-small"><?= icon('logout', 16) ?> Log out</a>
        </div>
    </div>
</header>

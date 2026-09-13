<?php
/** @var array $user Expects $user to be set by the including page. */
$navCurrent = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$navLink = function (string $page, string $iconName, string $label) use ($navCurrent) {
    $active = $navCurrent === $page;
    echo '<a href="' . h($page) . '"' . ($active ? ' class="is-active" aria-current="page"' : '') . '>'
        . icon($iconName, 18) . ' ' . h($label) . '</a>';
};
?>
<header class="app-nav">
    <a class="app-nav-brand" href="index.php"><?= nutritale_logo_svg(28) ?> <span><?= APP_NAME ?></span></a>

    <input type="checkbox" id="nav-toggle" class="app-nav-toggle-input">
    <label for="nav-toggle" class="app-nav-toggle" aria-label="Toggle menu"><?= icon('list', 20) ?></label>
    <label for="nav-toggle" class="app-nav-backdrop" aria-hidden="true"></label>

    <div class="app-nav-collapsible">
        <label for="nav-toggle" class="app-nav-close" aria-label="Close menu"><?= icon('x', 18) ?></label>
        <nav class="app-nav-links">
            <?php
            $navLink('index.php', 'list', 'Recipes');
            $navLink('pantry.php', 'wand', 'What Can I Make?');
            $navLink('favorites.php', 'heart', 'Favorites');
            $navLink('planner.php', 'calendar', 'Planner');
            $navLink('my_recipes.php', 'plus', 'My Recipes');
            $navLink('marketplace.php', 'shopping-cart', 'Marketplace');
            ?>
            <?php if (!empty($user['is_admin'])): ?>
                <div class="app-nav-divider"></div>
                <?php $navLink('admin.php', 'shield', 'Admin'); ?>
            <?php endif; ?>
        </nav>
        <div class="app-nav-user">
            <?php if (empty($user['is_premium_member']) && empty($user['is_admin'])): ?>
                <a href="premium.php" class="btn btn-text btn-small" style="color:var(--green-dark);font-weight:600;"><?= icon('wand', 14) ?> Go Premium</a>
            <?php endif; ?>
            <?= render_theme_toggle() ?>
            <a href="profile.php" class="muted"><?= icon('settings', 16) ?> <?= h($user['name']) ?></a>
            <a href="logout.php" class="btn btn-text btn-small"><?= icon('logout', 16) ?> Log out</a>
        </div>
    </div>
</header>

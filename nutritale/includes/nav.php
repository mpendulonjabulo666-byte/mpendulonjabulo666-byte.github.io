<?php
/** @var array $user Expects $user to be set by the including page. */
$navCurrent = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
// The active row gets its icon lifted into a solid circle badge (matching
// the sidebar mockup's one use of that treatment - every other row's icon
// stays bare) plus a trailing chevron every row gets, active or not.
$navLink = function (string $page, string $iconName, string $label) use ($navCurrent) {
    $active = $navCurrent === $page;
    $iconHtml = $active
        ? '<span class="nav-icon-badge">' . icon($iconName, 15) . '</span>'
        : icon($iconName, 18);
    echo '<a href="' . h($page) . '"' . ($active ? ' class="is-active" aria-current="page"' : '') . '>'
        . '<span class="app-nav-link-main">' . $iconHtml . ' ' . h($label) . '</span>'
        . icon('chevron-right', 14)
        . '</a>';
};
?>
<header class="app-nav">
    <a class="app-nav-brand" href="index.php">
        <?= nutritale_logo_svg(36) ?>
        <span class="app-nav-brand-text">
            <?= brand_wordmark_html() ?>
            <span class="app-nav-tagline">Nourish Your Story</span>
        </span>
    </a>

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
                <div class="app-nav-divider"><?= icon('leaf', 12) ?></div>
                <?php $navLink('admin.php', 'shield', 'Admin'); ?>
            <?php endif; ?>
        </nav>
        <div class="app-nav-user">
            <?php if (empty($user['is_premium_member']) && empty($user['is_admin'])): ?>
                <a href="premium.php" class="btn btn-emphasis btn-block">
                    <span class="app-nav-link-main"><?= icon('sparkles', 16) ?> Go Premium</span>
                    <?= icon('chevron-right', 14) ?>
                </a>
            <?php endif; ?>
            <div class="app-nav-user-row">
                <a href="<?= !empty($user['is_admin']) ? 'admin_profile.php' : 'profile.php' ?>" class="muted"><?= icon('settings', 16) ?> <?= h($user['name']) ?></a>
            </div>
            <div class="app-nav-user-row">
                <a href="logout.php" class="btn btn-text btn-small"><?= icon('logout', 16) ?> Logout</a>
                <div class="app-nav-user-actions">
                    <?= render_theme_toggle() ?>
                    <span class="app-nav-leaf-accent" aria-hidden="true"><?= icon('leaf', 14) ?></span>
                </div>
            </div>
        </div>
    </div>
</header>

<?php
/** @var array $user Expects $user to be set by the including page. */
$navCurrent = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
// Page-to-page transitions (style.css, v23): the active tab/row's green
// pill glides to the new page's tab, while its white icon + label are a
// separate layer that fades out as the pill leaves and in as it arrives -
// so two labels never sit on top of each other inside the moving pill.
// The layer's name has to be unique to the page (the same name on both
// pages pairs them up and keeps them still, e.g. after a form on the same
// page reloads it), hence one name per destination.
$vtLabel = function (string $prefix, string $page): string {
    return ' style="view-transition-name: ' . $prefix . preg_replace('/[^a-z0-9_-]/', '', basename($page, '.php')) . '"';
};
// The active row gets its icon lifted into a solid circle badge (matching
// the sidebar mockup's one use of that treatment - every other row's icon
// stays bare) plus a trailing chevron every row gets, active or not.
$navLink = function (string $page, string $iconName, string $label) use ($navCurrent, $vtLabel) {
    $active = $navCurrent === $page;
    $iconHtml = $active
        ? '<span class="nav-icon-badge">' . icon($iconName, 15) . '</span>'
        : icon($iconName, 18);
    echo '<a href="' . h($page) . '"' . ($active ? ' class="is-active" aria-current="page"' : '') . '>'
        . '<span class="app-nav-link-main"' . ($active ? $vtLabel('nav-label-', $page) : '') . '>' . $iconHtml . ' ' . h($label) . '</span>'
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
    <label for="nav-toggle" class="app-nav-toggle" aria-label="Toggle menu" aria-expanded="false"><?= icon('list', 20) ?></label>
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
            if (!empty($user['is_admin']) || platform_setting('show_marketplace')) {
                $navLink('marketplace.php', 'shopping-cart', 'Marketplace');
            }
            ?>
            <?php if (!empty($user['is_admin'])): ?>
                <div class="app-nav-divider"><?= icon('leaf', 12) ?></div>
                <?php $navLink('admin.php', 'shield', 'Admin'); ?>
            <?php endif; ?>
        </nav>
        <div class="app-nav-user">
            <?php if (empty($user['is_premium_member']) && empty($user['is_admin'])): ?>
                <a href="premium.php" class="premium-cta">
                    <span class="app-nav-link-main"><?= icon('sparkles', 16) ?> Go Premium</span>
                    <?= icon('chevron-right', 14) ?>
                </a>
            <?php endif; ?>
            <div class="app-nav-user-row">
                <a href="<?= !empty($user['is_admin']) ? 'admin_profile.php' : 'profile.php' ?>" class="app-nav-identity-link">
                    <?= user_avatar($user['name'], 34, $user['avatar_path'] ?? null) ?>
                    <span class="app-nav-identity-text">
                        <span class="app-nav-identity-name"><?= h($user['name']) ?></span>
                        <span class="app-nav-identity-plan"><?= !empty($user['is_admin']) ? 'Admin' : (!empty($user['is_premium_member']) ? 'Premium' : 'Free plan') ?></span>
                    </span>
                </a>
                <?= render_theme_toggle() ?>
            </div>
            <a href="logout.php" class="ring-pill ring-pill-block">
                <span class="ring-pill-disc"><?= icon('logout', 16) ?></span>
                <span class="ring-pill-label">Log out</span>
            </a>
        </div>
    </div>
</header>

<?php
/* Phone-only bottom tab bar. Hidden from 901px up, where the sidebar
   already covers the same destinations. Only the logged-in app gets this -
   landing.php has its own header and does not include this file.
   Four real tabs plus a raised, glowing center FAB for Add Recipe (the
   floating-pill-with-center-button pattern). Planner stays reachable from
   the hamburger drawer above; it gave up its tabbar slot to the FAB.
   Third element lists every page the tab counts as "current" for. Profile
   needs both: an admin's tab points at admin_profile.php, but they can
   still land on plain profile.php, and the tab should light up either way. */
$tabsBeforeFab = [
    ['index.php', 'list', 'Recipes', ['index.php']],
    ['pantry.php', 'wand', 'Pantry', ['pantry.php']],
];
$tabsAfterFab = [
    ['favorites.php', 'heart', 'Favorites', ['favorites.php']],
    [
        !empty($user['is_admin']) ? 'admin_profile.php' : 'profile.php',
        'user',
        'Profile',
        ['profile.php', 'admin_profile.php'],
    ],
];
$renderTab = function (array $tab) use ($navCurrent, $vtLabel) {
    [$page, $iconName, $label, $activeOn] = $tab;
    $isActive = in_array($navCurrent, $activeOn, true);
    echo '<a href="' . h($page) . '" class="app-tabbar-item' . ($isActive ? ' is-active' : '') . '"'
        . ($isActive ? ' aria-current="page"' : '') . '>'
        . '<span class="app-tabbar-content"' . ($isActive ? $vtLabel('tab-label-', $page) : '') . '>'
        . '<span class="app-tabbar-icon">' . icon($iconName, 20) . '</span>'
        . '<span class="app-tabbar-label">' . h($label) . '</span>'
        . '</span></a>';
};
?>
<nav class="app-tabbar" aria-label="Main">
    <?php foreach ($tabsBeforeFab as $tab) { $renderTab($tab); } ?>
    <?php $fabActive = $navCurrent === 'add_recipe.php'; ?>
    <a href="add_recipe.php" class="app-tabbar-fab<?= $fabActive ? ' is-active' : '' ?>" aria-label="Add recipe"<?= $fabActive ? ' aria-current="page"' : '' ?>>
        <span class="app-tabbar-fab-glow" aria-hidden="true"></span>
        <span class="app-tabbar-fab-icon"<?= $fabActive ? $vtLabel('tab-label-', 'add_recipe.php') : '' ?>><?= icon('plus', 24) ?></span>
    </a>
    <?php foreach ($tabsAfterFab as $tab) { $renderTab($tab); } ?>
</nav>
<script src="assets/js/nav-drawer-keyboard.js?v=18" defer></script>
<?php
// First-visit tour (includes/app_tour.php): automatically on a new account's
// first page in the app, or on request with ?tour=1 (from the profile page).
// Never over onboarding or a checkout hand-off.
if (!in_array($navCurrent, ['onboarding.php', 'checkout.php', 'premium_checkout.php', 'ingredient_checkout.php'], true)
    && (!empty($_GET['tour']) || user_needs_tour($user))) {
    include __DIR__ . '/app_tour.php';
}
?>

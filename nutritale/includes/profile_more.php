<?php
/** @var array $user Phone-only overflow menu for the profile tab.
 *
 * Under 901px the hamburger drawer is gone (the bottom tab bar is the only
 * nav), so every destination that used to live only in the drawer is
 * reachable from here instead: My Recipes, the Meal Planner and Shopping
 * list, Marketplace, Go Premium, the admin portal, the app tour, dark mode
 * and log out. (Planner and Shopping list were missing - with the drawer
 * gone, a phone had no way to reach either.) Hidden from 901px up, where the
 * sidebar still carries all of these. */
$moreAdmin = !empty($user['is_admin']);
$moreLinks = [];
if ($moreAdmin) {
    $moreLinks[] = ['admin.php', 'shield', 'Admin portal', true];
}
$moreLinks[] = ['my_recipes.php', 'plus', 'My Recipes', false];
$moreLinks[] = ['planner.php', 'calendar', 'Meal Planner', false];
$moreLinks[] = ['shopping_list.php', 'shopping-cart', 'Shopping list', false];
if ($moreAdmin || platform_setting('show_marketplace')) {
    $moreLinks[] = ['marketplace.php', 'shopping-cart', 'Marketplace', false];
}
if (!$moreAdmin && empty($user['is_premium_member'])) {
    $moreLinks[] = ['premium.php', 'sparkles', 'Go Premium', false];
}
$moreLinks[] = ['index.php?tour=1', 'target', 'Take the app tour', false];
?>
<section class="profile-more" aria-label="More">
    <h2 class="profile-more-title">More</h2>
    <nav class="profile-more-links">
        <?php foreach ($moreLinks as [$href, $iconName, $label, $strong]): ?>
            <a href="<?= h($href) ?>"<?= $strong ? ' class="is-strong"' : '' ?>>
                <span class="profile-more-main"><?= icon($iconName, 18) ?> <?= h($label) ?></span>
                <?= icon('chevron-right', 14) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="profile-more-row">
        <span>Dark mode</span>
        <?= str_replace(' id="theme-toggle"', '', render_theme_toggle()) ?>
    </div>
    <a href="logout.php" class="ring-pill ring-pill-block">
        <span class="ring-pill-disc"><?= icon('logout', 16) ?></span>
        <span class="ring-pill-label">Log out</span>
    </a>
</section>

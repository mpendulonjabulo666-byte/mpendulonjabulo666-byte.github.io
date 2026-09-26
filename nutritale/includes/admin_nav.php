<?php
/** @var string $adminCurrent Set by the including page to one of the keys below. */
$adminTabs = [
    'admin.php' => ['grid', 'Dashboard'],
    'admin_recipes.php' => ['list', 'Recipes'],
    'admin_categories.php' => ['grid', 'Categories'],
    'admin_users.php' => ['users', 'Users'],
    'admin_reports.php' => ['alert-triangle', 'Reports'],
    'admin_payouts.php' => ['download', 'Payouts'],
    'admin_meal_plans.php' => ['calendar', 'Meal Plans'],
    'admin_analytics.php' => ['bar-chart', 'Analytics'],
    'admin_settings.php' => ['settings', 'Settings'],
];
?>
<nav class="admin-tabbar mb-16">
    <?php foreach ($adminTabs as $page => [$iconName, $label]): ?>
        <a href="<?= h($page) ?>" class="admin-tab<?= $adminCurrent === $page ? ' is-active' : '' ?>">
            <?= icon($iconName, 16) ?> <?= h($label) ?>
        </a>
    <?php endforeach; ?>
</nav>

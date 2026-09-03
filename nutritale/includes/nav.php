<?php
/** @var array $user Expects $user to be set by the including page. */
?>
<header class="app-nav">
    <a class="app-nav-brand" href="index.php"><?= nutritale_logo_svg(28) ?> <span><?= APP_NAME ?></span></a>

    <input type="checkbox" id="nav-toggle" class="app-nav-toggle-input">
    <label for="nav-toggle" class="app-nav-toggle" aria-label="Toggle menu"><?= icon('list', 20) ?></label>

    <div class="app-nav-collapsible">
        <nav class="app-nav-links">
            <a href="index.php"><?= icon('list', 18) ?> Recipes</a>
            <a href="favorites.php"><?= icon('heart', 18) ?> Favorites</a>
            <a href="planner.php"><?= icon('calendar', 18) ?> Planner</a>
            <a href="my_recipes.php"><?= icon('plus', 18) ?> My Recipes</a>
        </nav>
        <div class="app-nav-user">
            <a href="profile.php" class="muted"><?= icon('settings', 16) ?> <?= h($user['name']) ?></a>
            <a href="logout.php" class="btn btn-text btn-small"><?= icon('logout', 16) ?> Log out</a>
        </div>
    </div>
</header>

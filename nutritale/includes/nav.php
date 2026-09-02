<?php
/** @var array $user Expects $user to be set by the including page. */
?>
<header class="app-nav">
    <a class="app-nav-brand" href="index.php"><?= nutritale_logo_svg(28) ?> <span><?= APP_NAME ?></span></a>
    <nav class="app-nav-links">
        <a href="index.php"><?= icon('list', 18) ?> Recipes</a>
        <a href="favorites.php"><?= icon('heart', 18) ?> Favorites</a>
    </nav>
    <div class="app-nav-user">
        <span class="muted"><?= icon('user', 16) ?> <?= h($user['name']) ?></span>
        <a href="logout.php" class="btn btn-text btn-small"><?= icon('logout', 16) ?> Log out</a>
    </div>
</header>

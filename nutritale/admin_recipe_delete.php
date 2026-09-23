<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';

$user = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $recipeId = $_POST['recipe_id'] ?? '';
    $stmt = db()->prepare('DELETE FROM recipes WHERE id = ? AND is_generated = 1');
    $stmt->execute([$recipeId]);
    // Used to flash success unconditionally - is_generated = 1 is a
    // deliberate boundary (the seed catalog stays off-limits to deletion
    // from here), so a request against anything outside it always matched
    // 0 rows and still told the admin "Recipe removed." even though
    // nothing was. Now that admin_recipes.php's listing shows recipes
    // this button was never meant to touch, that false positive was a
    // real risk, not just a theoretical one - checked live for the first
    // time here (CONTINUE.md §2.19).
    if ($stmt->rowCount() > 0) {
        flash_set('success', 'Recipe removed.');
    } else {
        flash_set('error', 'That recipe can\'t be removed from here.');
    }
}

redirect('admin_recipes.php');

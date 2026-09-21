<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_admin();

// Scoped to is_generated = 0 (the seed catalog - the original 8, the
// 40-recipe import batch, and the 45 rows CONTINUE.md §2.18/§2.19
// couldn't explain and hid) - the same boundary admin_recipe_delete.php
// draws around deletion, mirrored here for hiding. A user-submitted
// recipe (is_generated = 1) is removed outright via that existing action
// instead; it was never given its own hide state, and this deliberately
// doesn't start doing that as a side effect of a page-listing change.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $recipeId = $_POST['recipe_id'] ?? '';
    $stmt = db()->prepare('UPDATE recipes SET is_published = 1 - is_published WHERE id = ? AND is_generated = 0');
    $stmt->execute([$recipeId]);
    if ($stmt->rowCount() > 0) {
        flash_set('success', 'Recipe visibility updated.');
    } else {
        flash_set('error', 'That recipe\'s visibility can\'t be changed from here.');
    }
}

redirect('admin_recipes.php');

<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';

$user = require_admin();

// Clears the photo on a built-in (is_generated = 0) recipe, for when the
// Unsplash import attached a picture that doesn't match the dish. Same
// boundary as admin_recipe_toggle_publish.php: user-submitted recipes are
// edited through add_recipe.php instead.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $recipeId = $_POST['recipe_id'] ?? '';
    $stmt = db()->prepare("UPDATE recipes SET image_url = '' WHERE id = ? AND is_generated = 0");
    $stmt->execute([$recipeId]);
    if ($stmt->rowCount() > 0) {
        flash_set('success', 'Photo cleared.');
    } else {
        flash_set('error', 'That recipe has no photo to clear, or can\'t be changed from here.');
    }
}

redirect('admin_recipes.php');

<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $recipeId = $_POST['recipe_id'] ?? '';
    $stmt = db()->prepare('DELETE FROM recipes WHERE id = ? AND is_generated = 1');
    $stmt->execute([$recipeId]);
    flash_set('success', 'Recipe removed.');
}

redirect('admin.php');

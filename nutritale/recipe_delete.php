<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $recipeId = $_POST['recipe_id'] ?? '';
    $stmt = db()->prepare('DELETE FROM recipes WHERE id = ? AND created_by = ?');
    $stmt->execute([$recipeId, $user['id']]);
    flash_set('success', 'Recipe deleted.');
}

redirect('my_recipes.php');

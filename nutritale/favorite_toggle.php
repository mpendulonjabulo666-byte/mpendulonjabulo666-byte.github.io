<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $recipeId = $_POST['recipe_id'] ?? '';

    $stmt = db()->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND recipe_id = ?');
    $stmt->execute([$user['id'], $recipeId]);

    if ($stmt->fetch()) {
        $del = db()->prepare('DELETE FROM favorites WHERE user_id = ? AND recipe_id = ?');
        $del->execute([$user['id'], $recipeId]);
    } else {
        $exists = db()->prepare('SELECT 1 FROM recipes WHERE id = ?');
        $exists->execute([$recipeId]);
        if ($exists->fetch()) {
            $ins = db()->prepare('INSERT INTO favorites (user_id, recipe_id) VALUES (?, ?)');
            $ins->execute([$user['id'], $recipeId]);
        }
    }
}

$redirect = $_POST['redirect'] ?? 'index.php';
if (!str_starts_with($redirect, '/') && str_contains($redirect, '://')) {
    $redirect = 'index.php';
}
redirect($redirect);

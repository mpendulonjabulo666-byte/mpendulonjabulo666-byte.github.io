<?php
// Second half of fetch_recipe_images.php's flow, deliberately separate:
// updates recipes.image_url to assets/img/recipes/{id}.jpg for every file
// still present there - after you've manually reviewed them and deleted
// any that don't actually match the dish. A recipe whose file you deleted
// (or that fetch_recipe_images.php never found a photo for) is left with
// image_url unset, same as if no photo had ever been sourced for it.
//
// Usage: php scripts/apply_recipe_images.php

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/database.php';

$pdo = db();
$dir = __DIR__ . '/../assets/img/recipes';

$recipes = $pdo->query("SELECT id FROM recipes WHERE image_url IS NULL OR image_url = ''")->fetchAll(PDO::FETCH_COLUMN);
if (!$recipes) {
    echo "Every recipe already has an image_url - nothing to do.\n";
    exit(0);
}

$update = $pdo->prepare('UPDATE recipes SET image_url = ? WHERE id = ?');
$applied = 0;
foreach ($recipes as $id) {
    $file = $dir . '/' . $id . '.jpg';
    if (!is_file($file)) {
        continue;
    }
    $update->execute(['assets/img/recipes/' . $id . '.jpg', $id]);
    $applied++;
    echo "Applied: $id\n";
}

echo "\nUpdated $applied recipe(s). " . (count($recipes) - $applied) . " still have no image_url.\n";

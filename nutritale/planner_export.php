<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$user = require_login();

$weekAnchor = $_GET['week'] ?? date('Y-m-d');
try {
    $monday = new DateTime($weekAnchor);
    $monday->modify('monday this week');
} catch (Exception $e) {
    $monday = new DateTime('monday this week');
}
$sunday = (clone $monday)->modify('+6 days');

$stmt = db()->prepare(
    'SELECT mpi.plan_date, mpi.meal_type, r.title, r.calories, r.protein_g, r.carbs_g, r.fat_g
     FROM meal_plan_items mpi JOIN recipes r ON r.id = mpi.recipe_id
     WHERE mpi.user_id = ? AND mpi.plan_date BETWEEN ? AND ?
     ORDER BY mpi.plan_date, FIELD(mpi.meal_type, "breakfast", "lunch", "dinner", "snack")'
);
$stmt->execute([$user['id'], $monday->format('Y-m-d'), $sunday->format('Y-m-d')]);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="meal-plan-' . $monday->format('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Date', 'Day', 'Meal', 'Recipe', 'Calories', 'Protein (g)', 'Carbs (g)', 'Fat (g)'], ',', '"', '\\');
foreach ($rows as $row) {
    $date = new DateTime($row['plan_date']);
    fputcsv($out, [
        $date->format('Y-m-d'), $date->format('D'), ucfirst($row['meal_type']), $row['title'],
        $row['calories'], $row['protein_g'], $row['carbs_g'], $row['fat_g'],
    ], ',', '"', '\\');
}
fclose($out);

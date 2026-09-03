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
    'SELECT ri.name, ri.unit, ri.display_quantity, ri.quantity, ri.category
     FROM meal_plan_items mpi
     JOIN recipe_ingredients ri ON ri.recipe_id = mpi.recipe_id
     WHERE mpi.user_id = ? AND mpi.plan_date BETWEEN ? AND ?'
);
$stmt->execute([$user['id'], $monday->format('Y-m-d'), $sunday->format('Y-m-d')]);

$items = [];
foreach ($stmt->fetchAll() as $row) {
    $key = strtolower(trim($row['name'])) . '|' . strtolower(trim($row['unit']));
    if (!isset($items[$key])) {
        $items[$key] = ['name' => $row['name'], 'unit' => $row['unit'], 'category' => $row['category'] ?: 'other', 'quantity' => 0, 'display' => []];
    }
    $items[$key]['quantity'] += (float)$row['quantity'];
    $items[$key]['display'][] = $row['display_quantity'];
}
ksort($items);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="shopping-list-' . $monday->format('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Category', 'Item', 'Quantity'], ',', '"', '\\');
foreach ($items as $item) {
    $qty = $item['unit'] !== ''
        ? rtrim(rtrim(number_format($item['quantity'], 2, '.', ''), '0'), '.') . ' ' . $item['unit']
        : implode(' + ', array_unique($item['display']));
    fputcsv($out, [ucfirst($item['category']), $item['name'], $qty], ',', '"', '\\');
}
fclose($out);

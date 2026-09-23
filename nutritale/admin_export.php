<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';

require_admin();

$type = $_GET['type'] ?? '';
if (!in_array($type, ['recipes', 'users'], true)) {
    http_response_code(400);
    die('Unknown export type.');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="nutritale-' . $type . '-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');

if ($type === 'recipes') {
    fputcsv($out, ['ID', 'Title', 'Meal type', 'Cuisine', 'Difficulty', 'Author', 'Created']);
    $stmt = db()->query(
        "SELECT r.id, r.title, r.meal_type, r.cuisine, r.difficulty, COALESCE(u.name, 'NutriTale') AS author, r.created_at
         FROM recipes r LEFT JOIN users u ON u.id = r.created_by ORDER BY r.created_at DESC"
    );
    foreach ($stmt as $row) {
        fputcsv($out, [$row['id'], $row['title'], $row['meal_type'], $row['cuisine'], $row['difficulty'], $row['author'], $row['created_at']]);
    }
} else {
    fputcsv($out, ['ID', 'Name', 'Email', 'Admin', 'Vendor', 'Premium', 'Last login', 'Joined']);
    $stmt = db()->query('SELECT id, name, email, is_admin, is_vendor, is_premium_member, last_login_at, created_at FROM users ORDER BY created_at DESC');
    foreach ($stmt as $row) {
        fputcsv($out, [
            $row['id'], $row['name'], $row['email'], $row['is_admin'] ? 'Yes' : 'No',
            $row['is_vendor'] ? 'Yes' : 'No', $row['is_premium_member'] ? 'Yes' : 'No',
            $row['last_login_at'] ?? '', $row['created_at'],
        ]);
    }
}

fclose($out);

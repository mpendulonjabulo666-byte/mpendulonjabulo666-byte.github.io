<?php
// Records that the first-visit tour has been shown (assets/js/app-tour.js
// posts here as soon as it starts), so it never starts again by itself.
// Replaying it from the profile page doesn't need this - that's ?tour=1.
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = current_user();
if (!$user || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_check()) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

try {
    db()->prepare('UPDATE users SET tour_seen_at = NOW() WHERE id = ? AND tour_seen_at IS NULL')->execute([$user['id']]);
    echo json_encode(['ok' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}

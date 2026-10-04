<?php
// Barcode -> product name for the pantry scanner (assets/js/pantry-scan.js).
// Premium-only, enforced here rather than by hiding the button. Product
// data comes from Open Food Facts (free, no key, ODbL - credited in the
// scan panel); responses are cached like the other external APIs.
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions_core.php';
require_once __DIR__ . '/includes/external_recipes.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Please log in again.']);
    exit;
}
if (!user_has_full_library($user)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Barcode scanning is a Premium feature.']);
    exit;
}

$code = preg_replace('/\s+/', '', (string)($_GET['code'] ?? ''));
if (!preg_match('/^\d{6,14}$/', $code)) {
    echo json_encode(['ok' => false, 'error' => "That doesn't look like a product barcode. Try the number printed under the bars."]);
    exit;
}

$url = 'https://world.openfoodfacts.org/api/v2/product/' . $code . '.json?fields=product_name,generic_name,brands';
$data = external_fetch_json([$url])[$url];
if (!is_array($data)) {
    // 'unreachable' tells the scanner JS to retry from the phone's browser
    // (free hosts block outgoing requests; the browser is not affected).
    echo json_encode(['ok' => false, 'unreachable' => true, 'error' => 'The product database is not responding right now. You can still type the ingredient in above.']);
    exit;
}
$p = $data['product'] ?? null;
if ((int)($data['status'] ?? 0) !== 1 || !$p) {
    echo json_encode(['ok' => false, 'error' => "We couldn't find that product. Type what it is in the box above instead."]);
    exit;
}

$name = product_pantry_name((string)($p['product_name'] ?? ''), (string)($p['brands'] ?? ''), (string)($p['generic_name'] ?? ''));
echo json_encode([
    'ok' => $name !== '',
    'name' => $name,
    'product' => trim((string)($p['brands'] ?? '') . ' ' . (string)($p['product_name'] ?? '')),
    'error' => $name === '' ? "Found the product but it has no name listed. Type what it is above." : null,
]);

<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$data = read_json_body();
$barcode = trim($data['barcode'] ?? '');

if ($barcode === '') {
    json_error('A barcode value is required.', 422);
}

$pdo = get_db();
$stmt = $pdo->prepare('SELECT * FROM drugs WHERE barcode = ? LIMIT 1');
$stmt->execute([$barcode]);
$drug = $stmt->fetch();

if (!$drug) {
    json_out([
        'success' => false,
        'status'  => 'not_found',
        'error'   => 'Drug not found in inventory.',
        'barcode' => $barcode,
    ], 404);
}

$daysToExpiry = (strtotime($drug['expiry_date']) - strtotime(date('Y-m-d'))) / 86400;

$status = 'ok';
if ((int)$drug['stock_quantity'] <= 0) {
    $status = 'out_of_stock';
} elseif ($daysToExpiry < 0) {
    $status = 'expired';
} elseif ($daysToExpiry <= NEAR_EXPIRY_DAYS) {
    $status = 'near_expiry';
} elseif ((int)$drug['stock_quantity'] <= (int)$drug['reorder_level']) {
    $status = 'low_stock';
}

json_out([
    'success' => true,
    'status'  => $status,
    'drug'    => $drug,
    'days_to_expiry' => (int)floor($daysToExpiry),
]);

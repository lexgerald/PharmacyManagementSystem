<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

$data = read_json_body();
$barcode  = trim($data['barcode'] ?? '');
$quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;

if ($barcode === '') {
    json_error('A barcode value is required.', 422);
}
if ($quantity <= 0) {
    json_error('Quantity must be at least 1.', 422);
}

$pdo = get_db();

try {
    $pdo->beginTransaction();

    // Lock the row so concurrent scans at another till can't oversell the same stock
    $stmt = $pdo->prepare('SELECT * FROM drugs WHERE barcode = ? FOR UPDATE');
    $stmt->execute([$barcode]);
    $drug = $stmt->fetch();

    if (!$drug) {
        $pdo->rollBack();
        json_out(['success' => false, 'status' => 'not_found', 'error' => 'Drug not found in inventory.'], 404);
    }

    if ((int)$drug['stock_quantity'] <= 0) {
        $pdo->rollBack();
        json_out(['success' => false, 'status' => 'out_of_stock', 'error' => $drug['name'] . ' is out of stock.'], 409);
    }

    if ((int)$drug['stock_quantity'] < $quantity) {
        $pdo->rollBack();
        json_out([
            'success' => false,
            'status'  => 'insufficient_stock',
            'error'   => 'Only ' . $drug['stock_quantity'] . ' unit(s) of ' . $drug['name'] . ' remain in stock.',
        ], 409);
    }

    $unitPrice  = (float)$drug['price'];
    $totalPrice = round($unitPrice * $quantity, 2);

    $update = $pdo->prepare('UPDATE drugs SET stock_quantity = stock_quantity - ? WHERE id = ?');
    $update->execute([$quantity, $drug['id']]);

    $insert = $pdo->prepare(
        'INSERT INTO sales (drug_id, quantity, unit_price, total_price, user_id) VALUES (?, ?, ?, ?, ?)'
    );
    $insert->execute([$drug['id'], $quantity, $unitPrice, $totalPrice, $user['id']]);
    $saleId = (int)$pdo->lastInsertId();

    $pdo->commit();

    $daysToExpiry = (int)floor((strtotime($drug['expiry_date']) - strtotime(date('Y-m-d'))) / 86400);

    json_out([
        'success' => true,
        'sale' => [
            'id'          => $saleId,
            'drug_name'   => $drug['name'],
            'quantity'    => $quantity,
            'unit_price'  => $unitPrice,
            'total_price' => $totalPrice,
            'remaining_stock' => (int)$drug['stock_quantity'] - $quantity,
            'sold_by'     => $user['full_name'],
        ],
        'near_expiry' => $daysToExpiry >= 0 && $daysToExpiry <= NEAR_EXPIRY_DAYS,
        'days_to_expiry' => $daysToExpiry,
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_error('Could not process the sale. Please try again.', 500);
}

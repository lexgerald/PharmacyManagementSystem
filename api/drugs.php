<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_login();

$pdo    = get_db();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handle_get($pdo);
        break;
    case 'POST':
        handle_post($pdo);
        break;
    case 'PUT':
        handle_put($pdo);
        break;
    case 'DELETE':
        handle_delete($pdo);
        break;
    default:
        json_error('Method not allowed.', 405);
}

/** List / search drugs. Supports ?q=search&low_stock=1 */
function handle_get(PDO $pdo): void
{
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM drugs WHERE id = ?');
        $stmt->execute([(int)$_GET['id']]);
        $drug = $stmt->fetch();
        if (!$drug) {
            json_error('Drug not found.', 404);
        }
        json_out(['success' => true, 'drug' => $drug]);
    }

    $sql    = 'SELECT * FROM drugs WHERE 1=1';
    $params = [];

    if (!empty($_GET['q'])) {
        $sql .= ' AND (name LIKE ? OR barcode LIKE ? OR category LIKE ?)';
        $term = '%' . $_GET['q'] . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    if (!empty($_GET['low_stock'])) {
        $sql .= ' AND stock_quantity <= reorder_level';
    }

    $sql .= ' ORDER BY name ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    json_out(['success' => true, 'drugs' => $stmt->fetchAll()]);
}

/** Create a new drug. */
function handle_post(PDO $pdo): void
{
    $data = read_json_body();
    $errors = validate_drug($data);
    if ($errors) {
        json_out(['success' => false, 'error' => 'Validation failed.', 'fields' => $errors], 422);
    }

    // Prevent duplicate barcodes with a friendly message
    $check = $pdo->prepare('SELECT id FROM drugs WHERE barcode = ?');
    $check->execute([$data['barcode']]);
    if ($check->fetch()) {
        json_error('A drug with this barcode already exists.', 409);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO drugs (barcode, name, category, strength, form, stock_quantity, reorder_level, expiry_date, price)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $data['barcode'],
        $data['name'],
        $data['category'],
        $data['strength'],
        $data['form'],
        $data['stock_quantity'],
        $data['reorder_level'],
        $data['expiry_date'],
        $data['price'],
    ]);

    json_out(['success' => true, 'id' => (int)$pdo->lastInsertId()], 201);
}

/** Update an existing drug. Expects JSON body including "id". */
function handle_put(PDO $pdo): void
{
    $data = read_json_body();
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) {
        json_error('A valid drug id is required.', 422);
    }

    $errors = validate_drug($data);
    if ($errors) {
        json_out(['success' => false, 'error' => 'Validation failed.', 'fields' => $errors], 422);
    }

    $check = $pdo->prepare('SELECT id FROM drugs WHERE barcode = ? AND id != ?');
    $check->execute([$data['barcode'], $id]);
    if ($check->fetch()) {
        json_error('Another drug already uses this barcode.', 409);
    }

    $stmt = $pdo->prepare(
        'UPDATE drugs SET barcode=?, name=?, category=?, strength=?, form=?, stock_quantity=?, reorder_level=?, expiry_date=?, price=?
         WHERE id=?'
    );
    $stmt->execute([
        $data['barcode'],
        $data['name'],
        $data['category'],
        $data['strength'],
        $data['form'],
        $data['stock_quantity'],
        $data['reorder_level'],
        $data['expiry_date'],
        $data['price'],
        $id,
    ]);

    if ($stmt->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT id FROM drugs WHERE id = ?');
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            json_error('Drug not found.', 404);
        }
    }

    json_out(['success' => true]);
}

/** Delete a drug. Expects ?id= */
function handle_delete(PDO $pdo): void
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_error('A valid drug id is required.', 422);
    }

    $stmt = $pdo->prepare('DELETE FROM drugs WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        json_error('Drug not found.', 404);
    }

    json_out(['success' => true]);
}

/** Validate incoming drug payloads. Returns an assoc array of field => message, empty if valid. */
function validate_drug(array $data): array
{
    $errors = [];
    $validForms = ['Tablet', 'Capsule', 'Syrup', 'Injection', 'Cream', 'Drops', 'Inhaler', 'Other'];

    if (empty($data['barcode']) || !preg_match('/^[A-Za-z0-9\-]{4,64}$/', $data['barcode'])) {
        $errors['barcode'] = 'Barcode is required (4-64 alphanumeric characters).';
    }
    if (empty($data['name']) || strlen($data['name']) > 150) {
        $errors['name'] = 'Drug name is required (max 150 characters).';
    }
    if (empty($data['category'])) {
        $errors['category'] = 'Category is required.';
    }
    if (!isset($data['form']) || !in_array($data['form'], $validForms, true)) {
        $errors['form'] = 'A valid form is required.';
    }
    if (!isset($data['stock_quantity']) || !is_numeric($data['stock_quantity']) || $data['stock_quantity'] < 0) {
        $errors['stock_quantity'] = 'Stock quantity must be a non-negative number.';
    }
    if (!isset($data['reorder_level']) || !is_numeric($data['reorder_level']) || $data['reorder_level'] < 0) {
        $errors['reorder_level'] = 'Reorder level must be a non-negative number.';
    }
    if (empty($data['expiry_date']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['expiry_date'])) {
        $errors['expiry_date'] = 'A valid expiry date (YYYY-MM-DD) is required.';
    }
    if (!isset($data['price']) || !is_numeric($data['price']) || $data['price'] < 0) {
        $errors['price'] = 'Price must be a non-negative number.';
    }

    return $errors;
}

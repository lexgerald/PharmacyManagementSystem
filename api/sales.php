<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed.', 405);
}

$pdo = get_db();

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(1, (int)($_GET['per_page'] ?? 25)));
$offset  = ($page - 1) * $perPage;

$sql    = 'FROM sales s JOIN drugs d ON d.id = s.drug_id JOIN users u ON u.id = s.user_id WHERE 1=1';
$params = [];

if (!empty($_GET['q'])) {
    $sql .= ' AND d.name LIKE ?';
    $params[] = '%' . $_GET['q'] . '%';
}
if (!empty($_GET['date'])) {
    $sql .= ' AND DATE(s.sold_at) = ?';
    $params[] = $_GET['date'];
}

$countStmt = $pdo->prepare('SELECT COUNT(*) ' . $sql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$dataStmt = $pdo->prepare(
    'SELECT s.id, d.name AS drug_name, d.barcode, s.quantity, s.unit_price, s.total_price, s.sold_at, u.full_name AS user_name '
    . $sql . ' ORDER BY s.sold_at DESC LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset
);
$dataStmt->execute($params);

json_out([
    'success' => true,
    'sales'   => $dataStmt->fetchAll(),
    'pagination' => [
        'page'     => $page,
        'per_page' => $perPage,
        'total'    => $total,
        'pages'    => (int)ceil($total / $perPage),
    ],
]);

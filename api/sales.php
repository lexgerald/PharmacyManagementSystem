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

// Search by drug name
if (!empty($_GET['q'])) {
    $sql .= ' AND d.name LIKE ?';
    $params[] = '%' . $_GET['q'] . '%';
}

// Filter by specific date
if (!empty($_GET['date'])) {
    $sql .= ' AND DATE(s.sold_at) = ?';
    $params[] = $_GET['date'];
}

// Filter by date range (for financial statement)
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $sql .= ' AND DATE(s.sold_at) BETWEEN ? AND ?';
    $params[] = $_GET['start_date'];
    $params[] = $_GET['end_date'];
}
// Filter by specific month and year (alternative to date range)
else if (!empty($_GET['month']) && !empty($_GET['year'])) {
    $month = (int)$_GET['month'];
    $year = (int)$_GET['year'];
    $sql .= ' AND MONTH(s.sold_at) = ? AND YEAR(s.sold_at) = ?';
    $params[] = $month;
    $params[] = $year;
}
// Filter by year only
else if (!empty($_GET['year'])) {
    $year = (int)$_GET['year'];
    $sql .= ' AND YEAR(s.sold_at) = ?';
    $params[] = $year;
}

// If 'limit=all' is passed, ignore pagination and return all records
$limitAll = isset($_GET['limit']) && $_GET['limit'] === 'all';

if (!$limitAll) {
    $countStmt = $pdo->prepare('SELECT COUNT(*) ' . $sql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
}

$dataStmt = $pdo->prepare(
    'SELECT s.id, d.name AS drug_name, d.barcode, s.quantity, s.unit_price, s.total_price, s.sold_at, u.full_name AS user_name '
    . $sql . ' ORDER BY s.sold_at DESC' 
    . ($limitAll ? '' : ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset)
);
$dataStmt->execute($params);

$response = [
    'success' => true,
    'sales'   => $dataStmt->fetchAll(),
];

if (!$limitAll) {
    $response['pagination'] = [
        'page'     => $page,
        'per_page' => $perPage,
        'total'    => $total,
        'pages'    => (int)ceil($total / $perPage),
    ];
}

json_out($response);
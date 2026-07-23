<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed.', 405);
}

$pdo = get_db();

$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$sql = 'SELECT s.id, d.name AS drug_name, d.barcode, s.quantity, s.unit_price, s.total_price, s.sold_at, u.full_name AS user_name
        FROM sales s
        JOIN drugs d ON d.id = s.drug_id
        JOIN users u ON u.id = s.user_id
        WHERE DATE(s.sold_at) BETWEEN ? AND ?
        ORDER BY s.sold_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute([$startDate, $endDate]);
$sales = $stmt->fetchAll();

// Calculate summary
$totalRevenue = 0;
$totalItems = 0;
foreach ($sales as $sale) {
    $totalRevenue += (float)$sale['total_price'];
    $totalItems += (int)$sale['quantity'];
}

json_out([
    'success' => true,
    'sales' => $sales,
    'summary' => [
        'total_transactions' => count($sales),
        'total_revenue' => $totalRevenue,
        'total_items' => $totalItems,
        'average_transaction' => count($sales) > 0 ? $totalRevenue / count($sales) : 0
    ]
]);
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed.', 405);
}

$pdo = get_db();

// Total distinct stock items
$totalItems = (int)$pdo->query('SELECT COUNT(*) FROM drugs')->fetchColumn();

// Low stock: stock_quantity <= reorder_level
$lowStockStmt = $pdo->query(
    'SELECT id, name, barcode, stock_quantity, reorder_level
     FROM drugs
     WHERE stock_quantity <= reorder_level
     ORDER BY stock_quantity ASC
     LIMIT 25'
);
$lowStock = $lowStockStmt->fetchAll();
$lowStockCount = (int)$pdo->query('SELECT COUNT(*) FROM drugs WHERE stock_quantity <= reorder_level')->fetchColumn();

// Today's sales count + revenue
$todayStmt = $pdo->query(
    "SELECT COUNT(*) AS cnt, COALESCE(SUM(total_price),0) AS revenue
     FROM sales
     WHERE DATE(sold_at) = CURDATE()"
);
$today = $todayStmt->fetch();

// Recent outgoing activity (last 10 dispensations)
$recentStmt = $pdo->query(
    'SELECT s.id, d.name AS drug_name, s.quantity, s.total_price, s.sold_at, u.full_name AS user_name
     FROM sales s
     JOIN drugs d ON d.id = s.drug_id
     JOIN users u ON u.id = s.user_id
     ORDER BY s.sold_at DESC
     LIMIT 10'
);
$recent = $recentStmt->fetchAll();

// Near-expiry items (within NEAR_EXPIRY_DAYS)
$nearExpiryStmt = $pdo->prepare(
    'SELECT id, name, barcode, expiry_date, stock_quantity
     FROM drugs
     WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
     ORDER BY expiry_date ASC
     LIMIT 25'
);
$nearExpiryStmt->execute([NEAR_EXPIRY_DAYS]);
$nearExpiry = $nearExpiryStmt->fetchAll();

json_out([
    'success' => true,
    'metrics' => [
        'total_items'      => $totalItems,
        'low_stock_count'  => $lowStockCount,
        'today_sales_count'=> (int)$today['cnt'],
        'today_revenue'    => (float)$today['revenue'],
        'near_expiry_count'=> count($nearExpiry),
    ],
    'low_stock'   => $lowStock,
    'near_expiry' => $nearExpiry,
    'recent_activity' => $recent,
]);

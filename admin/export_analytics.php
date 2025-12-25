<?php
session_start();
include '../helper/pdo_connection.php';
require_once '../helper/auth.php';

// Check if user has permission
if (!can_access_page('analytics.php')) {
    die("Access denied");
}

// Get filters
$period = isset($_GET['period']) ? $_GET['period'] : 'this_month';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : 'all';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Helper for date presets (same as in analytics.php)
if ($period !== 'custom') {
    switch ($period) {
        case 'today':
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d');
            break;
        case 'yesterday':
            $startDate = date('Y-m-d', strtotime('-1 day'));
            $endDate = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'this_week':
            $startDate = date('Y-m-d', strtotime('monday this week'));
            $endDate = date('Y-m-d');
            break;
        case 'this_month':
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-d');
            break;
        case 'this_year':
            $startDate = date('Y-01-01');
            $endDate = date('Y-m-d');
            break;
    }
}

// Fetch Data for CSV
$query = "
    SELECT p.name as product_name, p.category, 
           SUM(oi.quantity) as units_sold, 
           SUM(oi.quantity * oi.price) as total_revenue,
           p.stock as current_stock
    FROM Product p
    JOIN Order_Item oi ON p.product_id = oi.product_id
    JOIN `Order` o ON oi.order_id = o.order_id
    WHERE o.order_date BETWEEN ? AND ?
    AND o.status != 'Cancelled'
";

if ($categoryFilter != 'all') {
    $query .= " AND p.category = " . $pdo->quote($categoryFilter);
}

$query .= " GROUP BY p.product_id ORDER BY total_revenue DESC";

$stmt = $pdo->prepare($query);
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Header for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=analytics_report_' . date('Ymd') . '.csv');

$output = fopen('php://output', 'w');

// CSV Headers
fputcsv($output, ['Product Name', 'Category', 'Units Sold', 'Total Revenue (RM)', 'Current Stock']);

// CSV Data
foreach ($data as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;

<?php
session_start();
include __DIR__ . '/../helper/pdo_connection.php';
require_once __DIR__ . '/../helper/auth.php';
require_permission('analytics', 'view');

header('Content-Type: application/json');

$action = $_POST['action'] ?? null;
$date_range = (int)($_POST['date_range'] ?? 30);
$group_by = $_POST['group_by'] ?? 'month';

// Validate inputs
if (!$action || !in_array($action, ['get_analytics'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

if (!in_array($date_range, [7, 30, 183, 365])) {
    $date_range = 30;
}

if (!in_array($group_by, ['month', 'category'])) {
    $group_by = 'month';
}

$response = ['success' => false];

try {
    // Fetch revenue by month or category based on group_by
    if ($group_by === 'month') {
        $stmt = $pdo->query("
            SELECT DATE_FORMAT(order_date, '%Y-%m') AS month, SUM(total_amount) AS revenue
            FROM \`Order\`
            WHERE status != 'Cancelled'
              AND order_date >= DATE_SUB(CURDATE(), INTERVAL {$date_range} DAY)
            GROUP BY DATE_FORMAT(order_date, '%Y-%m')
            ORDER BY month ASC
        ");
    } else {
        // By category
        $stmt = $pdo->query("
            SELECT p.category AS month, SUM(oi.quantity * oi.price) AS revenue
            FROM Order_Item oi
            JOIN \`Order\` o ON oi.order_id = o.order_id
            JOIN Product p ON oi.product_id = p.product_id
            WHERE o.status != 'Cancelled'
              AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL {$date_range} DAY)
            GROUP BY p.category
            ORDER BY revenue DESC
        ");
    }
    $revenue_by_month = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch orders by status (always same)
    $stmt = $pdo->query("SELECT status, COUNT(*) AS count FROM \`Order\` GROUP BY status");
    $orders_by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['revenue_by_month'] = $revenue_by_month;
    $response['orders_by_status'] = $orders_by_status;
    $response['date_range'] = $date_range;
    $response['group_by'] = $group_by;

} catch (Exception $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($response);
?>

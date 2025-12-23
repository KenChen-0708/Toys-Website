<?php
session_start();
require 'helper/pdo_connection.php';
require_once 'helper/auth.php';
redirect_if_admin();

// Check if user is logged in
if (!isset($_SESSION['member'])) {
    header('Location: /pages/auth/login.php');
    exit();
}

$member_id = $_SESSION['member']['id'];

try {
    // Fetch all orders for the member
    $stmt = $pdo->prepare("
        SELECT o.*, 
               COUNT(oi.order_item_id) as item_count
        FROM `Order` o
        LEFT JOIN Order_Item oi ON o.order_id = oi.order_id
        WHERE o.member_id = ?
        GROUP BY o.order_id
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$member_id]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching orders: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        h1 {
            font-size: 32px;
            color: #333;
        }

        .btn-back {
            padding: 12px 24px;
            background: #f0f0f0;
            color: #333;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-back:hover {
            background: #e0e0e0;
        }

        .orders-grid {
            display: grid;
            gap: 20px;
        }

        .order-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
        }

        .order-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .order-number {
            font-size: 20px;
            font-weight: 700;
            color: #333;
        }

        .order-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-shipped {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-delivered {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }

        .detail-value.amount {
            color: #4CAF50;
            font-size: 20px;
        }

        .order-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            text-align: center;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
        }

        .btn-secondary {
            background: #2196F3;
            color: white;
        }

        .btn-secondary:hover {
            background: #0b7dda;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }

        .empty-state h2 {
            font-size: 24px;
            color: #666;
            margin-bottom: 15px;
        }

        .empty-state p {
            color: #999;
            margin-bottom: 25px;
        }

        .shipping-address {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            font-size: 14px;
            color: #555;
            line-height: 1.6;
        }
    </style>
</head>

<body class="<?php echo (isset($_SESSION['member']['theme']) && $_SESSION['member']['theme'] === 'dark') ? 'dark-theme' : ''; ?>">
    <div class="container">
        <div class="header">
            <h1>Order History</h1>
            <a href="index.php" class="btn-back">← Back to Home</a>
        </div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <h2>No Orders Yet</h2>
                <p>You haven't placed any orders yet. Start shopping to see your orders here!</p>
                <a href="index.php" class="btn btn-primary">Start Shopping</a>
            </div>
        <?php else: ?>
            <div class="orders-grid">
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <span class="order-number">Order #<?php echo $order['order_id']; ?></span>
                            <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                <?php echo $order['status']; ?>
                            </span>
                        </div>

                        <div class="order-details">
                            <div class="detail-item">
                                <span class="detail-label">Order Date</span>
                                <span class="detail-value">
                                    <?php echo date('M j, Y', strtotime($order['order_date'])); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Items</span>
                                <span class="detail-value"><?php echo $order['item_count']; ?> item(s)</span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Total Amount</span>
                                <span class="detail-value amount">
                                    RM<?php echo number_format($order['total_amount'], 2); ?>
                                </span>
                            </div>

                            <div class="detail-item">
                                <span class="detail-label">Shipping Address</span>
                                <div class="shipping-address">
                                    <?php echo htmlspecialchars($order['shipping_address_line1']); ?><br>
                                    <?php if ($order['shipping_address_line2']): ?>
                                        <?php echo htmlspecialchars($order['shipping_address_line2']); ?><br>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($order['shipping_city']); ?>,
                                    <?php echo htmlspecialchars($order['shipping_state']); ?>
                                </div>
                            </div>
                        </div>

                        <div class="order-actions">
                            <a href="order_details.php?order_id=<?php echo $order['order_id']; ?>"
                                class="btn btn-secondary">View Details</a>

                            <?php if ($order['status'] === 'Pending'): ?>
                                <a href="payment.php?order_id=<?php echo $order['order_id']; ?>"
                                    class="btn btn-primary">Complete Payment</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
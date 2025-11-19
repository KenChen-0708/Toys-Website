<?php
session_start();
require 'helper/pdo_connection.php';

// Check if user is logged in
if (!isset($_SESSION['member'])) {
    header('Location: auth/login.php');
    exit();
}

$member_id = $_SESSION['member']['id'];
$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    header('Location: order_history.php');
    exit();
}

try {
    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT * FROM `Order` 
        WHERE order_id = ? AND member_id = ?
    ");
    $stmt->execute([$order_id, $member_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        header('Location: order_history.php');
        exit();
    }
    
    // Fetch order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.main_image_path
        FROM Order_Item oi
        JOIN Product p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();
    
    // Fetch payment details
    $stmt = $pdo->prepare("SELECT * FROM Payment WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $payment = $stmt->fetch();
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - #<?php echo $order_id; ?></title>
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
            max-width: 1000px;
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
        
        .section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #4CAF50;
            font-weight: 600;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }
        
        .info-value.amount {
            color: #4CAF50;
            font-size: 24px;
        }
        
        .status-badge {
            display: inline-block;
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
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .shipping-address {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            line-height: 1.8;
            color: #555;
        }
        
        .product-item {
            display: flex;
            gap: 20px;
            padding: 20px;
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .product-item:hover {
            border-color: #4CAF50;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 10px;
        }
        
        .product-meta {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .product-price {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f0f0f0;
        }
        
        .price-label {
            color: #666;
            font-size: 14px;
        }
        
        .price-value {
            font-size: 18px;
            font-weight: 700;
            color: #4CAF50;
        }
        
        .order-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 16px;
        }
        
        .summary-row.total {
            border-top: 2px solid #ddd;
            margin-top: 10px;
            padding-top: 15px;
            font-size: 22px;
            font-weight: 700;
            color: #4CAF50;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
            margin-right: 10px;
        }
        
        .btn-primary {
            background: #4CAF50;
            color: white;
        }
        
        .btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order #<?php echo $order_id; ?></h1>
            <a href="order_history.php" class="btn-back">← Back to Orders</a>
        </div>
        
        <!-- Order Information -->
        <div class="section">
            <h2 class="section-title">Order Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Order Date</span>
                    <span class="info-value"><?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Order Status</span>
                    <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                        <?php echo $order['status']; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Total Amount</span>
                    <span class="info-value amount">RM<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Payment Information -->
        <?php if ($payment): ?>
        <div class="section">
            <h2 class="section-title">Payment Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Payment Method</span>
                    <span class="info-value"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Payment Date</span>
                    <span class="info-value"><?php echo date('F j, Y g:i A', strtotime($payment['payment_date'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Payment Status</span>
                    <span class="status-badge status-<?php echo strtolower($payment['status']); ?>">
                        <?php echo $payment['status']; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Amount Paid</span>
                    <span class="info-value" style="color: #4CAF50;">RM<?php echo number_format($payment['amount'], 2); ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Shipping Address -->
        <div class="section">
            <h2 class="section-title">Shipping Address</h2>
            <div class="shipping-address">
                <?php echo htmlspecialchars($order['shipping_address_line1']); ?><br>
                <?php if ($order['shipping_address_line2']): ?>
                    <?php echo htmlspecialchars($order['shipping_address_line2']); ?><br>
                <?php endif; ?>
                <?php echo htmlspecialchars($order['shipping_city']); ?>, 
                <?php echo htmlspecialchars($order['shipping_state']); ?> 
                <?php echo htmlspecialchars($order['shipping_postal_code']); ?><br>
                <?php echo htmlspecialchars($order['shipping_country']); ?>
            </div>
        </div>
        
        <!-- Order Items -->
        <div class="section">
            <h2 class="section-title">Order Items</h2>
            <?php foreach ($order_items as $item): ?>
                <div class="product-item">
                    <?php if ($item['main_image_path']): ?>
                        <img src="<?php echo htmlspecialchars($item['main_image_path']); ?>" 
                             alt="<?php echo htmlspecialchars($item['name']); ?>"
                             class="product-image">
                    <?php else: ?>
                        <img src="images/no-image.png" alt="No image" class="product-image">
                    <?php endif; ?>
                    
                    <div class="product-info">
                        <div class="product-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="product-meta">
                            <strong>Quantity:</strong> <?php echo $item['quantity']; ?>
                        </div>
                        <div class="product-meta">
                            <strong>Unit Price:</strong> RM<?php echo number_format($item['price'], 2); ?>
                        </div>
                        <div class="product-price">
                            <span class="price-label">Subtotal:</span>
                            <span class="price-value">RM<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="order-summary">
                <div class="summary-row total">
                    <span>Total Amount</span>
                    <span>RM<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Actions -->
        <?php if ($order['status'] === 'Pending'): ?>
        <div style="text-align: center; margin-top: 30px;">
            <a href="payment.php?order_id=<?php echo $order_id; ?>" class="btn btn-primary">
                Complete Payment
            </a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>

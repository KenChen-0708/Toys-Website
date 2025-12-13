<?php
session_start();
require '../../stripe-php-master/init.php';
require '../../helper/pdo_connection.php';

// Check if user is logged in
if (!isset($_SESSION['member'])) {
    header('Location: /pages/auth/login.php');
    exit();
}

$member_id = $_SESSION['member']['id'];
$session_id = $_GET['session_id'] ?? null;
$order_id = $_GET['order_id'] ?? null;

if (!$session_id || !$order_id) {
    header('Location: ../../index.php');
    exit();
}

\Stripe\Stripe::setApiKey('sk_test_51SSdhpL3TTFqZeEiC8Zq2GlP6vX1oU0BVr1Il5xr0T5OXKXkewj27TGIxs0AvwzSfcHlY2znCsIlexDHk9gbJOXE00vbXRgf2n');

try {
    // Verify the Stripe session
    $session = \Stripe\Checkout\Session::retrieve($session_id);

    if ($session->payment_status === 'paid') {
        // Start transaction
        $pdo->beginTransaction();

        // Get order details first
        $stmt = $pdo->prepare("
            SELECT o.*, 
                   (SELECT COUNT(*) FROM Order_Item WHERE order_id = o.order_id) as item_count
            FROM `Order` o
            WHERE o.order_id = ? AND o.member_id = ?
        ");
        $stmt->execute([$order_id, $member_id]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new Exception("Order not found");
        }

        // Check if payment record already exists (prevent duplicate)
        $stmt = $pdo->prepare("SELECT payment_id FROM Payment WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $existing_payment = $stmt->fetch();

        if (!$existing_payment) {
            // Determine payment method from Stripe
            $payment_method = 'Credit Card'; // Default
            if (isset($session->payment_method_types[0])) {
                $stripe_method = $session->payment_method_types[0];
                switch ($stripe_method) {
                    case 'card':
                        $payment_method = 'Credit Card';
                        break;
                    case 'fpx':
                        $payment_method = 'Online Banking';
                        break;
                    case 'grabpay':
                        $payment_method = 'E-Wallet';
                        break;
                    default:
                        $payment_method = 'Credit Card';
                }
            }

            // Insert payment record
            $stmt = $pdo->prepare("
                INSERT INTO Payment (order_id, payment_method, payment_date, amount, status)
                VALUES (?, ?, NOW(), ?, 'Completed')
            ");
            $stmt->execute([$order_id, $payment_method, $order['total_amount']]);
        }

        // Update order status to 'Shipped'
        $stmt = $pdo->prepare("
            UPDATE `Order` 
            SET status = 'Shipped' 
            WHERE order_id = ? AND member_id = ?
        ");
        $stmt->execute([$order_id, $member_id]);

        // Commit transaction
        $pdo->commit();
    } else {
        header('Location: payment_cancel.php?order_id=' . $order_id);
        exit();
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Error verifying payment: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .success-container {
            background: white;
            border-radius: 20px;
            padding: 50px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: #4CAF50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease;
        }

        .success-icon::after {
            content: '✓';
            color: white;
            font-size: 60px;
            font-weight: bold;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 15px;
        }

        .message {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .order-details {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
            text-align: left;
        }

        .order-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .order-row:last-child {
            border-bottom: none;
        }

        .order-label {
            color: #666;
            font-weight: 500;
        }

        .order-value {
            color: #333;
            font-weight: 600;
        }

        .order-value.amount {
            color: #4CAF50;
            font-size: 20px;
        }

        .btn {
            display: inline-block;
            padding: 15px 40px;
            margin: 10px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
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

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .shipping-address {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
            text-align: left;
            color: #555;
            line-height: 1.6;
        }
    </style>
</head>

<body class="<?php echo (isset($_SESSION['member']['theme']) && $_SESSION['member']['theme'] === 'dark') ? 'dark-theme' : ''; ?>">
    <div class="success-container">
        <div class="success-icon"></div>

        <h1>Payment Successful!</h1>
        <p class="message">
            Thank you for your purchase. Your order has been confirmed and will be shipped soon.
        </p>

        <div class="order-details">
            <div class="order-row">
                <span class="order-label">Order Number</span>
                <span class="order-value">#<?php echo $order['order_id']; ?></span>
            </div>
            <div class="order-row">
                <span class="order-label">Order Date</span>
                <span class="order-value"><?php echo date('F j, Y', strtotime($order['order_date'])); ?></span>
            </div>
            <div class="order-row">
                <span class="order-label">Items</span>
                <span class="order-value"><?php echo $order['item_count']; ?> item(s)</span>
            </div>
            <div class="order-row">
                <span class="order-label">Total Amount</span>
                <span class="order-value amount">RM<?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
            <?php
            // Get payment details
            $stmt = $pdo->prepare("SELECT * FROM Payment WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $payment = $stmt->fetch();
            ?>
            <?php if ($payment): ?>
                <div class="order-row">
                    <span class="order-label">Payment Method</span>
                    <span class="order-value"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                </div>
                <div class="order-row">
                    <span class="order-label">Payment Status</span>
                    <span class="order-value" style="color: #4CAF50;"><?php echo $payment['status']; ?></span>
                </div>
            <?php endif; ?>
            <div class="order-row">
                <span class="order-label">Order Status</span>
                <span class="order-value" style="color: #2196F3;"><?php echo $order['status']; ?></span>
            </div>
            <div class="order-row">
                <span class="order-label">Shipping Address</span>
                <div class="order-value">
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
            </div>
        </div>

        <div>
            <a href=\"../orders/order_history.php\" class="btn btn-primary">View Order History</a>
            <a href=\"../../index.php\" class="btn btn-secondary">Continue Shopping</a>
        </div>
    </div>
</body>

</html>
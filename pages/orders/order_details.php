<?php
session_start();
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';
is_logged_in();

// Redirect if not logged in
if (!isset($_SESSION['member'])) {
    header('Location: login.php');
    exit;
}

$member_id = $_SESSION['member']['id'];

if (!$pdo) {
    die('Connection failed');
}

if (!isset($_GET['order_id'])) {
    echo 'Missing order id';
    exit;
}
$order_id = (int)$_GET['order_id'];

try {
    // Fetch order + member info with shipping address from Order table
    $stmt = $pdo->prepare("SELECT o.*, m.username, m.email, m.phone_number
                           FROM `Order` o 
                           JOIN Member m ON o.member_id = m.member_id 
                           WHERE o.order_id = ? AND o.member_id = ? LIMIT 1");
    $stmt->execute([$order_id, $member_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo 'Order not found or access denied.';
        exit;
    }

    // Order items
    $stmt = $pdo->prepare("SELECT oi.*, p.name, p.main_image_path FROM Order_Item oi JOIN Product p ON oi.product_id = p.product_id WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Payment
    $stmt = $pdo->prepare("SELECT * FROM Payment WHERE order_id = ? LIMIT 1");
    $stmt->execute([$order_id]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Simple ETA calculation
$eta = 'N/A';
$od = new DateTime($order['order_date']);
if ($order['status'] === 'Shipped') {
    $od->modify('+3 days');
    $eta = $od->format('Y-m-d');
} elseif ($order['status'] === 'Pending') {
    $od->modify('+5 days');
    $eta = $od->format('Y-m-d') . ' to ' . $od->modify('+2 days')->format('Y-m-d');
}

?>
<?php
$title = "Order Details";
?>

<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Order #<?php echo $order_id; ?></title>
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .detail-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 30px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .detail-container h1 {
            color: #2c3e50;
            margin-bottom: 25px;
            border-bottom: 3px solid #a8e6cf;
            padding-bottom: 15px;
        }

        .order-meta {
            background: #f8fffe;
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            border-left: 4px solid #a8e6cf;
        }

        .order-meta p {
            margin: 12px 0;
            font-size: 15px;
            color: #555;
        }

        .order-meta strong {
            display: inline-block;
            width: 180px;
            color: #2c3e50;
        }

        .status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status.Pending {
            background: #fff3cd;
            color: #856404;
        }

        .status.Shipped {
            background: #cce5ff;
            color: #004085;
        }

        .status.Delivered {
            background: #d4edda;
            color: #155724;
        }

        .status.Cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        h3 {
            color: #2c3e50;
            margin: 30px 0 20px;
            font-size: 22px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .item-card {
            background: #fafafa;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.3s ease;
        }

        .item-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .item-card img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 6px;
            margin-bottom: 12px;
        }

        .it-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .item-card>div {
            margin: 5px 0;
            color: #666;
            font-size: 14px;
        }

        .payment-info {
            background: #f8fffe;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #a8e6cf;
        }

        .payment-info p {
            margin: 10px 0;
            color: #555;
        }

        .payment-info strong {
            display: inline-block;
            width: 120px;
            color: #2c3e50;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #a8e6cf;
            color: #2c3e50;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #8dd4b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .back-btn {
            margin-bottom: 20px;
        }
    </style>
</head>

<?php include 'partials/header.php'; ?>
<div class="detail-container">
    <div class="back-btn">
        <a href="orders.php" class="btn">← Back to Orders</a>
    </div>

    <h1>Order #<?php echo $order_id; ?></h1>

    <div class="order-meta">
        <p><strong>Customer:</strong> <?php echo htmlspecialchars($order['username']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
        <?php if (!empty($order['phone_number'])): ?>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone_number']); ?></p>
        <?php endif; ?>
        <p><strong>Order Date:</strong> <?php echo $order['order_date']; ?></p>
        <p><strong>Status:</strong> <span class="status <?php echo $order['status']; ?>"><?php echo $order['status']; ?></span></p>
        <p><strong>Shipping Address:</strong>
            <?php
            $addr_parts = array_filter([
                $order['shipping_address_line1'] ?? '',
                $order['shipping_address_line2'] ?? '',
                $order['shipping_city'] ?? '',
                $order['shipping_state'] ?? '',
                $order['shipping_postal_code'] ?? '',
                $order['shipping_country'] ?? ''
            ]);
            echo htmlspecialchars(implode(', ', $addr_parts));
            ?>
        </p>
        <p><strong>Estimated Delivery:</strong> <?php echo $eta; ?></p>
        <p><strong>Total Amount:</strong> RM<?php echo number_format($order['total_amount'], 2); ?></p>
    </div>


    <h3>Order Items</h3>
    <div class="items-grid">
        <?php foreach ($items as $it): ?>
            <div class="item-card">
                <img src="../../<?php echo htmlspecialchars($it['main_image_path'] ?: 'assets/img/default.jpg'); ?>" alt="<?php echo htmlspecialchars($it['name']); ?>">
                <div class="it-name"><?php echo htmlspecialchars($it['name']); ?></div>
                <div>Quantity: <?php echo (int)$it['quantity']; ?></div>
                <div>Price: RM<?php echo number_format($it['price'], 2); ?></div>
                <div><strong>Subtotal: RM<?php echo number_format($it['price'] * $it['quantity'], 2); ?></strong></div>
            </div>
        <?php endforeach; ?>
    </div>


    <h3>Payment Information</h3>
    <?php if ($payment): ?>
        <div class="payment-info">
            <p><strong>Method:</strong> <?php echo htmlspecialchars($payment['payment_method']); ?></p>
            <p><strong>Amount:</strong> RM<?php echo number_format($payment['amount'], 2); ?></p>
            <p><strong>Status:</strong> <span class="status <?php echo $payment['status']; ?>"><?php echo htmlspecialchars($payment['status']); ?></span></p>
            <p><strong>Payment Date:</strong> <?php echo $payment['payment_date']; ?></p>
        </div>
    <?php else: ?>
        <div class="payment-info">
            <p>No payment record found for this order.</p>
        </div>
    <?php endif; ?>

</div>
</body>

</html>

<?php include 'partials/footer.php'; ?>
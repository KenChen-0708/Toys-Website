<?php
session_start();
require_once '../helper/connection.php';
require_once '../helper/auth.php';
//require_admin();
if (!$db_ok || !$conn) die('Connection failed');

if (!isset($_GET['order_id'])) {
    echo 'Missing order id';
    exit;
}
$order_id = (int)$_GET['order_id'];

// Fetch order + member
$stmt = $conn->prepare("SELECT o.*, m.username, m.email, m.phone_number FROM `Order` o JOIN Member m ON o.member_id = m.member_id WHERE o.order_id = ? LIMIT 1");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();
if (!$order) {
    echo 'Order not found.';
    exit;
}
$stmt->close();

// Order items
$stmt = $conn->prepare("SELECT oi.*, p.name, p.main_image_path FROM Order_Item oi JOIN Product p ON oi.product_id = p.product_id WHERE oi.order_id = ?");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$items_res = $stmt->get_result();
$items = [];
while ($r = $items_res->fetch_assoc()) $items[] = $r;
$stmt->close();

// Payment
$stmt = $conn->prepare("SELECT * FROM Payment WHERE order_id = ? LIMIT 1");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$pay_res = $stmt->get_result();
$payment = $pay_res->fetch_assoc();
$stmt->close();

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
<!doctype html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Order #<?php echo $order_id; ?></title>
    <link rel="stylesheet" href="../assets/css/manage_orders.css">
</head>

<body>
    <div class="detail-container">
        <a href="manage_orders.php" class="btn">← Back</a>
        <h1>Order #<?php echo $order_id; ?></h1>
        <div class="order-meta">
            <p><strong>Member:</strong> <?php echo htmlspecialchars($order['username']); ?> (<?php echo htmlspecialchars($order['email']); ?>)</p>
            <p><strong>Order Date:</strong> <?php echo $order['order_date']; ?></p>
            <p><strong>Status:</strong> <span class="status <?php echo $order['status']; ?>"><?php echo $order['status']; ?></span></p>
            <p><strong>Shipping Address:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
            <p><strong>Estimated Delivery:</strong> <?php echo $eta; ?></p>
            <p><strong>Total:</strong> RM<?php echo number_format($order['total_amount'], 2); ?></p>
        </div>


        <h3>Items</h3>
        <div class="items-grid">
            <?php foreach ($items as $it): ?>
                <div class="item-card">
                    <img src="<?php echo htmlspecialchars($it['main_image_path'] ?: '../assets/img/default.jpg'); ?>" alt="">
                    <div class="it-name"><?php echo htmlspecialchars($it['name']); ?></div>
                    <div>Qty: <?php echo (int)$it['quantity']; ?></div>
                    <div>Price: RM<?php echo number_format($it['price'], 2); ?></div>
                </div>
            <?php endforeach; ?>
        </div>


        <h3>Payment</h3>
        <?php if ($payment): ?>
            <p><strong>Method:</strong> <?php echo htmlspecialchars($payment['payment_method']); ?></p>
            <p><strong>Amount:</strong> RM<?php echo number_format($payment['amount'], 2); ?></p>
            <p><strong>Status:</strong> <?php echo htmlspecialchars($payment['status']); ?></p>
            <p><strong>Paid at:</strong> <?php echo $payment['payment_date']; ?></p>
        <?php else: ?>
            <p>No payment record found.</p>
        <?php endif; ?>


    </div>
</body>

</html>
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

if (!$session_id) {
    header('Location: ../../index.php');
    exit();
}

// Check if checkout data exists in session
if (!isset($_SESSION['checkout_data'])) {
    header('Location: ../checkout/checkout.php?error=session_expired');
    exit();
}

$checkout_data = $_SESSION['checkout_data'];

\Stripe\Stripe::setApiKey('sk_test_51SSdhpL3TTFqZeEiC8Zq2GlP6vX1oU0BVr1Il5xr0T5OXKXkewj27TGIxs0AvwzSfcHlY2znCsIlexDHk9gbJOXE00vbXRgf2n');

try {
    // Verify the Stripe session
    $session = \Stripe\Checkout\Session::retrieve($session_id);

    if ($session->payment_status === 'paid') {
        // Start transaction to create order after successful payment
        $pdo->beginTransaction();

        // Create the order now that payment is successful
        $stmt = $pdo->prepare("
            INSERT INTO `Order` (
                member_id, 
                shipping_address_line1, 
                shipping_address_line2, 
                shipping_city, 
                shipping_state, 
                shipping_postal_code, 
                shipping_country, 
                total_amount, 
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')
        ");
        $stmt->execute([
            $member_id,
            $checkout_data['shipping_address']['address_line1'],
            $checkout_data['shipping_address']['address_line2'],
            $checkout_data['shipping_address']['city'],
            $checkout_data['shipping_address']['state'],
            $checkout_data['shipping_address']['postal_code'],
            $checkout_data['shipping_address']['country'],
            $checkout_data['final_amount']
        ]);
        $order_id = $pdo->lastInsertId();

        // Create order items and update stock
        foreach ($checkout_data['cart_items'] as $item) {
            // Double-check stock availability
            $stmt = $pdo->prepare("SELECT stock FROM Product WHERE product_id = ?");
            $stmt->execute([$item['product_id']]);
            $current_stock = $stmt->fetchColumn();
            
            if ($current_stock < $item['quantity']) {
                throw new Exception("Insufficient stock for product: " . $item['name'] . ". Available: " . $current_stock . ", Requested: " . $item['quantity']);
            }
            
            // Create order item
            $stmt = $pdo->prepare("
                INSERT INTO Order_Item (order_id, product_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $order_id,
                $item['product_id'],
                $item['quantity'],
                $item['price']
            ]);

            // Update stock
            $stmt = $pdo->prepare("
                UPDATE Product 
                SET stock = stock - ? 
                WHERE product_id = ? AND stock >= ?
            ");
            $stmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception("Stock update failed for product: " . $item['name'] . ". Possible concurrent purchase.");
            }
        }

        // Handle voucher usage if applicable
        if ($checkout_data['voucher_data']) {
            $stmt = $pdo->prepare("
                INSERT INTO Voucher_Usage (voucher_id, member_id, order_id)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$checkout_data['voucher_data']['voucher_id'], $member_id, $order_id]);
        }

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
        $stmt->execute([$order_id, $payment_method, $checkout_data['final_amount']]);

        // Clear cart only after successful order creation
        if (!$checkout_data['is_buy_now']) {
            $stmt = $pdo->prepare("DELETE FROM Cart_Item WHERE cart_id IN (SELECT cart_id FROM Cart WHERE member_id = ?)");
            $stmt->execute([$member_id]);
        } else {
            // Clear buy_now session data
            unset($_SESSION['buy_now_item']);
        }

        // Clear checkout data from session
        unset($_SESSION['checkout_data']);

        // Get order details for display
        $stmt = $pdo->prepare("
            SELECT o.*, 
                   (SELECT COUNT(*) FROM Order_Item WHERE order_id = o.order_id) as item_count
            FROM `Order` o
            WHERE o.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();

        // Get order items for receipt
        $stmt = $pdo->prepare("
            SELECT oi.*, p.name as product_name, p.main_image_path as product_image
            FROM Order_Item oi
            JOIN Product p ON oi.product_id = p.product_id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll();

        // Commit transaction
        $pdo->commit();
    } else {
        header('Location: payment_cancel.php');
        exit();
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Payment Success Error: " . $e->getMessage());
    die("Error processing payment: " . $e->getMessage() . "<br><br><a href='../cart/cart.php'>Return to Cart</a>");
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="20" cy="80" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
            z-index: -1;
        }

        .success-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 50px;
            max-width: 700px;
            width: 100%;
            text-align: center;
            box-shadow: 
                0 32px 64px rgba(0, 0, 0, 0.2),
                0 16px 32px rgba(0, 0, 0, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .success-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.8), transparent);
        }

        .success-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: successAnimation 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            box-shadow: 
                0 8px 32px rgba(76, 175, 80, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            position: relative;
        }

        .success-icon::before {
            content: '';
            position: absolute;
            width: 140px;
            height: 140px;
            border: 2px solid rgba(76, 175, 80, 0.2);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .success-icon::after {
            content: '✓';
            color: white;
            font-size: 64px;
            font-weight: bold;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        @keyframes successAnimation {
            0% {
                transform: scale(0) rotate(-180deg);
                opacity: 0;
            }
            50% {
                transform: scale(1.1) rotate(-90deg);
                opacity: 0.8;
            }
            100% {
                transform: scale(1) rotate(0deg);
                opacity: 1;
            }
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 0.3;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.1;
            }
        }

        h1 {
            font-size: 36px;
            background: linear-gradient(135deg, #333, #555);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .message {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
            opacity: 0;
            animation: fadeInUp 0.6s ease 0.3s forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .order-details {
            background: linear-gradient(135deg, #f8f9fa, #ffffff);
            border-radius: 16px;
            padding: 30px;
            margin: 30px 0;
            text-align: left;
            box-shadow: 
                0 8px 32px rgba(0, 0, 0, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(0, 0, 0, 0.05);
            opacity: 0;
            animation: fadeInUp 0.6s ease 0.5s forwards;
        }

        .order-section {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        }

        .order-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .section-title {
            font-size: 14px;
            font-weight: 700;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title::before {
            content: '';
            width: 4px;
            height: 16px;
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-radius: 2px;
        }

        .order-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 14px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.06);
            transition: all 0.2s ease;
        }

        .order-row:hover {
            background: rgba(76, 175, 80, 0.02);
            margin: 0 -15px;
            padding: 14px 15px;
            border-radius: 8px;
        }

        .order-row:last-child {
            border-bottom: none;
        }

        .order-label {
            color: #666;
            font-weight: 500;
            font-size: 15px;
            min-width: 140px;
        }

        .order-value {
            color: #333;
            font-weight: 600;
            font-size: 15px;
            text-align: right;
            flex: 1;
        }

        .order-value.amount {
            color: #4CAF50;
            font-size: 24px;
            font-weight: 700;
        }

        .order-value.status-completed {
            color: #4CAF50;
        }

        .order-value.status-pending {
            color: #FF9800;
        }

        .order-value.status-preparing {
            color: #FF9800;
        }

        .order-value.status-shipped {
            color: #2196F3;
        }

        .btn {
            display: inline-block;
            padding: 16px 32px;
            margin: 8px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            box-shadow: 0 4px 16px rgba(76, 175, 80, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #45a049, #3d8b40);
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(76, 175, 80, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #333;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        .btn-print {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            color: white;
            box-shadow: 0 4px 16px rgba(33, 150, 243, 0.2);
        }

        .btn-print:hover {
            background: linear-gradient(135deg, #1976D2, #1565C0);
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(33, 150, 243, 0.3);
        }

        .button-group {
            opacity: 0;
            animation: fadeInUp 0.6s ease 0.7s forwards;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 40px;
        }

        @media (max-width: 768px) {
            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }

        .shipping-address {
            background: linear-gradient(135deg, #ffffff, #f8f9fa);
            border: 2px solid rgba(76, 175, 80, 0.1);
            border-radius: 12px;
            padding: 20px;
            margin-top: 10px;
            text-align: left;
            color: #555;
            line-height: 1.6;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
        }

        .btn-print {
            background: #2196F3;
            color: white;
        }

        .btn-print:hover {
            background: #1976D2;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(33, 150, 243, 0.3);
        }

        /* Print-specific styles */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            @page {
                margin: 0.5in;
                size: A4;
            }

            body {
                background: white !important;
                padding: 0;
                font-size: 12pt;
                line-height: 1.4;
            }

            body::before {
                display: none !important;
            }

            .success-container {
                background: white !important;
                box-shadow: none !important;
                padding: 0 !important;
                max-width: 100% !important;
                border: none !important;
                backdrop-filter: none !important;
            }

            .success-container::before {
                display: none !important;
            }

            .success-icon,
            .message,
            .btn,
            .button-group,
            .countdown-timer,
            script,
            [style*="position: fixed"] {
                display: none !important;
            }

            h1 {
                font-size: 18pt !important;
                margin-bottom: 15pt !important;
                color: black !important;
                background: none !important;
                -webkit-text-fill-color: black !important;
                text-align: center;
                page-break-after: avoid;
            }

            .order-details {
                background: white !important;
                border: 1px solid #333 !important;
                page-break-inside: avoid;
                margin: 15pt 0 !important;
                padding: 15pt !important;
            }

            .order-section {
                margin-bottom: 15pt !important;
                padding-bottom: 15pt !important;
                border-bottom: 1px solid #ccc !important;
                page-break-inside: avoid;
            }

            .order-section:last-child {
                border-bottom: none !important;
                margin-bottom: 0 !important;
                padding-bottom: 0 !important;
            }

            .section-title {
                color: black !important;
                font-size: 12pt !important;
                font-weight: bold !important;
                margin-bottom: 8pt !important;
                border-left: 3pt solid #333 !important;
                padding-left: 8pt !important;
            }

            .section-title::before {
                display: none !important;
            }

            .order-row {
                border-bottom: 1px solid #ccc !important;
                page-break-inside: avoid;
                padding: 8pt 0 !important;
                margin: 0 !important;
                background: none !important;
            }

            .order-row:hover {
                background: none !important;
            }

            .order-label,
            .order-value {
                color: black !important;
            }

            .shipping-address {
                border: 1px solid #333 !important;
                background: white !important;
                page-break-inside: avoid;
                padding: 10pt !important;
            }

            .receipt-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20pt;
                padding-bottom: 15pt;
                border-bottom: 2px solid #333;
                page-break-after: avoid;
            }

            .receipt-header h2 {
                font-size: 20pt;
                margin-bottom: 8pt;
                color: black !important;
            }

            .receipt-header p {
                margin: 3pt 0;
                color: #333 !important;
                font-size: 11pt;
            }

            .items-table {
                display: table !important;
                width: 100%;
                margin: 15pt 0;
                border-collapse: collapse;
                page-break-inside: auto;
            }

            .items-table h3 {
                color: black !important;
                font-size: 14pt;
                margin: 15pt 0 8pt 0;
                page-break-after: avoid;
            }

            .items-table table {
                page-break-inside: auto;
            }

            .items-table thead {
                display: table-header-group !important;
            }

            .items-table tbody {
                display: table-row-group !important;
            }

            .items-table tr {
                display: table-row !important;
                page-break-inside: avoid;
                page-break-after: auto;
            }

            .items-table th,
            .items-table td {
                display: table-cell !important;
                border: 1px solid #333 !important;
                padding: 8pt !important;
                text-align: left;
                color: black !important;
                font-size: 10pt;
            }

            .items-table th {
                background: #f0f0f0 !important;
                font-weight: bold;
                page-break-after: avoid;
            }

            .items-table .text-right,
            .items-table td[style*="text-align: right"] {
                text-align: right !important;
            }

            .items-table tr:last-child td {
                font-weight: bold;
                font-size: 11pt;
            }

            .receipt-footer {
                display: block !important;
                margin-top: 20pt;
                padding-top: 15pt;
                border-top: 2px solid #333;
                text-align: center;
                font-size: 9pt;
                color: #333 !important;
                page-break-inside: avoid;
            }

            .receipt-footer p {
                margin: 3pt 0;
                color: #333 !important;
            }

            /* Force page breaks */
            .page-break-before {
                page-break-before: always;
            }

            .page-break-after {
                page-break-after: always;
            }

            .no-page-break {
                page-break-inside: avoid;
            }
        }

        /* Receipt elements - hidden by default, shown only in print */
        .receipt-header,
        .items-table,
        .receipt-footer {
            display: none;
        }

        .items-table {
            width: 100%;
            margin: 20px 0;
        }

        .items-table th,
        .items-table td {
            padding: 10px;
            text-align: left;
        }

        .items-table .text-right {
            text-align: right;
        }
    </style>
</head>

<body class="<?php echo (isset($_SESSION['member']['theme']) && $_SESSION['member']['theme'] === 'dark') ? 'dark-theme' : ''; ?>">
    <div class="success-container">
        <!-- Receipt Header (only visible when printing) -->
        <div class="receipt-header">
            <h2>PAYMENT RECEIPT</h2>
            <p>Thank you for your purchase!</p>
            <p>Date: <?php echo date('F j, Y g:i A'); ?></p>
        </div>

        <div class="success-icon"></div>

        <h1>Payment Successful!</h1>
        <p class="message">
            Thank you for your purchase. Your order has been confirmed and will be shipped soon.
        </p>

        <div class="order-details">
            <!-- Order Information Section -->
            <div class="order-section">
                <div class="section-title">Order Information</div>
                <div class="order-row">
                    <span class="order-label">Order Number</span>
                    <span class="order-value">#<?php echo $order['order_id']; ?></span>
                </div>
                <div class="order-row">
                    <span class="order-label">Order Date</span>
                    <span class="order-value"><?php echo date('F j, Y', strtotime($order['order_date'])); ?></span>
                </div>
                <div class="order-row">
                    <span class="order-label">Order Status</span>
                    <span class="order-value status-<?php echo strtolower($order['status']); ?>"><?php echo $order['status']; ?></span>
                </div>
                <div class="order-row">
                    <span class="order-label">Items Ordered</span>
                    <span class="order-value"><?php echo $order['item_count']; ?> item(s)</span>
                </div>
            </div>

            <!-- Payment Information Section -->
            <?php
            // Get payment details
            $stmt = $pdo->prepare("SELECT * FROM Payment WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $payment = $stmt->fetch();
            ?>
            <?php if ($payment): ?>
            <div class="order-section">
                <div class="section-title">Payment Information</div>
                <div class="order-row">
                    <span class="order-label">Payment Method</span>
                    <span class="order-value"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                </div>
                <div class="order-row">
                    <span class="order-label">Payment Status</span>
                    <span class="order-value status-completed"><?php echo $payment['status']; ?></span>
                </div>
                
                <?php
                // Calculate subtotal from order items
                $subtotal = 0;
                foreach ($order_items as $item) {
                    $subtotal += ($item['price'] * $item['quantity']);
                }
                
                // Calculate shipping fee (same logic as checkout)
                $shipping_fee = 5.00;
                $free_shipping_threshold = 15.00;
                if ($subtotal >= $free_shipping_threshold) {
                    $shipping_fee = 0.00;
                }
                
                // Calculate any discount applied
                $discount_amount = $subtotal + $shipping_fee - $order['total_amount'];
                ?>
                
                <div class="order-row">
                    <span class="order-label">Subtotal</span>
                    <span class="order-value">RM<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="order-row">
                    <span class="order-label">Shipping</span>
                    <span class="order-value">
                        <?php if ($shipping_fee > 0): ?>
                            RM<?php echo number_format($shipping_fee, 2); ?>
                        <?php else: ?>
                            <span style="color: #28a745; font-weight: 600;">FREE</span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($discount_amount > 0.01): ?>
                <div class="order-row">
                    <span class="order-label">Discount</span>
                    <span class="order-value" style="color: #28a745; font-weight: 600;">-RM<?php echo number_format($discount_amount, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="order-row">
                    <span class="order-label">Total Amount</span>
                    <span class="order-value amount">RM<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>
            <?php endif; ?>

            <!-- Shipping Information Section -->
            <div class="order-section">
                <div class="section-title">Shipping Address</div>
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

        <!-- Order Items Table (only visible when printing) -->
        <div class="items-table">
            <h3 style="margin: 20px 0 10px 0;">Order Items</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="border: 1px solid #ddd; padding: 10px;">Product</th>
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: center;">Quantity</th>
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: right;">Unit Price</th>
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_items as $item): ?>
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 10px;">
                                <?php echo htmlspecialchars($item['product_name']); ?>
                            </td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">
                                <?php echo $item['quantity']; ?>
                            </td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">
                                RM<?php echo number_format($item['price'], 2); ?>
                            </td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">
                                RM<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <!-- Subtotal row -->
                    <tr>
                        <td colspan="3" style="border: 1px solid #ddd; padding: 10px; text-align: right;">
                            Subtotal:
                        </td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">
                            RM<?php echo number_format($subtotal, 2); ?>
                        </td>
                    </tr>
                    
                    <!-- Shipping row -->
                    <tr>
                        <td colspan="3" style="border: 1px solid #ddd; padding: 10px; text-align: right;">
                            Shipping:
                        </td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">
                            <?php if ($shipping_fee > 0): ?>
                                RM<?php echo number_format($shipping_fee, 2); ?>
                            <?php else: ?>
                                FREE
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- Discount row (if applicable) -->
                    <?php if ($discount_amount > 0.01): ?>
                    <tr>
                        <td colspan="3" style="border: 1px solid #ddd; padding: 10px; text-align: right;">
                            Discount:
                        </td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">
                            -RM<?php echo number_format($discount_amount, 2); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Total row -->
                    <tr>
                        <td colspan="3" style="border: 1px solid #ddd; padding: 10px; text-align: right; font-weight: bold;">
                            Total Amount:
                        </td>
                        <td style="border: 1px solid #ddd; padding: 10px; text-align: right; font-weight: bold; font-size: 18px;">
                            RM<?php echo number_format($order['total_amount'], 2); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Receipt Footer (only visible when printing) -->
        <div class="receipt-footer">
            <p><strong>Transaction ID:</strong> <?php echo $session_id; ?></p>
            <p>This is a computer-generated receipt and does not require a signature.</p>
            <p>For any inquiries, please contact our customer support.</p>
            <p style="margin-top: 10px;">Thank you for shopping with us!</p>
        </div>

        <div class="button-group">
            <button onclick="printReceipt()" class="btn btn-print">Print Receipt</button>
            <a href="../product/product_list.php" class="btn btn-secondary">Continue Shopping</a>
        </div>
    </div>
    
    <script>
        // Print receipt function
        function printReceipt() {
            window.print();
        }

        // Prevent browser back button after successful payment
        // This prevents users from going back to payment/checkout pages
        if (window.history && window.history.pushState) {
            // Replace current history entry
            window.history.replaceState(null, null, window.location.href);
            
            // Push a new state
            window.history.pushState(null, null, window.location.href);
            
            // Listen for back button
            window.addEventListener('popstate', function(event) {
                // Redirect to home page instead of going back
                window.location.href = '../../index.php';
            });
        }
        
        // Auto-redirect to home page after 60 seconds
        let countdown = 60;
        const countdownElement = document.createElement('div');
        countdownElement.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 14px;
            z-index: 1000;
            cursor: pointer;
        `;
        countdownElement.textContent = `Redirecting to home in ${countdown}s`;
        countdownElement.title = 'Click to cancel auto-redirect';
        document.body.appendChild(countdownElement);
        
        const countdownTimer = setInterval(() => {
            countdown--;
            countdownElement.textContent = `Redirecting to home in ${countdown}s`;
            
            if (countdown <= 0) {
                clearInterval(countdownTimer);
                countdownElement.textContent = 'Redirecting...';
                window.location.href = '../../index.php';
            }
        }, 1000);
        
        // Allow user to cancel auto-redirect by clicking the countdown
        countdownElement.addEventListener('click', () => {
            clearInterval(countdownTimer);
            countdownElement.textContent = 'Auto-redirect cancelled';
            setTimeout(() => {
                countdownElement.remove();
            }, 2000);
        });
        
        // Clear buy_now session if it exists (cleanup)
        fetch('../cart/cart_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=clear_buy_now_session'
        }).catch(() => {
            // Silently fail if cleanup doesn't work
        });
    </script>
</body>

</html>
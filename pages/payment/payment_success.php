<?php
session_start();
require '../../stripe-php-master/init.php';
require '../../helper/pdo_connection.php';
require '../../helper/email_receipt.php';

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
        
        // Send order receipt email after successful order creation
        try {
            $email_sent = send_order_receipt_email($order, $order_items, $_SESSION['member'], $checkout_data);
            if (!$email_sent) {
                // Log email failure but don't break the flow
                error_log("Failed to send order receipt email for order ID: " . $order_id . " to member: " . $_SESSION['member']['email']);
            }
        } catch (Exception $email_error) {
            // Log email error but don't break the flow
            error_log("Email error for order ID: " . $order_id . " - " . $email_error->getMessage());
        }
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
    <link rel="stylesheet" href="/assets/css/payment_success.css">
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
            <br><strong>A receipt has been sent to your email address.</strong>
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
                
                // Ensure discount amount is not negative (in case of calculation errors)
                if ($discount_amount < 0) {
                    $discount_amount = 0;
                }
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
    
    <script src="/assets/js/payment_success.js"></script>
</body>

</html>
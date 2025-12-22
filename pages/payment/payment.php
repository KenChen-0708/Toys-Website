<?php
session_start();
require '../../stripe-php-master/init.php'; // Stripe PHP SDK
require '../../helper/pdo_connection.php';   // PDO connection

// Check if user is logged in
if (!isset($_SESSION['member'])) {
    header('Location: /pages/auth/login.php');
    exit();
}

$member_id = $_SESSION['member']['id'];

// Get order_id from URL
$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    header('Location: ../checkout/checkout.php');
    exit();
}

\Stripe\Stripe::setApiKey('sk_test_51SSdhpL3TTFqZeEiC8Zq2GlP6vX1oU0BVr1Il5xr0T5OXKXkewj27TGIxs0AvwzSfcHlY2znCsIlexDHk9gbJOXE00vbXRgf2n'); // Replace with your Secret Key

try {
    // 1️⃣ Retrieve order details
    $stmt = $pdo->prepare("
        SELECT o.*, 
               CONCAT(o.shipping_address_line1, ', ', o.shipping_city, ', ', o.shipping_country) as shipping_address
        FROM `Order` o
        WHERE o.order_id = ? AND o.member_id = ? AND o.status = 'Pending'
    ");
    $stmt->execute([$order_id, $member_id]);
    $order = $stmt->fetch();

    if (!$order) {
        // Order not found or already processed - redirect to cart
        header('Location: ../cart/cart.php?error=order_not_found');
        exit();
    }

    // 2️⃣ Retrieve order items with product images
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.main_image_path
        FROM Order_Item oi
        JOIN Product p ON oi.product_id = p.product_id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll();

    if (!$order_items) {
        // No items in order - redirect back to cart
        header('Location: ../cart/cart.php?error=invalid_order');
        exit();
    }

    // Get total amount from order (already includes discounts)
    $total_amount = $order['total_amount'];

    // Build line items for Stripe with images
    $line_items = [];
    $domain = 'http://localhost:8000';

    // Calculate the sum of individual item prices from order items
    $items_subtotal = 0;
    foreach ($order_items as $item) {
        $items_subtotal += ($item['price'] * $item['quantity']);
    }

    // Check if there's a discount applied (with small tolerance for floating point precision)
    $discount_amount = $items_subtotal - $total_amount;
    $has_discount = $discount_amount > 0.01; // Check if discount is significant

    if ($has_discount) {
        // If there's a discount, create a single line item with the final total
        // This ensures Stripe receives the exact discounted amount
        
        // Create a descriptive name for the order
        $order_description = '';
        if (count($order_items) == 1) {
            $order_description = $order_items[0]['name'] . ' (Voucher Applied)';
        } else {
            $order_description = 'Order #' . $order_id . ' (' . count($order_items) . ' items, Voucher Applied)';
        }
        
        $line_items[] = [
            'price_data' => [
                'currency' => 'myr',
                'product_data' => [
                    'name' => $order_description,
                    'description' => 'Subtotal: RM' . number_format($items_subtotal, 2) . ', Discount: RM' . number_format($discount_amount, 2),
                ],
                'unit_amount' => intval($total_amount * 100), // Convert to cents
            ],
            'quantity' => 1,
        ];
    } else {
        // No discount - show individual items
        foreach ($order_items as $item) {
            $product_data = [
                'name' => $item['name'],
            ];

            // Add product image if available
            if (!empty($item['main_image_path'])) {
                // Convert relative path to absolute URL
                $image_url = $domain . '/' . ltrim($item['main_image_path'], '/');
                $product_data['images'] = [$image_url];
            }

            $line_items[] = [
                'price_data' => [
                    'currency' => 'myr',
                    'product_data' => $product_data,
                    'unit_amount' => intval($item['price'] * 100), // Convert to cents
                ],
                'quantity' => $item['quantity'],
            ];
        }
    }

    // 3️⃣ Create Stripe Checkout Session
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card', 'fpx', 'grabpay'],
        'line_items' => $line_items,
        'mode' => 'payment',
        'success_url' => $domain . '/pages/payment/payment_success.php?session_id={CHECKOUT_SESSION_ID}&order_id=' . $order_id,
        'cancel_url'  => $domain . '/pages/payment/payment_cancel.php?order_id=' . $order_id,
        'metadata' => [
            'member_id' => $member_id,
            'order_id' => $order_id
        ],
        'customer_email' => $_SESSION['member']['email'] ?? null,
    ]);

    // Redirect to Stripe Checkout
    header("Location: " . $session->url);
    exit();
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

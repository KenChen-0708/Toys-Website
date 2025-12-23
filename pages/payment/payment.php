<?php
session_start();
require '../../stripe-php-master/init.php'; // Stripe PHP SDK
require '../../helper/pdo_connection.php';   // PDO connection
require_once '../../helper/auth.php';
redirect_if_admin();

// Check if user is logged in
if (!isset($_SESSION['member'])) {
    header('Location: /pages/auth/login.php');
    exit();
}

$member_id = $_SESSION['member']['id'];

// Check if checkout data exists in session
if (!isset($_SESSION['checkout_data'])) {
    header('Location: ../checkout/checkout.php?error=session_expired');
    exit();
}

$checkout_data = $_SESSION['checkout_data'];

// Validate checkout data
if ($checkout_data['member_id'] !== $member_id) {
    unset($_SESSION['checkout_data']);
    header('Location: ../checkout/checkout.php?error=invalid_session');
    exit();
}

// Check if checkout data is not too old (30 minutes max)
if (time() - $checkout_data['checkout_timestamp'] > 1800) {
    unset($_SESSION['checkout_data']);
    header('Location: ../checkout/checkout.php?error=session_expired');
    exit();
}

\Stripe\Stripe::setApiKey('sk_test_51SSdhpL3TTFqZeEiC8Zq2GlP6vX1oU0BVr1Il5xr0T5OXKXkewj27TGIxs0AvwzSfcHlY2znCsIlexDHk9gbJOXE00vbXRgf2n'); // Replace with your Secret Key

try {
    // Get data from session
    $cart_items = $checkout_data['cart_items'];
    $total_amount = $checkout_data['final_amount'];
    $cart_total = $checkout_data['cart_total'];
    $final_discount = $checkout_data['final_discount'];
    $shipping_fee = $checkout_data['shipping_fee'] ?? 5.00;

    // Validate stock availability again (in case it changed)
    foreach ($cart_items as $item) {
        $stmt = $pdo->prepare("SELECT stock FROM Product WHERE product_id = ?");
        $stmt->execute([$item['product_id']]);
        $current_stock = $stmt->fetchColumn();

        if ($current_stock < $item['quantity']) {
            unset($_SESSION['checkout_data']);
            header('Location: ../cart/cart.php?error=insufficient_stock&product=' . urlencode($item['name']));
            exit();
        }
    }

    // Build line items for Stripe with images
    $line_items = [];
    $domain = 'http://localhost:8000';

    // Check if there's a discount applied
    $has_discount = $final_discount > 0.01;
    $has_shipping = $shipping_fee > 0.01;

    if ($has_discount || $has_shipping) {
        // If there's a discount or shipping, create a single line item with the final total
        $order_description = '';
        if (count($cart_items) == 1) {
            $order_description = $cart_items[0]['name'];
        } else {
            $order_description = 'Order (' . count($cart_items) . ' items)';
        }
        
        $description_parts = ['Subtotal: RM' . number_format($cart_total, 2)];
        
        if ($has_shipping) {
            $description_parts[] = 'Shipping: RM' . number_format($shipping_fee, 2);
        }
        
        if ($has_discount) {
            $description_parts[] = 'Discount: RM' . number_format($final_discount, 2);
            $order_description .= ' (Voucher Applied)';
        }

        $line_items[] = [
            'price_data' => [
                'currency' => 'myr',
                'product_data' => [
                    'name' => $order_description,
                    'description' => implode(', ', $description_parts),
                ],
                'unit_amount' => intval($total_amount * 100), // Convert to cents
            ],
            'quantity' => 1,
        ];
    } else {
        // No discount or shipping combined - show individual items
        foreach ($cart_items as $item) {
            $product_data = [
                'name' => $item['name'],
            ];

            // Add product image if available
            if (!empty($item['main_image_path'])) {
                try {
                    $clean_path = $item['main_image_path'];
                    if (strpos($clean_path, '../') === 0) {
                        $clean_path = substr($clean_path, 3);
                    }
                    $clean_path = ltrim($clean_path, '/');

                    $path_parts = explode('/', $clean_path);
                    $encoded_parts = array_map('rawurlencode', $path_parts);
                    $encoded_path = implode('/', $encoded_parts);
                    $image_url = $domain . '/' . $encoded_path;

                    if (filter_var($image_url, FILTER_VALIDATE_URL)) {
                        $product_data['images'] = [$image_url];
                    }
                } catch (Exception $img_error) {
                    error_log("Image URL error for product: " . $img_error->getMessage());
                }
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
        
        // Add shipping as separate line item if applicable
        if ($has_shipping) {
            $line_items[] = [
                'price_data' => [
                    'currency' => 'myr',
                    'product_data' => [
                        'name' => 'Shipping',
                        'description' => 'Standard shipping fee',
                    ],
                    'unit_amount' => intval($shipping_fee * 100), // Convert to cents
                ],
                'quantity' => 1,
            ];
        }
    }

    // Create Stripe Checkout Session
    $success_url = $domain . '/pages/payment/payment_success.php?session_id={CHECKOUT_SESSION_ID}';
    $cancel_url = $domain . '/pages/payment/payment_cancel.php';

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card', 'fpx', 'grabpay'],
        'line_items' => $line_items,
        'mode' => 'payment',
        'success_url' => $success_url,
        'cancel_url'  => $cancel_url,
        'metadata' => [
            'member_id' => $member_id,
            'checkout_timestamp' => $checkout_data['checkout_timestamp']
        ],
        'customer_email' => $_SESSION['member']['email'] ?? null,
    ]);

    // Redirect to Stripe Checkout
    header("Location: " . $session->url);
    exit();
} catch (Exception $e) {
    // Log the full error for debugging
    error_log("Stripe Payment Error: " . $e->getMessage());
    error_log("Checkout Data: " . print_r($checkout_data, true));

    // Show user-friendly error
    die("Payment Error: " . $e->getMessage() . "<br><br><a href='../cart/cart.php'>Return to Cart</a>");
}

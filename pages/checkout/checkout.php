<?php
session_start();
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';
redirect_if_admin();

// Prevent browser caching
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is logged in
if (!isset($_SESSION['member'])) {
    header('Location: /pages/auth/login.php');
    exit();
}

$member_id = $_SESSION['member']['id'];

// Clean up expired checkout data (older than 30 minutes)
if (
    isset($_SESSION['checkout_data']) &&
    (time() - $_SESSION['checkout_data']['checkout_timestamp']) > 1800
) {
    unset($_SESSION['checkout_data']);
}

$error_message = '';
$success_message = '';
$voucher_discount = 0;
$voucher_code = '';
$voucher_applied = false;

// Check if this is a "Buy Now" checkout or regular cart checkout
// If user came from cart page, clear any existing buy_now session
if (isset($_GET['from']) && $_GET['from'] === 'cart') {
    unset($_SESSION['buy_now_item']);
}

$is_buy_now = isset($_SESSION['buy_now_item']);
$cart_items = [];
$cart_total = 0;

if ($is_buy_now) {
    // Use buy_now item from session (doesn't affect actual cart)
    $buy_now_item = $_SESSION['buy_now_item'];
    $cart_items = [[
        'cart_id' => null,
        'cart_item_id' => null,
        'product_id' => $buy_now_item['product_id'],
        'quantity' => $buy_now_item['quantity'],
        'name' => $buy_now_item['name'],
        'price' => $buy_now_item['price'],
        'stock' => 999,
        'main_image_path' => $buy_now_item['main_image_path'],
        'subtotal' => $buy_now_item['quantity'] * $buy_now_item['price']
    ]];
    $cart_total = $cart_items[0]['subtotal'];
} else {
    // Fetch cart items (regular checkout)
    try {
        $stmt = $pdo->prepare("
            SELECT c.cart_id, ci.cart_item_id, ci.product_id, ci.quantity, 
                   p.name, p.price, p.stock, p.main_image_path,
                   (ci.quantity * p.price) as subtotal
            FROM Cart c
            INNER JOIN Cart_Item ci ON c.cart_id = ci.cart_id
            INNER JOIN Product p ON ci.product_id = p.product_id
            WHERE c.member_id = ? AND p.status = 'Available'
        ");
        $stmt->execute([$member_id]);
        $cart_items = $stmt->fetchAll();

        // Calculate cart total
        $cart_total = 0;
        foreach ($cart_items as $item) {
            $cart_total += $item['subtotal'];
        }
    } catch (PDOException $e) {
        $error_message = "Error fetching cart items: " . $e->getMessage();
    }
}

// Validate that user has items to checkout
if (empty($cart_items)) {
    // No items to checkout - redirect to appropriate page
    if ($is_buy_now) {
        // Buy now session expired or invalid
        header('Location: ../product/product_list.php?error=session_expired');
    } else {
        // Empty cart
        header('Location: ../cart/cart.php?error=empty_cart');
    }
    exit();
}

// Fetch existing shipping addresses
$shipping_addresses = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM Shipping_Address 
        WHERE member_id = ? 
        ORDER BY created_date DESC
    ");
    $stmt->execute([$member_id]);
    $shipping_addresses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error fetching addresses: " . $e->getMessage();
}

// Handle voucher validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_voucher'])) {
    $voucher_code = trim($_POST['voucher_code']);

    if (!empty($voucher_code)) {
        try {
            $stmt = $pdo->prepare("
                SELECT v.*, 
                       COUNT(vu.voucher_usage_id) as times_used_by_user
                FROM Voucher v
                LEFT JOIN Voucher_Usage vu ON v.voucher_id = vu.voucher_id 
                    AND vu.member_id = ?
                WHERE v.code = ? 
                    AND v.status = 'active'
                    AND v.start_date <= NOW()
                    AND v.end_date >= NOW()
                GROUP BY v.voucher_id
            ");
            $stmt->execute([$member_id, $voucher_code]);
            $voucher = $stmt->fetch();

            if ($voucher) {
                if ($voucher['times_used_by_user'] >= $voucher['usage_per_user']) {
                    $error_message = "You have already used this voucher the maximum number of times.";
                } elseif ($cart_total < $voucher['min_spend']) {
                    $error_message = "Minimum spend of RM" . number_format($voucher['min_spend'], 2) . " required for this voucher.";
                } else {
                    if ($voucher['discount_type'] === 'fixed') {
                        $voucher_discount = $voucher['discount_value'];
                    } else {
                        $voucher_discount = ($cart_total * $voucher['discount_value']) / 100;
                        if ($voucher['max_discount'] && $voucher_discount > $voucher['max_discount']) {
                            $voucher_discount = $voucher['max_discount'];
                        }
                    }
                    
                    // Ensure discount doesn't exceed cart total
                    if ($voucher_discount > $cart_total) {
                        $voucher_discount = $cart_total;
                    }
                    
                    $voucher_applied = true;
                    $success_message = "Voucher applied successfully! You saved RM" . number_format($voucher_discount, 2);
                }
            } else {
                $error_message = "Invalid or expired voucher code.";
            }
        } catch (PDOException $e) {
            $error_message = "Error validating voucher: " . $e->getMessage();
        }
    }
}

// Handle new shipping address (fallback for non-AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_address'])) {
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $country = trim($_POST['country'] ?? '');

    // Enhanced validation
    $validation_errors = [];

    if (empty($address_line1)) {
        $validation_errors[] = "Address Line 1 is required";
    } elseif (strlen($address_line1) < 5) {
        $validation_errors[] = "Address Line 1 must be at least 5 characters";
    } elseif (strlen($address_line1) > 100) {
        $validation_errors[] = "Address Line 1 must not exceed 100 characters";
    }

    if (empty($city)) {
        $validation_errors[] = "City is required";
    } elseif (strlen($city) < 2) {
        $validation_errors[] = "City must be at least 2 characters";
    } elseif (strlen($city) > 50) {
        $validation_errors[] = "City must not exceed 50 characters";
    }

    if (empty($country)) {
        $validation_errors[] = "Country is required";
    } elseif (strlen($country) < 2) {
        $validation_errors[] = "Country must be at least 2 characters";
    } elseif (strlen($country) > 50) {
        $validation_errors[] = "Country must not exceed 50 characters";
    }

    if (!empty($address_line2) && strlen($address_line2) > 100) {
        $validation_errors[] = "Address Line 2 must not exceed 100 characters";
    }

    if (empty($state)) {
        $validation_errors[] = "State is required";
    } elseif (strlen($state) > 50) {
        $validation_errors[] = "State must not exceed 50 characters";
    } elseif (!preg_match('/^[A-Za-z\s\-\']+$/', $state)) {
        $validation_errors[] = "State cannot contain numbers or special characters";
    }

    if (empty($postal_code)) {
        $validation_errors[] = "Postal code is required";
    } elseif (!preg_match('/^[0-9]{5}$/', $postal_code)) {
        $validation_errors[] = "Postal code must be exactly 5 digits";
    }

    if (!empty($validation_errors)) {
        $error_message = implode('. ', $validation_errors) . '.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO Shipping_Address 
                (member_id, address_line1, address_line2, city, state, postal_code, country, created_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $member_id,
                $address_line1,
                $address_line2,
                $city,
                $state,
                $postal_code,
                $country
            ]);

            $success_message = "Address added successfully!";

            // Refresh the addresses list
            $stmt = $pdo->prepare("
                SELECT * FROM Shipping_Address 
                WHERE member_id = ? 
                ORDER BY created_date DESC
            ");
            $stmt->execute([$member_id]);
            $shipping_addresses = $stmt->fetchAll();
        } catch (PDOException $e) {
            $error_message = "Error adding address: " . $e->getMessage();
        }
    }
}

// Handle checkout preparation (store data in session, don't create order yet)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $shipping_address_id = $_POST['shipping_address_id'] ?? null;
    $voucher_code = $_POST['voucher_code_hidden'] ?? '';

    if (empty($shipping_address_id)) {
        $error_message = "Please select a shipping address.";
    } else {
        try {
            // Validate shipping address
            $stmt = $pdo->prepare("SELECT * FROM Shipping_Address WHERE shipping_address_id = ? AND member_id = ?");
            $stmt->execute([$shipping_address_id, $member_id]);
            $selected_address = $stmt->fetch();

            if (!$selected_address) {
                throw new Exception("Invalid shipping address selected.");
            }

            // Validate stock availability before proceeding
            foreach ($cart_items as $item) {
                $stmt = $pdo->prepare("SELECT stock FROM Product WHERE product_id = ?");
                $stmt->execute([$item['product_id']]);
                $current_stock = $stmt->fetchColumn();

                if ($current_stock < $item['quantity']) {
                    throw new Exception("Insufficient stock for product: " . $item['name'] . ". Available: " . $current_stock . ", Requested: " . $item['quantity']);
                }
            }

            // Validate voucher if provided
            $final_discount = 0;
            $voucher_data = null;
            if (!empty($voucher_code)) {
                $stmt = $pdo->prepare("
                    SELECT v.*, 
                           COUNT(vu.voucher_usage_id) as times_used_by_user
                    FROM Voucher v
                    LEFT JOIN Voucher_Usage vu ON v.voucher_id = vu.voucher_id 
                        AND vu.member_id = ?
                    WHERE v.code = ? 
                        AND v.status = 'active'
                        AND v.start_date <= NOW()
                        AND v.end_date >= NOW()
                    GROUP BY v.voucher_id
                ");
                $stmt->execute([$member_id, $voucher_code]);
                $voucher = $stmt->fetch();

                if (
                    $voucher && $voucher['times_used_by_user'] < $voucher['usage_per_user']
                    && $cart_total >= $voucher['min_spend']
                ) {
                    if ($voucher['discount_type'] === 'fixed') {
                        $final_discount = $voucher['discount_value'];
                    } else {
                        $final_discount = ($cart_total * $voucher['discount_value']) / 100;
                        if ($voucher['max_discount'] && $final_discount > $voucher['max_discount']) {
                            $final_discount = $voucher['max_discount'];
                        }
                    }
                    
                    // Ensure discount doesn't exceed cart total
                    if ($final_discount > $cart_total) {
                        $final_discount = $cart_total;
                    }
                    
                    $voucher_data = $voucher;
                }
            }

            // Calculate shipping fee
            $shipping_fee = 5.00; // Default shipping fee RM5
            $free_shipping_threshold = 15.00; // Free shipping for orders over RM15

            // Apply free shipping if cart total (before discount) meets threshold
            if ($cart_total >= $free_shipping_threshold) {
                $shipping_fee = 0.00;
            }

            $final_amount = $cart_total - $final_discount + $shipping_fee;
            
            // Ensure final amount is never negative (minimum should be shipping fee if applicable)
            if ($final_amount < 0) {
                $final_amount = $shipping_fee;
                // Adjust discount to prevent negative total
                $final_discount = $cart_total;
            }

            // Store checkout data in session for payment processing
            $_SESSION['checkout_data'] = [
                'member_id' => $member_id,
                'cart_items' => $cart_items,
                'is_buy_now' => $is_buy_now,
                'shipping_address' => $selected_address,
                'voucher_data' => $voucher_data,
                'voucher_code' => $voucher_code,
                'cart_total' => $cart_total,
                'shipping_fee' => $shipping_fee,
                'final_discount' => $final_discount,
                'final_amount' => $final_amount,
                'checkout_timestamp' => time()
            ];

            // Redirect to payment without creating order
            header("Location: ../payment/payment.php");
            exit();
        } catch (Exception $e) {
            $error_message = "Error preparing checkout: " . $e->getMessage();
        }
    }
}

// Calculate shipping fee
$shipping_fee = 5.00; // Default shipping fee RM5
$free_shipping_threshold = 15.00; // Free shipping for orders over RM15

// Apply free shipping if cart total (before discount) meets threshold
if ($cart_total >= $free_shipping_threshold) {
    $shipping_fee = 0.00;
}

$final_total = $cart_total - $voucher_discount + $shipping_fee;

// Ensure final total is never negative (minimum should be shipping fee if applicable)
if ($final_total < 0) {
    $final_total = $shipping_fee;
    // Adjust discount to prevent negative total
    $voucher_discount = $cart_total;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Online Store</title>
    <link rel="stylesheet" href="/assets/css/dark_mode_style.css">
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        /* Scoped styles for checkout page only - don't override header/footer */
        .checkout-page * {
            box-sizing: border-box;
        }

        .checkout-page {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
            line-height: 1.6;
            min-height: 100vh;
        }

        .checkout-page .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .checkout-page .page-title {
            font-size: 32px;
            color: #333;
            margin-bottom: 30px;
        }

        .checkout-page .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 450px;
            gap: 25px;
        }

        .checkout-page .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 15px;
            position: fixed;
            top: 20px;
            right: 20px;
            max-width: 400px;
            z-index: 1000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .checkout-page .alert-error {
            background-color: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .checkout-page .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .checkout-page .section {
            background: white;
            padding: 30px;
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .checkout-page .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #4CAF50;
            font-weight: 600;
        }

        /* Address Section */
        .checkout-page .address-grid {
            display: grid;
            gap: 15px;
            margin-bottom: 20px;
        }

        .checkout-page .no-addresses {
            background-color: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
        }

        .checkout-page .address-option {
            position: relative;
            border: 2px solid #ddd;
            padding: 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .checkout-page .address-option:hover {
            border-color: #4CAF50;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .checkout-page .address-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .checkout-page .address-option input[type="radio"]:checked+.address-content {
            border-left: 4px solid #4CAF50;
            padding-left: 16px;
        }

        .checkout-page .address-option input[type="radio"]:checked~.check-icon {
            display: block;
        }

        .checkout-page .address-content {
            transition: all 0.3s;
        }

        .checkout-page .check-icon {
            display: none;
            position: absolute;
            top: 15px;
            right: 15px;
            width: 24px;
            height: 24px;
            background-color: #4CAF50;
            border-radius: 50%;
            color: white;
            text-align: center;
            line-height: 24px;
            font-size: 14px;
        }

        .checkout-page .address-line {
            margin-bottom: 5px;
            color: #333;
        }

        .checkout-page .address-line.primary {
            font-weight: 600;
            font-size: 16px;
        }

        .checkout-page .add-address-toggle {
            display: inline-block;
            padding: 12px 24px;
            background-color: #f8f9fa;
            color: #4CAF50;
            border: 2px dashed #4CAF50;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .checkout-page .add-address-toggle::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(76, 175, 80, 0.1), transparent);
            transition: left 0.5s;
        }

        .checkout-page .add-address-toggle:hover {
            background-color: #4CAF50;
            color: white;
            border-style: solid;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .checkout-page .add-address-toggle:hover::before {
            left: 100%;
        }

        .checkout-page .address-form {
            display: none;
            background-color: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-top: 15px;
        }

        .checkout-page .address-form.active {
            display: block;
        }

        .checkout-page .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .checkout-page .form-group {
            margin-bottom: 15px;
        }

        .checkout-page .form-group.full-width {
            grid-column: 1 / -1;
        }

        .checkout-page label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }

        .checkout-page label .required {
            color: #e74c3c;
        }

        .checkout-page input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s;
        }

        .checkout-page input[type="text"]:focus {
            outline: none;
            border-color: #4CAF50;
        }

        /* Order Summary Section */
        .checkout-page .summary-product {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .checkout-page .summary-product:last-of-type {
            border-bottom: none;
            margin-bottom: 20px;
        }

        .checkout-page .summary-product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }

        .checkout-page .summary-product-info {
            flex: 1;
        }

        .checkout-page .summary-product-name {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .checkout-page .summary-product-meta {
            font-size: 13px;
            color: #666;
        }

        .checkout-page .summary-product-price {
            font-weight: 600;
            color: #333;
        }

        /* Voucher Input */
        .checkout-page .voucher-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .checkout-page .voucher-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
        }

        .checkout-page .voucher-input:focus {
            outline: none;
            border-color: #4CAF50;
        }

        /* Price Summary */
        .checkout-page .price-summary {
            border-top: 2px solid #eee;
            padding-top: 15px;
        }

        .checkout-page .price-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 15px;
        }

        .checkout-page .price-row.discount {
            color: #28a745;
            font-weight: 600;
        }

        .checkout-page .price-row.total {
            border-top: 2px solid #ddd;
            margin-top: 10px;
            padding-top: 15px;
            font-size: 22px;
            font-weight: 700;
            color: #696969ff;
        }

        /* Buttons */
        .checkout-page .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .checkout-page .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .checkout-page .btn:hover::before {
            left: 100%;
        }

        .checkout-page .btn:active {
            transform: translateY(0);
        }

        .checkout-page .btn-apply {
            background-color: #666;
            color: white;
        }

        .checkout-page .btn-apply:hover {
            background-color: #555;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 102, 102, 0.3);
        }

        .checkout-page .btn-primary {
            background-color: #4CAF50;
            color: white;
            width: 100%;
            font-size: 18px;
            padding: 16px;
        }

        .checkout-page .btn-primary::before {
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        }

        .checkout-page .btn-primary:hover {
            background-color: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(76, 175, 80, 0.4);
        }

        .checkout-page .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
            width: 100%;
        }

        .checkout-page .btn-secondary:hover {
            background-color: #e0e0e0;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Enhanced styles for address form buttons */
        .checkout-page .address-form .btn {
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 2px solid transparent;
            width: auto;
        }

        .checkout-page .address-form .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        }

        .checkout-page .address-form .btn-secondary {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: 2px solid #4CAF50;
        }

        .checkout-page .address-form .btn-secondary::before {
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
        }

        .checkout-page .address-form .btn-secondary:hover {
            background: linear-gradient(135deg, #45a049, #3d8b40);
            border-color: #45a049;
        }

        .checkout-page .address-form #clearAddressBtn {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #6c757d;
            border: 2px solid #dee2e6;
        }

        .checkout-page .address-form #clearAddressBtn:hover {
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
            color: #495057;
            border-color: #adb5bd;
        }

        /* Button container styling */
        .checkout-page .address-form .form-group.full-width>div {
            display: flex;
            gap: 12px;
            margin-bottom: 8px;
        }

        .checkout-page .address-form .form-group.full-width>div .btn {
            flex: 1;
            min-height: 44px;
            font-size: 14px;
        }

        @media (max-width: 968px) {
            .checkout-page .checkout-layout {
                grid-template-columns: 1fr;
            }

            .checkout-page .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<?php include '../../partials/header.php'; ?>
<div class="checkout-page">
    <div class="container">
        <h1 class="page-title">Checkout</h1>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="checkout.php">
            <div class="checkout-layout">

                <!-- Left: Shipping Address -->
                <div>
                    <div class="section">
                        <h2 class="section-title">Shipping Address</h2>

                        <div class="address-grid" id="addressGrid">
                            <?php if (!empty($shipping_addresses)): ?>
                                <?php foreach ($shipping_addresses as $address): ?>
                                    <label class="address-option">
                                        <input type="radio"
                                            name="shipping_address_id"
                                            value="<?php echo $address['shipping_address_id']; ?>"
                                            required>
                                        <div class="address-content">
                                            <div class="address-line primary">
                                                <?php echo htmlspecialchars($address['address_line1']); ?>
                                            </div>
                                            <?php if ($address['address_line2']): ?>
                                                <div class="address-line">
                                                    <?php echo htmlspecialchars($address['address_line2']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="address-line">
                                                <?php echo htmlspecialchars($address['city']); ?>,
                                                <?php echo htmlspecialchars($address['state']); ?>
                                                <?php echo htmlspecialchars($address['postal_code']); ?>
                                            </div>
                                            <div class="address-line">
                                                <?php echo htmlspecialchars($address['country']); ?>
                                            </div>
                                        </div>
                                        <div class="check-icon">✓</div>
                                    </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-addresses" id="noAddressesMessage">
                                    <p style="color: #666; font-style: italic; text-align: center; padding: 20px;">
                                        No addresses found. Please add a new address below.
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <a href="#" class="add-address-toggle" onclick="toggleAddressForm(event)">
                            + Add New Address
                        </a>

                        <div class="address-form" id="addressForm">
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label>Address Line 1 <span class="required">*</span></label>
                                    <input type="text" name="address_line1" placeholder="Street address, P.O. box"
                                        maxlength="100"
                                        title="Address Line 1 must be between 5-100 characters">
                                </div>

                                <div class="form-group full-width">
                                    <label>Address Line 2</label>
                                    <input type="text" name="address_line2" placeholder="Apartment, suite, unit, etc."
                                        maxlength="100"
                                        title="Address Line 2 must not exceed 100 characters">
                                </div>

                                <div class="form-group">
                                    <label>City <span class="required">*</span></label>
                                    <input type="text" name="city" placeholder="City"
                                        maxlength="50"
                                        pattern="[A-Za-z\s\-']+"
                                        title="City must be 2-50 characters and contain only letters, spaces, hyphens, and apostrophes">
                                </div>

                                <div class="form-group">
                                    <label>State <span class="required">*</span></label>
                                    <input type="text" name="state" placeholder="State/Province"
                                        maxlength="50"
                                        pattern="[A-Za-z\s\-']+"
                                        title="State must not exceed 50 characters and contain only letters, spaces, hyphens, and apostrophes">
                                </div>

                                <div class="form-group">
                                    <label>Postal Code <span class="required">*</span></label>
                                    <input type="text" name="postal_code" placeholder="Postal code"
                                        pattern="[0-9]{5}" maxlength="5"
                                        title="Please enter a 5-digit postal code">
                                </div>

                                <div class="form-group">
                                    <label>Country <span class="required">*</span></label>
                                    <input type="text" name="country" placeholder="Country" value="Malaysia"
                                        minlength="2" maxlength="50"
                                        pattern="[A-Za-z\s\-']+"
                                        title="Country must be 2-50 characters and contain only letters, spaces, hyphens, and apostrophes">
                                </div>

                                <div class="form-group full-width">
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" name="add_address" class="btn btn-secondary" id="saveAddressBtn">
                                            Save Address
                                        </button>
                                        <button type="button" class="btn" id="clearAddressBtn" style="background: #f0f0f0; color: #666;">
                                            Clear Form
                                        </button>
                                    </div>
                                    <p style="font-size: 12px; color: #666; margin-top: 8px;">
                                        <em>Address will be saved and available for selection immediately.</em>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Order Summary -->
                <div>
                    <div class="section">
                        <h2 class="section-title">Order Summary</h2>

                        <!-- Product List -->
                        <?php foreach ($cart_items as $item): ?>
                            <div class="summary-product">
                                <?php if ($item['main_image_path']): ?>
                                    <img src="../../<?php echo htmlspecialchars($item['main_image_path']); ?>"
                                        alt="<?php echo htmlspecialchars($item['name']); ?>"
                                        class="summary-product-img">
                                <?php else: ?>
                                    <img src="../../images/no-image.png" alt="No image" class="summary-product-img">
                                <?php endif; ?>

                                <div class="summary-product-info">
                                    <div class="summary-product-name">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </div>
                                    <div class="summary-product-meta">
                                        Qty: <?php echo $item['quantity']; ?> × RM<?php echo number_format($item['price'], 2); ?>
                                    </div>
                                </div>

                                <div class="summary-product-price">
                                    RM<?php echo number_format($item['subtotal'], 2); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Voucher Input -->
                        <div class="voucher-input-group">
                            <input type="text"
                                name="voucher_code"
                                class="voucher-input"
                                placeholder="Voucher code"
                                value="<?php echo htmlspecialchars($voucher_code); ?>">
                            <button type="submit" name="apply_voucher" class="btn btn-apply">
                                Apply
                            </button>
                        </div>

                        <!-- Price Breakdown -->
                        <div class="price-summary">
                            <div class="price-row">
                                <span>Subtotal</span>
                                <span>RM<?php echo number_format($cart_total, 2); ?></span>
                            </div>

                            <div class="price-row">
                                <span>Shipping</span>
                                <span>
                                    <?php if ($shipping_fee > 0): ?>
                                        RM<?php echo number_format($shipping_fee, 2); ?>
                                    <?php else: ?>
                                        <span style="color: #28a745; font-weight: 600;">FREE</span>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <?php if ($cart_total < $free_shipping_threshold && $shipping_fee > 0): ?>
                                <div class="price-row" style="font-size: 12px; color: #666; font-style: italic;">
                                    <span>Free shipping on orders over RM<?php echo number_format($free_shipping_threshold, 2); ?></span>
                                    <span>Add RM<?php echo number_format($free_shipping_threshold - $cart_total, 2); ?> more</span>
                                </div>
                            <?php endif; ?>

                            <?php if ($voucher_applied): ?>
                                <div class="price-row discount">
                                    <span>Discount</span>
                                    <span>-RM<?php echo number_format($voucher_discount, 2); ?></span>
                                </div>
                                <input type="hidden" name="voucher_code_hidden" value="<?php echo htmlspecialchars($voucher_code); ?>">
                            <?php endif; ?>

                            <div class="price-row total">
                                <span>Total</span>
                                <span>RM<?php echo number_format($final_total, 2); ?></span>
                            </div>
                        </div>

                        <!-- Checkout Button -->
                        <button type="submit" name="place_order" class="btn btn-primary">
                            <span>Proceed to Payment</span>
                            <span>→</span>
                        </button>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <script>
        function toggleAddressForm(e) {
            e.preventDefault();
            const form = document.getElementById('addressForm');

            form.classList.toggle('active');

            // Don't automatically add required attributes - only validate when saving
            // This prevents blocking the checkout process if user just types but doesn't save
        }

        // Highlight selected address
        document.querySelectorAll('.address-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.address-option').forEach(opt => {
                    opt.style.borderColor = '#ddd';
                    opt.style.boxShadow = 'none';
                });
                this.style.borderColor = '#4CAF50';
                this.style.boxShadow = '0 4px 12px rgba(76, 175, 80, 0.2)';
            });
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Add progressive enhancement - if JavaScript fails, form still works
        document.body.classList.add('js-enabled');

        // Field validation function
        function validateField(field) {
            const value = field.value.trim();
            const name = field.name;
            let isValid = true;
            let errorMessage = '';

            // Remove existing error message
            const existingError = field.parentNode.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
            }

            // Validate based on field name
            switch (name) {
                case 'address_line1':
                    if (value && (value.length < 5 || value.length > 100)) {
                        isValid = false;
                        errorMessage = 'Address Line 1 must be between 5-100 characters';
                    }
                    break;

                case 'address_line2':
                    if (value && value.length > 100) {
                        isValid = false;
                        errorMessage = 'Address Line 2 must not exceed 100 characters';
                    }
                    break;

                case 'city':
                    if (value && (value.length < 2 || value.length > 50)) {
                        isValid = false;
                        errorMessage = 'City must be between 2-50 characters';
                    } else if (value && !/^[A-Za-z\s\-']+$/.test(value)) {
                        isValid = false;
                        errorMessage = 'City can only contain letters, spaces, hyphens, and apostrophes';
                    } else if (value && /\d/.test(value)) {
                        isValid = false;
                        errorMessage = 'City cannot contain numbers';
                    }
                    break;

                case 'state':
                    if (value && value.length > 50) {
                        isValid = false;
                        errorMessage = 'State must not exceed 50 characters';
                    } else if (value && !/^[A-Za-z\s\-']+$/.test(value)) {
                        isValid = false;
                        errorMessage = 'State can only contain letters, spaces, hyphens, and apostrophes';
                    } else if (value && /\d/.test(value)) {
                        isValid = false;
                        errorMessage = 'State cannot contain numbers';
                    }
                    break;

                case 'postal_code':
                    if (value && !/^[0-9]{5}$/.test(value)) {
                        isValid = false;
                        errorMessage = 'Postal code must be exactly 5 digits';
                    }
                    break;

                case 'country':
                    if (value && (value.length < 2 || value.length > 50)) {
                        isValid = false;
                        errorMessage = 'Country must be between 2-50 characters';
                    } else if (value && !/^[A-Za-z\s\-']+$/.test(value)) {
                        isValid = false;
                        errorMessage = 'Country can only contain letters, spaces, hyphens, and apostrophes';
                    }
                    break;
            }

            // Show error if validation failed
            if (!isValid) {
                field.style.borderColor = '#ef4444';
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.style.color = '#ef4444';
                errorDiv.style.fontSize = '12px';
                errorDiv.style.marginTop = '4px';
                errorDiv.textContent = errorMessage;
                field.parentNode.appendChild(errorDiv);
            } else {
                field.style.borderColor = '#10b981';
            }

            return isValid;
        }

        // Show error message below a specific field
        function showFieldError(field, message) {
            field.style.borderColor = '#ef4444';
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.style.color = '#ef4444';
            errorDiv.style.fontSize = '12px';
            errorDiv.style.marginTop = '4px';
            errorDiv.textContent = message;
            field.parentNode.appendChild(errorDiv);
        }

        // Show multiple field errors (for save validation)
        function showFieldErrors(errors) {
            // Clear all existing errors first
            document.querySelectorAll('.error-message').forEach(error => error.remove());

            // Map error messages to fields and show them
            errors.forEach(error => {
                if (error.includes('Address Line 1')) {
                    const field = document.querySelector('input[name="address_line1"]');
                    if (field) showFieldError(field, error);
                } else if (error.includes('Address Line 2')) {
                    const field = document.querySelector('input[name="address_line2"]');
                    if (field) showFieldError(field, error);
                } else if (error.includes('City')) {
                    const field = document.querySelector('input[name="city"]');
                    if (field) showFieldError(field, error);
                } else if (error.includes('State')) {
                    const field = document.querySelector('input[name="state"]');
                    if (field) showFieldError(field, error);
                } else if (error.includes('Postal code')) {
                    const field = document.querySelector('input[name="postal_code"]');
                    if (field) showFieldError(field, error);
                } else if (error.includes('Country')) {
                    const field = document.querySelector('input[name="country"]');
                    if (field) showFieldError(field, error);
                }
            });
        }

        // Prevent form submission conflicts and add validation
        document.addEventListener('submit', function(e) {
            console.log('Form submission detected:', e.target);

            // Only prevent default for AJAX-handled forms when JS is working
            if (e.target.querySelector('button[name="add_address"]') || e.target.querySelector('button[name="apply_voucher"]')) {
                console.log('AJAX form detected, allowing default behavior');
                // These are handled by AJAX, don't prevent
                return;
            }

            // Validate main checkout form
            if (e.target.querySelector('button[name="place_order"]')) {
                console.log('Checkout form submission detected');

                // Clear ALL address form inputs to prevent any validation blocking
                const addressForm = document.getElementById('addressForm');
                if (addressForm) {
                    const addressInputs = addressForm.querySelectorAll('input[type="text"]');
                    addressInputs.forEach(input => {
                        input.value = '';
                        input.removeAttribute('required');
                        input.style.borderColor = '';
                        // Remove any error messages
                        const errorMsg = input.parentNode.querySelector('.error-message');
                        if (errorMsg) {
                            errorMsg.remove();
                        }
                    });
                    console.log('Cleared all address form inputs and errors');
                }

                // Check if user selected an existing address
                const selectedAddress = document.querySelector('input[name="shipping_address_id"]:checked');
                if (!selectedAddress) {
                    console.log('No address selected, preventing submission');
                    e.preventDefault();
                    alert('Please select a shipping address before proceeding to payment.');
                    return false;
                } else {
                    console.log('Address selected, allowing submission');
                }
            }
        });

        // AJAX for voucher application
        document.addEventListener('DOMContentLoaded', function() {
            // Add real-time validation for address form
            const addressForm = document.getElementById('addressForm');
            if (addressForm) {
                const inputs = addressForm.querySelectorAll('input[type="text"]');
                inputs.forEach(input => {
                    // Strict input filtering for text-only fields (city, state, country)
                    if (['city', 'state', 'country'].includes(input.name)) {
                        // Prevent any numbers or invalid characters during typing
                        input.addEventListener('keypress', function(e) {
                            const char = String.fromCharCode(e.which);
                            // Only allow letters, spaces, hyphens, apostrophes, and control keys
                            if (!/[A-Za-z\s\-']/.test(char) && e.which !== 8 && e.which !== 0 && e.which !== 9) {
                                e.preventDefault();
                                return false;
                            }
                        });

                        // Handle paste events - strip out any numbers or invalid characters
                        input.addEventListener('paste', function(e) {
                            e.preventDefault();
                            const paste = (e.clipboardData || window.clipboardData).getData('text');
                            const cleaned = paste.replace(/[^A-Za-z\s\-']/g, '');
                            this.value = cleaned;
                            // Trigger input event to clear any error styling
                            this.dispatchEvent(new Event('input'));
                        });

                        // Additional sanitization on input change
                        input.addEventListener('input', function(e) {
                            const originalValue = this.value;
                            const cleanedValue = originalValue.replace(/[^A-Za-z\s\-']/g, '');
                            if (originalValue !== cleanedValue) {
                                this.value = cleanedValue;
                            }
                        });
                    }

                    // Strict number-only filtering for postal code
                    if (input.name === 'postal_code') {
                        // Prevent any non-numbers during typing
                        input.addEventListener('keypress', function(e) {
                            const char = String.fromCharCode(e.which);
                            // Only allow numbers and control keys
                            if (!/[0-9]/.test(char) && e.which !== 8 && e.which !== 0 && e.which !== 9) {
                                e.preventDefault();
                                return false;
                            }
                        });

                        // Handle paste events - only keep numbers
                        input.addEventListener('paste', function(e) {
                            e.preventDefault();
                            const paste = (e.clipboardData || window.clipboardData).getData('text');
                            const cleaned = paste.replace(/[^0-9]/g, '');
                            this.value = cleaned.substring(0, 5); // Limit to 5 digits
                            // Trigger input event to clear any error styling
                            this.dispatchEvent(new Event('input'));
                        });

                        // Additional sanitization on input change
                        input.addEventListener('input', function(e) {
                            const originalValue = this.value;
                            const cleanedValue = originalValue.replace(/[^0-9]/g, '').substring(0, 5);
                            if (originalValue !== cleanedValue) {
                                this.value = cleanedValue;
                            }
                        });
                    }

                    input.addEventListener('blur', function() {
                        validateField(this);
                    });

                    input.addEventListener('input', function() {
                        // Clear previous error styling when user starts typing
                        this.style.borderColor = '';
                        const errorMsg = this.parentNode.querySelector('.error-message');
                        if (errorMsg) {
                            errorMsg.remove();
                        }
                    });
                });

                // Add clear form functionality
                const clearBtn = document.getElementById('clearAddressBtn');
                if (clearBtn) {
                    clearBtn.addEventListener('click', function() {
                        // Clear all form inputs
                        inputs.forEach(input => {
                            input.value = '';
                            input.style.borderColor = '';
                            const errorMsg = input.parentNode.querySelector('.error-message');
                            if (errorMsg) {
                                errorMsg.remove();
                            }
                        });
                        // Reset country to default
                        const countryInput = addressForm.querySelector('input[name="country"]');
                        if (countryInput) {
                            countryInput.value = 'Malaysia';
                        }
                    });
                }
            }

            const voucherForm = document.querySelector('form[method="POST"]');
            const applyVoucherBtn = document.querySelector('button[name="apply_voucher"]');

            if (applyVoucherBtn) {
                applyVoucherBtn.addEventListener('click', function(e) {
                    e.preventDefault();

                    const voucherCode = document.querySelector('input[name="voucher_code"]').value;
                    const formData = new FormData();
                    formData.append('apply_voucher', '1');
                    formData.append('voucher_code', voucherCode);

                    // Show loading state
                    applyVoucherBtn.disabled = true;
                    applyVoucherBtn.innerHTML = 'Applying...';

                    fetch('checkout_ajax.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            // Remove existing alerts
                            document.querySelectorAll('.alert').forEach(a => a.remove());

                            if (data.success) {
                                // Show success message
                                showAlert(data.message, 'success');

                                // Update price summary
                                updatePriceSummary(data.cart_total, data.voucher_discount, data.final_total, voucherCode);
                            } else {
                                // Show error message
                                showAlert(data.message, 'error');
                            }

                            // Reset button
                            applyVoucherBtn.disabled = false;
                            applyVoucherBtn.innerHTML = 'Apply';
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showAlert('An error occurred. Please try again.', 'error');
                            applyVoucherBtn.disabled = false;
                            applyVoucherBtn.innerHTML = 'Apply';
                        });
                });
            }

            // AJAX for adding new address
            const addAddressBtn = document.querySelector('button[name="add_address"]');

            if (addAddressBtn) {
                addAddressBtn.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Comprehensive client-side validation - only runs when saving
                    const addressLine1 = document.querySelector('input[name="address_line1"]').value.trim();
                    const city = document.querySelector('input[name="city"]').value.trim();
                    const country = document.querySelector('input[name="country"]').value.trim();
                    const state = document.querySelector('input[name="state"]').value.trim();
                    const postalCode = document.querySelector('input[name="postal_code"]').value.trim();
                    const addressLine2 = document.querySelector('input[name="address_line2"]').value.trim();

                    // Validate all fields using the same logic as the validateField function
                    let validationErrors = [];

                    if (!addressLine1) {
                        validationErrors.push('Address Line 1 is required');
                    } else if (addressLine1.length < 5 || addressLine1.length > 100) {
                        validationErrors.push('Address Line 1 must be between 5-100 characters');
                    }

                    if (!city) {
                        validationErrors.push('City is required');
                    } else if (city.length < 2 || city.length > 50) {
                        validationErrors.push('City must be between 2-50 characters');
                    } else if (!/^[A-Za-z\s\-']+$/.test(city) || /\d/.test(city)) {
                        validationErrors.push('City cannot contain numbers or special characters');
                    }

                    if (!country) {
                        validationErrors.push('Country is required');
                    } else if (country.length < 2 || country.length > 50) {
                        validationErrors.push('Country must be between 2-50 characters');
                    } else if (!/^[A-Za-z\s\-']+$/.test(country) || /\d/.test(country)) {
                        validationErrors.push('Country cannot contain numbers or special characters');
                    }

                    if (!state) {
                        validationErrors.push('State is required');
                    } else if (state.length > 50 || !/^[A-Za-z\s\-']+$/.test(state) || /\d/.test(state)) {
                        validationErrors.push('State cannot contain numbers or special characters');
                    }

                    if (!postalCode) {
                        validationErrors.push('Postal code is required');
                    } else if (!/^[0-9]{5}$/.test(postalCode)) {
                        validationErrors.push('Postal code must be exactly 5 digits');
                    }

                    if (addressLine2 && addressLine2.length > 100) {
                        validationErrors.push('Address Line 2 must not exceed 100 characters');
                    }

                    if (validationErrors.length > 0) {
                        // Show errors below each field instead of alert box
                        showFieldErrors(validationErrors);
                        return;
                    }

                    const formData = new FormData();
                    formData.append('add_address', '1');
                    formData.append('address_line1', addressLine1);
                    formData.append('address_line2', addressLine2);
                    formData.append('city', city);
                    formData.append('state', state);
                    formData.append('postal_code', postalCode);
                    formData.append('country', country);

                    // Show loading state
                    addAddressBtn.disabled = true;
                    addAddressBtn.innerHTML = 'Saving...';

                    fetch('checkout_ajax.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Address addition response:', data); // Debug log

                            // Remove existing alerts
                            document.querySelectorAll('.alert').forEach(a => a.remove());

                            if (data.success) {
                                // Show success message
                                showAlert(data.message, 'success');

                                // Add new address to the list
                                addAddressToList(data.address);

                                // Clear form
                                document.querySelector('input[name="address_line1"]').value = '';
                                document.querySelector('input[name="address_line2"]').value = '';
                                document.querySelector('input[name="city"]').value = '';
                                document.querySelector('input[name="state"]').value = '';
                                document.querySelector('input[name="postal_code"]').value = '';

                                // Hide form
                                document.getElementById('addressForm').classList.remove('active');
                            } else {
                                // Show error message
                                showAlert(data.message, 'error');
                            }

                            // Reset button
                            addAddressBtn.disabled = false;
                            addAddressBtn.innerHTML = 'Save Address';
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showAlert('An error occurred while saving the address. Please try again.', 'error');
                            addAddressBtn.disabled = false;
                            addAddressBtn.innerHTML = 'Save Address';
                        });
                });
            }
        });

        function showAlert(message, type) {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            document.body.appendChild(alert);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                alert.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }

        function updatePriceSummary(cartTotal, voucherDiscount, finalTotal, voucherCode) {
            const priceSummary = document.querySelector('.price-summary');
            
            // Calculate shipping fee
            const freeShippingThreshold = 15.00;
            const defaultShippingFee = 5.00;
            const shippingFee = cartTotal >= freeShippingThreshold ? 0 : defaultShippingFee;

            let html = `
                <div class="price-row">
                    <span>Subtotal</span>
                    <span>RM${parseFloat(cartTotal).toFixed(2)}</span>
                </div>
                <div class="price-row">
                    <span>Shipping</span>
                    <span>${shippingFee > 0 ? 'RM' + shippingFee.toFixed(2) : '<span style="color: #28a745; font-weight: 600;">FREE</span>'}</span>
                </div>
            `;

            // Show free shipping message if applicable
            if (cartTotal < freeShippingThreshold && shippingFee > 0) {
                const amountNeeded = freeShippingThreshold - cartTotal;
                html += `
                    <div class="price-row" style="font-size: 12px; color: #666; font-style: italic;">
                        <span>Free shipping on orders over RM${freeShippingThreshold.toFixed(2)}</span>
                        <span>Add RM${amountNeeded.toFixed(2)} more</span>
                    </div>
                `;
            }

            // Ensure discount doesn't exceed cart total
            let adjustedVoucherDiscount = voucherDiscount;
            if (adjustedVoucherDiscount > cartTotal) {
                adjustedVoucherDiscount = cartTotal;
            }

            if (adjustedVoucherDiscount > 0) {
                html += `
                    <div class="price-row discount">
                        <span>Discount</span>
                        <span>-RM${parseFloat(adjustedVoucherDiscount).toFixed(2)}</span>
                    </div>
                    <input type="hidden" name="voucher_code_hidden" value="${voucherCode}">
                `;
            }

            // Recalculate final total with shipping and prevent negative values
            let calculatedTotal = cartTotal + shippingFee - adjustedVoucherDiscount;
            
            // Ensure final total is never negative (minimum should be shipping fee if applicable)
            if (calculatedTotal < 0) {
                calculatedTotal = shippingFee;
            }

            html += `
                <div class="price-row total">
                    <span>Total</span>
                    <span>RM${calculatedTotal.toFixed(2)}</span>
                </div>
            `;

            priceSummary.innerHTML = html;
        }

        function addAddressToList(address) {
            const addressGrid = document.querySelector('#addressGrid');
            const noAddressesMessage = document.querySelector('#noAddressesMessage');

            // Remove "no addresses" message if it exists
            if (noAddressesMessage) {
                noAddressesMessage.remove();
            }

            const addressHTML = `
                <label class="address-option newly-added">
                    <input type="radio" 
                           name="shipping_address_id" 
                           value="${address.shipping_address_id}" 
                           required
                           checked>
                    <div class="address-content">
                        <div class="address-line primary">
                            ${address.address_line1}
                        </div>
                        ${address.address_line2 ? `<div class="address-line">${address.address_line2}</div>` : ''}
                        <div class="address-line">
                            ${address.city}, ${address.state} ${address.postal_code}
                        </div>
                        <div class="address-line">
                            ${address.country}
                        </div>
                    </div>
                    <div class="check-icon">✓</div>
                </label>
            `;

            addressGrid.insertAdjacentHTML('afterbegin', addressHTML);

            // Re-attach event listeners
            document.querySelectorAll('.address-option').forEach(option => {
                option.addEventListener('click', function() {
                    document.querySelectorAll('.address-option').forEach(opt => {
                        opt.style.borderColor = '#ddd';
                        opt.style.boxShadow = 'none';
                        opt.classList.remove('newly-added');
                    });
                    this.style.borderColor = '#4CAF50';
                    this.style.boxShadow = '0 4px 12px rgba(76, 175, 80, 0.2)';
                });
            });

            // Remove the highlight class after animation completes
            setTimeout(() => {
                const newAddressOption = addressGrid.querySelector('.newly-added');
                if (newAddressOption) {
                    newAddressOption.classList.remove('newly-added');
                }
            }, 2000);
        }
    </script>
    <style>
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        /* Loading state for buttons */
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Highlight newly added address */
        .address-option.newly-added {
            border-color: #4CAF50 !important;
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3) !important;
            animation: highlightNew 2s ease-in-out;
        }

        @keyframes highlightNew {
            0% {
                background-color: rgba(76, 175, 80, 0.1);
            }

            100% {
                background-color: transparent;
            }
        }
    </style>

    <script>
        // Prevent browser back button after accessing checkout
        // This prevents users from going back after placing an order
        if (window.history && window.history.pushState) {
            window.history.pushState('forward', null, window.location.href);
            window.addEventListener('popstate', function() {
                window.history.pushState('forward', null, window.location.href);
            });
        }
    </script>
</div>

<?php include '../../partials/footer.php'; ?>
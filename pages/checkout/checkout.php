<?php
session_start();
require_once '../../helper/pdo_connection.php';

// Check if user is logged in
if (!isset($_SESSION['member'])) {
    header('Location: /pages/auth/login.php');
    exit();
}

$member_id = $_SESSION['member']['id'];

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

// Handle order placement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $shipping_address_id = $_POST['shipping_address_id'] ?? null;
    $voucher_code = $_POST['voucher_code_hidden'] ?? '';

    if (empty($shipping_address_id)) {
        $error_message = "Please select a shipping address.";
    } else {
        try {
            $pdo->beginTransaction();

            $final_discount = 0;
            $voucher_id = null;
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
                    $voucher_id = $voucher['voucher_id'];

                    if ($voucher['discount_type'] === 'fixed') {
                        $final_discount = $voucher['discount_value'];
                    } else {
                        $final_discount = ($cart_total * $voucher['discount_value']) / 100;
                        if ($voucher['max_discount'] && $final_discount > $voucher['max_discount']) {
                            $final_discount = $voucher['max_discount'];
                        }
                    }
                }
            }

            $final_amount = $cart_total - $final_discount;

            // Get the selected shipping address details
            $stmt = $pdo->prepare("SELECT * FROM Shipping_Address WHERE shipping_address_id = ? AND member_id = ?");
            $stmt->execute([$shipping_address_id, $member_id]);
            $selected_address = $stmt->fetch();

            if (!$selected_address) {
                throw new Exception("Invalid shipping address selected.");
            }

            // Insert order with shipping address details
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
                $selected_address['address_line1'],
                $selected_address['address_line2'],
                $selected_address['city'],
                $selected_address['state'],
                $selected_address['postal_code'],
                $selected_address['country'],
                $final_amount
            ]);
            $order_id = $pdo->lastInsertId();

            foreach ($cart_items as $item) {
                // First, check current stock before creating order item
                $stmt = $pdo->prepare("SELECT stock FROM Product WHERE product_id = ?");
                $stmt->execute([$item['product_id']]);
                $current_stock = $stmt->fetchColumn();
                
                if ($current_stock < $item['quantity']) {
                    throw new Exception("Insufficient stock for product: " . $item['name'] . ". Available: " . $current_stock . ", Requested: " . $item['quantity']);
                }
                
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

                // Update stock with additional safety check
                $stmt = $pdo->prepare("
                    UPDATE Product 
                    SET stock = stock - ? 
                    WHERE product_id = ? AND stock >= ?
                ");
                $affected_rows = $stmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
                
                // Double-check that the update actually happened
                if ($stmt->rowCount() === 0) {
                    throw new Exception("Stock update failed for product: " . $item['name'] . ". Possible concurrent purchase.");
                }
            }

            if ($voucher_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO Voucher_Usage (voucher_id, member_id, order_id)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$voucher_id, $member_id, $order_id]);
            }

            // Clear cart only if not buy_now (buy_now doesn't affect cart)
            if (!$is_buy_now) {
                $stmt = $pdo->prepare("DELETE FROM Cart_Item WHERE cart_id IN (SELECT cart_id FROM Cart WHERE member_id = ?)");
                $stmt->execute([$member_id]);
            } else {
                // Clear buy_now session data
                unset($_SESSION['buy_now_item']);
            }

            $pdo->commit();

            header("Location: ../payment/payment.php?order_id=" . $order_id);
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = "Error placing order: " . $e->getMessage();
        }
    }
}

$final_total = $cart_total - $voucher_discount;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Online Store</title>
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
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-title {
            font-size: 32px;
            color: #333;
            margin-bottom: 30px;
        }

        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 450px;
            gap: 25px;
        }

        .alert {
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

        .alert-error {
            background-color: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .section {
            background: white;
            padding: 30px;
            margin-bottom: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .section-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #4CAF50;
            font-weight: 600;
        }

        /* Address Section */
        .address-grid {
            display: grid;
            gap: 15px;
            margin-bottom: 20px;
        }

        .no-addresses {
            background-color: #f8f9fa;
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
        }

        .address-option {
            position: relative;
            border: 2px solid #ddd;
            padding: 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .address-option:hover {
            border-color: #4CAF50;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .address-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .address-option input[type="radio"]:checked+.address-content {
            border-left: 4px solid #4CAF50;
            padding-left: 16px;
        }

        .address-option input[type="radio"]:checked~.check-icon {
            display: block;
        }

        .address-content {
            transition: all 0.3s;
        }

        .check-icon {
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

        .address-line {
            margin-bottom: 5px;
            color: #333;
        }

        .address-line.primary {
            font-weight: 600;
            font-size: 16px;
        }

        .add-address-toggle {
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
        }

        .add-address-toggle:hover {
            background-color: #4CAF50;
            color: white;
            border-style: solid;
        }

        .address-form {
            display: none;
            background-color: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            margin-top: 15px;
        }

        .address-form.active {
            display: block;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }

        label .required {
            color: #e74c3c;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            transition: border-color 0.3s;
        }

        input[type="text"]:focus {
            outline: none;
            border-color: #4CAF50;
        }

        /* Order Summary Section */
        .summary-product {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .summary-product:last-of-type {
            border-bottom: none;
            margin-bottom: 20px;
        }

        .summary-product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
        }

        .summary-product-info {
            flex: 1;
        }

        .summary-product-name {
            font-size: 15px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .summary-product-meta {
            font-size: 13px;
            color: #666;
        }

        .summary-product-price {
            font-weight: 600;
            color: #333;
        }

        /* Voucher Input */
        .voucher-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .voucher-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
        }

        .voucher-input:focus {
            outline: none;
            border-color: #4CAF50;
        }

        /* Price Summary */
        .price-summary {
            border-top: 2px solid #eee;
            padding-top: 15px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 15px;
        }

        .price-row.discount {
            color: #28a745;
            font-weight: 600;
        }

        .price-row.total {
            border-top: 2px solid #ddd;
            margin-top: 10px;
            padding-top: 15px;
            font-size: 22px;
            font-weight: 700;
            color: #696969ff;
        }

        /* Buttons */
        .btn {
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
        }

        .btn-apply {
            background-color: #666;
            color: white;
        }

        .btn-apply:hover {
            background-color: #555;
        }

        .btn-primary {
            background-color: #4CAF50;
            color: white;
            width: 100%;
            font-size: 18px;
            padding: 16px;
        }

        .btn-primary:hover {
            background-color: #45a049;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
            width: 100%;
        }

        .btn-secondary:hover {
            background-color: #e0e0e0;
        }

        /* Enhanced styles for address form buttons */
        .address-form .btn {
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border: 2px solid transparent;
            width: auto;
        }

        .address-form .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .address-form .btn-secondary {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            border: 2px solid #4CAF50;
        }

        .address-form .btn-secondary:hover {
            background: linear-gradient(135deg, #45a049, #3d8b40);
            border-color: #45a049;
        }

        .address-form #clearAddressBtn {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #6c757d;
            border: 2px solid #dee2e6;
        }

        .address-form #clearAddressBtn:hover {
            background: linear-gradient(135deg, #e9ecef, #dee2e6);
            color: #495057;
            border-color: #adb5bd;
        }

        /* Button container styling */
        .address-form .form-group.full-width > div {
            display: flex;
            gap: 12px;
            margin-bottom: 8px;
        }

        .address-form .form-group.full-width > div .btn {
            flex: 1;
            min-height: 44px;
            font-size: 14px;
        }

        @media (max-width: 968px) {
            .checkout-layout {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="<?php echo (isset($_SESSION['member']['theme']) && $_SESSION['member']['theme'] === 'dark') ? 'dark-theme' : ''; ?>">
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

            let html = `
                <div class="price-row">
                    <span>Subtotal</span>
                    <span>RM${parseFloat(cartTotal).toFixed(2)}</span>
                </div>
            `;

            if (voucherDiscount > 0) {
                html += `
                    <div class="price-row discount">
                        <span>Discount</span>
                        <span>-RM${parseFloat(voucherDiscount).toFixed(2)}</span>
                    </div>
                    <input type="hidden" name="voucher_code_hidden" value="${voucherCode}">
                `;
            }

            html += `
                <div class="price-row total">
                    <span>Total</span>
                    <span>RM${parseFloat(finalTotal).toFixed(2)}</span>
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
</body>

</html>
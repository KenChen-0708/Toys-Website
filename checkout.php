<?php
session_start();
require_once 'helper/pdo_connection.php';

// Check if user is logged in
if (!isset($_SESSION['member'])) {
    header('Location: auth/login.php');
    exit();
}

$member_id = $_SESSION['member']['id'];

$error_message = '';
$success_message = '';
$voucher_discount = 0;
$voucher_code = '';
$voucher_applied = false;

// Fetch cart items
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
    
    // if (empty($cart_items)) {
    //     header(header: 'Location: cart.php');
    //     exit();
    // }
    
    // Calculate cart total
    $cart_total = 0;
    foreach ($cart_items as $item) {
        $cart_total += $item['subtotal'];
    }
    
} catch (PDOException $e) {
    $error_message = "Error fetching cart items: " . $e->getMessage();
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

// Handle new shipping address
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_address'])) {
    $address_line1 = trim($_POST['address_line1']);
    $address_line2 = trim($_POST['address_line2']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $postal_code = trim($_POST['postal_code']);
    $country = trim($_POST['country']);
    
    if (empty($address_line1) || empty($city) || empty($country)) {
        $error_message = "Please fill in all required address fields.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO Shipping_Address 
                (member_id, address_line1, address_line2, city, state, postal_code, country)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $member_id, $address_line1, $address_line2, 
                $city, $state, $postal_code, $country
            ]);
            
            $success_message = "Address added successfully!";
            
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
                
                if ($voucher && $voucher['times_used_by_user'] < $voucher['usage_per_user'] 
                    && $cart_total >= $voucher['min_spend']) {
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
            
            $stmt = $pdo->prepare("
                INSERT INTO `Order` (member_id, shipping_address_id, total_amount, status)
                VALUES (?, ?, ?, 'Pending')
            ");
            $stmt->execute([$member_id, $shipping_address_id, $final_amount]);
            $order_id = $pdo->lastInsertId();
            
            foreach ($cart_items as $item) {
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
                
                $stmt = $pdo->prepare("
                    UPDATE Product 
                    SET stock = stock - ? 
                    WHERE product_id = ?
                ");
                $stmt->execute([$item['quantity'], $item['product_id']]);
            }
            
            if ($voucher_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO Voucher_Usage (voucher_id, member_id, order_id)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$voucher_id, $member_id, $order_id]);
            }
            
            $stmt = $pdo->prepare("DELETE FROM Cart_Item WHERE cart_id IN (SELECT cart_id FROM Cart WHERE member_id = ?)");
            $stmt->execute([$member_id]);
            
            $pdo->commit();
            
            header("Location: payment.php?order_id=" . $order_id);
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
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
        
        /* Address Section */
        .address-grid {
            display: grid;
            gap: 15px;
            margin-bottom: 20px;
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .address-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .address-option input[type="radio"]:checked + .address-content {
            border-left: 4px solid #4CAF50;
            padding-left: 16px;
        }
        
        .address-option input[type="radio"]:checked ~ .check-icon {
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
            color: #4CAF50;
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
<body>
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
                        
                        <?php if (!empty($shipping_addresses)): ?>
                            <div class="address-grid">
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
                            </div>
                        <?php endif; ?>
                        
                        <a href="#" class="add-address-toggle" onclick="toggleAddressForm(event)">
                            + Add New Address
                        </a>
                        
                        <div class="address-form" id="addressForm">
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label>Address Line 1 <span class="required">*</span></label>
                                    <input type="text" name="address_line1" placeholder="Street address, P.O. box">
                                </div>
                                
                                <div class="form-group full-width">
                                    <label>Address Line 2</label>
                                    <input type="text" name="address_line2" placeholder="Apartment, suite, unit, etc.">
                                </div>
                                
                                <div class="form-group">
                                    <label>City <span class="required">*</span></label>
                                    <input type="text" name="city" placeholder="City">
                                </div>
                                
                                <div class="form-group">
                                    <label>State</label>
                                    <input type="text" name="state" placeholder="State/Province">
                                </div>
                                
                                <div class="form-group">
                                    <label>Postal Code</label>
                                    <input type="text" name="postal_code" placeholder="Postal code">
                                </div>
                                
                                <div class="form-group">
                                    <label>Country <span class="required">*</span></label>
                                    <input type="text" name="country" placeholder="Country" value="Malaysia">
                                </div>
                                
                                <div class="form-group full-width">
                                    <button type="submit" name="add_address" class="btn btn-secondary">
                                        Save Address
                                    </button>
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
                                    <img src="<?php echo htmlspecialchars($item['main_image_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                                         class="summary-product-img">
                                <?php else: ?>
                                    <img src="images/no-image.png" alt="No image" class="summary-product-img">
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
        
        // AJAX for voucher application
        document.addEventListener('DOMContentLoaded', function() {
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
                    
                    const formData = new FormData();
                    formData.append('add_address', '1');
                    formData.append('address_line1', document.querySelector('input[name="address_line1"]').value);
                    formData.append('address_line2', document.querySelector('input[name="address_line2"]').value);
                    formData.append('city', document.querySelector('input[name="city"]').value);
                    formData.append('state', document.querySelector('input[name="state"]').value);
                    formData.append('postal_code', document.querySelector('input[name="postal_code"]').value);
                    formData.append('country', document.querySelector('input[name="country"]').value);
                    
                    // Show loading state
                    addAddressBtn.disabled = true;
                    addAddressBtn.innerHTML = 'Saving...';
                    
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
                        showAlert('An error occurred. Please try again.', 'error');
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
            const addressGrid = document.querySelector('.address-grid');
            
            const addressHTML = `
                <label class="address-option">
                    <input type="radio" 
                           name="shipping_address_id" 
                           value="${address.shipping_address_id}" 
                           required>
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
                    });
                    this.style.borderColor = '#4CAF50';
                    this.style.boxShadow = '0 4px 12px rgba(76, 175, 80, 0.2)';
                });
            });
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
    </style>
</body>
</html>
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
            
            // Ensure final amount is never less than shipping fee (minimum RM5 when shipping applies)
            if ($shipping_fee > 0 && $final_amount < $shipping_fee) {
                $final_amount = $shipping_fee;
                // Adjust discount to ensure minimum total of shipping fee
                $final_discount = $cart_total;
            } elseif ($shipping_fee == 0 && $final_amount < 0) {
                // For free shipping orders, ensure total is never negative
                $final_amount = 0;
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

// Ensure final total is never less than shipping fee (minimum RM5 when shipping applies)
if ($shipping_fee > 0 && $final_total < $shipping_fee) {
    // Customer should pay minimum of shipping fee (RM5)
    $final_total = $shipping_fee;
    // Recalculate discount: if final_total = shipping_fee, then discount = cart_total
    // Because: cart_total - discount + shipping_fee = shipping_fee
    // Therefore: discount = cart_total
    $voucher_discount = $cart_total;
} elseif ($shipping_fee == 0 && $final_total < 0) {
    // For free shipping orders, ensure total is never negative
    $final_total = 0;
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
    <link rel="stylesheet" href="/assets/css/checkout.css">
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
                                <?php foreach ($shipping_addresses as $idx => $address): ?>
                                    <?php $isDefault = ($idx === 0); ?>
                                    <label class="address-option <?php echo $isDefault ? 'address-option--default' : ''; ?>">
                                        <input type="radio"
                                            name="shipping_address_id"
                                            value="<?php echo $address['shipping_address_id']; ?>"
                                            <?php echo $isDefault ? 'checked' : ''; ?>
                                            required>
                                        <?php if ($isDefault): ?>
                                            <div class="address-default-badge">Default</div>
                                        <?php endif; ?>
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

    <script src="/assets/js/checkout.js"></script>
</div>

<?php include '../../partials/footer.php'; ?>
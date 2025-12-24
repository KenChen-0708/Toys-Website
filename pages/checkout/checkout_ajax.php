<?php
session_start();
require_once '../../helper/pdo_connection.php';

header('Content-Type: application/json');

// Error handling function
function sendJsonResponse($success, $message, $data = []) {
    $response = array_merge(['success' => $success, 'message' => $message], $data);
    echo json_encode($response);
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['member'])) {
    sendJsonResponse(false, 'Please log in to continue.');
}

$member_id = $_SESSION['member']['id'];

// Handle voucher application
if (isset($_POST['apply_voucher'])) {
    $voucher_code = trim($_POST['voucher_code']);
    
    if (empty($voucher_code)) {
        sendJsonResponse(false, 'Please enter a voucher code.');
    }
    
    try {
        // Check if this is a "Buy Now" checkout or regular cart checkout
        $is_buy_now = isset($_SESSION['buy_now_item']);
        $cart_total = 0;
        
        if ($is_buy_now) {
            // Use buy_now item from session
            $buy_now_item = $_SESSION['buy_now_item'];
            $cart_total = $buy_now_item['quantity'] * $buy_now_item['price'];
        } else {
            // Get cart total from database
            $stmt = $pdo->prepare("
                SELECT SUM(ci.quantity * p.price) as cart_total
                FROM Cart c
                INNER JOIN Cart_Item ci ON c.cart_id = ci.cart_id
                INNER JOIN Product p ON ci.product_id = p.product_id
                WHERE c.member_id = ? AND p.status = 'Available'
            ");
            $stmt->execute([$member_id]);
            $result = $stmt->fetch();
            $cart_total = $result['cart_total'] ?? 0;
        }
        
        // Validate voucher
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
        
        if (!$voucher) {
            sendJsonResponse(false, 'Invalid or expired voucher code.');
        }
        
        if ($voucher['times_used_by_user'] >= $voucher['usage_per_user']) {
            sendJsonResponse(false, 'You have already used this voucher the maximum number of times.');
        }
        
        if ($cart_total < $voucher['min_spend']) {
            sendJsonResponse(false, 'Minimum spend of RM' . number_format($voucher['min_spend'], 2) . ' required for this voucher.');
        }
        
        // Calculate discount
        $voucher_discount = 0;
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
        
        sendJsonResponse(true, 'Voucher applied successfully! You saved RM' . number_format($voucher_discount, 2), [
            'cart_total' => $cart_total,
            'shipping_fee' => $shipping_fee,
            'voucher_discount' => $voucher_discount,
            'final_total' => $final_total
        ]);
        
    } catch (PDOException $e) {
        error_log("Voucher validation error: " . $e->getMessage());
        sendJsonResponse(false, 'Error validating voucher. Please try again.');
    }
}

// Handle add new address
if (isset($_POST['add_address'])) {
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
        sendJsonResponse(false, implode('. ', $validation_errors) . '.');
    }
    
    try {
        // Insert new address
        $stmt = $pdo->prepare("
            INSERT INTO Shipping_Address 
            (member_id, address_line1, address_line2, city, state, postal_code, country, created_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $result = $stmt->execute([
            $member_id, $address_line1, $address_line2, 
            $city, $state, $postal_code, $country
        ]);
        
        if (!$result) {
            sendJsonResponse(false, 'Failed to insert address into database.');
        }
        
        $new_address_id = $pdo->lastInsertId();
        
        if (!$new_address_id) {
            sendJsonResponse(false, 'Failed to get new address ID.');
        }
        
        // Fetch the newly added address
        $stmt = $pdo->prepare("SELECT * FROM Shipping_Address WHERE shipping_address_id = ?");
        $stmt->execute([$new_address_id]);
        $address = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$address) {
            sendJsonResponse(false, 'Failed to retrieve newly added address.');
        }
        
        sendJsonResponse(true, 'Address added successfully!', ['address' => $address]);
        
    } catch (PDOException $e) {
        error_log("Address addition error: " . $e->getMessage());
        sendJsonResponse(false, 'Database error occurred while adding address. Please try again.');
    }
}

sendJsonResponse(false, 'Invalid request.');
?>
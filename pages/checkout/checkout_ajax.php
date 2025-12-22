<?php
session_start();
require_once '../../helper/pdo_connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['member'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to continue.']);
    exit();
}

$member_id = $_SESSION['member']['id'];

// Handle voucher application
if (isset($_POST['apply_voucher'])) {
    $voucher_code = trim($_POST['voucher_code']);
    
    if (empty($voucher_code)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a voucher code.']);
        exit();
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
            echo json_encode(['success' => false, 'message' => 'Invalid voucher code.']);
            exit();
        }
        
        if ($voucher['times_used_by_user'] >= $voucher['usage_per_user']) {
            echo json_encode(['success' => false, 'message' => 'You have already used this voucher the maximum number of times.']);
            exit();
        }
        
        if ($cart_total < $voucher['min_spend']) {
            echo json_encode([
                'success' => false, 
                'message' => 'Minimum spend of RM' . number_format($voucher['min_spend'], 2) . ' required for this voucher.'
            ]);
            exit();
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
        
        $final_total = $cart_total - $voucher_discount;
        
        echo json_encode([
            'success' => true,
            'message' => 'Voucher applied successfully! You saved RM' . number_format($voucher_discount, 2),
            'cart_total' => $cart_total,
            'voucher_discount' => $voucher_discount,
            'final_total' => $final_total
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error validating voucher: ' . $e->getMessage()]);
    }
    exit();
}

// Handle add new address
if (isset($_POST['add_address'])) {
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');
    $country = trim($_POST['country'] ?? '');
    
    // Validate required fields
    if (empty($address_line1) || empty($city) || empty($country)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Please fill in all required fields (Address Line 1, City, and Country).'
        ]);
        exit();
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
            echo json_encode(['success' => false, 'message' => 'Failed to insert address into database.']);
            exit();
        }
        
        $new_address_id = $pdo->lastInsertId();
        
        if (!$new_address_id) {
            echo json_encode(['success' => false, 'message' => 'Failed to get new address ID.']);
            exit();
        }
        
        // Fetch the newly added address
        $stmt = $pdo->prepare("SELECT * FROM Shipping_Address WHERE shipping_address_id = ?");
        $stmt->execute([$new_address_id]);
        $address = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$address) {
            echo json_encode(['success' => false, 'message' => 'Failed to retrieve newly added address.']);
            exit();
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Address added successfully!',
            'address' => $address
        ]);
        
    } catch (PDOException $e) {
        error_log("Address addition error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Database error occurred while adding address. Please try again.'
        ]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);
?>
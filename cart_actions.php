<?php
session_start();
require_once 'helper/pdo_connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['member'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to add items to cart',
        'redirect' => 'auth/login.php'
    ]);
    exit();
}

$member_id = $_SESSION['member']['id'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            $product_id = (int)($_POST['product_id'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 1);

            if ($product_id <= 0 || $quantity <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product or quantity']);
                exit();
            }

            // Check if product exists and is available
            $stmt = $pdo->prepare("SELECT product_id, name, price, stock, status FROM Product WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();

            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit();
            }

            if ($product['status'] !== 'Available') {
                echo json_encode(['success' => false, 'message' => 'Product is not available']);
                exit();
            }

            if ($quantity > $product['stock']) {
                echo json_encode(['success' => false, 'message' => 'Insufficient stock available']);
                exit();
            }

            // Get or create cart for user
            $stmt = $pdo->prepare("SELECT cart_id FROM Cart WHERE user_id = ?");
            $stmt->execute([$member_id]);
            $cart = $stmt->fetch();

            if (!$cart) {
                // Create new cart
                $stmt = $pdo->prepare("INSERT INTO Cart (user_id) VALUES (?)");
                $stmt->execute([$member_id]);
                $cart_id = $pdo->lastInsertId();
            } else {
                $cart_id = $cart['cart_id'];
            }

            // Check if product already in cart
            $stmt = $pdo->prepare("SELECT cart_item_id, quantity FROM Cart_Item WHERE cart_id = ? AND product_id = ?");
            $stmt->execute([$cart_id, $product_id]);
            $cart_item = $stmt->fetch();

            if ($cart_item) {
                // Update quantity
                $new_quantity = $cart_item['quantity'] + $quantity;
                
                if ($new_quantity > $product['stock']) {
                    echo json_encode(['success' => false, 'message' => 'Cannot add more items. Stock limit reached']);
                    exit();
                }

                $stmt = $pdo->prepare("UPDATE Cart_Item SET quantity = ? WHERE cart_item_id = ?");
                $stmt->execute([$new_quantity, $cart_item['cart_item_id']]);
            } else {
                // Add new item to cart
                $stmt = $pdo->prepare("INSERT INTO Cart_Item (cart_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$cart_id, $product_id, $quantity]);
            }

            // Get cart count
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Cart_Item WHERE cart_id = ?");
            $stmt->execute([$cart_id]);
            $cart_count = $stmt->fetch()['count'];

            echo json_encode([
                'success' => true,
                'message' => 'Product added to cart successfully',
                'cart_count' => $cart_count
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
}
?>

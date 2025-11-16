<?php
session_start();
require_once 'helper/pdo_connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['member'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to manage wishlist',
        'redirect' => 'auth/login.php',
        'debug' => 'No session found'
    ]);
    exit();
}

if (!isset($_SESSION['member']['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Session error - please log in again',
        'redirect' => 'auth/login.php',
        'debug' => 'Session exists but no ID: ' . json_encode($_SESSION['member'])
    ]);
    exit();
}

$member_id = $_SESSION['member']['id'];
$action = $_POST['action'] ?? '';

// Debug: Check if member exists in database
$stmt = $pdo->prepare("SELECT member_id FROM Member WHERE member_id = ?");
$stmt->execute([$member_id]);
$member_exists = $stmt->fetch();

if (!$member_exists) {
    echo json_encode([
        'success' => false,
        'message' => 'Member not found in database. Please log in again.',
        'debug' => 'Member ID ' . $member_id . ' does not exist in Member table'
    ]);
    exit();
}

try {
    switch ($action) {
        case 'add':
            $product_id = (int)($_POST['product_id'] ?? 0);

            if ($product_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product']);
                exit();
            }

            // Check if product exists
            $stmt = $pdo->prepare("SELECT product_id, name FROM Product WHERE product_id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();

            if (!$product) {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
                exit();
            }

            // Get or create wishlist for user
            $stmt = $pdo->prepare("SELECT wishlist_id FROM Wishlist WHERE member_id = ?");
            $stmt->execute([$member_id]);
            $wishlist = $stmt->fetch();

            if (!$wishlist) {
                // Create new wishlist
                $stmt = $pdo->prepare("INSERT INTO Wishlist (member_id) VALUES (?)");
                $stmt->execute([$member_id]);
                $wishlist_id = $pdo->lastInsertId();
            } else {
                $wishlist_id = $wishlist['wishlist_id'];
            }

            // Check if product already in wishlist
            $stmt = $pdo->prepare("SELECT wishlist_item_id FROM Wishlist_Item WHERE wishlist_id = ? AND product_id = ?");
            $stmt->execute([$wishlist_id, $product_id]);
            $existing = $stmt->fetch();

            if ($existing) {
                echo json_encode(['success' => false, 'message' => 'Product already in wishlist']);
                exit();
            }

            // Add to wishlist
            $stmt = $pdo->prepare("INSERT INTO Wishlist_Item (wishlist_id, product_id) VALUES (?, ?)");
            $stmt->execute([$wishlist_id, $product_id]);

            // Get wishlist count
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Wishlist_Item WHERE wishlist_id = ?");
            $stmt->execute([$wishlist_id]);
            $wishlist_count = $stmt->fetch()['count'];

            echo json_encode([
                'success' => true,
                'message' => 'Product added to wishlist',
                'wishlist_count' => $wishlist_count
            ]);
            break;

        case 'remove':
            $product_id = (int)($_POST['product_id'] ?? 0);

            if ($product_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product']);
                exit();
            }

            // Remove from wishlist
            $stmt = $pdo->prepare("
                DELETE wi FROM Wishlist_Item wi
                JOIN Wishlist w ON wi.wishlist_id = w.wishlist_id
                WHERE w.member_id = ? AND wi.product_id = ?
            ");
            $stmt->execute([$member_id, $product_id]);

            // Get updated wishlist count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count FROM Wishlist_Item wi
                JOIN Wishlist w ON wi.wishlist_id = w.wishlist_id
                WHERE w.member_id = ?
            ");
            $stmt->execute([$member_id]);
            $wishlist_count = $stmt->fetch()['count'];

            echo json_encode([
                'success' => true,
                'message' => 'Product removed from wishlist',
                'wishlist_count' => $wishlist_count
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred: ' . $e->getMessage(),
        'error' => $e->getMessage(),
        'debug' => [
            'member_id' => $member_id ?? 'not set',
            'session' => $_SESSION['member'] ?? 'not logged in'
        ]
    ]);
}
?>
<?php
session_start();
require_once '../../helper/pdo_connection.php';

header('Content-Type: application/json');

$cart_count = 0;
$wishlist_count = 0;

if (isset($_SESSION['member'])) {
    $member_id = $_SESSION['member']['id'];
    
    try {
        // Get cart count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Cart_Item ci JOIN Cart c ON ci.cart_id = c.cart_id WHERE c.member_id = ?");
        $stmt->execute([$member_id]);
        $result = $stmt->fetch();
        $cart_count = $result ? $result['count'] : 0;
        
        // Get wishlist count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Wishlist_Item wi JOIN Wishlist w ON wi.wishlist_id = w.wishlist_id WHERE w.member_id = ?");
        $stmt->execute([$member_id]);
        $result = $stmt->fetch();
        $wishlist_count = $result ? $result['count'] : 0;
        
    } catch (PDOException $e) {
        // Silently fail and return 0 counts
    }
}

echo json_encode([
    'success' => true,
    'cart_count' => $cart_count,
    'wishlist_count' => $wishlist_count
]);
?>
<?php
session_start();
require_once 'helper/pdo_connection.php';

header('Content-Type: application/json');

$cart_count = 0;
$wishlist_count = 0;

if (isset($_SESSION['member'])) {
    $member_id = $_SESSION['member']['id'];
    
    try {
        // Get cart count
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM Cart c 
            JOIN Cart_Item ci ON c.cart_id = ci.cart_id 
            WHERE c.user_id = ?
        ");
        $stmt->execute([$member_id]);
        $result = $stmt->fetch();
        $cart_count = $result ? $result['count'] : 0;
        
        // Get wishlist count (if wishlist table exists)
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Wishlist WHERE member_id = ?");
            $stmt->execute([$member_id]);
            $result = $stmt->fetch();
            $wishlist_count = $result ? $result['count'] : 0;
        } catch (PDOException $e) {
            // Wishlist table might not exist
            $wishlist_count = 0;
        }
        
    } catch (PDOException $e) {
        // Error getting counts
    }
}

echo json_encode([
    'success' => true,
    'cart_count' => $cart_count,
    'wishlist_count' => $wishlist_count
]);
?>

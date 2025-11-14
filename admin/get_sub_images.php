<?php
header('Content-Type: application/json');

require_once '../helper/pdo_connection.php';

if (!isset($_GET['product_id']) || empty($_GET['product_id'])) {
    echo json_encode(['error' => 'Product ID is required']);
    exit;
}

try {
    $product_id = intval($_GET['product_id']);
    
    $stmt = $pdo->prepare("SELECT sub_image_id, sub_image_path FROM Product_Sub_Image WHERE product_id = ? ORDER BY sub_image_id");
    $stmt->execute([$product_id]);
    
    $subImages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($subImages);
} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}

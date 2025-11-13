<?php
header('Content-Type: application/json');

// Include database connection
include '../helper/connection.php';

// Check connection
if (!$db_ok || !$conn) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed', 'message' => 'Unable to connect to database']);
    exit;
}

$product_id = isset($_GET['product_id']) ? $conn->real_escape_string($_GET['product_id']) : '';

if (empty($product_id)) {
    echo json_encode(['error' => 'No product ID provided']);
    exit;
}

$sql = "SELECT sub_image_id, sub_image_path FROM Product_Sub_Image WHERE product_id='$product_id' ORDER BY sub_image_id ASC";
$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'message' => $conn->error]);
    exit;
}

$sub_images = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sub_images[] = $row;
    }
}

echo json_encode($sub_images);

$conn->close();
?>
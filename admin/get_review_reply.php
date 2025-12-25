<?php
session_start();

require_once '../helper/pdo_connection.php';
require_once '../helper/auth.php';

// Check if admin is logged in
if (!isset($_SESSION['admin']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Validate review_id
$review_id = isset($_GET['review_id']) ? (int)$_GET['review_id'] : 0;
if ($review_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid review ID']);
    exit;
}

try {
    // Fetch existing reply
    $stmt = $pdo->prepare("
        SELECT reply_id, review_id, reply_text, created_date
        FROM Review_Reply
        WHERE review_id = ?
    ");
    $stmt->execute([$review_id]);
    $reply = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($reply) {
        echo json_encode([
            'success' => true,
            'reply' => $reply
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'reply' => null
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

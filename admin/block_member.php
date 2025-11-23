<?php
session_start();

require_once '../helper/pdo_connection.php';
require_once '../helper/auth.php';

// Ensure only super admin or user admin can block members
require_admin();

$adminRole = get_admin_role();
if ($adminRole !== 'super admin' && $adminRole !== 'user admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to block/unblock members.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$memberId = isset($data['member_id']) ? (int)$data['member_id'] : 0;
$action = isset($data['action']) ? $data['action'] : ''; // 'block' or 'unblock'

if ($memberId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid member ID']);
    exit;
}

if ($action !== 'block' && $action !== 'unblock') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    // Check if member exists
    $checkStmt = $pdo->prepare("SELECT member_id, username, is_blocked FROM Member WHERE member_id = ?");
    $checkStmt->execute([$memberId]);
    $member = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
        exit;
    }

    // Update block status
    $newBlockStatus = ($action === 'block') ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE Member SET is_blocked = ? WHERE member_id = ?");
    $stmt->execute([$newBlockStatus, $memberId]);

    $statusText = ($action === 'block') ? 'blocked' : 'unblocked';
    $message = "Member '{$member['username']}' has been {$statusText} successfully.";
    echo json_encode([
        'success'    => true,
        'message'    => $message,
        'is_blocked' => $newBlockStatus
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

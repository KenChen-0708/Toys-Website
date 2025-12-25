<?php
session_start();
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';

is_logged_in();
redirect_if_admin();

$member_id = $_SESSION['member']['id'] ?? null;
$edit_window_hours = 48; // hours allowed for editing an existing review
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

function send_response($success, $message, $ajax = false)
{
	if ($ajax) {
		header('Content-Type: application/json');
		echo json_encode(['success' => $success, 'message' => $message]);
	}
	exit;
}

if (!$member_id) {
	header('Location: /pages/auth/login.php');
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	send_response(false, 'Invalid request method.', $is_ajax);
	exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$review_text = trim($_POST['review_text'] ?? '');

if ($order_id <= 0 || $product_id <= 0) {
	send_response(false, 'Invalid order or product.', $is_ajax);
}

if ($rating < 1 || $rating > 5) {
	send_response(false, 'Rating must be between 1 and 5.', $is_ajax);
}

if (strlen($review_text) < 5) {
	send_response(false, 'Please provide a brief comment (minimum 5 characters).', $is_ajax);
}

try {
	// Ensure the order belongs to the member and is delivered
	$order_stmt = $pdo->prepare("SELECT status FROM `Order` WHERE order_id = ? AND member_id = ? LIMIT 1");
	$order_stmt->execute([$order_id, $member_id]);
	$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

	if (!$order) {
		send_response(false, 'Order not found.', $is_ajax);
	}

	if ($order['status'] !== 'Delivered') {
		send_response(false, 'You can only review delivered orders.', $is_ajax);
	}

	// Ensure the product exists in the order
	$item_stmt = $pdo->prepare("SELECT COUNT(*) FROM Order_Item WHERE order_id = ? AND product_id = ?");
	$item_stmt->execute([$order_id, $product_id]);
	if ((int)$item_stmt->fetchColumn() === 0) {
		send_response(false, 'Product not found in this order.', $is_ajax);
	}

	// Check for an existing review for this product by this member
	$existing_stmt = $pdo->prepare("SELECT review_id, created_date FROM Review WHERE member_id = ? AND product_id = ? LIMIT 1");
	$existing_stmt->execute([$member_id, $product_id]);
	$existing = $existing_stmt->fetch(PDO::FETCH_ASSOC);

	if ($existing) {
		$created_at = !empty($existing['created_date']) ? new DateTimeImmutable($existing['created_date']) : null;
		$deadline = $created_at ? $created_at->modify("+{$edit_window_hours} hours") : null;
		$now = new DateTimeImmutable();

		if ($deadline && $now > $deadline) {
			send_response(false, "You can edit your review within {$edit_window_hours} hours of submitting.", $is_ajax);
		}

		$update_stmt = $pdo->prepare("UPDATE Review SET rating = ?, review_text = ? WHERE review_id = ?");
		$update_stmt->execute([$rating, $review_text, $existing['review_id']]);
		send_response(true, 'Review updated successfully!', $is_ajax);
	} else {
		$insert_stmt = $pdo->prepare("INSERT INTO Review (product_id, member_id, rating, review_text) VALUES (?, ?, ?, ?)");
		$insert_stmt->execute([$product_id, $member_id, $rating, $review_text]);
		send_response(true, 'Review submitted successfully!', $is_ajax);
	}
} catch (Throwable $e) {
	send_response(false, 'Unable to save review right now.', $is_ajax);
}

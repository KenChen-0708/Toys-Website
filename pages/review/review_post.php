<?php
session_start();
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';

is_logged_in();
redirect_if_admin();

$member_id = $_SESSION['member']['id'] ?? null;
$edit_window_hours = 48; // hours allowed for editing an existing review
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';
$upload_dir_fs = realpath(__DIR__ . '/../../assets/img/reviews') ?: (__DIR__ . '/../../assets/img/reviews');

// Ensure reviews upload directory exists
if (!is_dir($upload_dir_fs)) {
	mkdir($upload_dir_fs, 0775, true);
}

// Ensure media table exists (idempotent for older DBs)
$pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS Review_Media (
	review_media_id INT AUTO_INCREMENT PRIMARY KEY,
	review_id INT NOT NULL,
	file_path VARCHAR(255) NOT NULL,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (review_id) REFERENCES Review(review_id)
		ON DELETE CASCADE ON UPDATE CASCADE
);
SQL);

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
$photos = $_FILES['photos'] ?? null;

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

	// Helper: validate and move uploaded photos (up to 3 images)
	$uploaded_media = [];
	if ($photos && isset($photos['name']) && is_array($photos['name'])) {
		$files = [];
		$names = $photos['name'];
		$total = 0;
		foreach ($names as $idx => $name) {
			if ($name === '' || $photos['error'][$idx] === UPLOAD_ERR_NO_FILE) {
				continue;
			}
			$files[] = [
				'name' => $name,
				'tmp_name' => $photos['tmp_name'][$idx],
				'error' => $photos['error'][$idx],
				'size' => $photos['size'][$idx]
			];
			$total++;
		}

		if ($total > 3) {
			send_response(false, 'You can upload up to 3 images per review.', $is_ajax);
		}

		$allowed_mime = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
		$finfo = finfo_open(FILEINFO_MIME_TYPE);

		foreach ($files as $file) {
			if ($file['error'] !== UPLOAD_ERR_OK) {
				send_response(false, 'File upload error: ' . $file['error'], $is_ajax);
			}
			$mime = finfo_file($finfo, $file['tmp_name']);
			if (!in_array($mime, $allowed_mime, true)) {
				send_response(false, 'Invalid image type. Only JPG, PNG, and WEBP are allowed.', $is_ajax);
			}
			if ($file['size'] > 2 * 1024 * 1024) {
				send_response(false, 'Each image must be 2MB or smaller.', $is_ajax);
			}

			$ext = match ($mime) {
				'image/jpeg', 'image/jpg' => 'jpg',
				'image/png' => 'png',
				'image/webp' => 'webp',
				default => 'jpg',
			};

			$unique = 'review_' . $member_id . '_' . $product_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
			$target_fs = rtrim($upload_dir_fs, '/\\') . DIRECTORY_SEPARATOR . $unique;
			$public_path = '/assets/img/reviews/' . $unique;

			if (!move_uploaded_file($file['tmp_name'], $target_fs)) {
				send_response(false, 'Failed to save uploaded image.', $is_ajax);
			}

			$uploaded_media[] = $public_path;
		}

		if ($finfo) {
			finfo_close($finfo);
		}
	}

	// Fetch existing media paths for cleanup on replace
	$existing_media = [];
	if ($existing) {
		$media_stmt = $pdo->prepare("SELECT file_path FROM Review_Media WHERE review_id = ?");
		$media_stmt->execute([$existing['review_id']]);
		$existing_media = $media_stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
	}

	if ($existing) {
		$created_at = !empty($existing['created_date']) ? new DateTimeImmutable($existing['created_date']) : null;
		$deadline = $created_at ? $created_at->modify("+{$edit_window_hours} hours") : null;
		$now = new DateTimeImmutable();

		if ($deadline && $now > $deadline) {
			send_response(false, "You can edit your review within {$edit_window_hours} hours of submitting.", $is_ajax);
		}

		$update_stmt = $pdo->prepare("UPDATE Review SET rating = ?, review_text = ? WHERE review_id = ?");
		$update_stmt->execute([$rating, $review_text, $existing['review_id']]);

		// Replace media only if new uploads were provided
		if (!empty($uploaded_media)) {
			$delete_stmt = $pdo->prepare("DELETE FROM Review_Media WHERE review_id = ?");
			$delete_stmt->execute([$existing['review_id']]);

			$insert_media = $pdo->prepare("INSERT INTO Review_Media (review_id, file_path) VALUES (?, ?)");
			foreach ($uploaded_media as $path) {
				$insert_media->execute([$existing['review_id'], $path]);
			}

			// Remove old files from disk
			foreach ($existing_media as $path) {
				if (strpos($path, '/assets/img/reviews/') === 0) {
					$fs = realpath(__DIR__ . '/../../' . ltrim($path, '/'));
					if ($fs && is_file($fs)) {
						@unlink($fs);
					}
				}
			}
		}

		send_response(true, 'Review updated successfully!', $is_ajax);
	} else {
		$insert_stmt = $pdo->prepare("INSERT INTO Review (product_id, member_id, rating, review_text) VALUES (?, ?, ?, ?)");
		$insert_stmt->execute([$product_id, $member_id, $rating, $review_text]);
		$review_id = (int)$pdo->lastInsertId();

		if (!empty($uploaded_media) && $review_id > 0) {
			$insert_media = $pdo->prepare("INSERT INTO Review_Media (review_id, file_path) VALUES (?, ?)");
			foreach ($uploaded_media as $path) {
				$insert_media->execute([$review_id, $path]);
			}
		}
		send_response(true, 'Review submitted successfully!', $is_ajax);
	}
} catch (Throwable $e) {
	send_response(false, 'Unable to save review right now.', $is_ajax);
}

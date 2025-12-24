<?php
session_start();
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';

// Only logged-in members can review
is_logged_in();
redirect_if_admin();

$member_id = $_SESSION['member']['id'] ?? null;
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if (!$member_id) {
	header('Location: /pages/auth/login.php');
	exit;
}
$page_title = 'Review Order';
$error_message = '';
$notice_type = $_GET['type'] ?? '';
$notice_message = $_GET['message'] ?? '';
$order = null;
$items = [];
$existing_reviews = [];

try {
	if ($order_id <= 0) {
		throw new RuntimeException('Missing order id.');
	}
	// Confirm the order belongs to the member and is delivered
	$order_stmt = $pdo->prepare("SELECT order_id, status, order_date, total_amount FROM `Order` WHERE order_id = ? AND member_id = ? LIMIT 1");
	$order_stmt->execute([$order_id, $member_id]);
	$order = $order_stmt->fetch(PDO::FETCH_ASSOC);
	if (!$order) {
		throw new RuntimeException('Order not found or not accessible.');
	}
	if ($order['status'] !== 'Delivered') {
		throw new RuntimeException('You can only review delivered orders.');
	}
	// Fetch all order items with product info
	$items_stmt = $pdo->prepare("SELECT oi.order_item_id, oi.product_id, oi.quantity, oi.price, p.name, p.main_image_path
		FROM Order_Item oi
		JOIN Product p ON oi.product_id = p.product_id
		WHERE oi.order_id = ?");
	$items_stmt->execute([$order_id]);
	$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

	$product_ids = array_column($items, 'product_id');
	$existing_reviews = [];
	if (!empty($product_ids)) {
		$placeholders = implode(',', array_fill(0, count($product_ids), '?'));
		$review_stmt = $pdo->prepare("SELECT * FROM Review WHERE member_id = ? AND product_id IN ($placeholders)");
		$review_stmt->execute(array_merge([$member_id], $product_ids));
		foreach ($review_stmt->fetchAll(PDO::FETCH_ASSOC) as $review) {
			$existing_reviews[$review['product_id']] = $review;
		}
	}
} catch (Throwable $e) {
	$error_message = $e->getMessage();
}


// If AJAX request, output modal content only
if ($is_ajax) {
	?>
	<div style="max-height: 70vh; overflow-y: auto;">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #e2e8f0;">
			<div>
				<h2 style="margin: 0; font-size: 20px;">Leave a Review</h2>
				<?php if (!empty($order)): ?>
					<p style="margin: 4px 0 0 0; color: #64748b; font-size: 14px;">Order <?php echo htmlspecialchars($order_id); ?> · Placed on <?php echo htmlspecialchars(date('M j, Y', strtotime($order['order_date']))); ?> · RM<?php echo number_format((float)$order['total_amount'], 2); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<?php if ($error_message): ?>
			<div class="alert alert-error" style="margin-bottom: 16px;"><?php echo htmlspecialchars($error_message); ?></div>
		<?php endif; ?>

		<?php if (empty($error_message)): ?>
			<?php if (empty($items)): ?>
				<div class="alert alert-info">No items found for this order.</div>
			<?php else: ?>
				<div class="review-grid" style="gap: 12px;">
					<?php foreach ($items as $item):
						$product_id = (int)$item['product_id'];
						$existing = $existing_reviews[$product_id] ?? null;
					?>
						<section class="review-card" style="padding: 14px;">
							<div class="product-meta" style="grid-template-columns: auto 1fr auto; gap: 12px;">
								<img class="product-thumb" src="../../<?php echo htmlspecialchars($item['main_image_path'] ?: 'assets/img/default.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width: 60px; height: 60px;">
								<div>
									<h3 style="margin: 0 0 4px 0; font-size: 14px;"><?php echo htmlspecialchars($item['name']); ?></h3>
									<p class="muted" style="margin: 0; font-size: 12px;">Qty: <?php echo (int)$item['quantity']; ?> · RM<?php echo number_format((float)$item['price'], 2); ?></p>
								</div>
								<?php if ($existing): ?>
									<span class="chip chip-success" style="font-size: 11px; padding: 6px 10px;">Submitted</span>
								<?php else: ?>
									<span class="chip chip-ready" style="font-size: 11px; padding: 6px 10px;">Ready for feedback</span>
								<?php endif; ?>
							</div>

							<?php if ($existing): ?>
								<div class="existing-review" style="padding: 10px 12px;">
									<div class="rating-static" style="margin-bottom: 8px;">
										<?php for ($i = 1; $i <= 5; $i++): ?>
											<span class="star <?php echo $i <= (int)$existing['rating'] ? 'filled' : ''; ?>">★</span>
										<?php endfor; ?>
									</div>
									<p class="review-text" style="margin: 0 0 6px 0; font-size: 13px;"><?php echo nl2br(htmlspecialchars($existing['review_text'] ?? '')); ?></p>
									<p class="submitted-on" style="margin: 0; font-size: 11px;">Submitted on <?php echo htmlspecialchars(date('M j, Y H:i', strtotime($existing['created_date']))); ?></p>
								</div>
							<?php else: ?>
								<form class="review-form" style="gap: 8px;">
									<input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">
									<input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

									<label class="form-label" style="font-size: 13px;">Your rating</label>
									<div class="rating-input">
										<?php for ($i = 5; $i >= 1; $i--): ?>
											<input type="radio" id="rating-<?php echo $product_id; ?>-<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" required>
											<label for="rating-<?php echo $product_id; ?>-<?php echo $i; ?>" style="width: 28px; height: 28px; font-size: 14px;">★</label>
										<?php endfor; ?>
									</div>
									<label class="form-label" for="review-text-<?php echo $product_id; ?>" style="font-size: 13px;">Feedback</label>
									<textarea id="review-text-<?php echo $product_id; ?>" name="review_text" rows="3" placeholder="What did you like or dislike?" maxlength="1000" required style="font-size: 13px;"></textarea>

									<button type="submit" class="btn btn-primary" style="font-size: 13px; padding: 8px 14px;">Submit Review</button>
								</form>
							<?php endif; ?>
						</section>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
	exit;
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Review Order #<?php echo htmlspecialchars($order_id); ?></title>
	<link rel="stylesheet" href="/assets/css/dark_mode_style.css">
	<link rel="stylesheet" href="/assets/css/styles.css">
	<link rel="stylesheet" href="/assets/css/review.css">
</head>

<body>
	<?php include '../../partials/header.php'; ?>

	<main class="review-page">
		<div class="review-header">
			<div>
				<p class="eyebrow">Order Review</p>
				<h1>Order #<?php echo htmlspecialchars($order_id); ?></h1>
				<?php if (!empty($order)): ?>
					<p class="sub">Placed on <?php echo htmlspecialchars(date('M j, Y', strtotime($order['order_date']))); ?> · RM<?php echo number_format((float)$order['total_amount'], 2); ?></p>
				<?php endif; ?>
			</div>
			<a class="btn btn-secondary" href="/orders.php">Back to Orders</a>
		</div>

		<?php if ($error_message): ?>
			<div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
		<?php endif; ?>

		<?php if ($notice_message): ?>
			<div class="alert alert-<?php echo $notice_type === 'success' ? 'success' : 'info'; ?>"><?php echo htmlspecialchars($notice_message); ?></div>
		<?php endif; ?>

		<?php if (empty($error_message)): ?>
			<?php if (empty($items)): ?>
				<div class="alert alert-info">No items found for this order.</div>
			<?php else: ?>
				<div class="review-grid">
					<?php foreach ($items as $item):
						$product_id = (int)$item['product_id'];
						$existing = $existing_reviews[$product_id] ?? null;
					?>
						<section class="review-card">
							<div class="product-meta">
								<img class="product-thumb" src="../../<?php echo htmlspecialchars($item['main_image_path'] ?: 'assets/img/default.jpg'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>">
								<div>
									<h3><?php echo htmlspecialchars($item['name']); ?></h3>
									<p class="muted">Qty: <?php echo (int)$item['quantity']; ?> · RM<?php echo number_format((float)$item['price'], 2); ?></p>
								</div>
								<?php if ($existing): ?>
									<span class="chip chip-success">Submitted</span>
								<?php else: ?>
									<span class="chip chip-ready">Ready for feedback</span>
								<?php endif; ?>
							</div>

							<?php if ($existing): ?>
								<div class="existing-review">
									<div class="rating-static">
										<?php for ($i = 1; $i <= 5; $i++): ?>
											<span class="star <?php echo $i <= (int)$existing['rating'] ? 'filled' : ''; ?>">★</span>
										<?php endfor; ?>
									</div>
									<p class="review-text"><?php echo nl2br(htmlspecialchars($existing['review_text'] ?? '')); ?></p>
									<p class="submitted-on">Submitted on <?php echo htmlspecialchars(date('M j, Y H:i', strtotime($existing['created_date']))); ?></p>
								</div>
								<a class="btn btn-success" href="product_details.php?id=<?php echo $product_id; ?>">View Product</a>
							<?php else: ?>
								<form class="review-form" method="POST" action="review_post.php">
									<input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">
									<input type="hidden" name="product_id" value="<?php echo $product_id; ?>">

									<label class="form-label">Your rating</label>
									<div class="rating-input">
										<?php for ($i = 5; $i >= 1; $i--): ?>
											<input type="radio" id="rating-<?php echo $product_id; ?>-<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" required>
											<label for="rating-<?php echo $product_id; ?>-<?php echo $i; ?>">★</label>
										<?php endfor; ?>
									</div>
									<label class="form-label" for="review-text-<?php echo $product_id; ?>">Feedback</label>
									<textarea id="review-text-<?php echo $product_id; ?>" name="review_text" rows="4" placeholder="What did you like or dislike?" maxlength="1000" required></textarea>

									<button type="submit" class="btn btn-primary">Submit Review</button>
								</form>
							<?php endif; ?>
						</section>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</main>

	<?php include '../../partials/footer.php'; ?>
</body>

</html>

<?php
// Redirect legacy access to the consolidated review page
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$target = 'review.php';

if ($order_id > 0) {
	$target .= '?order_id=' . $order_id;
}

header('Location: ' . $target);
exit;

<?php
session_start();
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';
redirect_if_admin();

// Check if user is logged in
if (!isset($_SESSION['member'])) {
  $_SESSION['message'] = "Please log in to continue.";
  header('Location: /pages/auth/login.php');
  exit();
}

$member_id = $_SESSION['member']['id'];

// Handle error messages from redirects
$error_message = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'empty_cart':
            $error_message = 'Your cart is empty. Please add items before proceeding to checkout.';
            break;
        case 'session_expired':
            $error_message = 'Your checkout session has expired. Please try again.';
            break;
        case 'invalid_order':
            $error_message = 'Invalid order. Please try again.';
            break;
        case 'order_not_found':
            $error_message = 'Order not found or already processed.';
            break;
        case 'insufficient_stock':
            $product_name = $_GET['product'] ?? 'a product';
            $error_message = 'Insufficient stock for ' . htmlspecialchars($product_name) . '. Please update your cart.';
            break;
        case 'invalid_session':
            $error_message = 'Invalid session. Please start checkout again.';
            break;
        default:
            $error_message = 'An error occurred. Please try again.';
    }
}

// Clear buy_now session when user visits cart page
// This ensures cart checkout always shows cart items, not buy_now items
if (isset($_SESSION['buy_now_item'])) {
  unset($_SESSION['buy_now_item']);
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
  header('Content-Type: application/json');

  $action = $_POST['action'] ?? '';

  try {
    switch ($action) {
      case 'update_quantity':
        $cart_item_id = (int)$_POST['cart_item_id'];
        $quantity = (int)$_POST['quantity'];

        if ($quantity < 1) {
          echo json_encode(['success' => false, 'message' => 'Quantity must be at least 1']);
          exit();
        }

        // Check stock availability
        $stmt = $pdo->prepare("
                    SELECT p.stock 
                    FROM Cart_Item ci 
                    JOIN Product p ON ci.product_id = p.product_id 
                    WHERE ci.cart_item_id = ?
                ");
        $stmt->execute([$cart_item_id]);
        $product = $stmt->fetch();

        if ($quantity > $product['stock']) {
          echo json_encode(['success' => false, 'message' => 'Insufficient stock available']);
          exit();
        }

        $stmt = $pdo->prepare("UPDATE Cart_Item SET quantity = ? WHERE cart_item_id = ?");
        $stmt->execute([$quantity, $cart_item_id]);

        echo json_encode(['success' => true, 'message' => 'Quantity updated successfully']);
        break;

      case 'remove_item':
        $cart_item_id = (int)$_POST['cart_item_id'];

        $stmt = $pdo->prepare("DELETE FROM Cart_Item WHERE cart_item_id = ?");
        $stmt->execute([$cart_item_id]);

        echo json_encode(['success' => true, 'message' => 'Item removed from cart']);
        break;

      case 'clear_cart':
        // Get cart_id for the member
        $stmt = $pdo->prepare("SELECT cart_id FROM Cart WHERE member_id = ?");
        $stmt->execute([$member_id]);
        $cart = $stmt->fetch();

        if ($cart) {
          $stmt = $pdo->prepare("DELETE FROM Cart_Item WHERE cart_id = ?");
          $stmt->execute([$cart['cart_id']]);
        }

        echo json_encode(['success' => true, 'message' => 'Cart cleared successfully']);
        break;

      default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
  } catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
  }
  exit();
}

// Get cart items for display
try {
  $stmt = $pdo->prepare("
        SELECT 
            ci.cart_item_id,
            ci.quantity,
            p.product_id,
            p.name,
            p.price,
            p.stock,
            p.main_image_path,
            p.status,
            (ci.quantity * p.price) as subtotal
        FROM Cart c
        JOIN Cart_Item ci ON c.cart_id = ci.cart_id
        JOIN Product p ON ci.product_id = p.product_id
        WHERE c.member_id = ?
        ORDER BY ci.cart_item_id DESC
    ");
  $stmt->execute([$member_id]);
  $cart_items = $stmt->fetchAll();
} catch (PDOException $e) {
  $error_message = "Error fetching cart items: " . $e->getMessage();
  $cart_items = [];
}

// Calculate totals
$total_amount = 0;
foreach ($cart_items as $item) {
  $total_amount += $item['subtotal'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shopping Cart - Online Store</title>
  <link rel="stylesheet" href="/assets/css/dark_mode_style.css">
  <link rel="stylesheet" href="/assets/css/styles.css">
  <link rel="stylesheet" href="/assets/css/cart.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<?php include '../../partials/header.php'; ?>
<div class="cart-page">
  <div class="container">
    <div class="cart-header">
      <h1>Shopping Cart</h1>
      <a href="../product/product_list.php" class="btn btn-primary">Continue Shopping</a>
    </div>

    <div id="alert-container"></div>
    <?php if (!empty($error_message)): ?>
      <div class="alert alert-error" style="display: block; position: fixed; top: 20px; left: 50%; transform: translateX(-50%); z-index: 1000;">
        <?= htmlspecialchars($error_message) ?>
      </div>
      <script>
        // Auto-hide error message after 5 seconds
        setTimeout(function() {
          document.querySelector('.alert-error').style.display = 'none';
        }, 5000);
      </script>
    <?php endif; ?>
    <div class="loading" id="loading">Processing...</div>

    <!-- Batch Actions Bar -->
    <div class="batch-actions" id="batch-actions">
      <div class="select-all-container">
        <input type="checkbox" id="select-all">
        <label for="select-all">Select All</label>
      </div>
      <button class="btn btn-danger" id="remove-selected">Remove Selected (<span id="selected-count">0</span>)</button>
    </div>

    <section id="cart-content">
      <?php if (empty($cart_items)): ?>
        <div class="cart-empty">
          <div>∅</div>
          <h2>Your cart is empty</h2>
          <p>Add items to get started</p>
          <br>
          <a href="../product/product_list.php" class="btn btn-primary">Browse Products</a>
        </div>
      <?php else: ?>
        <div class="cart-table">
          <?php foreach ($cart_items as $item): ?>
            <div class="cart-item" data-cart-item-id="<?= $item['cart_item_id'] ?>" data-price="<?= $item['price'] ?>" data-product-id="<?= $item['product_id'] ?>">
              <div class="item-checkbox">
                <input type="checkbox" class="item-select" data-cart-item-id="<?= $item['cart_item_id'] ?>">
              </div>

              <div class="item-image-container">
                <img src="../../<?= htmlspecialchars($item['main_image_path'] ?: 'assets/images/no-image.png') ?>"
                  alt="<?= htmlspecialchars($item['name']) ?>"
                  class="item-image">
              </div>

              <div class="item-details">
                <h3><?= htmlspecialchars($item['name']) ?></h3>
                <?php if ($item['status'] === 'Unavailable'): ?>
                  <span class="status status-unavailable">
                    <?= htmlspecialchars($item['status']) ?>
                  </span>
                <?php endif; ?>
                <div class="stock-info">Stock: <?= $item['stock'] ?> available</div>
              </div>

              <div class="item-price">
                RM <?= number_format($item['price'], 2) ?>
              </div>

              <div class="item-quantity">
                <input type="number"
                  class="quantity-input"
                  value="<?= $item['quantity'] ?>"
                  min="1"
                  max="<?= $item['stock'] ?>"
                  data-cart-item-id="<?= $item['cart_item_id'] ?>">
              </div>

              <div class="item-subtotal">
                RM <span class="subtotal-value"><?= number_format($item['subtotal'], 2) ?></span>
              </div>

              <div class="item-remove">
                <button class="btn-remove" data-cart-item-id="<?= $item['cart_item_id'] ?>" title="Remove item">
                  ×
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="cart-summary">
          <div class="summary-row">
            <span>Subtotal:</span>
            <span id="cart-subtotal">RM <?= number_format($total_amount, 2) ?></span>
          </div>
          <div class="summary-row total">
            <span>Total:</span>
            <span id="cart-total">RM <?= number_format($total_amount, 2) ?></span>
          </div>
        </div>

        <div class="cart-actions">
          <button class="btn btn-danger" id="clear-cart">Clear Cart</button>
          <a href="../checkout/checkout.php?from=cart" class="btn btn-success" style="margin-left: auto;">Proceed to Checkout</a>
        </div>
      <?php endif; ?>
    </section>

    <!-- Confirmation Modal -->
    <div class="modal-overlay" id="confirm-modal">
      <div class="modal-content">
        <h3 id="modal-title">Confirm Action</h3>
        <p id="modal-message">Are you sure you want to proceed?</p>
        <div class="modal-actions">
          <button class="btn" id="modal-cancel">Cancel</button>
          <button class="btn btn-primary" id="modal-confirm">Confirm</button>
        </div>
      </div>
    </div>
  </div>

  <script src="/assets/js/cart.js"></script>
</div>

<?php include '../../partials/footer.php'; ?>
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
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    /* Scoped styles for cart page only - don't override header/footer */
    .cart-page * {
      box-sizing: border-box;
    }

    .cart-page {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
      background-color: #fafafa;
      color: #1a1a1a;
      line-height: 1.6;
      min-height: 100vh;
      padding-top: 20px;
    }

    .cart-page .container {
      max-width: 1100px;
      margin: 40px auto;
      background: white;
      border: 1px solid #e5e5e5;
    }

    .cart-page h1 {
      font-size: 28px;
      font-weight: 500;
      color: #1a1a1a;
      margin: 0;
      letter-spacing: -0.5px;
    }

    .cart-page .cart-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 32px 40px;
      border-bottom: 1px solid #e5e5e5;
    }

    .cart-page .btn {
      padding: 11px 24px;
      border: 1px solid #1a1a1a;
      background: white;
      color: #1a1a1a;
      cursor: pointer;
      font-size: 13px;
      font-weight: 500;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-block;
      letter-spacing: 0.3px;
      border-radius: 4px;
      position: relative;
      overflow: hidden;
    }

    .cart-page .btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
      transition: left 0.5s;
    }

    .cart-page .btn:hover {
      background-color: #1a1a1a;
      color: white;
      border-color: #1a1a1a;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(26, 26, 26, 0.15);
    }

    .cart-page .btn:hover::before {
      left: 100%;
    }

    .cart-page .btn:active {
      transform: translateY(0);
      box-shadow: 0 2px 6px rgba(26, 26, 26, 0.1);
    }

    .cart-page .btn-primary {
      background-color: #1a1a1a;
      color: white;
      border-color: #1a1a1a;
    }

    .cart-page .btn-primary::before {
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    }

    .cart-page .btn-primary:hover {
      background-color: #000;
      border-color: #000;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }

    .cart-page .btn-danger {
      background-color: white;
      color: #1a1a1a;
      border-color: #d0d0d0;
    }

    .cart-page .btn-danger:hover {
      background-color: #1a1a1a;
      border-color: #1a1a1a;
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(26, 26, 26, 0.15);
    }

    .cart-page .btn-success {
      background-color: #1a1a1a;
      color: white;
      border-color: #1a1a1a;
    }

    .cart-page .btn-success::before {
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
    }

    .cart-page .btn-success:hover {
      background-color: #000;
      border-color: #000;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
    }

    .cart-page .alert {
      padding: 16px 24px;
      margin: 0 auto;
      display: none;
      font-size: 13px;
      width: 320px;
      max-width: 90%;
      text-align: center;
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 999;
      animation: fadeIn 0.3s ease;
      border-radius: 6px;
    }

    @keyframes fadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    .cart-page .alert-success {
      background-color: #1a1a1a;
      color: white;
    }

    .cart-page .alert-error {
      background-color: #1a1a1a;
      color: white;
    }

    .cart-page .cart-empty {
      text-align: center;
      padding: 80px 40px;
      color: #999;
    }

    .cart-page .cart-empty div {
      font-size: 48px;
      margin-bottom: 20px;
      opacity: 0.3;
    }

    .cart-page .cart-empty h2 {
      font-size: 18px;
      font-weight: 500;
      color: #666;
      margin-bottom: 8px;
    }

    .cart-page .cart-empty p {
      font-size: 14px;
      color: #999;
    }

    .cart-page .cart-table {
      width: 100%;
    }

    .cart-page .cart-item {
      display: grid;
      grid-template-columns: 40px 100px 1fr 100px 140px 120px 60px;
      gap: 24px;
      padding: 32px 40px;
      border-bottom: 1px solid #e5e5e5;
      align-items: center;
    }

    .cart-page .cart-item:hover {
      background-color: #fafafa;
    }

    .cart-page .item-checkbox {
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .cart-page .item-select,
    .cart-page #select-all {
      width: 18px;
      height: 18px;
      cursor: pointer;
      accent-color: #1a1a1a;
    }

    .cart-page .item-image {
      width: 100px;
      height: 100px;
      object-fit: cover;
      border: 1px solid #e5e5e5;
    }

    .cart-page .item-details h3 {
      color: #1a1a1a;
      margin-bottom: 8px;
      font-size: 15px;
      font-weight: 500;
      letter-spacing: -0.2px;
    }

    .cart-page .item-details .status {
      font-size: 11px;
      padding: 4px 8px;
      display: inline-block;
      margin-top: 4px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 500;
    }

    .cart-page .status-available {
      background-color: #f5f5f5;
      color: #666;
      border: 1px solid #e5e5e5;
    }

    .cart-page .status-unavailable {
      background-color: #f5f5f5;
      color: #999;
      border: 1px solid #e5e5e5;
    }

    .cart-page .item-price {
      font-size: 15px;
      font-weight: 400;
      color: #1a1a1a;
      letter-spacing: -0.2px;
    }

    .cart-page .item-quantity {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .cart-page .quantity-input {
      width: 70px;
      padding: 10px;
      border: 1px solid #d0d0d0;
      text-align: center;
      font-size: 13px;
      background: white;
      color: #1a1a1a;
      transition: border-color 0.3s ease;
    }

    .cart-page .quantity-input:focus {
      outline: none;
      border-color: #1a1a1a;
    }

    .cart-page .quantity-input:hover {
      border-color: #999;
    }

    .cart-page .stock-info {
      font-size: 11px;
      color: #999;
      margin-top: 6px;
      letter-spacing: 0.2px;
    }

    .cart-page .item-subtotal {
      font-size: 15px;
      font-weight: 500;
      color: #1a1a1a;
      letter-spacing: -0.2px;
    }

    .cart-page .item-remove {
      text-align: center;
    }

    .cart-page .btn-remove {
      background: none;
      color: #999;
      border: none;
      padding: 8px;
      cursor: pointer;
      font-size: 20px;
      transition: all 0.3s ease;
      width: 32px;
      height: 32px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
      position: relative;
      overflow: hidden;
    }

    .cart-page .btn-remove::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      background-color: #1a1a1a;
      border-radius: 50%;
      transition: all 0.3s ease;
      transform: translate(-50%, -50%);
      z-index: -1;
    }

    .cart-page .btn-remove:hover {
      color: white;
      transform: rotate(90deg) scale(1.1);
    }

    .cart-page .btn-remove:hover::before {
      width: 100%;
      height: 100%;
    }

    .cart-page .btn-remove:active {
      transform: rotate(90deg) scale(0.95);
    }

    .cart-page .cart-summary {
      background-color: #fafafa;
      padding: 32px 40px;
      border-top: 1px solid #e5e5e5;
      margin-top: 0;
    }

    .cart-page .summary-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      font-size: 14px;
      color: #666;
    }

    .cart-page .summary-row.total {
      border-top: 1px solid #d0d0d0;
      margin-top: 16px;
      padding-top: 20px;
      font-size: 20px;
      font-weight: 500;
      color: #1a1a1a;
      letter-spacing: -0.3px;
    }

    .cart-page .cart-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 24px 40px 32px;
      gap: 16px;
    }

    .cart-page .loading {
      display: none;
      text-align: center;
      padding: 20px;
      color: #999;
      font-size: 13px;
      background: #fafafa;
      border-bottom: 1px solid #e5e5e5;
    }

    /* Confirmation Modal */
    .cart-page .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }

    .cart-page .modal-overlay.active {
      display: flex;
    }

    .cart-page .modal-content {
      background: white;
      padding: 32px;
      max-width: 400px;
      width: 90%;
      border: 1px solid #e5e5e5;
    }

    .cart-page .modal-content h3 {
      font-size: 18px;
      font-weight: 500;
      color: #1a1a1a;
      margin-bottom: 12px;
    }

    .cart-page .modal-content p {
      font-size: 14px;
      color: #666;
      margin-bottom: 24px;
      line-height: 1.6;
    }

    .cart-page .modal-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
    }

    .cart-page .batch-actions {
      padding: 20px 40px;
      border-bottom: 1px solid #e5e5e5;
      display: none;
      background-color: #fafafa;
    }

    .cart-page .batch-actions.active {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .cart-page .select-all-container {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #666;
    }

    @media (max-width: 768px) {
      .cart-page .container {
        margin: 0;
        border: none;
      }

      .cart-page .cart-header {
        padding: 24px 20px;
      }

      .cart-page h1 {
        font-size: 22px;
      }

      .cart-page .cart-item {
        grid-template-columns: 40px 80px 1fr;
        gap: 16px;
        padding: 24px 20px;
      }

      .cart-page .item-price,
      .cart-page .item-quantity,
      .cart-page .item-subtotal {
        grid-column: 3;
      }

      .cart-page .item-remove {
        grid-column: 3;
        text-align: right;
      }

      .cart-page .batch-actions {
        padding: 16px 20px;
        flex-direction: column;
        align-items: flex-start;
      }

      .cart-page .cart-summary {
        padding: 24px 20px;
      }

      .cart-page .cart-actions {
        flex-direction: column;
        padding: 20px;
      }
    }
  </style>
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
            <div class="cart-item" data-cart-item-id="<?= $item['cart_item_id'] ?>" data-price="<?= $item['price'] ?>">
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

  <script>
    $(document).ready(function() {
      let confirmCallback = null;

      // Custom confirmation modal
      function showConfirm(title, message, callback) {
        $('#modal-title').text(title);
        $('#modal-message').text(message);
        confirmCallback = callback;
        $('#confirm-modal').addClass('active');
      }

      function hideConfirm() {
        $('#confirm-modal').removeClass('active');
        confirmCallback = null;
      }

      $('#modal-confirm').on('click', function() {
        if (confirmCallback) {
          confirmCallback();
        }
        hideConfirm();
      });

      $('#modal-cancel').on('click', hideConfirm);

      // Close modal on overlay click
      $('#confirm-modal').on('click', function(e) {
        if (e.target === this) {
          hideConfirm();
        }
      });

      // Show alert message
      function showAlert(message, type) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
        const alertHtml = `<div class="alert ${alertClass}">${message}</div>`;
        $('#alert-container').html(alertHtml).find('.alert').fadeIn();

        setTimeout(function() {
          $('#alert-container .alert').fadeOut(function() {
            $(this).remove();
          });
        }, 3000);
      }

      // Update selected count
      function updateSelectedCount() {
        const selectedCount = $('.item-select:checked').length;
        $('#selected-count').text(selectedCount);

        if (selectedCount > 0) {
          $('#batch-actions').addClass('active');
        } else {
          $('#batch-actions').removeClass('active');
        }

        // Update select all checkbox
        const totalItems = $('.item-select').length;
        $('#select-all').prop('checked', selectedCount === totalItems && totalItems > 0);
      }

      // Select all checkbox
      $('#select-all').on('change', function() {
        $('.item-select').prop('checked', $(this).is(':checked'));
        updateSelectedCount();
      });

      // Individual checkbox
      $(document).on('change', '.item-select', function() {
        updateSelectedCount();
      });

      // Calculate and update totals
      function updateTotals() {
        let total = 0;
        $('.cart-item').each(function() {
          const subtotalText = $(this).find('.subtotal-value').text().replace(/,/g, '');
          const subtotal = parseFloat(subtotalText);
          // Only add to total if it's a valid number
          if (!isNaN(subtotal) && isFinite(subtotal)) {
            total += subtotal;
          }
        });

        // Ensure total is always a valid number
        if (isNaN(total) || !isFinite(total)) {
          total = 0;
        }

        $('#cart-subtotal').text('RM ' + total.toFixed(2));
        $('#cart-total').text('RM ' + total.toFixed(2));
      }

      // Prevent non-numeric input in quantity fields
      $(document).on('keypress', '.quantity-input', function(e) {
        // Allow: backspace, delete, tab, escape, enter
        if ([46, 8, 9, 27, 13].indexOf(e.keyCode) !== -1 ||
            // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
            (e.keyCode === 65 && e.ctrlKey === true) ||
            (e.keyCode === 67 && e.ctrlKey === true) ||
            (e.keyCode === 86 && e.ctrlKey === true) ||
            (e.keyCode === 88 && e.ctrlKey === true)) {
          return;
        }
        // Ensure that it is a number and stop the keypress
        if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
          e.preventDefault();
        }
      });

      // Handle paste events to prevent invalid data
      $(document).on('paste', '.quantity-input', function(e) {
        const $input = $(this);
        setTimeout(function() {
          const value = parseInt($input.val());
          if (isNaN(value) || value < 1) {
            $input.val(1);
            $input.trigger('input');
          }
        }, 1);
      });

      // Update quantity
      let updateTimeout;
      $(document).on('input', '.quantity-input', function() {
        const $input = $(this);
        const cartItemId = $input.data('cart-item-id');
        let quantity = parseInt($input.val());
        const $cartItem = $input.closest('.cart-item');
        const price = parseFloat($cartItem.data('price'));
        const maxStock = parseInt($input.attr('max'));

        // Handle invalid input
        if (isNaN(quantity) || quantity < 1) {
          $input.val(1);
          quantity = 1;
        }

        if (quantity > maxStock) {
          showAlert('Quantity exceeds available stock', 'error');
          $input.val(maxStock);
          quantity = maxStock;
        }

        // Update subtotal immediately with validated quantity
        const newSubtotal = (price * quantity).toFixed(2);
        $cartItem.find('.subtotal-value').text(newSubtotal.replace(/\B(?=(\d{3})+(?!\d))/g, ","));
        updateTotals();

        // Debounce AJAX call
        clearTimeout(updateTimeout);
        updateTimeout = setTimeout(function() {
          $.ajax({
            url: 'cart.php',
            method: 'POST',
            data: {
              action: 'update_quantity',
              cart_item_id: cartItemId,
              quantity: quantity
            },
            dataType: 'json',
            beforeSend: function() {
              $('#loading').show();
            },
            success: function(response) {
              $('#loading').hide();
              if (!response.success) {
                showAlert(response.message, 'error');
                // Revert to previous valid value if server rejects
                location.reload();
              }
            },
            error: function() {
              $('#loading').hide();
              showAlert('An error occurred while updating quantity', 'error');
            }
          });
        }, 500);
      });

      // Remove single item
      $(document).on('click', '.btn-remove', function() {
        const cartItemId = $(this).data('cart-item-id');
        const $cartItem = $(this).closest('.cart-item');

        showConfirm('Remove Item', 'Are you sure you want to remove this item from your cart?', function() {
          $.ajax({
            url: 'cart.php',
            method: 'POST',
            data: {
              action: 'remove_item',
              cart_item_id: cartItemId
            },
            dataType: 'json',
            beforeSend: function() {
              $('#loading').show();
            },
            success: function(response) {
              $('#loading').hide();
              if (response.success) {
                $cartItem.fadeOut(300, function() {
                  $(this).remove();
                  updateTotals();
                  updateSelectedCount();

                  // Update header cart count
                  if (typeof window.loadHeaderCounts === 'function') {
                    window.loadHeaderCounts();
                  }

                  if ($('.cart-item').length === 0) {
                    location.reload();
                  }
                });
                showAlert(response.message, 'success');
              } else {
                showAlert(response.message, 'error');
              }
            },
            error: function() {
              $('#loading').hide();
              showAlert('An error occurred while removing item', 'error');
            }
          });
        });
      });

      // Remove selected items
      $('#remove-selected').on('click', function() {
        const selectedItems = $('.item-select:checked');
        const count = selectedItems.length;

        if (count === 0) return;

        showConfirm('Remove Selected Items', `Are you sure you want to remove ${count} item(s) from your cart?`, function() {
          const itemIds = [];
          const $itemsToRemove = [];

          selectedItems.each(function() {
            itemIds.push($(this).data('cart-item-id'));
            $itemsToRemove.push($(this).closest('.cart-item'));
          });

          let completed = 0;
          let hasError = false;

          $('#loading').show();

          itemIds.forEach(function(cartItemId, index) {
            $.ajax({
              url: 'cart.php',
              method: 'POST',
              data: {
                action: 'remove_item',
                cart_item_id: cartItemId
              },
              dataType: 'json',
              success: function(response) {
                if (response.success) {
                  $itemsToRemove[index].fadeOut(300, function() {
                    $(this).remove();

                    // Update totals after each removal
                    updateTotals();
                    updateSelectedCount();
                  });
                } else {
                  hasError = true;
                }
              },
              error: function() {
                hasError = true;
              },
              complete: function() {
                completed++;
                if (completed === itemIds.length) {
                  $('#loading').hide();

                  // Final update after all removals
                  setTimeout(function() {
                    updateTotals();
                    updateSelectedCount();

                    // Update header cart count
                    if (typeof window.loadHeaderCounts === 'function') {
                      window.loadHeaderCounts();
                    }

                    if (hasError) {
                      showAlert('Some items could not be removed', 'error');
                    } else {
                      showAlert(`${count} item(s) removed successfully`, 'success');
                    }

                    if ($('.cart-item').length === 0) {
                      setTimeout(function() {
                        location.reload();
                      }, 1000);
                    }
                  }, 400);
                }
              }
            });
          });
        });
      });

      // Clear cart
      $('#clear-cart').on('click', function() {
        showConfirm('Clear Cart', 'Are you sure you want to clear your entire cart? This action cannot be undone.', function() {
          $.ajax({
            url: 'cart.php',
            method: 'POST',
            data: {
              action: 'clear_cart'
            },
            dataType: 'json',
            beforeSend: function() {
              $('#loading').show();
            },
            success: function(response) {
              $('#loading').hide();
              if (response.success) {
                // Update header cart count
                if (typeof window.loadHeaderCounts === 'function') {
                  window.loadHeaderCounts();
                }
                
                showAlert(response.message, 'success');
                setTimeout(function() {
                  location.reload();
                }, 1000);
              } else {
                showAlert(response.message, 'error');
              }
            },
            error: function() {
              $('#loading').hide();
              showAlert('An error occurred while clearing cart', 'error');
            }
          });
        });
      });
    });
  </script>
</div>

<?php include '../../partials/footer.php'; ?>
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

// Handle AJAX requests
if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
) {

  header('Content-Type: application/json');
  $action = $_POST['action'] ?? '';

  try {
    switch ($action) {

      /* -----------------------------------
                REMOVE SINGLE ITEM
            -----------------------------------*/
      case 'remove_item':
        $wishlist_item_id = (int)$_POST['wishlist_item_id'];

        $stmt = $pdo->prepare("
                    DELETE wi
                    FROM Wishlist_Item wi
                    JOIN Wishlist w ON wi.wishlist_id = w.wishlist_id
                    WHERE wi.wishlist_item_id = ? AND w.member_id = ?
                ");
        $stmt->execute([$wishlist_item_id, $member_id]);

        echo json_encode(['success' => true, 'message' => 'Item removed from wishlist']);
        break;


      /* -----------------------------------
                ADD TO CART
            -----------------------------------*/
      case 'add_to_cart':
        $product_id = (int)$_POST['product_id'];

        // Product available?
        $stmt = $pdo->prepare("SELECT stock, status FROM Product WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if (!$product || $product['status'] === 'Unavailable' || $product['stock'] < 1) {
          echo json_encode(['success' => false, 'message' => 'Product is not available']);
          exit();
        }

        // Get or create cart (FIXED: user_id → member_id)
        $stmt = $pdo->prepare("SELECT cart_id FROM Cart WHERE member_id = ?");
        $stmt->execute([$member_id]);
        $cart = $stmt->fetch();

        if (!$cart) {
          $stmt = $pdo->prepare("INSERT INTO Cart (member_id) VALUES (?)");
          $stmt->execute([$member_id]);
          $cart_id = $pdo->lastInsertId();
        } else {
          $cart_id = $cart['cart_id'];
        }

        // Check if product already in cart
        $stmt = $pdo->prepare("SELECT cart_item_id, quantity FROM Cart_Item WHERE cart_id = ? AND product_id = ?");
        $stmt->execute([$cart_id, $product_id]);
        $cart_item = $stmt->fetch();

        if ($cart_item) {
          $new_quantity = $cart_item['quantity'] + 1;

          if ($new_quantity > $product['stock']) {
            echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
            exit();
          }

          $stmt = $pdo->prepare("UPDATE Cart_Item SET quantity = ? WHERE cart_item_id = ?");
          $stmt->execute([$new_quantity, $cart_item['cart_item_id']]);
        } else {
          $stmt = $pdo->prepare("INSERT INTO Cart_Item (cart_id, product_id, quantity) VALUES (?, ?, 1)");
          $stmt->execute([$cart_id, $product_id]);
        }

        echo json_encode(['success' => true, 'message' => 'Item added to cart']);
        break;


      /* -----------------------------------
                CLEAR WISHLIST
            -----------------------------------*/
      case 'clear_wishlist':
        $stmt = $pdo->prepare("
                    DELETE wi
                    FROM Wishlist_Item wi
                    JOIN Wishlist w ON wi.wishlist_id = w.wishlist_id
                    WHERE w.member_id = ?
                ");
        $stmt->execute([$member_id]);

        echo json_encode(['success' => true, 'message' => 'Wishlist cleared']);
        break;


      /* -----------------------------------
                ADD TO WISHLIST
            -----------------------------------*/
      case 'add_to_wishlist':
        $product_id = (int)$_POST['product_id'];

        // Ensure wishlist exists (member_id UNIQUE)
        $stmt = $pdo->prepare("SELECT wishlist_id FROM Wishlist WHERE member_id = ?");
        $stmt->execute([$member_id]);
        $wishlist = $stmt->fetch();

        if (!$wishlist) {
          $stmt = $pdo->prepare("INSERT INTO Wishlist (member_id) VALUES (?)");
          $stmt->execute([$member_id]);
          $wishlist_id = $pdo->lastInsertId();
        } else {
          $wishlist_id = $wishlist['wishlist_id'];
        }

        // Check if item already exists
        $stmt = $pdo->prepare("SELECT wishlist_item_id FROM Wishlist_Item WHERE wishlist_id = ? AND product_id = ?");
        $stmt->execute([$wishlist_id, $product_id]);

        if ($stmt->fetch()) {
          echo json_encode(['success' => false, 'message' => 'Already in wishlist']);
          exit();
        }

        // Add
        $stmt = $pdo->prepare("INSERT INTO Wishlist_Item (wishlist_id, product_id) VALUES (?, ?)");
        $stmt->execute([$wishlist_id, $product_id]);

        echo json_encode(['success' => true, 'message' => 'Added to wishlist']);
        break;


      default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
  } catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
  }

  exit();
}


// FETCH WISHLIST ITEMS FOR PAGE
try {
  $stmt = $pdo->prepare("
        SELECT 
            wi.wishlist_item_id,
            w.wishlist_id,
            wi.date_added,
            p.product_id,
            p.name,
            p.price,
            p.stock,
            p.main_image_path,
            p.status
        FROM Wishlist w
        JOIN Wishlist_Item wi ON w.wishlist_id = wi.wishlist_id
        JOIN Product p ON wi.product_id = p.product_id
        WHERE w.member_id = ?
        ORDER BY wi.date_added DESC
    ");

  $stmt->execute([$member_id]);
  $wishlist_items = $stmt->fetchAll();
} catch (PDOException $e) {
  $wishlist_items = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Wishlist - Online Store</title>
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.0.0/uicons-regular-straight/css/uicons-regular-straight.css" />
  <link rel="stylesheet" href="/assets/css/dark_mode_style.css">
  <link rel="stylesheet" href="/assets/css/styles.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
      background-color: #fafafa;
      color: #1a1a1a;
      line-height: 1.6;
    }

    .container {
      max-width: 1100px;
      margin: 40px auto;
      background: white;
      border: 1px solid #e5e5e5;
    }

    h1 {
      font-size: 28px;
      font-weight: 500;
      color: #1a1a1a;
      margin: 0;
      letter-spacing: -0.5px;
    }

    .wishlist-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 32px 40px;
      border-bottom: 1px solid #e5e5e5;
    }

    .btn {
      padding: 11px 24px;
      border: 1px solid #1a1a1a;
      background: white;
      color: #1a1a1a;
      cursor: pointer;
      font-size: 13px;
      font-weight: 500;
      transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
      text-decoration: none;
      display: inline-block;
      letter-spacing: 0.3px;
    }

    .btn:hover {
      background-color: #1a1a1a;
      color: white;
      border-color: #1a1a1a;
    }

    .btn-primary {
      background-color: #1a1a1a;
      color: white;
      border-color: #1a1a1a;
    }

    .btn-primary:hover {
      background-color: #000;
      border-color: #000;
    }

    .btn-danger {
      background-color: white;
      color: #1a1a1a;
      border-color: #d0d0d0;
    }

    .btn-danger:hover {
      background-color: #1a1a1a;
      border-color: #1a1a1a;
      color: white;
    }

    .btn-success {
      background-color: #1a1a1a;
      color: white;
      border-color: #1a1a1a;
    }

    .btn-success:hover {
      background-color: #000;
      border-color: #000;
    }

    .alert {
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

    .alert-success {
      background-color: #1a1a1a;
      color: white;
    }

    .alert-error {
      background-color: #1a1a1a;
      color: white;
    }

    .wishlist-empty {
      text-align: center;
      padding: 80px 40px;
      color: #999;
    }

    .wishlist-empty div {
      font-size: 48px;
      margin-bottom: 20px;
      opacity: 0.3;
    }

    .wishlist-empty h2 {
      font-size: 18px;
      font-weight: 500;
      color: #666;
      margin-bottom: 8px;
    }

    .wishlist-empty p {
      font-size: 14px;
      color: #999;
    }

    .wishlist-table {
      width: 100%;
    }

    .wishlist-item {
      display: grid;
      grid-template-columns: 40px 100px 2fr 120px 120px 100px 140px 60px;
      gap: 20px;
      padding: 32px 40px;
      border-bottom: 1px solid #e5e5e5;
      align-items: center;
      transition: background-color 0.2s ease;
    }

    .wishlist-item:hover {
      background-color: #f8f8f8;
    }

    .item-image-container,
    .item-details h3 {
      cursor: pointer;
    }

    .item-image-container:hover,
    .item-details h3:hover {
      opacity: 0.8;
    }

    .item-checkbox {
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .item-select,
    #select-all {
      width: 18px;
      height: 18px;
      cursor: pointer;
      accent-color: #1a1a1a;
    }

    .item-image {
      width: 100px;
      height: 100px;
      object-fit: cover;
      border: 1px solid #e5e5e5;
    }

    .item-details h3 {
      color: #1a1a1a;
      margin: 0;
      font-size: 15px;
      font-weight: 500;
      letter-spacing: -0.2px;
    }

    .item-stock {
      font-size: 14px;
      color: #666;
      font-weight: 500;
    }

    .item-stock.out-of-stock {
      color: #999;
    }

    .item-date {
      font-size: 13px;
      color: #999;
    }

    .item-price {
      font-size: 15px;
      font-weight: 400;
      color: #1a1a1a;
      letter-spacing: -0.2px;
    }

    .stock-info {
      font-size: 11px;
      color: #999;
      margin-top: 6px;
      letter-spacing: 0.2px;
    }

    .item-actions {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .btn-add-cart {
      padding: 8px 16px;
      border: none;
      background: #0891b2;
      color: white;
      cursor: pointer;
      font-size: 12px;
      font-weight: 600;
      transition: all 0.3s ease;
      text-align: center;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }

    .btn-add-cart i {
      font-size: 14px;
    }

    .btn-add-cart:hover:not(:disabled) {
      background-color: #0e7490;
      transform: translateY(-1px);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    }

    .btn-add-cart:disabled {
      background-color: #d0d0d0;
      cursor: not-allowed;
      opacity: 0.5;
    }

    .item-remove {
      text-align: center;
    }

    .btn-remove {
      background: none;
      color: #999;
      border: none;
      padding: 8px;
      cursor: pointer;
      font-size: 20px;
      transition: color 0.3s ease, background-color 0.3s ease, transform 0.3s ease;
      width: 32px;
      height: 32px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 50%;
    }

    .btn-remove:hover {
      color: white;
      background-color: #1a1a1a;
      transform: rotate(90deg);
    }

    .wishlist-actions {
      display: flex;
      justify-content: space-between;
      padding: 24px 40px 32px;
      gap: 16px;
    }

    .loading {
      display: none;
      text-align: center;
      padding: 20px;
      color: #999;
      font-size: 13px;
      background: #fafafa;
      border-bottom: 1px solid #e5e5e5;
    }

    /* Confirmation Modal */
    .modal-overlay {
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

    .modal-overlay.active {
      display: flex;
    }

    .modal-content {
      background: white;
      padding: 32px;
      max-width: 400px;
      width: 90%;
      border: 1px solid #e5e5e5;
    }

    .modal-content h3 {
      font-size: 18px;
      font-weight: 500;
      color: #1a1a1a;
      margin-bottom: 12px;
    }

    .modal-content p {
      font-size: 14px;
      color: #666;
      margin-bottom: 24px;
      line-height: 1.6;
    }

    .modal-actions {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
    }

    .batch-actions {
      padding: 20px 40px;
      border-bottom: 1px solid #e5e5e5;
      display: none;
      background-color: #fafafa;
    }

    .batch-actions.active {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .select-all-container {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: #666;
    }

    .added-date {
      font-size: 11px;
      color: #999;
      margin-top: 4px;
    }

    @media (max-width: 768px) {
      .container {
        margin: 0;
        border: none;
      }

      .wishlist-header {
        padding: 24px 20px;
      }

      h1 {
        font-size: 22px;
      }

      .wishlist-item {
        grid-template-columns: 40px 80px 1fr 80px;
        gap: 16px;
        padding: 24px 20px;
      }

      .item-stock,
      .item-date,
      .item-price {
        display: none;
      }

      .item-actions {
        grid-column: 3;
        margin-top: 8px;
      }

      .item-remove {
        grid-column: 4;
        text-align: right;
      }

      .batch-actions {
        padding: 16px 20px;
        flex-direction: column;
        align-items: flex-start;
      }

      .wishlist-actions {
        flex-direction: column;
        padding: 20px;
      }
    }
  </style>
</head>

<body class="<?php echo (isset($_SESSION['member']['theme']) && $_SESSION['member']['theme'] === 'dark') ? 'dark-theme' : ''; ?>">
  <?php include '../../partials/header.php';?>
  <div class="container">
    <div class="wishlist-header">
      <h1>My Wishlist</h1>
      <a href="../product/product_list.php" class="btn btn-primary">Continue Shopping</a>
    </div>

    <div id="alert-container"></div>
    <div class="loading" id="loading">Processing...</div>

    <!-- Batch Actions Bar -->
    <div class="batch-actions" id="batch-actions">
      <div class="select-all-container">
        <input type="checkbox" id="select-all">
        <label for="select-all">Select All</label>
      </div>
      <button class="btn btn-danger" id="remove-selected">Remove Selected (<span id="selected-count">0</span>)</button>
    </div>

    <section id="wishlist-content">
      <?php if (empty($wishlist_items)): ?>
        <div class="wishlist-empty">
          <div>♡</div>
          <h2>Your wishlist is empty</h2>
          <p>Save items you love for later</p>
          <br>
          <a href="../product/product_list.php" class="btn btn-primary">Browse Products</a>
        </div>
      <?php else: ?>
        <div class="wishlist-table">
          <?php foreach ($wishlist_items as $item): ?>
            <div class="wishlist-item" data-wishlist-item-id="<?= $item['wishlist_item_id'] ?>" data-product-id="<?= $item['product_id'] ?>">
              <div class="item-checkbox" onclick="event.stopPropagation();">
                <input type="checkbox" class="item-select" data-wishlist-item-id="<?= $item['wishlist_item_id'] ?>">
              </div>

              <div class="item-image-container">
                <img src="../../<?= htmlspecialchars($item['main_image_path'] ?: 'assets/images/no-image.png') ?>"
                  alt="<?= htmlspecialchars($item['name']) ?>"
                  class="item-image">
              </div>

              <div class="item-details">
                <h3><?= htmlspecialchars($item['name']) ?></h3>
              </div>

              <div class="item-stock <?= ($item['status'] === 'Unavailable' || $item['stock'] < 1) ? 'out-of-stock' : '' ?>">
                <?= $item['stock'] ?> in stock
              </div>

              <div class="item-date">
                <?= date('M d, Y', strtotime($item['date_added'])) ?>
              </div>

              <div class="item-price">
                RM <?= number_format($item['price'], 2) ?>
              </div>

              <div class="item-actions">
                <button class="btn-add-cart"
                  data-product-id="<?= $item['product_id'] ?>"
                  <?= ($item['status'] === 'Unavailable' || $item['stock'] < 1) ? 'disabled' : '' ?>>
                  <i class="fi fi-rs-shopping-cart-add"></i>
                  Add to Cart
                </button>
              </div>

              <div class="item-remove">
                <button class="btn-remove" data-wishlist-item-id="<?= $item['wishlist_item_id'] ?>" title="Remove item">
                  ×
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="wishlist-actions">
          <button class="btn btn-danger" id="clear-wishlist">Clear Wishlist</button>
          <a href="../cart/cart.php" class="btn btn-success" style="margin-left: auto;">View Cart</a>
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
      $(document).on('change', '.item-select', function(e) {
        e.stopPropagation();
        updateSelectedCount();
      });

      // Prevent checkbox click from triggering navigation
      $(document).on('click', '.item-checkbox', function(e) {
        e.stopPropagation();
      });

      // Add to cart
      $(document).on('click', '.btn-add-cart', function(e) {
        e.stopPropagation();
        const $btn = $(this);
        const productId = $btn.data('product-id');

        $.ajax({
          url: 'wishlist.php',
          method: 'POST',
          data: {
            action: 'add_to_cart',
            product_id: productId
          },
          dataType: 'json',
          beforeSend: function() {
            $btn.prop('disabled', true).html('<i class="fi fi-rs-spinner"></i> Adding...');
          },
          success: function(response) {
            if (response.success) {
              showAlert(response.message, 'success');
              $btn.html('<i class="fi fi-rs-check"></i> Added!');
              setTimeout(function() {
                $btn.html('<i class="fi fi-rs-shopping-cart-add"></i> Add to Cart').prop('disabled', false);
              }, 2000);
            } else {
              showAlert(response.message, 'error');
              $btn.html('<i class="fi fi-rs-shopping-cart-add"></i> Add to Cart').prop('disabled', false);
            }
          },
          error: function() {
            showAlert('An error occurred while adding to cart', 'error');
            $btn.html('<i class="fi fi-rs-shopping-cart-add"></i> Add to Cart').prop('disabled', false);
          }
        });
      });

      // Remove single item
      $(document).on('click', '.btn-remove', function(e) {
        e.stopPropagation();
        const wishlistItemId = $(this).data('wishlist-item-id');
        const $wishlistItem = $(this).closest('.wishlist-item');

        showConfirm('Remove Item', 'Are you sure you want to remove this item from your wishlist?', function() {
          $.ajax({
            url: 'wishlist.php',
            method: 'POST',
            data: {
              action: 'remove_item',
              wishlist_item_id: wishlistItemId
            },
            dataType: 'json',
            beforeSend: function() {
              $('#loading').show();
            },
            success: function(response) {
              $('#loading').hide();
              if (response.success) {
                $wishlistItem.fadeOut(300, function() {
                  $(this).remove();
                  updateSelectedCount();

                  if ($('.wishlist-item').length === 0) {
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

        showConfirm('Remove Selected Items', `Are you sure you want to remove ${count} item(s) from your wishlist?`, function() {
          const itemIds = [];
          const $itemsToRemove = [];

          selectedItems.each(function() {
            itemIds.push($(this).data('wishlist-item-id'));
            $itemsToRemove.push($(this).closest('.wishlist-item'));
          });

          let completed = 0;
          let hasError = false;

          $('#loading').show();

          itemIds.forEach(function(wishlistItemId, index) {
            $.ajax({
              url: 'wishlist.php',
              method: 'POST',
              data: {
                action: 'remove_item',
                wishlist_item_id: wishlistItemId
              },
              dataType: 'json',
              success: function(response) {
                if (response.success) {
                  $itemsToRemove[index].fadeOut(300, function() {
                    $(this).remove();
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

                  setTimeout(function() {
                    updateSelectedCount();

                    if (hasError) {
                      showAlert('Some items could not be removed', 'error');
                    } else {
                      showAlert(`${count} item(s) removed successfully`, 'success');
                    }

                    if ($('.wishlist-item').length === 0) {
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

      // Clear wishlist
      $('#clear-wishlist').on('click', function() {
        showConfirm('Clear Wishlist', 'Are you sure you want to clear your entire wishlist? This action cannot be undone.', function() {
          $.ajax({
            url: 'wishlist.php',
            method: 'POST',
            data: {
              action: 'clear_wishlist'
            },
            dataType: 'json',
            beforeSend: function() {
              $('#loading').show();
            },
            success: function(response) {
              $('#loading').hide();
              if (response.success) {
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
              showAlert('An error occurred while clearing wishlist', 'error');
            }
          });
        });
      });

      // Handle clicks on product image and name to navigate to product page
      $(document).on('click', '.item-image-container, .item-details h3', function() {
        const productId = $(this).closest('.wishlist-item').data('product-id');
        if (productId) {
          window.location.href = '../product/product_details.php?id=' + productId;
        }
      });
    });
  </script>
</body>

</html>
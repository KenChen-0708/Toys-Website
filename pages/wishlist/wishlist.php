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
  <link rel="stylesheet" href="/assets/css/wishlist.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<?php include '../../partials/header.php';?>
<div class="wishlist-page">
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

  <script src="/assets/js/wishlist.js"></script>
</div>

<?php include '../../partials/footer.php'; ?>
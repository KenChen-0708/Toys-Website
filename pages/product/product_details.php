<?php
session_start();
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';
redirect_if_admin();

function user_initials($name)
{
  $parts = preg_split('/\s+/', trim((string)$name));
  $first = mb_substr($parts[0] ?? '', 0, 1);
  $second = mb_substr($parts[1] ?? '', 0, 1);
  return mb_strtoupper($first . $second ?: $first);
}

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch product details
$product = null;
$sub_images = [];

if ($product_id > 0) {
  $stmt = $pdo->prepare("SELECT * FROM Product WHERE product_id = ?");
  $stmt->execute([$product_id]);
  $product = $stmt->fetch();

  if ($product) {
    $stmt = $pdo->prepare("SELECT sub_image_path FROM Product_Sub_Image WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $sub_images = $stmt->fetchAll(PDO::FETCH_COLUMN);
  }
}

if (!$product) {
  header("Location: /pages/product/product_list.php");
  exit();
}

// Fetch reviews
$reviews = [];
$review_count = 0;
$average_rating = 0;
$review_media = [];
$review_replies = [];
try {
  $stmt = $pdo->prepare("SELECT r.*, m.username as member_name 
                         FROM Review r 
                         JOIN Member m ON r.member_id = m.member_id 
                         WHERE r.product_id = ? 
                         ORDER BY r.created_date DESC");
  $stmt->execute([$product_id]);
  $reviews = $stmt->fetchAll();
  $review_count = count($reviews);

  if ($review_count > 0) {
    $total_rating = array_sum(array_column($reviews, 'rating'));
    $average_rating = round($total_rating / $review_count, 1);
    
    // Load review media
    $review_ids = array_column($reviews, 'review_id');
    if (!empty($review_ids)) {
      try {
        $placeholders = implode(',', array_fill(0, count($review_ids), '?'));
        $media_stmt = $pdo->prepare("SELECT review_id, file_path FROM Review_Media WHERE review_id IN ($placeholders)");
        $media_stmt->execute($review_ids);
        foreach ($media_stmt->fetchAll(PDO::FETCH_ASSOC) as $media) {
          $rid = (int)$media['review_id'];
          if (!isset($review_media[$rid])) {
            $review_media[$rid] = [];
          }
          $review_media[$rid][] = $media['file_path'];
        }
      } catch (PDOException $mediaEx) {
        // Skip media if table doesn't exist
      }
    }

    // Load review replies
    try {
      $placeholders = implode(',', array_fill(0, count($review_ids), '?'));
      $reply_stmt = $pdo->prepare("SELECT reply_id, review_id, reply_text, created_date FROM Review_Reply WHERE review_id IN ($placeholders)");
      $reply_stmt->execute($review_ids);
      foreach ($reply_stmt->fetchAll(PDO::FETCH_ASSOC) as $reply) {
        $rid = (int)$reply['review_id'];
        $review_replies[$rid] = $reply;
      }
    } catch (PDOException $replyEx) {
      // Skip replies if table doesn't exist
    }
  }
} catch (PDOException $e) {
  $reviews = [];
  $review_count = 0;
}

$title = htmlspecialchars($product['name']) . " — Details";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="/assets/css/dark_mode_style.css">
  <link rel="stylesheet" href="/assets/css/styles.css">
  <link rel="stylesheet" href="/assets/css/product_details.css">
  <title><?= htmlspecialchars($title) ?></title>
</head>

<?php include '../../partials/header.php'; ?>
<div class="product-details-page">
  <main class="main">
    <!-- Product Section -->
    <div class="product-section">
      <!-- Image Gallery -->
      <div class="gallery">
        <div class="gallery__main">
          <img src="../../<?= htmlspecialchars($product['main_image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="gallery__main-img" id="mainImage" />
        </div>
        <div class="gallery__thumbs">
          <?php if (!empty($sub_images)): ?>
            <div class="gallery__thumb">
              <img src="../../<?= htmlspecialchars($product['main_image_path']) ?>" alt="Main" class="gallery__thumb-img" onclick="changeImage(this.src)" />
            </div>
            <?php foreach (array_slice($sub_images, 0, 3) as $sub_image): ?>
              <div class="gallery__thumb">
                <img src="../../<?= htmlspecialchars($sub_image) ?>" alt="Product" class="gallery__thumb-img" onclick="changeImage(this.src)" />
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="gallery__thumb">
              <img src="../../<?= htmlspecialchars($product['main_image_path']) ?>" alt="Main" class="gallery__thumb-img" onclick="changeImage(this.src)" />
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Product Info -->
      <div class="product-info">
        <span class="product-badge"><?= htmlspecialchars($product['category']) ?></span>

        <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>

        <div class="product-rating-row">
          <div class="rating-stars">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <?php if ($i <= floor($average_rating)): ?>
                <span class="star filled">★</span>
              <?php elseif ($i - 0.5 <= $average_rating): ?>
                <span class="star half">☆</span>
              <?php else: ?>
                <span class="star">☆</span>
              <?php endif; ?>
            <?php endfor; ?>
          </div>
          <span class="rating-text">
            <span class="rating-number"><?= $average_rating > 0 ? number_format($average_rating, 1) : 'No rating' ?></span>
            (<?= $review_count ?> review<?= $review_count != 1 ? 's' : '' ?>)
          </span>
        </div>

        <div class="product-price-section">
          <div class="price-wrapper">
            <span class="current-price">RM <?= number_format($product['price'], 2) ?></span>
          </div>
          <?php if ($product['status'] == 'Available' && $product['stock'] > 0): ?>
            <span class="stock-badge in-stock">
              <span>✓</span> In Stock
            </span>
          <?php else: ?>
            <span class="stock-badge out-of-stock">
              <span>✗</span> Out of Stock
            </span>
          <?php endif; ?>
        </div>

        <p class="product-description">
          <?= nl2br(htmlspecialchars($product['description'])) ?>
        </p>

        <div class="product-features">
          <div class="feature-item">
            <div class="feature-icon">
              <span>📦</span>
            </div>
            <div class="feature-text">
              <strong>Stock Available</strong>
              <?= $product['stock'] ?> units in stock
            </div>
          </div>
          <div class="feature-item">
            <div class="feature-icon">
              <span>🚚</span>
            </div>
            <div class="feature-text">
              <strong>Free Shipping</strong>
              On orders over RM15
            </div>
          </div>
        </div>

        <div class="quantity-section">
          <label class="section-label">Quantity:</label>
          <div class="quantity-selector">
            <button class="qty-btn qty-minus" type="button" onclick="decreaseQty()">
              <span>−</span>
            </button>
            <input type="number" class="quantity-input" id="quantity" value="1" min="1" max="<?= $product['stock'] ?>" />
            <button class="qty-btn qty-plus" type="button" onclick="increaseQty()">
              <span>+</span>
            </button>
          </div>
        </div>

        <div class="action-buttons">
          <button class="btn btn-primary" id="addToCartBtn" data-product-id="<?= $product_id ?>" <?= ($product['status'] != 'Available' || $product['stock'] <= 0) ? 'disabled' : '' ?>>
            <img src="../../assets/img/icon-cart.svg" alt="Cart" style="width: 16px; height: 16px; margin-right: 8px;">
            Add to Cart
          </button>
          <button class="btn btn-secondary" id="buyNowBtn" data-product-id="<?= $product_id ?>" <?= ($product['status'] != 'Available' || $product['stock'] <= 0) ? 'disabled' : '' ?>>
            <span>🛍</span>
            Buy Now
          </button>
          <button class="btn btn-icon" id="addToWishlistBtn" data-product-id="<?= $product_id ?>" title="Add to Wishlist">
            <img src="../../assets/img/icon-heart.svg" alt="Heart" style="width: 16px; height: 16px;">
          </button>
        </div>

        <div class="product-meta">
          <div class="meta-item">
            <span class="meta-label">SKU:</span>
            <span class="meta-value">#<?= str_pad($product['product_id'], 6, '0', STR_PAD_LEFT) ?></span>
          </div>
          <div class="meta-item">
            <span class="meta-label">Category:</span>
            <span class="meta-value"><?= htmlspecialchars($product['category']) ?></span>
          </div>
          <div class="meta-item">
            <span class="meta-label">Availability:</span>
            <span class="meta-value">
              <?php if ($product['status'] == 'Available' && $product['stock'] > 0): ?>
                Available
              <?php else: ?>
                Out of Stock
              <?php endif; ?>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Reviews Section -->
    <div class="reviews-section">
      <h2 class="section-title">Customer Reviews</h2>

      <?php if ($review_count > 0): ?>
        <div class="reviews-summary">
          <div class="rating-overview">
            <div class="rating-score"><?= number_format($average_rating, 1) ?></div>
            <div class="rating-stars-large">
              <?php for ($i = 1; $i <= 5; $i++): ?>
                <?php if ($i <= floor($average_rating)): ?>
                  <span class="star filled">★</span>
                <?php elseif ($i - 0.5 <= $average_rating): ?>
                  <span class="star half">☆</span>
                <?php else: ?>
                  <span class="star">☆</span>
                <?php endif; ?>
              <?php endfor; ?>
            </div>
            <div class="rating-count">Based on <?= $review_count ?> review<?= $review_count != 1 ? 's' : '' ?></div>
          </div>
        </div>

        <div class="reviews-list">
          <?php foreach ($reviews as $review): ?>
            <div class="review-card">
              <div class="review-header">
                <div class="review-avatar">
                  <?= htmlspecialchars(user_initials($review['member_name'])) ?>
                </div>
                <div class="review-author-info">
                  <div class="review-author"><?= htmlspecialchars($review['member_name']) ?></div>
                  <div class="review-date"><?= date('F j, Y', strtotime($review['created_date'])) ?></div>
                </div>
                <div class="review-rating">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <?php if ($i <= $review['rating']): ?>
                      <span class="star filled">★</span>
                    <?php else: ?>
                      <span class="star">☆</span>
                    <?php endif; ?>
                  <?php endfor; ?>
                </div>
              </div>
              <p class="review-text"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
              <?php 
                $media_list = $review_media[$review['review_id']] ?? [];
                if (!empty($media_list)):
              ?>
                <div class="review-media" style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px;">
                  <?php foreach ($media_list as $path): ?>
                    <img src="<?= htmlspecialchars($path) ?>" alt="Review image" style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px; border: 1px solid #e0e0e0; cursor: pointer;" onclick="window.open(this.src, '_blank')">
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <!-- Admin Reply Section -->
              <?php if (isset($review_replies[$review['review_id']])): 
                $reply = $review_replies[$review['review_id']];
              ?>
                <div class="admin-reply" style="margin-top: 16px; padding: 12px; background-color: #f0f9ff; border-left: 4px solid #3b82f6; border-radius: 4px;">
                  <div style="font-weight: 600; color: #1e40af; margin-bottom: 8px; font-size: 0.9rem;">
                    Merchant Reply
                  </div>
                  <p style="margin: 0; color: #374151; line-height: 1.5;"><?= nl2br(htmlspecialchars($reply['reply_text'])) ?></p>
                  <div style="margin-top: 8px; font-size: 0.8rem; color: #6b7280;">
                    <?= date('F j, Y', strtotime($reply['created_date'])) ?>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="no-reviews">
          <p>No reviews yet. Be the first to review this product!</p>
        </div>
      <?php endif; ?>
    </div>

    <!-- Related Products -->
    <?php
    // Fetch related products from same category (only available products)
    $stmt = $pdo->prepare("SELECT p.*, 
                          (SELECT sub_image_path FROM Product_Sub_Image WHERE product_id = p.product_id LIMIT 1) as first_sub_image
                          FROM Product p 
                          WHERE p.category = ? AND p.product_id != ? AND p.status = 'Available'
                          LIMIT 4");
    $stmt->execute([$product['category'], $product_id]);
    $related_products = $stmt->fetchAll();

    // Only display section if there are related products
    if (!empty($related_products)):
    ?>
      <div class="related-section">
        <h2 class="related-title">Related Products</h2>
        <div class="products-grid">
          <?php
          foreach ($related_products as $related):
            $hover_image = $related['first_sub_image'] ?? $related['main_image_path'];
          ?>
            <article class="product-card">
              <div class="product-image-wrapper">
                <a href="product_details.php?id=<?= $related['product_id'] ?>" class="product-link">
                  <img src="../../<?= htmlspecialchars($related['main_image_path']) ?>"
                    alt="<?= htmlspecialchars($related['name']) ?>"
                    class="product-image primary" />
                  <img src="../../<?= htmlspecialchars($hover_image) ?>"
                    alt="<?= htmlspecialchars($related['name']) ?>"
                    class="product-image secondary" />
                </a>

                <div class="product-actions">
                  <button class="action-btn wishlist-btn" data-product-id="<?= $related['product_id'] ?>">
                    <img src="../../assets/img/icon-heart.svg" alt="Heart" style="width: 16px; height: 16px;">
                  </button>
                  <a href="product_details.php?id=<?= $related['product_id'] ?>" class="action-btn">
                    <span>👁</span>
                  </a>
                </div>
              </div>

              <div class="product-card-info">
                <span class="product-category"><?= htmlspecialchars($related['category']) ?></span>
                <h3 class="product-name">
                  <a href="product_details.php?id=<?= $related['product_id'] ?>">
                    <?= htmlspecialchars($related['name']) ?>
                  </a>
                </h3>

                <div class="product-rating-small">
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star filled">★</span>
                  <span class="star">☆</span>
                </div>

                <div class="product-footer">
                  <span class="product-price">RM <?= number_format($related['price'], 2) ?></span>
                  <button class="add-cart-btn" data-product-id="<?= $related['product_id'] ?>">
                    <img src="../../assets/img/icon-cart.svg" alt="Cart" style="width: 16px; height: 16px;">
                  </button>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    // Pass product ID to JavaScript
    window.productId = <?= $product_id ?>;
  </script>
  <script src="/assets/js/product_details.js"></script>
</div>

<?php include '../../partials/footer.php'; ?>
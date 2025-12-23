<?php
session_start();
require_once 'helper/auth.php';
require_once 'helper/pdo_connection.php';
redirect_if_admin();
$title = "Home";

// Fetch product categories with product count
$productCategories = [];
try {
  $stmt = $pdo->prepare("
    SELECT 
      category,
      COUNT(*) as product_count,
      MAX(main_image_path) as category_image
    FROM Product
    WHERE status = 'Available' AND category IS NOT NULL AND category != ''
    GROUP BY category
    ORDER BY product_count DESC
  ");
  $stmt->execute();
  $productCategories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
  $productCategories = [];
}

// Fetch popular products by average review rating
$popularProducts = [];
try {
  $stmt = $pdo->prepare("
    SELECT 
      p.product_id,
      p.name,
      p.main_image_path,
      p.category,
      COALESCE(AVG(r.rating), 0) as avg_rating,
      COUNT(r.review_id) as review_count
    FROM Product p
    LEFT JOIN Review r ON p.product_id = r.product_id
    WHERE p.status = 'Available'
    GROUP BY p.product_id, p.name, p.main_image_path, p.category
    ORDER BY avg_rating DESC, review_count DESC
    LIMIT 8
  ");
  $stmt->execute();
  $popularProducts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
  $popularProducts = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <!--=============== FLATICON ===============-->
  <link
    rel="stylesheet"
    href="https://cdn-uicons.flaticon.com/2.0.0/uicons-regular-straight/css/uicons-regular-straight.css" />

  <!--=============== CSS ===============-->
  <link rel="stylesheet" href="/assets/css/styles.css" />
  <link rel="stylesheet" href="/assets/css/index.css" />
  <link rel="stylesheet" href="/assets/css/dark_mode_style.css">

  <title><?= htmlspecialchars($title) ?></title>
</head>
<?php include 'partials/header.php'; ?>
<?php include './chatbot.html'; ?>

<!--=============== MAIN ===============-->
<main class="main">
  <!--=============== HOME ===============-->
  <section class="home section--lg">
    <div class="home__container container grid">
      <div class="home__content">
        <span class="home__subtitle">Playtime Deals</span>
        <h1 class="home__title">
          Toys & Games <span>Great Collection</span>
        </h1>
        <p class="home__description">
          Play more, save more — up to 20% off
        </p>
        <a href="pages/product/product_list.php" class="btn">Shop Now</a>
      </div>
      <img src="assets/img/homepg.png" class="home__img" alt="hats" />
    </div>
  </section>

  <!--=============== SHOP BY CATEGORY ===============-->
  <section class="shop-category section">
    <div class="container">
      <div class="shop-category__header">
        <h3 class="section__title"><span>Shop</span> By Category</h3>
        <p class="shop-category__subtitle">Explore our amazing collection of toys and games.</p>
      </div>
      
      <div class="category-grid">
        <?php foreach ($productCategories as $cat): ?>
          <div class="category-card">
            <div class="category-card__image">
              <img src="<?= htmlspecialchars($cat['category_image'] ?? 'assets/img/placeholder.jpg') ?>" alt="<?= htmlspecialchars($cat['category']) ?>" />
            </div>
            <h4 class="category-card__title"><?= htmlspecialchars($cat['category']) ?></h4>
            <a href="pages/product/product_list.php?category=<?= urlencode($cat['category']) ?>" class="category-card__btn">Go Shop</a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!--=============== CATEGORIES ===============-->
  <section class="categories container section">
    <h3 class="section__title"><span>Popular</span> Products</h3>
    <p class="categories__subtitle">Check out our bestselling toys and games that customers love.</p>

    <div class="categories__shell">
      <button class="cat-btn cat-btn--prev" aria-label="Previous" disabled>
        <i class="fi fi-rs-angle-left"></i>
      </button>

      <div class="categories__viewport" id="catViewport">
        <div class="categories__track" id="catTrack">
          <?php foreach ($popularProducts as $product): ?>
            <a href="pages/product/product_details.php?id=<?= (int)$product['product_id'] ?>" class="category__item">
              <img src="<?= htmlspecialchars($product['main_image_path'] ?? 'assets/img/placeholder.jpg') ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="category__img" />
              <h3 class="category__title"><?= htmlspecialchars($product['name']) ?></h3>
              <?php if ($product['review_count'] > 0): ?>
                <div style="font-size: 0.85rem; color: var(--text-color-light); text-align: center; margin-top: 0.5rem;">
                  <i class="fi fi-rs-star" style="color: #ffd700; font-size: 0.75rem;"></i>
                  <?= number_format($product['avg_rating'], 1) ?> (<?= (int)$product['review_count'] ?>)
                </div>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <button class="cat-btn cat-btn--next" aria-label="Next">
        <i class="fi fi-rs-angle-right"></i>
      </button>
    </div>
  </section>

  <?php include 'partials/footer.php'; ?>

  <script>
    (function() {
      const viewport = document.getElementById('catViewport');
      const track = document.getElementById('catTrack');
      const prevBtn = document.querySelector('.cat-btn--prev');
      const nextBtn = document.querySelector('.cat-btn--next');

      // Compute how much to scroll: one "page" width of the viewport
      function scrollAmount() {
        return viewport.clientWidth;
      }

      function updateButtons() {
        // allow tiny epsilon to avoid off-by-a-few px
        const maxScroll = track.scrollWidth - viewport.clientWidth - 2;
        prevBtn.disabled = viewport.scrollLeft <= 2;
        nextBtn.disabled = viewport.scrollLeft >= maxScroll;
      }

      prevBtn.addEventListener('click', () => {
        viewport.scrollBy({
          left: -scrollAmount(),
          behavior: 'smooth'
        });
        // Wait a bit then update
        setTimeout(updateButtons, 300);
      });

      nextBtn.addEventListener('click', () => {
        viewport.scrollBy({
          left: scrollAmount(),
          behavior: 'smooth'
        });
        setTimeout(updateButtons, 300);
      });

      // Keep buttons in sync on user scroll / resize
      viewport.addEventListener('scroll', updateButtons, {
        passive: true
      });
      window.addEventListener('resize', updateButtons);

      // Optional: mouse wheel horizontal scroll
      viewport.addEventListener('wheel', (e) => {
        if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
          e.preventDefault();
          viewport.scrollBy({
            left: e.deltaY,
            behavior: 'auto'
          });
        }
      }, {
        passive: false
      });

      // Optional: basic drag-to-scroll (skip if clicking links)
      let isDown = false,
        startX = 0,
        startLeft = 0,
        isDragging = false;
      viewport.addEventListener('pointerdown', e => {
        // Don't start drag on links or their children
        if (e.target.closest('a')) return;
        isDown = true;
        startX = e.clientX;
        startLeft = viewport.scrollLeft;
        viewport.setPointerCapture(e.pointerId);
      });
      viewport.addEventListener('pointermove', e => {
        if (!isDown) return;
        // Only consider it a drag if moved more than 5px
        const diff = Math.abs(e.clientX - startX);
        if (diff > 5) isDragging = true;
        if (!isDragging) return;
        viewport.scrollLeft = startLeft - (e.clientX - startX);
      });
      viewport.addEventListener('pointerup', e => {
        isDown = false;
        isDragging = false;
      });
      viewport.addEventListener('pointercancel', () => {
        isDown = false;
        isDragging = false;
      });

      updateButtons();
    })();
  </script>
</main>
</body>

</html>
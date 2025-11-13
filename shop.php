<?php 
  $title = "Shop";
  include 'partials/header.php';
  require_once 'helper/connection.php';

  // Fetch all products from database
  $products = [];
  $total_products = 0;
  
  if ($db_ok && $conn) {
    $query = "SELECT p.*, 
              (SELECT sub_image_path FROM Product_Sub_Image WHERE product_id = p.product_id LIMIT 1) as first_sub_image
              FROM Product p 
              ORDER BY p.product_id DESC";
    $result = $conn->query($query);
    
    if ($result) {
      $products = $result->fetch_all(MYSQLI_ASSOC);
      $total_products = count($products);
    }
  }
?>

<main class="main">
  <!--=============== BREADCRUMB ===============-->
  <section class="breadcrumb">
    <ul class="breadcrumb__list flex container">
      <li><a href="index.php" class="breadcrumb__link">Home</a></li>
      <li><span class="breadcrumb__link">></span></li>
      <li><span class="breadcrumb__link">Shop</span></li>
    </ul>
  </section>

  <!--=============== PRODUCTS ===============-->
  <section class="products container section--lg">
    <p class="total__products">We found <span><?php echo $total_products; ?></span> items for you!</p>

    <div class="products__container grid">
      <?php if (!empty($products)): ?>
        <?php foreach ($products as $product): 
          $hover_image = $product['first_sub_image'] ?? $product['main_image_path'];
          $status_badge = ($product['status'] == 'Out of Stock') ? 'Out of Stock' : '';
        ?>
        <div class="product__item">
          <div class="product__banner">
            <a href="details.php?id=<?php echo $product['product_id']; ?>" class="product__images">
              <img src="<?php echo htmlspecialchars($product['main_image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product__img default" />
              <img src="<?php echo htmlspecialchars($hover_image); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product__img hover" />
            </a>
            <div class="product__actions">
              <a href="details.php?id=<?php echo $product['product_id']; ?>" class="action__btn" aria-label="Quick View"><i class="fi fi-rs-eye"></i></a>
              <a href="#" class="action__btn" aria-label="Add to Wishlist"><i class="fi fi-rs-heart"></i></a>
              <a href="#" class="action__btn" aria-label="Compare"><i class="fi fi-rs-shuffle"></i></a>
            </div>
            <?php if ($status_badge): ?>
              <div class="product__badge light-pink"><?php echo $status_badge; ?></div>
            <?php endif; ?>
          </div>
          <div class="product__content">
            <span class="product__category"><?php echo htmlspecialchars($product['category']); ?></span>
            <a href="details.php?id=<?php echo $product['product_id']; ?>">
              <h3 class="product__title"><?php echo htmlspecialchars($product['name']); ?></h3>
            </a>
            <div class="product__rating">
              <i class="fi fi-rs-star"></i><i class="fi fi-rs-star"></i><i class="fi fi-rs-star"></i><i class="fi fi-rs-star"></i><i class="fi fi-rs-star"></i>
            </div>
            <div class="product__price flex">
              <span class="new__price">$<?php echo number_format($product['price'], 2); ?></span>
            </div>
            <a href="#" class="action__btn cart__btn" aria-label="Add To Cart"><i class="fi fi-rs-shopping-bag-add"></i></a>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>No products available at the moment.</p>
      <?php endif; ?>
    </div>

    <ul class="pagination">
      <li><a href="#" class="pagination__link active">01</a></li>
      <li><a href="#" class="pagination__link">02</a></li>
      <li><a href="#" class="pagination__link">03</a></li>
      <li><a href="#" class="pagination__link">...</a></li>
      <li><a href="#" class="pagination__link">16</a></li>
      <li>
        <a href="#" class="pagination__link icon">
          <i class="fi-rs-angle-double-small-right"></i>
        </a>
      </li>
    </ul>
  </section>

</main>

<?php include 'partials/footer.php'; ?>

<?php
session_start();
$title = "Home";
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
        <span class="home__subtitle">Hot Promotions</span>
        <h1 class="home__title">
          Fashion Trending <span>Great Collection</span>
        </h1>
        <p class="home__description">
          Save more with coupons & up tp 20% off
        </p>
        <a href="pages/product/product_list.php" class="btn">Shop Now</a>
      </div>
      <img src="assets/img/home-img.png" class="home__img" alt="hats" />
    </div>
  </section>

  <!--=============== CATEGORIES ===============-->
  <section class="categories container section">
    <h3 class="section__title"><span>Popular</span> Categories</h3>

    <div class="categories__shell">
      <button class="cat-btn cat-btn--prev" aria-label="Previous" disabled>
        <i class="fi fi-rs-angle-left"></i>
      </button>

      <div class="categories__viewport" id="catViewport">
        <div class="categories__track" id="catTrack">
          <a href="pages/product/product_list.php" class="category__item">
            <img src="assets/img/category-1.jpg" alt="" class="category__img" />
            <h3 class="category__title">T-Shirt</h3>
          </a>
          <a href="pages/product/product_list.php" class="category__item">
            <img src="assets/img/category-2.jpg" alt="" class="category__img" />
            <h3 class="category__title">Bags</h3>
          </a>
          <a href="pages/product/product_list.php" class="category__item">
            <img src="assets/img/category-3.jpg" alt="" class="category__img" />
            <h3 class="category__title">Sandal</h3>
          </a>
          <a href="pages/product/product_list.php" class="category__item">
            <img src="assets/img/category-4.jpg" alt="" class="category__img" />
            <h3 class="category__title">Scarf Cap</h3>
          </a>
          <a href="pages/product/product_list.php" class="category__item">
            <img src="assets/img/category-5.jpg" alt="" class="category__img" />
            <h3 class="category__title">Shoes</h3>
          </a>
          <a href="pages/product/product_list.php" class="category__item">
            <img src="assets/img/category-6.jpg" alt="" class="category__img" />
            <h3 class="category__title">Pillowcase</h3>
          </a>
          <a href="pages/product/product_list.php" class="category__item">
            <img src="assets/img/category-7.jpg" alt="" class="category__img" />
            <h3 class="category__title">Jumpsuit</h3>
          </a>
          <a href="pages/product/product_list.php" class="category__item">
            <img src="assets/img/category-8.jpg" alt="" class="category__img" />
            <h3 class="category__title">Hats</h3>
          </a>
        </div>
      </div>

      <button class="cat-btn cat-btn--next" aria-label="Next">
        <i class="fi fi-rs-angle-right"></i>
      </button>
    </div>
  </section>


  <!--=============== PRODUCTS ===============-->
  <section class="products container section">
    <div class="tab__btns">
      <span class="tab__btn active-tab" data-target="#featured">Featured</span>
      <span class="tab__btn" data-target="#popular">Popular</span>
      <span class="tab__btn" data-target="#new-added">New Added</span>
    </div>

    <div class="tab__items">
      <div class="tab__item active-tab" content id="featured">
        <div class="products__container grid">
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-1-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-1-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-pink">Hot</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-2-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-2-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-green">Hot</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-3-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-3-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-orange">Hot</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-4-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-4-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-blue">Hot</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-5-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-5-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-blue">-30%</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-6-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-6-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-blue">-22%</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-7-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-7-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-green">-22%</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-8-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-8-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
      <div class="tab__item" content id="popular">
        <div class="products__container grid">
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-9-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-9-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-pink">Hot</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-2-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-2-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-green">Hot</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-10-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-10-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-orange">Hot</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-4-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-4-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-blue">Hot</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-5-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-5-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-blue">-30%</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-11-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-11-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-blue">-22%</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-7-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-7-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-green">-22%</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-8-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-8-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
      <div class="tab__item" content id="new-added">
        <div class="products__container grid">
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-1-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-1-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-pink">Hot</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-12-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-12-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-green">Hot</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-13-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-13-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-orange">Hot</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-4-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-4-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-blue">Hot</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-10-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-10-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-blue">-30%</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-6-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-6-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-blue">-22%</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-9-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-9-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
              <div class="product__badge light-green">-22%</div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
          <div class="product__item">
            <div class="product__banner">
              <a href="pages/product/product_details.php" class="product__images">
                <img
                  src="assets/img/product-8-1.jpg"
                  alt=""
                  class="product__img default" />
                <img
                  src="assets/img/product-8-2.jpg"
                  alt=""
                  class="product__img hover" />
              </a>
              <div class="product__actions">
                <a href="#" class="action__btn" aria-label="Quick View">
                  <i class="fi fi-rs-eye"></i>
                </a>
                <a
                  href="#"
                  class="action__btn"
                  aria-label="Add to Wishlist">
                  <i class="fi fi-rs-heart"></i>
                </a>
                <a href="#" class="action__btn" aria-label="Compare">
                  <i class="fi fi-rs-shuffle"></i>
                </a>
              </div>
            </div>
            <div class="product__content">
              <span class="product__category">Clothing</span>
              <a href="pages/product/product_details.php">
                <h3 class="product__title">Colorful Pattern Shirts</h3>
              </a>
              <div class="product__rating">
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
                <i class="fi fi-rs-star"></i>
              </div>
              <div class="product__price flex">
                <span class="new__price">$238.85</span>
                <span class="old__price">$245.8</span>
              </div>
              <a
                href="#"
                class="action__btn cart__btn"
                aria-label="Add To Cart">
                <i class="fi fi-rs-product_listping-bag-add"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!--=============== DEALS ===============-->
  <section class="deals section">
    <div class="deals__container container grid">
      <div class="deals__item">
        <div class="deals__group">
          <h3 class="deals__brand">Deals of the Day</h3>
          <span class="deals__category">Limited quantities</span>
        </div>
        <h4 class="deals__title">Summer Collection New Modern Design</h4>
        <div class="deals__price flex">
          <span class="new__price">$139.00</span>
          <span class="old__price">$160.99</span>
        </div>
        <div class="deals__group">
          <p class="deals__countdown-text">Hurry Up! Offer Ends In:</p>
          <div class="countdown">
            <div class="countdown__amount">
              <p class="countdown__period">02</p>
              <span class="unit">Days</span>
            </div>
            <div class="countdown__amount">
              <p class="countdown__period">22</p>
              <span class="unit">Hours</span>
            </div>
            <div class="countdown__amount">
              <p class="countdown__period">57</p>
              <span class="unit">Mins</span>
            </div>
            <div class="countdown__amount">
              <p class="countdown__period">28</p>
              <span class="unit">Sec</span>
            </div>
          </div>
        </div>
        <div class="deals__btn">
          <a href="pages/product/product_details.php" class="btn btn--md">Shop Now</a>
        </div>
      </div>
      <div class="deals__item">
        <div class="deals__group">
          <h3 class="deals__brand">Women Clothing</h3>
          <span class="deals__category">Shirts & Bag</span>
        </div>
        <h4 class="deals__title">Try Something new on vacation</h4>
        <div class="deals__price flex">
          <span class="new__price">$178.00</span>
          <span class="old__price">$256.99</span>
        </div>
        <div class="deals__group">
          <p class="deals__countdown-text">Hurry Up! Offer Ends In:</p>
          <div class="countdown">
            <div class="countdown__amount">
              <p class="countdown__period">02</p>
              <span class="unit">Days</span>
            </div>
            <div class="countdown__amount">
              <p class="countdown__period">22</p>
              <span class="unit">Hours</span>
            </div>
            <div class="countdown__amount">
              <p class="countdown__period">57</p>
              <span class="unit">Mins</span>
            </div>
            <div class="countdown__amount">
              <p class="countdown__period">28</p>
              <span class="unit">Sec</span>
            </div>
          </div>
        </div>
        <div class="deals__btn">
          <a href="pages/product/product_details.php" class="btn btn--md">Shop Now</a>
        </div>
      </div>
    </div>
  </section>

  <!--=============== SHOWCASE ===============-->
  <section class="showcase section">
    <div class="showcase__container container grid">
      <div class="showcase__wrapper">
        <h3 class="section__title">Hot Releases</h3>
        <div class="showcase__item">
          <a href="pages/product/product_details.php" class="showcase__img-box">
            <img
              src="./assets/img/showcase-img-1.jpg"
              alt=""
              class="showcase__img" />
          </a>
          <div class="showcase__content">
            <a href="pages/product/product_details.php">
              <h4 class="showcase__title">
                Floral Print Casual Cotton Dress
              </h4>
            </a>
            <div class="showcase__price flex">
              <span class="new__price">$238.85</span>
              <span class="old__price">$245.8</span>
            </div>
          </div>
        </div>
        <div class="showcase__item">
          <a href="pages/product/product_details.php" class="showcase__img-box">
            <img
              src="./assets/img/showcase-img-2.jpg"
              alt=""
              class="showcase__img" />
          </a>
          <div class="showcase__content">
            <a href="pages/product/product_details.php">
              <h4 class="showcase__title">
                Ruffled Solid Long Sleeve Blouse
              </h4>
            </a>
            <div class="showcase__price flex">
              <span class="new__price">$238.85</span>
              <span class="old__price">$245.8</span>
            </div>
          </div>
        </div>
        <div class="showcase__item">
          <a href="pages/product/product_details.php" class="showcase__img-box">
            <img
              src="./assets/img/showcase-img-3.jpg"
              alt=""
              class="showcase__img" />
          </a>
          <div class="showcase__content">
            <a href="pages/product/product_details.php">
              <h4 class="showcase__title">
                Multi-Color Print V-neck T-shirt
              </h4>
            </a>
            <div class="showcase__price flex">
              <span class="new__price">$238.85</span>
              <span class="old__price">$245.8</span>
            </div>
          </div>
        </div>
      </div>
      <div class="showcase__wrapper">
        <h3 class="section__title">Deals & Outlet</h3>
        <div class="showcase__item">
          <a href="pages/product/product_details.php" class="showcase__img-box">
            <img
              src="./assets/img/showcase-img-4.jpg"
              alt=""
              class="showcase__img" />
          </a>
          <div class="showcase__content">
            <a href="pages/product/product_details.php">
              <h4 class="showcase__title">Fish Print Patched T-shirt</h4>
            </a>
            <div class="showcase__price flex">
              <span class="new__price">$238.85</span>
              <span class="old__price">$245.8</span>
            </div>
          </div>
        </div>
        <div class="showcase__item">
          <a href="pages/product/product_details.php" class="showcase__img-box">
            <img
              src="./assets/img/showcase-img-5.jpg"
              alt=""
              class="showcase__img" />
          </a>
          <div class="showcase__content">
            <a href="pages/product/product_details.php">
              <h4 class="showcase__title">Fintage Floral Print Dress</h4>
            </a>
            <div class="showcase__price flex">
              <span class="new__price">$238.85</span>
              <span class="old__price">$245.8</span>
            </div>
          </div>
        </div>
        <div class="showcase__item">
          <a href="pages/product/product_details.php" class="showcase__img-box">
            <img
              src="./assets/img/showcase-img-6.jpg"
              alt=""
              class="showcase__img" />
          </a>
          <div class="showcase__content">
            <a href="pages/product/product_details.php">
              <h4 class="showcase__title">
                Multi-Color Stripe Circle T-shirt
              </h4>
            </a>
            <div class="showcase__price flex">
              <span class="new__price">$238.85</span>
              <span class="old__price">$245.8</span>
            </div>
          </div>
        </div>
      </div>
      <div class="showcase__wrapper">
        <h3 class="section__title">Top Selling</h3>
        <div class="showcase__item">
          <a href="pages/product/product_details.php" class="showcase__img-box">
            <img
              src="./assets/img/showcase-img-7.jpg"
              alt=""
              class="showcase__img" />
          </a>
          <div class="showcase__content">
            <a href="pages/product/product_details.php">
              <h4 class="showcase__title">
                Geometric Printed Long Sleeve Blouse
              </h4>
            </a>
            <div class="showcase__price flex">
              <span class="new__price">$238.85</span>
              <span class="old__price">$245.8</span>
            </div>
          </div>
        </div>
        <div class="showcase__item">
          <a href="pages/product/product_details.php" class="showcase__img-box">
            <img
              src="./assets/img/showcase-img-8.jpg"
              alt=""
              class="showcase__img" />
          </a>
          <div class="showcase__content">
            <a href="pages/product/product_details.php">
              <h4 class="showcase__title">Print Patchwork Maxi Dress</h4>
            </a>
            <div class="showcase__price flex">
              <span class="new__price">$238.85</span>
              <span class="old__price">$245.8</span>
            </div>
          </div>
        </div>
        <div class="showcase__item">
          <a href="pages/product/product_details.php" class="showcase__img-box">
            <img
              src="./assets/img/showcase-img-9.jpg"
              alt=""
              class="showcase__img" />
          </a>
          <div class="showcase__content">
            <a href="pages/product/product_details.php">
              <h4 class="showcase__title">
                Daisy Floral Print Straps Jumpsuit
              </h4>
            </a>
            <div class="showcase__price flex">
              <span class="new__price">$238.85</span>
              <span class="old__price">$245.8</span>
            </div>
          </div>
        </div>
      </div>
      <div class="showcase__wrapper">
        <h3 class="section__title">Trendy</h3>
        <div class="showcase__item">
          <a href="pages/product/product_details.php" class="showcase__img-box">
            <img
              src="./assets/img/showcase-img-7.jpg"
              alt=""
              class="showcase__img" />
          </a>
          <div class="showcase__content">
            <a href="pages/product/product_details.php">
              <h4 class="showcase__title">Floral Print Casual Cotton</h4>
            </a>
            <div class="showcase__price flex">
              <span class="new__price">$238.85</span>
              <span class="old__price">$245.8</span>
            </div>
          </div>
        </div>
        <div class="showcase__item">
          <a href="pages/product/product_details.php" class="showcase__img-box">
            <img
              src="./assets/img/showcase-img-8.jpg"
              alt=""
              class="showcase__img" />
          </a>
          <div class="showcase__content">
            <a href="pages/product/product_details.php">
              <h4 class="showcase__title">Ruffled Solid Long Sleeve</h4>
            </a>
            <div class="showcase__price flex">
              <span class="new__price">$238.85</span>
              <span class="old__price">$245.8</span>
            </div>
          </div>
        </div>
        <div class="showcase__item">
          <a href="pages/product/product_details.php" class="showcase__img-box">
            <img
              src="./assets/img/showcase-img-9.jpg"
              alt=""
              class="showcase__img" />
          </a>
          <div class="showcase__content">
            <a href="pages/product/product_details.php">
              <h4 class="showcase__title">Multi-Color Print V-neck</h4>
            </a>
            <div class="showcase__price flex">
              <span class="new__price">$238.85</span>
              <span class="old__price">$245.8</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

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

      // Optional: basic drag-to-scroll
      let isDown = false,
        startX = 0,
        startLeft = 0;
      viewport.addEventListener('pointerdown', e => {
        isDown = true;
        startX = e.clientX;
        startLeft = viewport.scrollLeft;
        viewport.setPointerCapture(e.pointerId);
      });
      viewport.addEventListener('pointermove', e => {
        if (!isDown) return;
        viewport.scrollLeft = startLeft - (e.clientX - startX);
      });
      viewport.addEventListener('pointerup', () => {
        isDown = false;
      });
      viewport.addEventListener('pointercancel', () => {
        isDown = false;
      });

      updateButtons();
    })();
  </script>


  <?php include 'partials/footer.php'; ?>
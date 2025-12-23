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
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.0.0/uicons-regular-straight/css/uicons-regular-straight.css" />
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.0.0/uicons-solid-straight/css/uicons-solid-straight.css" />
  <title><?= htmlspecialchars($title) ?></title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --primary: #0891b2;
      --primary-dark: #0e7490;
      --primary-light: #e0f2fe;
      --success: #10b981;
      --danger: #ef4444;
      --warning: #f59e0b;
      --dark: #1e293b;
      --gray-50: #f8fafc;
      --gray-100: #f1f5f9;
      --gray-200: #e2e8f0;
      --gray-300: #cbd5e1;
      --gray-400: #94a3b8;
      --gray-500: #64748b;
      --gray-600: #475569;
      --gray-700: #334155;
      --gray-800: #1e293b;
      --white: #ffffff;
      --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
      --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);
      --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1);
      --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1);
      --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1);
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      color: var(--dark);
      background: var(--gray-50);
      line-height: 1.6;
    }

    /* Header Styles */
    .header {
      background: var(--white);
      box-shadow: var(--shadow-sm);
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .header__top {
      background: var(--dark);
      color: var(--white);
      padding: 0.75rem 0;
      font-size: 0.875rem;
    }

    .header__container {
      max-width: 1280px;
      margin: 0 auto;
      padding: 0 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .header__contact {
      display: flex;
      gap: 1.5rem;
    }

    .header__top-action {
      color: var(--white);
      text-decoration: none;
      margin-left: 1rem;
      transition: color 0.3s;
    }

    .header__top-action:hover {
      color: var(--primary);
    }

    .header__user-profile {
      position: relative;
    }

    .avatar-btn {
      background: none;
      border: none;
      cursor: pointer;
      padding: 0;
    }

    .avatar-circle {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--white);
    }

    .profile-menu {
      display: none;
      position: absolute;
      top: calc(100% + 0.5rem);
      right: 0;
      background: var(--white);
      border-radius: 0.5rem;
      box-shadow: var(--shadow-xl);
      min-width: 220px;
      overflow: hidden;
    }

    .profile-menu__header {
      padding: 1rem;
      border-bottom: 1px solid var(--gray-200);
    }

    .profile-menu a,
    .profile-menu button {
      display: block;
      width: 100%;
      padding: 0.75rem 1rem;
      text-decoration: none;
      color: var(--dark);
      border: none;
      background: none;
      text-align: left;
      cursor: pointer;
      transition: background 0.2s;
      font-size: 0.875rem;
    }

    .profile-menu a:hover,
    .profile-menu button:hover {
      background: var(--gray-50);
    }

    .nav {
      max-width: 1280px;
      margin: 0 auto;
      padding: 1rem 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 2rem;
    }

    .nav__logo-img {
      height: 40px;
    }

    .nav__menu {
      display: flex;
      align-items: center;
      gap: 2rem;
      flex: 1;
    }

    .nav__list {
      display: flex;
      list-style: none;
      gap: 2rem;
    }

    .nav__link {
      text-decoration: none;
      color: var(--dark);
      font-weight: 500;
      transition: color 0.3s;
    }

    .nav__link:hover,
    .nav__link.active-link {
      color: var(--primary);
    }

    .header__search {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex: 1;
      max-width: 400px;
    }

    .form__input {
      flex: 1;
      padding: 0.625rem 1rem;
      border: 1px solid var(--gray-300);
      border-radius: 0.5rem;
      font-size: 0.875rem;
    }

    .search__btn {
      background: var(--primary);
      border: none;
      padding: 0.625rem 1rem;
      border-radius: 0.5rem;
      cursor: pointer;
      transition: background 0.3s;
    }

    .search__btn:hover {
      background: var(--primary-dark);
    }

    .search__btn img {
      width: 20px;
      height: 20px;
      filter: brightness(0) invert(1);
    }

    .header__user-actions {
      display: flex;
      gap: 1rem;
    }

    .header__action-btn {
      position: relative;
      text-decoration: none;
    }

    .header__action-btn img {
      width: 24px;
      height: 24px;
    }

    .header__action-btn .count {
      position: absolute;
      top: -8px;
      right: -8px;
      background: var(--primary);
      color: var(--white);
      font-size: 0.75rem;
      font-weight: 600;
      min-width: 20px;
      height: 20px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0 0.375rem;
    }

    /* Breadcrumb - removed */

    /* Main Content */
    .main {
      max-width: 1280px;
      margin: 0 auto;
      padding: 2rem 1.5rem;
    }

    /* Product Section */
    .product-section {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 3rem;
      margin-bottom: 3rem;
    }

    /* Image Gallery */
    .gallery {
      position: sticky;
      top: 100px;
      height: fit-content;
    }

    .gallery__main {
      background: var(--white);
      border-radius: 1rem;
      overflow: hidden;
      margin-bottom: 1rem;
      box-shadow: var(--shadow);
    }

    .gallery__main-img {
      width: 100%;
      aspect-ratio: 1;
      object-fit: cover;
    }

    .gallery__thumbs {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 0.75rem;
    }

    .gallery__thumb {
      background: var(--white);
      border-radius: 0.5rem;
      overflow: hidden;
      cursor: pointer;
      border: 2px solid transparent;
      transition: all 0.3s;
    }

    .gallery__thumb:hover {
      border-color: var(--primary);
      transform: translateY(-2px);
    }

    .gallery__thumb-img {
      width: 100%;
      aspect-ratio: 1;
      object-fit: cover;
    }

    /* Product Info */
    .product-info {
      background: var(--white);
      border-radius: 1rem;
      padding: 2rem;
      box-shadow: var(--shadow);
    }

    .product-badge {
      display: inline-block;
      padding: 0.375rem 0.875rem;
      background: var(--primary-light);
      color: var(--primary-dark);
      border-radius: 0.375rem;
      font-size: 0.75rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 1rem;
    }

    .product-title {
      font-size: 2rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 1rem;
      line-height: 1.2;
    }

    .product-rating-row {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.5rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid var(--gray-200);
    }

    .rating-stars {
      display: flex;
      gap: 0.25rem;
      color: #fbbf24;
    }

    .rating-text {
      color: var(--gray-600);
      font-size: 0.875rem;
    }

    .rating-number {
      font-weight: 600;
      color: var(--dark);
    }

    .product-price-section {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem;
      padding: 1.5rem;
      background: var(--gray-50);
      border-radius: 0.75rem;
    }

    .price-wrapper {
      display: flex;
      align-items: baseline;
      gap: 0.75rem;
    }

    .current-price {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--primary);
    }

    .stock-badge {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      border-radius: 0.5rem;
      font-size: 0.875rem;
      font-weight: 600;
    }

    .stock-badge.in-stock {
      background: #d1fae5;
      color: #065f46;
    }

    .stock-badge.out-of-stock {
      background: #fee2e2;
      color: #991b1b;
    }

    .product-description {
      color: var(--gray-600);
      line-height: 1.8;
      margin-bottom: 1.5rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid var(--gray-200);
    }

    .product-features {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1rem;
      margin-bottom: 2rem;
    }

    .feature-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.875rem;
      background: var(--gray-50);
      border-radius: 0.5rem;
    }

    .feature-icon {
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--white);
      border-radius: 0.5rem;
      color: var(--primary);
      font-size: 1.25rem;
    }

    .feature-text {
      flex: 1;
      font-size: 0.875rem;
      color: var(--gray-600);
    }

    .feature-text strong {
      color: var(--dark);
      display: block;
      margin-bottom: 0.125rem;
    }

    .quantity-section {
      margin-bottom: 1.5rem;
    }

    .section-label {
      font-weight: 600;
      color: var(--dark);
      margin-bottom: 0.75rem;
      display: block;
      font-size: 0.875rem;
    }

    .quantity-selector {
      display: inline-flex;
      align-items: center;
      border: 2px solid var(--gray-200);
      border-radius: 0.5rem;
      overflow: hidden;
      background: var(--white);
    }

    .qty-btn {
      width: 44px;
      height: 44px;
      border: none;
      background: var(--gray-50);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--dark);
      transition: all 0.3s;
    }

    .qty-btn:hover:not(:disabled) {
      background: var(--primary);
      color: var(--white);
    }

    .qty-btn:disabled {
      opacity: 0.4;
      cursor: not-allowed;
    }

    .quantity-input {
      width: 60px;
      height: 44px;
      border: none;
      text-align: center;
      font-weight: 600;
      font-size: 1rem;
      color: var(--dark);
    }

    .action-buttons {
      display: grid;
      grid-template-columns: 1fr 1fr auto;
      gap: 0.75rem;
      margin-bottom: 1.5rem;
    }

    .btn {
      padding: 0.875rem 1.5rem;
      border: none;
      border-radius: 0.5rem;
      font-weight: 600;
      font-size: 0.875rem;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      text-decoration: none;
    }

    .btn-primary {
      background: var(--primary);
      color: var(--white);
    }

    .btn-primary:hover:not(:disabled) {
      background: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .btn-secondary {
      background: var(--dark);
      color: var(--white);
    }

    .btn-secondary:hover:not(:disabled) {
      background: var(--gray-700);
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .btn-icon {
      width: 44px;
      height: 44px;
      padding: 0;
      background: var(--white);
      border: 2px solid var(--gray-200);
      color: var(--dark);
    }

    .btn-icon:hover {
      background: var(--primary);
      color: var(--white);
      border-color: var(--primary);
    }

    .product-meta {
      display: grid;
      gap: 0.75rem;
      padding: 1.5rem;
      background: var(--gray-50);
      border-radius: 0.75rem;
    }

    .meta-item {
      display: flex;
      justify-content: space-between;
      font-size: 0.875rem;
    }

    .meta-label {
      color: var(--gray-600);
    }

    .meta-value {
      font-weight: 600;
      color: var(--dark);
    }

    /* Reviews Section */
    .reviews-section {
      background: var(--white);
      border-radius: 1rem;
      padding: 2rem;
      box-shadow: var(--shadow);
      margin-bottom: 3rem;
    }

    .section-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 1.5rem;
    }

    .reviews-summary {
      display: grid;
      grid-template-columns: auto 1fr;
      gap: 2rem;
      padding: 1.5rem;
      background: var(--gray-50);
      border-radius: 0.75rem;
      margin-bottom: 2rem;
    }

    .rating-overview {
      text-align: center;
    }

    .rating-score {
      font-size: 3rem;
      font-weight: 700;
      color: var(--dark);
      line-height: 1;
    }

    .rating-stars-large {
      display: flex;
      gap: 0.25rem;
      color: #fbbf24;
      font-size: 1.25rem;
      justify-content: center;
      margin: 0.5rem 0;
    }

    .rating-count {
      color: var(--gray-600);
      font-size: 0.875rem;
    }

    .reviews-list {
      display: grid;
      gap: 1.5rem;
    }

    .review-card {
      padding: 1.5rem;
      border: 1px solid var(--gray-200);
      border-radius: 0.75rem;
      transition: all 0.3s;
    }

    .review-card:hover {
      box-shadow: var(--shadow-md);
    }

    .review-header {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .review-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--white);
      font-weight: 700;
      font-size: 1rem;
    }

    .review-author-info {
      flex: 1;
    }

    .review-author {
      font-weight: 600;
      color: var(--dark);
      margin-bottom: 0.25rem;
    }

    .review-date {
      font-size: 0.75rem;
      color: var(--gray-500);
    }

    .review-rating {
      display: flex;
      gap: 0.25rem;
      color: #fbbf24;
      font-size: 0.875rem;
    }

    .review-text {
      color: var(--gray-600);
      line-height: 1.7;
    }

    .no-reviews {
      text-align: center;
      padding: 3rem;
      color: var(--gray-500);
    }

    /* Related Products */
    .related-section {
      margin-bottom: 3rem;
    }

    .related-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--dark);
      margin-bottom: 1.5rem;
    }

    .products-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1.5rem;
    }

    .product-card {
      background: var(--white);
      border: 1px solid var(--gray-200);
      border-radius: 0.75rem;
      overflow: hidden;
      transition: all 0.3s;
    }

    .product-card:hover {
      box-shadow: var(--shadow-lg);
      transform: translateY(-4px);
    }

    .product-image-wrapper {
      position: relative;
      overflow: hidden;
      background: var(--gray-50);
      aspect-ratio: 1;
    }

    .product-link {
      display: block;
      height: 100%;
    }

    .product-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: all 0.3s;
    }

    .product-image.secondary {
      position: absolute;
      top: 0;
      left: 0;
      opacity: 0;
    }

    .product-card:hover .product-image.primary {
      opacity: 0;
    }

    .product-card:hover .product-image.secondary {
      opacity: 1;
    }

    .product-card:hover .product-image {
      transform: scale(1.05);
    }

    .product-badge-card {
      position: absolute;
      top: 0.75rem;
      left: 0.75rem;
      padding: 0.375rem 0.75rem;
      background: var(--danger);
      color: var(--white);
      border-radius: 0.375rem;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .product-actions {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      display: flex;
      gap: 0.5rem;
      opacity: 0;
      transition: opacity 0.3s;
    }

    .product-card:hover .product-actions {
      opacity: 1;
    }

    .action-btn {
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--primary-light);
      border: none;
      border-radius: 50%;
      color: var(--dark);
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
    }

    .action-btn:hover {
      background: var(--primary);
      color: var(--white);
      transform: scale(1.1);
    }

    .product-card-info {
      padding: 1rem;
    }

    .product-category {
      font-size: 0.75rem;
      color: var(--gray-500);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 0.5rem;
    }

    .product-name {
      font-size: 0.875rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
      line-height: 1.4;
    }

    .product-name a {
      color: var(--dark);
      text-decoration: none;
      transition: color 0.3s;
    }

    .product-name a:hover {
      color: var(--primary);
    }

    .product-rating-small {
      display: flex;
      gap: 0.125rem;
      color: #fbbf24;
      font-size: 0.75rem;
      margin-bottom: 0.75rem;
    }

    .product-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .product-price {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--dark);
    }

    .add-cart-btn {
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--primary-light);
      border: none;
      border-radius: 50%;
      color: var(--primary);
      cursor: pointer;
      transition: all 0.3s;
    }

    .add-cart-btn:hover:not(:disabled) {
      background: var(--primary);
      color: var(--white);
      transform: scale(1.1);
    }

    .add-cart-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* Responsive */
    @media (max-width: 1024px) {
      .product-section {
        grid-template-columns: 1fr;
      }

      .gallery {
        position: static;
      }

      .products-grid {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    @media (max-width: 768px) {
      .nav__list {
        display: none;
      }

      .product-features {
        grid-template-columns: 1fr;
      }

      .action-buttons {
        grid-template-columns: 1fr;
      }

      .products-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .product-title {
        font-size: 1.5rem;
      }

      .current-price {
        font-size: 2rem;
      }
    }

    @media (max-width: 640px) {
      .gallery__thumbs {
        grid-template-columns: repeat(3, 1fr);
      }

      .reviews-summary {
        grid-template-columns: 1fr;
      }

      .products-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <!-- Header -->
  <header class="header">
    <div class="header__top">
      <div class="header__container">
        <div class="header__contact">
          <span>(+01) - 2345 - 6789</span>
          <span>Our location</span>
        </div>
        <p class="header__alert-news">Super Values Deals - Save more coupons</p>
        <div>
          <?php if (is_logged_in()): ?>
            <div class="header__user-profile">
              <button class="avatar-btn">
                <span class="avatar-circle"><?= htmlspecialchars(user_initials($_SESSION['member']['username'])) ?></span>
              </button>
              <div class="profile-menu">
                <div class="profile-menu__header">
                  <strong><?= htmlspecialchars($_SESSION['member']['username']) ?></strong><br>
                  <small><?= htmlspecialchars($_SESSION['member']['email']) ?></small>
                </div>
                <a href="/accounts.php">My Account</a>
                <a href="/orders.php">My Orders</a>
                <form action="/pages/auth/logout.php" method="post">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <button type="submit">Log Out</button>
                </form>
              </div>
            </div>
          <?php else: ?>
            <a href="/pages/auth/login.php" class="header__top-action">Log In</a>
            <a href="/pages/auth/register.php" class="header__top-action">Register</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <nav class="nav">
      <a href="/index.php" class="nav__logo">
        <img class="nav__logo-img" src="/assets/img/logo.jpg" alt="website logo" />
      </a>
      <div class="nav__menu">
        <ul class="nav__list">
          <li><a href="/index.php" class="nav__link">Home</a></li>
          <li><a href="/pages/product/product_list.php" class="nav__link active-link">Product</a></li>
          <li><a href="/accounts.php" class="nav__link">My Account</a></li>
          <li><a href="/compare.php" class="nav__link">Compare</a></li>
        </ul>
        <div class="header__search">
          <input type="text" placeholder="Search For Items..." class="form__input" />
          <button class="search__btn">
            <img src="/assets/img/search.png" alt="search icon" />
          </button>
        </div>
      </div>
      <div class="header__user-actions">
        <a href="/pages/wishlist/wishlist.php" class="header__action-btn" title="Wishlist">
          <img src="/assets/img/icon-heart.svg" alt="Wishlist" />
          <span class="count wishlist-count">0</span>
        </a>
        <a href="/pages/cart/cart.php" class="header__action-btn" title="Cart">
          <img src="/assets/img/icon-cart.svg" alt="Cart" />
          <span class="count cart-count">0</span>
        </a>
      </div>
    </nav>
  </header>



  <!-- Main Content -->
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
                <i class="fi fi-ss-star"></i>
              <?php elseif ($i - 0.5 <= $average_rating): ?>
                <i class="fi fi-rs-star-half"></i>
              <?php else: ?>
                <i class="fi fi-rs-star"></i>
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
              <i class="fi fi-rs-check-circle"></i> In Stock
            </span>
          <?php else: ?>
            <span class="stock-badge out-of-stock">
              <i class="fi fi-rs-cross-circle"></i> Out of Stock
            </span>
          <?php endif; ?>
        </div>

        <p class="product-description">
          <?= nl2br(htmlspecialchars($product['description'])) ?>
        </p>

        <div class="product-features">
          <div class="feature-item">
            <div class="feature-icon">
              <i class="fi fi-rs-box"></i>
            </div>
            <div class="feature-text">
              <strong>Stock Available</strong>
              <?= $product['stock'] ?> units in stock
            </div>
          </div>
          <div class="feature-item">
            <div class="feature-icon">
              <i class="fi fi-rs-truck-side"></i>
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
              <i class="fi fi-rs-minus-small"></i>
            </button>
            <input type="number" class="quantity-input" id="quantity" value="1" min="1" max="<?= $product['stock'] ?>" />
            <button class="qty-btn qty-plus" type="button" onclick="increaseQty()">
              <i class="fi fi-rs-plus-small"></i>
            </button>
          </div>
        </div>

        <div class="action-buttons">
          <button class="btn btn-primary" id="addToCartBtn" <?= ($product['status'] != 'Available' || $product['stock'] <= 0) ? 'disabled' : '' ?>>
            <i class="fi fi-rs-shopping-cart-add"></i>
            Add to Cart
          </button>
          <button class="btn btn-secondary" id="buyNowBtn" <?= ($product['status'] != 'Available' || $product['stock'] <= 0) ? 'disabled' : '' ?>>
            <i class="fi fi-rs-shopping-bag"></i>
            Buy Now
          </button>
          <button class="btn btn-icon" id="addToWishlistBtn" title="Add to Wishlist">
            <i class="fi fi-rs-heart"></i>
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
            <span class="meta-value"><?= htmlspecialchars($product['status']) ?></span>
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
                  <i class="fi fi-ss-star"></i>
                <?php elseif ($i - 0.5 <= $average_rating): ?>
                  <i class="fi fi-rs-star-half"></i>
                <?php else: ?>
                  <i class="fi fi-rs-star"></i>
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
                      <i class="fi fi-ss-star"></i>
                    <?php else: ?>
                      <i class="fi fi-rs-star"></i>
                    <?php endif; ?>
                  <?php endfor; ?>
                </div>
              </div>
              <p class="review-text"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
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
                    <i class="fi fi-rs-heart"></i>
                  </button>
                  <a href="product_details.php?id=<?= $related['product_id'] ?>" class="action-btn">
                    <i class="fi fi-rs-eye"></i>
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
                  <i class="fi fi-ss-star"></i>
                  <i class="fi fi-ss-star"></i>
                  <i class="fi fi-ss-star"></i>
                  <i class="fi fi-ss-star"></i>
                  <i class="fi fi-rs-star"></i>
                </div>

                <div class="product-footer">
                  <span class="product-price">RM <?= number_format($related['price'], 2) ?></span>
                  <button class="add-cart-btn" data-product-id="<?= $related['product_id'] ?>">
                    <i class="fi fi-rs-shopping-cart-add"></i>
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
    // Image gallery functionality
    function changeImage(src) {
      document.getElementById('mainImage').src = src;
    }

    // Quantity controls
    function increaseQty() {
      const input = document.getElementById('quantity');
      const max = parseInt(input.max);
      const current = parseInt(input.value);
      if (current < max) {
        input.value = current + 1;
      }
    }

    function decreaseQty() {
      const input = document.getElementById('quantity');
      const min = parseInt(input.min);
      const current = parseInt(input.value);
      if (current > min) {
        input.value = current - 1;
      }
    }

    // Validate quantity input on manual change
    document.getElementById('quantity')?.addEventListener('input', function() {
      const max = parseInt(this.max);
      const min = parseInt(this.min);
      let value = parseInt(this.value);

      if (isNaN(value) || value < min) {
        this.value = min;
      } else if (value > max) {
        this.value = max;
        showToast('Maximum available stock is ' + max + ' units', 'error');
      }
    });

    // Profile menu toggle
    document.querySelector('.avatar-btn')?.addEventListener('click', function(e) {
      e.stopPropagation();
      const menu = this.nextElementSibling;
      menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    });

    document.addEventListener('click', function(e) {
      const menu = document.querySelector('.profile-menu');
      if (menu && !e.target.closest('.header__user-profile')) {
        menu.style.display = 'none';
      }
    });

    // Add to Cart functionality
    $(document).ready(function() {
      // Load cart count on page load
      loadCartCount();

      $('#addToCartBtn').on('click', function() {
        const $btn = $(this);
        if ($btn.is(':disabled')) return;

        const productId = <?= $product_id ?>;
        const quantity = parseInt($('#quantity').val());
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<i class="fi fi-rs-spinner"></i> Adding...');

        $.ajax({
          url: '../cart/cart_actions.php',
          method: 'POST',
          data: {
            action: 'add',
            product_id: productId,
            quantity: quantity
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              showToast('Product added to cart!', 'success');
              if (response.cart_count !== undefined) {
                $('.count.cart-count').text(response.cart_count);
              }
              $btn.html('<i class="fi fi-rs-check"></i> Added!');
              setTimeout(() => {
                $btn.html(originalHtml).prop('disabled', false);
              }, 1500);
            } else {
              showToast(response.message || 'Failed to add to cart', 'error');
              if (response.redirect) {
                setTimeout(() => window.location.href = response.redirect, 1500);
              }
              $btn.html(originalHtml).prop('disabled', false);
            }
          },
          error: function() {
            showToast('An error occurred. Please try again.', 'error');
            $btn.html(originalHtml).prop('disabled', false);
          }
        });
      });

      // Add to wishlist functionality
      $('#addToWishlistBtn').on('click', function() {
        const $btn = $(this);
        if ($btn.is(':disabled')) return;

        const productId = <?= $product_id ?>;
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<i class="fi fi-rs-spinner"></i>');

        $.ajax({
          url: '../wishlist/wishlist_actions.php',
          method: 'POST',
          data: {
            action: 'add',
            product_id: productId
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              showToast('Product added to wishlist!', 'success');
              if (response.wishlist_count !== undefined) {
                $('.count:not(.cart-count)').text(response.wishlist_count);
              }
              $btn.html('<i class="fi fi-ss-heart"></i>');
              setTimeout(() => {
                $btn.html(originalHtml).prop('disabled', false);
              }, 1500);
            } else {
              showToast(response.message || 'Failed to add to wishlist', 'error');
              if (response.redirect) {
                setTimeout(() => window.location.href = response.redirect, 1500);
              }
              $btn.html(originalHtml).prop('disabled', false);
            }
          },
          error: function() {
            showToast('An error occurred. Please try again.', 'error');
            $btn.html(originalHtml).prop('disabled', false);
          }
        });
      });

      // Buy Now functionality - Go directly to checkout without affecting cart
      $('#buyNowBtn').on('click', function(e) {
        e.preventDefault();

        const $btn = $(this);
        if ($btn.is(':disabled')) return;

        const productId = <?= $product_id ?>;
        const quantity = parseInt($('#quantity').val());
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<i class="fi fi-rs-spinner"></i> Processing...');

        $.ajax({
          url: '../cart/cart_actions.php',
          method: 'POST',
          data: {
            action: 'buy_now',
            product_id: productId,
            quantity: quantity
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              // Redirect to checkout page
              window.location.href = '../checkout/checkout.php';
            } else {
              showToast(response.message || 'Failed to process. Please try again.', 'error');
              if (response.redirect) {
                setTimeout(() => window.location.href = response.redirect, 1500);
              } else {
                $btn.html(originalHtml).prop('disabled', false);
              }
            }
          },
          error: function() {
            showToast('An error occurred. Please try again.', 'error');
            $btn.html(originalHtml).prop('disabled', false);
          }
        });
      });

      // Add to cart for related products
      $(document).on('click', '.add-cart-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        if ($btn.is(':disabled')) return;

        const productId = $btn.data('product-id');
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<i class="fi fi-rs-spinner"></i>');

        $.ajax({
          url: '../cart/cart_actions.php',
          method: 'POST',
          data: {
            action: 'add',
            product_id: productId,
            quantity: 1
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              showToast('Product added to cart!', 'success');
              if (response.cart_count !== undefined) {
                $('.count.cart-count').text(response.cart_count);
              }
              $btn.html('<i class="fi fi-rs-check"></i>');
              setTimeout(() => {
                $btn.html(originalHtml).prop('disabled', false);
              }, 1000);
            } else {
              showToast(response.message || 'Failed to add to cart', 'error');
              if (response.redirect) {
                setTimeout(() => window.location.href = response.redirect, 1500);
              }
              $btn.html(originalHtml).prop('disabled', false);
            }
          },
          error: function() {
            showToast('An error occurred. Please try again.', 'error');
            $btn.html(originalHtml).prop('disabled', false);
          }
        });
      });

      // Add to wishlist for related products
      $(document).on('click', '.wishlist-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const productId = $btn.data('product-id');
        const originalHtml = $btn.html();

        $btn.html('<i class="fi fi-rs-spinner"></i>');

        $.ajax({
          url: '../wishlist/wishlist_actions.php',
          method: 'POST',
          data: {
            action: 'add',
            product_id: productId
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              showToast('Product added to wishlist!', 'success');
              if (response.wishlist_count !== undefined) {
                $('.count:not(.cart-count)').text(response.wishlist_count);
              }
              $btn.html('<i class="fi fi-ss-heart"></i>');
              setTimeout(() => {
                $btn.html(originalHtml);
              }, 1000);
            } else {
              showToast(response.message || 'Failed to add to wishlist', 'error');
              if (response.redirect) {
                setTimeout(() => window.location.href = response.redirect, 1500);
              }
              $btn.html(originalHtml);
            }
          },
          error: function() {
            showToast('An error occurred. Please try again.', 'error');
            $btn.html(originalHtml);
          }
        });
      });

      // Load cart count
      function loadCartCount() {
        $.ajax({
          url: '../cart/get_cart_count.php',
          method: 'GET',
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              $('.count.cart-count').text(response.cart_count || 0);
              $('.count:not(.cart-count)').text(response.wishlist_count || 0);
            }
          },
          error: function() {
            // Silently fail
          }
        });
      }

      // Toast notification - centered with transparent background
      function showToast(message, type = 'success') {
        $('.toast').remove();

        const iconMap = {
          success: 'fi-rs-check-circle',
          error: 'fi-rs-cross-circle',
          info: 'fi-rs-info'
        };

        const $toast = $(`
          <div class="toast toast--${type}" style="
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            padding: 1.25rem 2rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
            z-index: 10000;
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            min-width: 300px;
            max-width: 90%;
            pointer-events: none;
            border: 2px solid ${type === 'success' ? '#10b981' : '#ef4444'};
          ">
            <i class="fi ${iconMap[type]}" style="font-size: 1.5rem; flex-shrink: 0; color: ${type === 'success' ? '#10b981' : '#ef4444'};"></i>
            <span style="font-size: 1rem; font-weight: 600; color: #1f2937;">${escapeHtml(message)}</span>
          </div>
        `);

        $('body').append($toast);
        setTimeout(() => $toast.css({
          opacity: 1,
          transform: 'translate(-50%, -50%) scale(1)'
        }), 10);

        setTimeout(() => {
          $toast.css({
            opacity: 0,
            transform: 'translate(-50%, -50%) scale(0.8)'
          });
          setTimeout(() => $toast.remove(), 300);
        }, 2500);
      }

      // Escape HTML to prevent XSS
      function escapeHtml(text) {
        const map = {
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
      }
    });
  </script>
</body>

</html>
<?php 
session_start();
$title = "Products";
require_once 'helper/pdo_connection.php';
require_once __DIR__ . '/helper/auth.php';

function user_initials($name) {
  $parts = preg_split('/\s+/', trim((string)$name));
  $first = mb_substr($parts[0] ?? '', 0, 1);
  $second = mb_substr($parts[1] ?? '', 0, 1);
  return mb_strtoupper($first . $second ?: $first);
}

// Get categories for filter
$cat_query = "SELECT DISTINCT category FROM Product WHERE category IS NOT NULL AND category != '' ORDER BY category";
$categories = $pdo->query($cat_query)->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.0.0/uicons-regular-straight/css/uicons-regular-straight.css" />
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.0.0/uicons-solid-straight/css/uicons-solid-straight.css" />
  <link rel="stylesheet" href="assets/css/product_list.css" />
  <title><?= htmlspecialchars($title) ?></title>
</head>
<body>
  <!-- ========== HEADER ========== -->
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
              <button class="avatar-btn" aria-haspopup="true" aria-expanded="false">
                <span class="avatar-circle"><?= htmlspecialchars(user_initials($_SESSION['member']['name'])) ?></span>
              </button>
              <div class="profile-menu">
                <div class="profile-menu__header">
                  <strong><?= htmlspecialchars($_SESSION['member']['name']) ?></strong><br>
                  <small><?= htmlspecialchars($_SESSION['member']['email']) ?></small>
                </div>
                <a href="/accounts.php">My Account</a>
                <a href="/orders.php">My Orders</a>
                <form action="/auth/logout.php" method="post">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <button type="submit" class="logout-btn">Log Out</button>
                </form>
              </div>
            </div>
          <?php else: ?>
            <a href="/auth/login.php" class="header__top-action">Log In</a>
            <a href="/auth/register.php" class="header__top-action">Register</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <nav class="nav container">
      <a href="/index.php" class="nav__logo">
        <img class="nav__logo-img" src="/assets/img/logo.jpg" alt="website logo" />
      </a>
      <div class="nav__menu" id="nav-menu">
        <ul class="nav__list">
          <li><a href="/index.php" class="nav__link">Home</a></li>
          <li><a href="/product_list.php" class="nav__link active-link">Product</a></li>
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
        <a href="/wishlist.php" class="header__action-btn" title="Wishlist">
          <img src="/assets/img/icon-heart.svg" alt="Wishlist" />
          <span class="count wishlist-count">0</span>
        </a>
        <a href="/cart.php" class="header__action-btn" title="Cart">
          <img src="/assets/img/icon-cart.svg" alt="Cart" />
          <span class="count cart-count">0</span>
        </a>
      </div>
    </nav>
  </header>

  <!-- ========== MAIN CONTENT ========== -->
  <main class="products-page">
    <div class="container">
      <!-- Page Header -->
      <div class="page-header">
        <div class="header-content">
          <h1 class="page-title">Discover Products</h1>
          <p class="page-subtitle">
            <span class="product-count" id="productCount">0</span> products available
          </p>
        </div>
        
        <div class="header-actions">
          <button class="mobile-filter-btn" id="mobileFilterBtn">
            <i class="fi fi-rs-settings-sliders"></i>
            <span>Filters</span>
          </button>
          
          <div class="sort-wrapper">
            <label for="sortSelect" class="sort-label">Sort by</label>
            <select id="sortSelect" class="sort-select">
              <option value="newest">Latest</option>
              <option value="price_low">Price: Low to High</option>
              <option value="price_high">Price: High to Low</option>
              <option value="name_asc">Name: A-Z</option>
              <option value="name_desc">Name: Z-A</option>
            </select>
          </div>
        </div>
      </div>

      <div class="products-layout">
        <!-- Filters Sidebar -->
        <aside class="filters-sidebar" id="filterSidebar">
          <div class="sidebar-header">
            <h2 class="sidebar-title">Filters</h2>
            <button class="close-sidebar" id="closeSidebar">
              <i class="fi fi-rs-cross"></i>
            </button>
          </div>

          <div class="filter-group">
            <label class="filter-label">Search</label>
            <div class="search-wrapper">
              <i class="fi fi-rs-search search-icon"></i>
              <input type="text" id="searchInput" class="search-input" placeholder="Search products...">
            </div>
          </div>

          <div class="filter-group">
            <label class="filter-label">Category</label>
            <select id="categorySelect" class="filter-select">
              <option value="">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>">
                  <?php echo htmlspecialchars($cat); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="filter-group">
            <label class="filter-label">Price Range</label>
            <div class="price-inputs">
              <input type="number" id="minPrice" class="price-input" placeholder="Min" min="0" step="0.01">
              <span class="price-separator">—</span>
              <input type="number" id="maxPrice" class="price-input" placeholder="Max" min="0" step="0.01">
            </div>
          </div>

          <div class="filter-actions">
            <button class="btn btn-primary btn-block" id="applyFilters">Apply Filters</button>
            <button class="btn btn-secondary btn-block" id="clearFilters">Clear All</button>
          </div>
        </aside>

        <!-- Products Content -->
        <div class="products-content">
          <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
          </div>

          <div class="products-grid" id="productsGrid">
            <!-- Products will be loaded here via AJAX -->
          </div>

          <!-- Pagination -->
          <nav class="pagination" id="pagination" style="display: none;">
            <!-- Pagination will be generated via AJAX -->
          </nav>
        </div>
      </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>
  </main>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="assets/js/product_list.js"></script>
</body>
</html>
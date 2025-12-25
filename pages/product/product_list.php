<?php
session_start();
$title = "Products";
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

// Get categories for filter
$cat_query = "SELECT DISTINCT category FROM Product WHERE category IS NOT NULL AND category != '' ORDER BY category";
$categories = $pdo->query($cat_query)->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="../../assets/css/product_list.css" />
  <link rel="stylesheet" href="/assets/css/styles.css">
  <title><?= htmlspecialchars($title) ?></title>
</head>

<body>

  <?php include '../../partials/header.php'; ?>

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
            <span>⚙</span>
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
              <span>×</span>
            </button>
          </div>

          <div class="filter-group">
            <label class="filter-label">Search</label>
            <div class="search-wrapper">
              <img src="../../assets/img/search.png" alt="Search" class="search-icon" style="width: 16px; height: 16px;">
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
              <input type="number" id="minPrice" class="price-input" placeholder="Min" min="0" step="1">
              <span class="price-separator">—</span>
              <input type="number" id="maxPrice" class="price-input" placeholder="Max" min="0" step="1">
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
  <script src="../../assets/js/product_list.js"></script>
  <?php include '../../partials/footer.php'; ?>

</body>

</html>
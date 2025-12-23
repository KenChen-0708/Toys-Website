/**
 * Product List Page JavaScript
 * Extracted from product_list.php
 */

$(document).ready(function () {
  let currentPage = 1;
  let isLoading = false;

  // Check URL for category parameter and set filter
  const urlParams = new URLSearchParams(window.location.search);
  const categoryParam = urlParams.get('category');
  if (categoryParam) {
    $('#categorySelect').val(decodeURIComponent(categoryParam));
  }

  // Initialize - load products and cart count on page load
  loadProducts();
  // Use global loadHeaderCounts if available (from header-counts.js), otherwise use local fallback
  if (typeof window.loadHeaderCounts === 'function') {
    window.loadHeaderCounts();
  } else {
    loadCartCount();
  }

  // Mobile filter toggle
  $('#mobileFilterBtn').on('click', function () {
    $('#filterSidebar').addClass('active');
    $('#sidebarOverlay').addClass('active');
    $('body').css('overflow', 'hidden');
  });

  // Close sidebar
  $('#closeSidebar, #sidebarOverlay').on('click', function () {
    closeMobileSidebar();
  });

  // Apply filters
  $('#applyFilters').on('click', function () {
    currentPage = 1;
    loadProducts();
    closeMobileSidebar();
  });

  // Clear filters
  $('#clearFilters').on('click', function () {
    $('#searchInput').val('');
    $('#categorySelect').val('');
    $('#minPrice').val('');
    $('#maxPrice').val('');
    $('#sortSelect').val('newest');
    currentPage = 1;
    loadProducts();
    closeMobileSidebar();
  });

  // Sort change
  $('#sortSelect').on('change', function () {
    currentPage = 1;
    loadProducts();
  });

  // Search on Enter key
  $('#searchInput').on('keypress', function (e) {
    if (e.which === 13) {
      currentPage = 1;
      loadProducts();
      closeMobileSidebar();
    }
  });

  // Price input validation - prevent negative values
  $('#minPrice, #maxPrice').on('input', function() {
    let value = parseFloat($(this).val());
    if (value < 0) {
      $(this).val('');
    }
  });

  // Price input validation on blur - ensure min <= max
  $('#minPrice, #maxPrice').on('blur', function() {
    validatePriceRange();
  });

  // Prevent negative values on keypress
  $('#minPrice, #maxPrice').on('keypress', function(e) {
    // Allow: backspace, delete, tab, escape, enter
    if ([46, 8, 9, 27, 13].indexOf(e.keyCode) !== -1 ||
        // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
        (e.keyCode === 65 && e.ctrlKey === true) ||
        (e.keyCode === 67 && e.ctrlKey === true) ||
        (e.keyCode === 86 && e.ctrlKey === true) ||
        (e.keyCode === 88 && e.ctrlKey === true) ||
        // Allow: home, end, left, right
        (e.keyCode >= 35 && e.keyCode <= 39)) {
      return;
    }
    // Ensure that it is a number and stop the keypress
    if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105) && e.keyCode !== 190) {
      e.preventDefault();
    }
    // Prevent minus sign
    if (e.keyCode === 189 || e.keyCode === 109) {
      e.preventDefault();
    }
  });

  // Pagination clicks (delegated)
  $(document).on('click', '.pagination-btn, .pagination-number', function (e) {
    e.preventDefault();
    const page = $(this).data('page');
    if (page && !$(this).hasClass('active') && !isLoading) {
      currentPage = page;
      loadProducts();
      smoothScrollToTop();
    }
  });

  // Add to cart (delegated)
  $(document).on('click', '.add-to-cart-btn', function (e) {
    e.preventDefault();
    const $btn = $(this);
    if ($btn.is(':disabled')) return;

    const productId = $(this).data('product-id');
    addToCart(productId, $btn);
  });

  // Add to wishlist (delegated)
  $(document).on('click', '.wishlist-btn', function (e) {
    e.preventDefault();
    const productId = $(this).data('product-id');
    const $btn = $(this);
    addToWishlist(productId, $btn);
  });



  // Reset filters button (delegated - for dynamically created button in empty state)
  $(document).on('click', '#resetFilters', function (e) {
    e.preventDefault();
    $('#searchInput').val('');
    $('#categorySelect').val('');
    $('#minPrice').val('');
    $('#maxPrice').val('');
    $('#sortSelect').val('newest');
    currentPage = 1;
    loadProducts();
    closeMobileSidebar();
  });

  /**
   * Validate price range inputs
   */
  function validatePriceRange() {
    const minPrice = parseFloat($('#minPrice').val()) || 0;
    const maxPrice = parseFloat($('#maxPrice').val()) || 0;
    
    // If both values are set and min > max, swap them
    if (minPrice > 0 && maxPrice > 0 && minPrice > maxPrice) {
      $('#minPrice').val(maxPrice);
      $('#maxPrice').val(minPrice);
    }
  }

  /**
   * Close mobile sidebar
   */
  function closeMobileSidebar() {
    $('#filterSidebar').removeClass('active');
    $('#sidebarOverlay').removeClass('active');
    $('body').css('overflow', '');
  }

  /**
   * Load products via AJAX
   */
  function loadProducts() {
    if (isLoading) return;
    isLoading = true;

    const params = {
      page: currentPage,
      search: $('#searchInput').val(),
      category: $('#categorySelect').val(),
      min_price: $('#minPrice').val(),
      max_price: $('#maxPrice').val(),
      sort: $('#sortSelect').val(),
      ajax: 1
    };

    // Show loading overlay
    $('#loadingOverlay').addClass('active');

    $.ajax({
      url: 'load_products.php',
      method: 'GET',
      data: params,
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          renderProducts(response.products);
          renderPagination(response.pagination);
          $('#productCount').text(response.total);

          // Update URL without reload
          updateURL(params);
        } else {
          console.error('Failed to load products:', response.message);
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        console.error('AJAX error:', textStatus, errorThrown);
      },
      complete: function () {
        isLoading = false;
        $('#loadingOverlay').removeClass('active');
      }
    });
  }

  /**
   * Render products in the grid
   */
  function renderProducts(products) {
    const $grid = $('#productsGrid');
    $grid.empty();

    if (!products || products.length === 0) {
      $grid.html(`
        <div class="no-products">
          <p>No products found matching your criteria.</p>
          <button id="resetFilters" class="btn btn-primary">Reset Filters</button>
        </div>
      `);
      return;
    }

    products.forEach(product => {
      const ratingStars = product.avg_rating ? '⭐'.repeat(Math.round(product.avg_rating)) : '';
      const productCard = `
        <div class="product-card">
          <div class="product-image-wrapper">
            <img src="${product.main_image_path || '/assets/img/placeholder.jpg'}" alt="${product.name}" class="product-image">
            <div class="product-overlay">
              <button class="add-to-cart-btn" data-product-id="${product.product_id}">Add to Cart</button>
              <button class="wishlist-btn" data-product-id="${product.product_id}">♥ Wishlist</button>
            </div>
          </div>
          <div class="product-info">
            <h3 class="product-name">${product.name}</h3>
            ${product.avg_rating ? `<div class="product-rating">${ratingStars} ${product.avg_rating.toFixed(1)} (${product.review_count})</div>` : ''}
            <div class="product-price">$${parseFloat(product.regular_price).toFixed(2)}</div>
            <a href="product_details.php?id=${product.product_id}" class="view-details">View Details</a>
          </div>
        </div>
      `;
      $grid.append(productCard);
    });
  }

  /**
   * Render pagination controls
   */
  function renderPagination(pagination) {
    const $pagination = $('#pagination');
    $pagination.empty();

    if (!pagination || pagination.total_pages <= 1) {
      $pagination.hide();
      return;
    }

    $pagination.show();

    // Previous button
    if (pagination.current_page > 1) {
      $pagination.append(`<button class="pagination-btn" data-page="${pagination.current_page - 1}">← Previous</button>`);
    }

    // Page numbers
    for (let i = 1; i <= pagination.total_pages; i++) {
      const activeClass = i === pagination.current_page ? 'active' : '';
      $pagination.append(`<button class="pagination-number ${activeClass}" data-page="${i}">${i}</button>`);
    }

    // Next button
    if (pagination.current_page < pagination.total_pages) {
      $pagination.append(`<button class="pagination-btn" data-page="${pagination.current_page + 1}">Next →</button>`);
    }
  }

  /**
   * Update URL without page reload
   */
  function updateURL(params) {
    const queryString = new URLSearchParams();
    if (params.search) queryString.append('search', params.search);
    if (params.category) queryString.append('category', params.category);
    if (params.min_price) queryString.append('min_price', params.min_price);
    if (params.max_price) queryString.append('max_price', params.max_price);
    if (params.sort !== 'newest') queryString.append('sort', params.sort);
    if (params.page > 1) queryString.append('page', params.page);

    const newURL = window.location.pathname + (queryString.toString() ? '?' + queryString.toString() : '');
    window.history.replaceState({ path: newURL }, '', newURL);
  }

  /**
   * Smooth scroll to top of products
   */
  function smoothScrollToTop() {
    $('html, body').animate({ scrollTop: $('#productsGrid').offset().top - 100 }, 300);
  }

  /**
   * Add product to cart via AJAX
   */
  function addToCart(productId, $btn) {
    const originalText = $btn.text();
    $btn.prop('disabled', true).text('Adding...');

    $.ajax({
      url: '../../pages/cart/cart_actions.php',
      method: 'POST',
      data: {
        action: 'add',
        product_id: productId,
        quantity: 1
      },
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          $btn.text('✓ Added').removeClass('add-to-cart-btn').addClass('added-btn');
          if (typeof window.loadHeaderCounts === 'function') {
            window.loadHeaderCounts();
          }
          setTimeout(() => {
            $btn.text(originalText).addClass('add-to-cart-btn').removeClass('added-btn').prop('disabled', false);
          }, 1500);
        } else {
          alert(response.message || 'Error adding to cart');
          $btn.prop('disabled', false).text(originalText);
        }
      },
      error: function () {
        alert('Error adding to cart');
        $btn.prop('disabled', false).text(originalText);
      }
    });
  }

  /**
   * Add product to wishlist via AJAX
   */
  function addToWishlist(productId, $btn) {
    $.ajax({
      url: '../../pages/wishlist/wishlist_actions.php',
      method: 'POST',
      data: {
        action: 'toggle',
        product_id: productId
      },
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          $btn.toggleClass('in-wishlist', response.in_wishlist);
          $btn.text(response.in_wishlist ? '♥ In Wishlist' : '♡ Add to Wishlist');
        } else {
          alert(response.message || 'Error updating wishlist');
        }
      },
      error: function () {
        alert('Error updating wishlist');
      }
    });
  }

  /**
   * Load cart count via AJAX
   */
  function loadCartCount() {
    $.ajax({
      url: '../../pages/cart/get_cart_count.php',
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          $('#cartCount').text(response.count);
        }
      },
      error: function () {
        console.error('Error loading cart count');
      }
    });
  }
});

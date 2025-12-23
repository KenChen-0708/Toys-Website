/**
 * Product List Page JavaScript
 * Extracted from product_list.php
 */

$(document).ready(function () {
  let currentPage = 1;
  let isLoading = false;

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
          showToast(response.message || 'Failed to load products', 'error');
        }
      },
      error: function () {
        showToast('An error occurred while loading products', 'error');
      },
      complete: function () {
        $('#loadingOverlay').removeClass('active');
        isLoading = false;
      }
    });
  }

  /**
   * Render products
   */
  function renderProducts(products) {
    const $grid = $('#productsGrid');

    if (!products || products.length === 0) {
      $grid.html(`
        <div class="empty-state" style="grid-column: 1 / -1;">
          <div class="empty-icon">
            <i class="fi fi-rs-box-open"></i>
          </div>
          <h3 class="empty-title">No Products Found</h3>
          <p class="empty-text">We couldn't find any products matching your criteria. Try adjusting your filters.</p>
          <button class="btn btn-primary" id="resetFilters">Reset Filters</button>
        </div>
      `);
      return;
    }

    let html = '';
    products.forEach(product => {
      const hoverImage = product.first_sub_image || product.main_image_path;
      const averageRating = parseFloat(product.average_rating) || 0;
      const reviewCount = parseInt(product.review_count) || 0;

      // Generate dynamic star rating
      let starsHtml = '';
      for (let i = 1; i <= 5; i++) {
        if (i <= Math.floor(averageRating)) {
          starsHtml += '<i class="fi fi-ss-star"></i>';
        } else if (i - 0.5 <= averageRating) {
          starsHtml += '<i class="fi fi-rs-star-half"></i>';
        } else {
          starsHtml += '<i class="fi fi-rs-star"></i>';
        }
      }

      html += `
        <article class="product-card">
          <div class="product-image-wrapper">
            <a href="product_details.php?id=${product.product_id}" class="product-link">
              <img src="../../${escapeHtml(product.main_image_path)}" 
                   alt="${escapeHtml(product.name)}" 
                   class="product-image primary" 
                   loading="lazy" />
              <img src="../../${escapeHtml(hoverImage)}" 
                   alt="${escapeHtml(product.name)}" 
                   class="product-image secondary" 
                   loading="lazy" />
            </a>
            
            <div class="product-actions">
              <button class="action-btn wishlist-btn" data-product-id="${product.product_id}" aria-label="Add to Wishlist">
                <i class="fi fi-rs-heart"></i>
              </button>
              <a href="product_details.php?id=${product.product_id}" class="action-btn view-btn" aria-label="Quick View">
                <i class="fi fi-rs-eye"></i>
              </a>
            </div>
          </div>
          
          <div class="product-info">
            <span class="product-category">${escapeHtml(product.category)}</span>
            <h3 class="product-name">
              <a href="product_details.php?id=${product.product_id}">
                ${escapeHtml(product.name)}
              </a>
            </h3>
            
            <div class="product-rating">
              ${starsHtml}
              ${reviewCount > 0 ? `<span class="rating-count">(${reviewCount})</span>` : ''}
            </div>
            
            <div class="product-footer">
              <span class="product-price">RM ${parseFloat(product.price).toFixed(2)}</span>
              <button class="add-to-cart-btn" data-product-id="${product.product_id}" aria-label="Add to Cart">
                <i class="fi fi-rs-shopping-cart-add"></i>
              </button>
            </div>
          </div>
        </article>
      `;
    });

    $grid.html(html);
  }

  /**
   * Render pagination
   */
  function renderPagination(pagination) {
    const $pagination = $('#pagination');

    if (!pagination || pagination.total_pages <= 1) {
      $pagination.hide();
      return;
    }

    let html = '';

    // Previous button
    if (pagination.current_page > 1) {
      html += `
        <a href="#" class="pagination-btn" data-page="${pagination.current_page - 1}">
          <i class="fi fi-rs-angle-left"></i>
        </a>
      `;
    }

    html += '<div class="pagination-numbers">';

    // First page
    if (pagination.start_page > 1) {
      html += `<a href="#" class="pagination-number" data-page="1">1</a>`;
      if (pagination.start_page > 2) {
        html += '<span class="pagination-ellipsis">...</span>';
      }
    }

    // Page numbers
    for (let i = pagination.start_page; i <= pagination.end_page; i++) {
      const activeClass = i === pagination.current_page ? 'active' : '';
      html += `<a href="#" class="pagination-number ${activeClass}" data-page="${i}">${i}</a>`;
    }

    // Last page
    if (pagination.end_page < pagination.total_pages) {
      if (pagination.end_page < pagination.total_pages - 1) {
        html += '<span class="pagination-ellipsis">...</span>';
      }
      html += `<a href="#" class="pagination-number" data-page="${pagination.total_pages}">${pagination.total_pages}</a>`;
    }

    html += '</div>';

    // Next button
    if (pagination.current_page < pagination.total_pages) {
      html += `
        <a href="#" class="pagination-btn" data-page="${pagination.current_page + 1}">
          <i class="fi fi-rs-angle-right"></i>
        </a>
      `;
    }

    $pagination.html(html).show();
  }

  /**
   * Update URL without reload
   */
  function updateURL(params) {
    const cleanParams = {};
    Object.keys(params).forEach(key => {
      if (params[key] && key !== 'ajax') {
        cleanParams[key] = params[key];
      }
    });

    const queryString = $.param(cleanParams);
    const newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
    window.history.pushState({ path: newUrl }, '', newUrl);
  }

  /**
   * Add product to cart
   */
  function addToCart(productId, $btn) {
    $btn.prop('disabled', true);
    const originalHtml = $btn.html();
    $btn.html('<i class="fi fi-rs-spinner"></i>');

    $.ajax({
      url: '../cart/cart_actions.php',
      method: 'POST',
      data: {
        action: 'add',
        product_id: productId,
        quantity: 1
      },
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          showToast('Product added to cart!', 'success');
          if (response.cart_count !== undefined) {
            updateCartCount(response.cart_count);
          }

          $btn.html('<i class="fi fi-rs-check"></i>');
          setTimeout(() => {
            $btn.html(originalHtml);
            $btn.prop('disabled', false);
          }, 1000);
        } else {
          showToast(response.message || 'Failed to add to cart', 'error');
          $btn.html(originalHtml);
          $btn.prop('disabled', false);
        }
      },
      error: function () {
        showToast('An error occurred. Please try again.', 'error');
        $btn.html(originalHtml);
        $btn.prop('disabled', false);
      }
    });
  }

  /**
   * Add product to wishlist
   */
  function addToWishlist(productId, $btn) {
    const isActive = $btn.hasClass('active');

    $.ajax({
      url: '../wishlist/wishlist_actions.php',
      method: 'POST',
      data: {
        action: isActive ? 'remove' : 'add',
        product_id: productId
      },
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          $btn.toggleClass('active');
          const message = isActive ? 'Removed from wishlist' : 'Added to wishlist!';
          showToast(message, 'success');
          if (response.wishlist_count !== undefined) {
            updateWishlistCount(response.wishlist_count);
          }
        } else {
          showToast(response.message || 'Failed to update wishlist', 'error');
        }
      },
      error: function () {
        showToast('An error occurred. Please try again.', 'error');
      }
    });
  }



  /**
   * Load cart count
   */
  function loadCartCount() {
    $.ajax({
      url: '../cart/get_cart_count.php',
      method: 'GET',
      dataType: 'json',
      success: function (response) {
        if (response.success) {
          $('.cart-count').text(response.cart_count || 0);
          $('.wishlist-count').text(response.wishlist_count || 0);
        }
      },
      error: function () {
        // Silently fail
      }
    });
  }

  /**
   * Update cart count
   * Uses global function from header-counts.js if available
   */
  function updateCartCount(count) {
    if (typeof window.updateCartCount === 'function') {
      window.updateCartCount(count);
    } else {
      // Fallback if header-counts.js is not loaded
      $('.cart-count').text(count).addClass('pulse');
      setTimeout(() => $('.cart-count').removeClass('pulse'), 500);
    }
  }

  /**
   * Update wishlist count
   * Uses global function from header-counts.js if available
   */
  function updateWishlistCount(count) {
    if (typeof window.updateWishlistCount === 'function') {
      window.updateWishlistCount(count);
    } else {
      // Fallback if header-counts.js is not loaded
      $('.wishlist-count').text(count).addClass('pulse');
      setTimeout(() => $('.wishlist-count').removeClass('pulse'), 500);
    }
  }

  /**
   * Smooth scroll to top
   */
  function smoothScrollToTop() {
    $('html, body').animate({ scrollTop: 0 }, 400, 'swing');
  }

  /**
   * Show toast notification
   */
  function showToast(message, type = 'success') {
    $('.toast').remove();

    const iconMap = {
      success: 'fi-rs-check-circle',
      error: 'fi-rs-cross-circle',
      info: 'fi-rs-info'
    };

    const $toast = $(`
      <div class="toast toast--${type}">
        <i class="fi ${iconMap[type]} toast-icon"></i>
        <span class="toast-message">${escapeHtml(message)}</span>
      </div>
    `);

    $('body').append($toast);
    setTimeout(() => $toast.addClass('show'), 10);

    setTimeout(() => {
      $toast.removeClass('show');
      setTimeout(() => $toast.remove(), 300);
    }, 2500);
  }

  /**
   * Escape HTML to prevent XSS
   */
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

  // Handle browser back/forward
  window.addEventListener('popstate', function () {
    location.reload();
  });

  // Profile menu toggle
  document.addEventListener('click', function (e) {
    const prof = e.target.closest('.header__user-profile');
    document.querySelectorAll('.header__user-profile .profile-menu').forEach(m => m.style.display = 'none');
    if (prof && e.target.closest('.avatar-btn')) {
      const menu = prof.querySelector('.profile-menu');
      menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }
  });
});

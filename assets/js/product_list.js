/**
 * Modern Product List - jQuery functionality
 */

$(document).ready(function() {
  
  // Mobile filter toggle
  $('#mobileFilterBtn').on('click', function() {
    $('#filterSidebar').addClass('active');
    $('#sidebarOverlay').addClass('active');
    $('body').css('overflow', 'hidden');
  });

  // Close sidebar
  $('#closeSidebar, #sidebarOverlay').on('click', function() {
    $('#filterSidebar').removeClass('active');
    $('#sidebarOverlay').removeClass('active');
    $('body').css('overflow', '');
  });

  // Apply filters
  $('#applyFilters').on('click', function() {
    applyFilters();
    closeMobileSidebar();
  });

  // Clear filters
  $('#clearFilters, #resetFilters').on('click', function() {
    clearFilters();
  });

  // Sort change
  $('#sortSelect').on('change', function() {
    applyFilters();
  });

  // Search on Enter key
  $('#searchInput').on('keypress', function(e) {
    if (e.which === 13) {
      applyFilters();
      closeMobileSidebar();
    }
  });

  // Pagination clicks
  $(document).on('click', '.pagination-btn, .pagination-number', function(e) {
    e.preventDefault();
    const page = $(this).data('page');
    if (page && !$(this).hasClass('active')) {
      loadProducts(page);
      smoothScrollToTop();
    }
  });

  // Add to cart
  $(document).on('click', '.add-to-cart-btn', function(e) {
    e.preventDefault();
    const $btn = $(this);
    if ($btn.is(':disabled')) return;
    
    const productId = $(this).data('product-id');
    addToCart(productId, $btn);
  });

  // Add to wishlist
  $(document).on('click', '.wishlist-btn', function(e) {
    e.preventDefault();
    const productId = $(this).data('product-id');
    const $btn = $(this);
    addToWishlist(productId, $btn);
  });

  // Add to compare
  $(document).on('click', '.compare-btn', function(e) {
    e.preventDefault();
    const productId = $(this).data('product-id');
    const $btn = $(this);
    addToCompare(productId, $btn);
  });

  /**
   * Close mobile sidebar
   */
  function closeMobileSidebar() {
    if (window.innerWidth <= 992) {
      $('#filterSidebar').removeClass('active');
      $('#sidebarOverlay').removeClass('active');
      $('body').css('overflow', '');
    }
  }

  /**
   * Apply filters and reload products
   */
  function applyFilters() {
    loadProducts(1);
  }

  /**
   * Clear all filters
   */
  function clearFilters() {
    $('#searchInput').val('');
    $('#categorySelect').val('');
    $('#minPrice').val('');
    $('#maxPrice').val('');
    $('#sortSelect').val('newest');
    applyFilters();
    closeMobileSidebar();
  }

  /**
   * Load products with new parameters
   */
  function loadProducts(page = 1) {
    const params = {
      page: page,
      search: $('#searchInput').val(),
      category: $('#categorySelect').val(),
      min_price: $('#minPrice').val(),
      max_price: $('#maxPrice').val(),
      sort: $('#sortSelect').val()
    };

    // Remove empty parameters
    Object.keys(params).forEach(key => {
      if (!params[key]) delete params[key];
    });

    // Build query string
    const queryString = $.param(params);
    
    // Update URL without reload
    const newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
    window.history.pushState({ path: newUrl }, '', newUrl);

    // Reload page with new parameters
    window.location.href = newUrl;
  }

  /**
   * Add product to cart
   */
  function addToCart(productId, $btn) {
    // Disable button and show loading state
    $btn.prop('disabled', true);
    const originalHtml = $btn.html();
    $btn.html('<i class="fi fi-rs-spinner"></i>');

    $.ajax({
      url: '/cart_actions.php',
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
          updateCartCount(response.cart_count);
          
          // Animate button
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
      error: function() {
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
      url: '/wishlist_actions.php',
      method: 'POST',
      data: {
        action: isActive ? 'remove' : 'add',
        product_id: productId
      },
      dataType: 'json',
      success: function(response) {
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
      error: function() {
        showToast('An error occurred. Please try again.', 'error');
      }
    });
  }

  /**
   * Add product to compare
   */
  function addToCompare(productId, $btn) {
    $.ajax({
      url: '/compare_actions.php',
      method: 'POST',
      data: {
        action: 'add',
        product_id: productId
      },
      dataType: 'json',
      success: function(response) {
        if (response.success) {
          $btn.addClass('active');
          showToast('Product added to compare!', 'success');
        } else {
          showToast(response.message || 'Failed to add to compare', 'error');
        }
      },
      error: function() {
        showToast('An error occurred. Please try again.', 'error');
      }
    });
  }

  /**
   * Update cart count in header
   */
  function updateCartCount(count) {
    const $cartCount = $('.header__action-btn[title="Cart"] .count, .cart-count');
    if ($cartCount.length) {
      $cartCount.text(count);
      
      // Animate the count
      $cartCount.addClass('pulse');
      setTimeout(() => $cartCount.removeClass('pulse'), 500);
    }
  }

  /**
   * Update wishlist count in header
   */
  function updateWishlistCount(count) {
    const $wishlistCount = $('.header__action-btn[title="Wishlist"] .count, .wishlist-count');
    if ($wishlistCount.length) {
      $wishlistCount.text(count);
      
      // Animate the count
      $wishlistCount.addClass('pulse');
      setTimeout(() => $wishlistCount.removeClass('pulse'), 500);
    }
  }

  /**
   * Smooth scroll to top
   */
  function smoothScrollToTop() {
    $('html, body').animate({ 
      scrollTop: 0 
    }, 400, 'swing');
  }

  /**
   * Show toast notification
   */
  function showToast(message, type = 'success') {
    // Remove existing toasts
    $('.toast').remove();

    // Create toast element
    const iconMap = {
      success: 'fi-rs-check-circle',
      error: 'fi-rs-cross-circle',
      info: 'fi-rs-info'
    };

    const $toast = $(`
      <div class="toast toast--${type}">
        <i class="fi ${iconMap[type]} toast-icon"></i>
        <span class="toast-message">${message}</span>
      </div>
    `);

    // Add to body
    $('body').append($toast);

    // Trigger animation
    setTimeout(() => $toast.addClass('show'), 10);

    // Auto remove after 3 seconds
    setTimeout(() => {
      $toast.removeClass('show');
      setTimeout(() => $toast.remove(), 300);
    }, 3000);
  }

  // Inject toast styles if not present
  if (!$('#toast-styles').length) {
    $(`
      <style id="toast-styles">
        .toast {
          position: fixed;
          top: 20px;
          right: 20px;
          display: flex;
          align-items: center;
          gap: 0.75rem;
          padding: 1rem 1.25rem;
          background: white;
          border-radius: 0.75rem;
          box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
          z-index: 10000;
          opacity: 0;
          transform: translateX(100px);
          transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
          min-width: 280px;
          max-width: 400px;
        }

        .toast.show {
          opacity: 1;
          transform: translateX(0);
        }

        .toast-icon {
          font-size: 1.25rem;
          flex-shrink: 0;
        }

        .toast-message {
          font-size: 0.875rem;
          font-weight: 500;
          color: #1f2937;
        }

        .toast--success {
          border-left: 4px solid #10b981;
        }

        .toast--success .toast-icon {
          color: #10b981;
        }

        .toast--error {
          border-left: 4px solid #ef4444;
        }

        .toast--error .toast-icon {
          color: #ef4444;
        }

        .toast--info {
          border-left: 4px solid #3b82f6;
        }

        .toast--info .toast-icon {
          color: #3b82f6;
        }

        /* Pulse animation for count badges */
        @keyframes pulse {
          0%, 100% { transform: scale(1); }
          50% { transform: scale(1.2); }
        }

        .pulse {
          animation: pulse 0.5s ease-in-out;
        }

        /* Responsive toast */
        @media screen and (max-width: 576px) {
          .toast {
            right: 10px;
            left: 10px;
            min-width: auto;
          }
        }
      </style>
    `).appendTo('head');
  }

  // Handle browser back/forward buttons
  window.addEventListener('popstate', function() {
    location.reload();
  });

});
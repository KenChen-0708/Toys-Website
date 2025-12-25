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

// Prevent non-numeric input in quantity field
document.getElementById('quantity')?.addEventListener('keypress', function(e) {
  // Allow: backspace, delete, tab, escape, enter
  if ([46, 8, 9, 27, 13].indexOf(e.keyCode) !== -1 ||
      // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
      (e.keyCode === 65 && e.ctrlKey === true) ||
      (e.keyCode === 67 && e.ctrlKey === true) ||
      (e.keyCode === 86 && e.ctrlKey === true) ||
      (e.keyCode === 88 && e.ctrlKey === true)) {
    return;
  }
  // Ensure that it is a number and stop the keypress
  if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
    e.preventDefault();
  }
});

// Handle paste events to prevent invalid data
document.getElementById('quantity')?.addEventListener('paste', function(e) {
  const input = this;
  setTimeout(function() {
    const value = parseInt(input.value);
    const min = parseInt(input.min);
    if (isNaN(value) || value < min) {
      input.value = min;
    }
  }, 1);
});

// Profile menu toggle
document.querySelector('.avatar-btn')?.addEventListener('click', function(e) {
  e.stopPropagation();
  const menu = this.nextElementSibling;
  menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
});

// Load header counts on page load
if (typeof window.loadHeaderCounts === 'function') {
  window.loadHeaderCounts();
}

// jQuery functionality
$(document).ready(function() {
  // Load cart count on page load
  loadCartCount();

  // Add to Cart functionality
  $('#addToCartBtn').on('click', function() {
    const $btn = $(this);
    if ($btn.is(':disabled')) return;

    const productId = $btn.data('product-id') || window.productId;
    const quantity = parseInt($('#quantity').val());
    const originalHtml = $btn.html();

    $btn.prop('disabled', true).html('<span class="spinner">⟳</span> Adding...');

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
          
          // Update header cart count
          if (typeof window.loadHeaderCounts === 'function') {
            window.loadHeaderCounts();
          }
          
          $btn.html('<span>✓</span> Added!');
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

    const productId = $btn.data('product-id') || window.productId;
    const originalHtml = $btn.html();

    $btn.prop('disabled', true).html('<span class="spinner">⟳</span>');

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
          
          // Update header wishlist count
          if (typeof window.loadHeaderCounts === 'function') {
            window.loadHeaderCounts();
          }
          
          $btn.html('<span>♥</span>');
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

    const productId = $btn.data('product-id') || window.productId;
    const quantity = parseInt($('#quantity').val());
    const originalHtml = $btn.html();

    $btn.prop('disabled', true).html('<span class="spinner">⟳</span> Processing...');

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

    $btn.prop('disabled', true).html('<span class="spinner">⟳</span>');

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
          
          // Update header cart count
          if (typeof window.loadHeaderCounts === 'function') {
            window.loadHeaderCounts();
          }
          
          $btn.html('<span>✓</span>');
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

    $btn.html('<span class="spinner">⟳</span>');

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
          
          // Update header wishlist count
          if (typeof window.loadHeaderCounts === 'function') {
            window.loadHeaderCounts();
          }
          
          $btn.html('<span>♥</span>');
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

  // Make showToast available globally
  window.showToast = showToast;
});
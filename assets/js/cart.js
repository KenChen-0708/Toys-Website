$(document).ready(function() {
  let confirmCallback = null;

  // Custom confirmation modal
  function showConfirm(title, message, callback) {
    $('#modal-title').text(title);
    $('#modal-message').text(message);
    confirmCallback = callback;
    $('#confirm-modal').addClass('active');
  }

  function hideConfirm() {
    $('#confirm-modal').removeClass('active');
    confirmCallback = null;
  }

  $('#modal-confirm').on('click', function() {
    if (confirmCallback) {
      confirmCallback();
    }
    hideConfirm();
  });

  $('#modal-cancel').on('click', hideConfirm);

  // Close modal on overlay click
  $('#confirm-modal').on('click', function(e) {
    if (e.target === this) {
      hideConfirm();
    }
  });

  // Show alert message
  function showAlert(message, type) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
    const alertHtml = `<div class="alert ${alertClass}">${message}</div>`;
    $('#alert-container').html(alertHtml).find('.alert').fadeIn();

    setTimeout(function() {
      $('#alert-container .alert').fadeOut(function() {
        $(this).remove();
      });
    }, 3000);
  }

  // Update selected count
  function updateSelectedCount() {
    const selectedCount = $('.item-select:checked').length;
    $('#selected-count').text(selectedCount);

    if (selectedCount > 0) {
      $('#batch-actions').addClass('active');
    } else {
      $('#batch-actions').removeClass('active');
    }

    // Update select all checkbox
    const totalItems = $('.item-select').length;
    $('#select-all').prop('checked', selectedCount === totalItems && totalItems > 0);
  }

  // Select all checkbox
  $('#select-all').on('change', function() {
    $('.item-select').prop('checked', $(this).is(':checked'));
    updateSelectedCount();
  });

  // Individual checkbox
  $(document).on('change', '.item-select', function() {
    updateSelectedCount();
  });

  // Calculate and update totals
  function updateTotals() {
    let total = 0;
    $('.cart-item').each(function() {
      const subtotalText = $(this).find('.subtotal-value').text().replace(/,/g, '');
      const subtotal = parseFloat(subtotalText);
      // Only add to total if it's a valid number
      if (!isNaN(subtotal) && isFinite(subtotal)) {
        total += subtotal;
      }
    });

    // Ensure total is always a valid number
    if (isNaN(total) || !isFinite(total)) {
      total = 0;
    }

    $('#cart-subtotal').text('RM ' + total.toFixed(2));
    $('#cart-total').text('RM ' + total.toFixed(2));
  }

  // Prevent non-numeric input in quantity fields
  $(document).on('keypress', '.quantity-input', function(e) {
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
  $(document).on('paste', '.quantity-input', function(e) {
    const $input = $(this);
    setTimeout(function() {
      const value = parseInt($input.val());
      if (isNaN(value) || value < 1) {
        $input.val(1);
        $input.trigger('input');
      }
    }, 1);
  });

  // Update quantity
  let updateTimeout;
  $(document).on('input', '.quantity-input', function() {
    const $input = $(this);
    const cartItemId = $input.data('cart-item-id');
    let quantity = parseInt($input.val());
    const $cartItem = $input.closest('.cart-item');
    const price = parseFloat($cartItem.data('price'));
    const maxStock = parseInt($input.attr('max'));

    // Handle invalid input
    if (isNaN(quantity) || quantity < 1) {
      $input.val(1);
      quantity = 1;
    }

    if (quantity > maxStock) {
      showAlert('Quantity exceeds available stock', 'error');
      $input.val(maxStock);
      quantity = maxStock;
    }

    // Update subtotal immediately with validated quantity
    const newSubtotal = (price * quantity).toFixed(2);
    $cartItem.find('.subtotal-value').text(newSubtotal.replace(/\B(?=(\d{3})+(?!\d))/g, ","));
    updateTotals();

    // Debounce AJAX call
    clearTimeout(updateTimeout);
    updateTimeout = setTimeout(function() {
      $.ajax({
        url: 'cart.php',
        method: 'POST',
        data: {
          action: 'update_quantity',
          cart_item_id: cartItemId,
          quantity: quantity
        },
        dataType: 'json',
        beforeSend: function() {
          $('#loading').show();
        },
        success: function(response) {
          $('#loading').hide();
          if (!response.success) {
            showAlert(response.message, 'error');
            // Revert to previous valid value if server rejects
            location.reload();
          }
        },
        error: function() {
          $('#loading').hide();
          showAlert('An error occurred while updating quantity', 'error');
        }
      });
    }, 500);
  });

  // Remove single item
  $(document).on('click', '.btn-remove', function() {
    const cartItemId = $(this).data('cart-item-id');
    const $cartItem = $(this).closest('.cart-item');

    showConfirm('Remove Item', 'Are you sure you want to remove this item from your cart?', function() {
      $.ajax({
        url: 'cart.php',
        method: 'POST',
        data: {
          action: 'remove_item',
          cart_item_id: cartItemId
        },
        dataType: 'json',
        beforeSend: function() {
          $('#loading').show();
        },
        success: function(response) {
          $('#loading').hide();
          if (response.success) {
            $cartItem.fadeOut(300, function() {
              $(this).remove();
              updateTotals();
              updateSelectedCount();

              // Update header cart count
              if (typeof window.loadHeaderCounts === 'function') {
                window.loadHeaderCounts();
              }

              if ($('.cart-item').length === 0) {
                location.reload();
              }
            });
            showAlert(response.message, 'success');
          } else {
            showAlert(response.message, 'error');
          }
        },
        error: function() {
          $('#loading').hide();
          showAlert('An error occurred while removing item', 'error');
        }
      });
    });
  });

  // Remove selected items
  $('#remove-selected').on('click', function() {
    const selectedItems = $('.item-select:checked');
    const count = selectedItems.length;

    if (count === 0) return;

    showConfirm('Remove Selected Items', `Are you sure you want to remove ${count} item(s) from your cart?`, function() {
      const itemIds = [];
      const $itemsToRemove = [];

      selectedItems.each(function() {
        itemIds.push($(this).data('cart-item-id'));
        $itemsToRemove.push($(this).closest('.cart-item'));
      });

      let completed = 0;
      let hasError = false;

      $('#loading').show();

      itemIds.forEach(function(cartItemId, index) {
        $.ajax({
          url: 'cart.php',
          method: 'POST',
          data: {
            action: 'remove_item',
            cart_item_id: cartItemId
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              $itemsToRemove[index].fadeOut(300, function() {
                $(this).remove();

                // Update totals after each removal
                updateTotals();
                updateSelectedCount();
              });
            } else {
              hasError = true;
            }
          },
          error: function() {
            hasError = true;
          },
          complete: function() {
            completed++;
            if (completed === itemIds.length) {
              $('#loading').hide();

              // Final update after all removals
              setTimeout(function() {
                updateTotals();
                updateSelectedCount();

                // Update header cart count
                if (typeof window.loadHeaderCounts === 'function') {
                  window.loadHeaderCounts();
                }

                if (hasError) {
                  showAlert('Some items could not be removed', 'error');
                } else {
                  showAlert(`${count} item(s) removed successfully`, 'success');
                }

                if ($('.cart-item').length === 0) {
                  setTimeout(function() {
                    location.reload();
                  }, 1000);
                }
              }, 400);
            }
          }
        });
      });
    });
  });

  // Clear cart
  $('#clear-cart').on('click', function() {
    showConfirm('Clear Cart', 'Are you sure you want to clear your entire cart? This action cannot be undone.', function() {
      $.ajax({
        url: 'cart.php',
        method: 'POST',
        data: {
          action: 'clear_cart'
        },
        dataType: 'json',
        beforeSend: function() {
          $('#loading').show();
        },
        success: function(response) {
          $('#loading').hide();
          if (response.success) {
            // Update header cart count
            if (typeof window.loadHeaderCounts === 'function') {
              window.loadHeaderCounts();
            }
            
            showAlert(response.message, 'success');
            setTimeout(function() {
              location.reload();
            }, 1000);
          } else {
            showAlert(response.message, 'error');
          }
        },
        error: function() {
          $('#loading').hide();
          showAlert('An error occurred while clearing cart', 'error');
        }
      });
    });
  });

  // Handle clicks on product image and name to navigate to product page
  $(document).on('click', '.item-image-container, .item-details h3', function() {
    const productId = $(this).closest('.cart-item').data('product-id');
    if (productId) {
      window.location.href = '../product/product_details.php?id=' + productId;
    }
  });
});
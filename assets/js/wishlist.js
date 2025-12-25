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
  $(document).on('change', '.item-select', function(e) {
    e.stopPropagation();
    updateSelectedCount();
  });

  // Prevent checkbox click from triggering navigation
  $(document).on('click', '.item-checkbox', function(e) {
    e.stopPropagation();
  });

  // Add to cart
  $(document).on('click', '.btn-add-cart', function(e) {
    e.stopPropagation();
    const $btn = $(this);
    const productId = $btn.data('product-id');

    $.ajax({
      url: 'wishlist.php',
      method: 'POST',
      data: {
        action: 'add_to_cart',
        product_id: productId
      },
      dataType: 'json',
      beforeSend: function() {
        $btn.prop('disabled', true).html('<i class="fi fi-rs-spinner"></i> Adding...');
      },
      success: function(response) {
        if (response.success) {
          showAlert(response.message, 'success');
          $btn.html('<i class="fi fi-rs-check"></i> Added!');
          
          // Update header cart count
          if (typeof window.loadHeaderCounts === 'function') {
            window.loadHeaderCounts();
          }
          
          setTimeout(function() {
            $btn.html('<img src="../../assets/img/icon-cart.svg" alt="Cart" style="width: 16px; height: 16px; margin-right: 8px;"> Add to Cart').prop('disabled', false);
          }, 2000);
        } else {
          showAlert(response.message, 'error');
          $btn.html('<img src="../../assets/img/icon-cart.svg" alt="Cart" style="width: 16px; height: 16px; margin-right: 8px;"> Add to Cart').prop('disabled', false);
        }
      },
      error: function() {
        showAlert('An error occurred while adding to cart', 'error');
        $btn.html('<img src="../../assets/img/icon-cart.svg" alt="Cart" style="width: 16px; height: 16px; margin-right: 8px;"> Add to Cart').prop('disabled', false);
      }
    });
  });

  // Remove single item
  $(document).on('click', '.btn-remove', function(e) {
    e.stopPropagation();
    const wishlistItemId = $(this).data('wishlist-item-id');
    const $wishlistItem = $(this).closest('.wishlist-item');

    showConfirm('Remove Item', 'Are you sure you want to remove this item from your wishlist?', function() {
      $.ajax({
        url: 'wishlist.php',
        method: 'POST',
        data: {
          action: 'remove_item',
          wishlist_item_id: wishlistItemId
        },
        dataType: 'json',
        beforeSend: function() {
          $('#loading').show();
        },
        success: function(response) {
          $('#loading').hide();
          if (response.success) {
            $wishlistItem.fadeOut(300, function() {
              $(this).remove();
              updateSelectedCount();

              // Update header wishlist count
              if (typeof window.loadHeaderCounts === 'function') {
                window.loadHeaderCounts();
              }

              if ($('.wishlist-item').length === 0) {
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

    showConfirm('Remove Selected Items', `Are you sure you want to remove ${count} item(s) from your wishlist?`, function() {
      const itemIds = [];
      const $itemsToRemove = [];

      selectedItems.each(function() {
        itemIds.push($(this).data('wishlist-item-id'));
        $itemsToRemove.push($(this).closest('.wishlist-item'));
      });

      let completed = 0;
      let hasError = false;

      $('#loading').show();

      itemIds.forEach(function(wishlistItemId, index) {
        $.ajax({
          url: 'wishlist.php',
          method: 'POST',
          data: {
            action: 'remove_item',
            wishlist_item_id: wishlistItemId
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              $itemsToRemove[index].fadeOut(300, function() {
                $(this).remove();
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

              setTimeout(function() {
                updateSelectedCount();

                // Update header wishlist count
                if (typeof window.loadHeaderCounts === 'function') {
                  window.loadHeaderCounts();
                }

                if (hasError) {
                  showAlert('Some items could not be removed', 'error');
                } else {
                  showAlert(`${count} item(s) removed successfully`, 'success');
                }

                if ($('.wishlist-item').length === 0) {
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

  // Clear wishlist
  $('#clear-wishlist').on('click', function() {
    showConfirm('Clear Wishlist', 'Are you sure you want to clear your entire wishlist? This action cannot be undone.', function() {
      $.ajax({
        url: 'wishlist.php',
        method: 'POST',
        data: {
          action: 'clear_wishlist'
        },
        dataType: 'json',
        beforeSend: function() {
          $('#loading').show();
        },
        success: function(response) {
          $('#loading').hide();
          if (response.success) {
            // Update header wishlist count
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
          showAlert('An error occurred while clearing wishlist', 'error');
        }
      });
    });
  });

  // Handle clicks on product image and name to navigate to product page
  $(document).on('click', '.item-image-container, .item-details h3', function() {
    const productId = $(this).closest('.wishlist-item').data('product-id');
    if (productId) {
      window.location.href = '../product/product_details.php?id=' + productId;
    }
  });
});
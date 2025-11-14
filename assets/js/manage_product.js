// Manage Product JavaScript with jQuery

$(document).ready(function() {
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
    
    // Initialize checkbox handlers
    initCheckboxHandlers();
});

// Toggle Sidebar for Mobile
function toggleSidebar() {
    $('#sidebar').toggleClass('active');
}

// Checkbox Selection Handlers
function initCheckboxHandlers() {
    // Select All checkbox
    $('#selectAll').on('change', function() {
        const isChecked = $(this).prop('checked');
        $('.product-checkbox').prop('checked', isChecked);
        updateBatchActions();
    });
    
    // Individual checkboxes
    $(document).on('change', '.product-checkbox', function() {
        updateBatchActions();
        
        // Update select all checkbox
        const totalCheckboxes = $('.product-checkbox').length;
        const checkedCheckboxes = $('.product-checkbox:checked').length;
        $('#selectAll').prop('checked', totalCheckboxes === checkedCheckboxes);
    });
}

// Update Batch Actions Visibility
function updateBatchActions() {
    const selectedCount = $('.product-checkbox:checked').length;
    
    if (selectedCount > 0) {
        $('#batchActions').fadeIn(300);
        $('.selected-count').text(selectedCount + ' selected');
    } else {
        $('#batchActions').fadeOut(300);
    }
}

// Get Selected Product IDs
function getSelectedProducts() {
    const selected = [];
    $('.product-checkbox:checked').each(function() {
        selected.push($(this).val());
    });
    return selected;
}

// Batch Delete
function batchDelete() {
    const selected = getSelectedProducts();
    
    if (selected.length === 0) {
        alert('Please select products to delete');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${selected.length} product(s)? This action cannot be undone.`)) {
        // Create form with selected products
        const $form = $('#batchDeleteForm');
        $form.find('input[name="selected_products[]"]').remove();
        
        selected.forEach(function(id) {
            $form.append(`<input type="hidden" name="selected_products[]" value="${id}">`);
        });
        
        $form.submit();
    }
}

// Open Batch Status Modal
function openBatchStatusModal() {
    const selected = getSelectedProducts();
    
    if (selected.length === 0) {
        alert('Please select products to update');
        return;
    }
    
    // Populate hidden inputs
    $('#batchStatusForm').find('input[name="selected_products[]"]').remove();
    selected.forEach(function(id) {
        $('#batchStatusForm').append(`<input type="hidden" name="selected_products[]" value="${id}">`);
    });
    
    $('#batchStatusModal').fadeIn(300);
}

// Open Batch Category Modal
function openBatchCategoryModal() {
    const selected = getSelectedProducts();
    
    if (selected.length === 0) {
        alert('Please select products to update');
        return;
    }
    
    // Populate hidden inputs
    $('#batchCategoryForm').find('input[name="selected_products[]"]').remove();
    selected.forEach(function(id) {
        $('#batchCategoryForm').append(`<input type="hidden" name="selected_products[]" value="${id}">`);
    });
    
    $('#batchCategoryModal').fadeIn(300);
}

// Filter Low Stock
function filterLowStock() {
    window.location.href = 'manage_product.php?stock_alert=1';
}

// Open Create Modal
function openCreateModal() {
    $('#createModal').fadeIn(300);
}

// Open CSV Modal
function openCSVModal() {
    $('#csvModal').fadeIn(300);
}

// Open Edit Modal
function openEditModal(product) {
    // Set form values
    $('#edit_product_id').val(product.product_id);
    $('#edit_name').val(product.name);
    $('#edit_price').val(product.price);
    $('#edit_description').val(product.description || '');
    $('#edit_category').val(product.category || '');
    $('#edit_stock').val(product.stock);
    $('#edit_status').val(product.status);
    $('#edit_existing_main_image').val(product.main_image_path || '');
    
    // Main image preview
    const $preview = $('#editPreview');
    if (product.main_image_path && product.main_image_path != '../assets/img/default.jpg') {
        $preview.attr('src', product.main_image_path).show();
    } else {
        $preview.attr('src', '../assets/img/default.jpg').show();
        $preview.on('error', function() {
            $(this).hide();
        });
    }
    
    // Load existing sub images
    loadSubImages(product.product_id);
    
    // Show modal
    $('#editModal').fadeIn(300);
}

// Load Sub Images via AJAX
function loadSubImages(productId) {
    const $container = $('#existingSubImages');
    
    // Show loading state
    $container.html('<div class="loading-message"><p>Loading sub images...</p></div>');
    
    $.ajax({
        url: 'get_sub_images.php',
        method: 'GET',
        data: { product_id: productId },
        dataType: 'json',
        success: function(data) {
            $container.empty();
            
            if (data.error) {
                // Handle error response
                $container.html(`
                    <div class="error-message">
                        <p><strong>Error:</strong> ${data.error}</p>
                        ${data.message ? `<p>${data.message}</p>` : ''}
                    </div>
                `);
            } else if (data.length === 0) {
                // No sub images found
                $container.addClass('empty').html(`
                    <div class="empty-message">
                        <i>📷</i>
                        <p>No sub images uploaded yet</p>
                        <small>Add sub images using the form below</small>
                    </div>
                `);
            } else {
                // Display sub images
                $container.removeClass('empty');
                $.each(data, function(index, img) {
                    const $div = $('<div>').addClass('sub-image-item');
                    const $img = $('<img>').attr({
                        'src': img.sub_image_path,
                        'alt': 'Sub Image ' + (index + 1)
                    });
                    const $deleteBtn = $('<button>')
                        .attr('type', 'button')
                        .addClass('sub-image-delete')
                        .html('×')
                        .on('click', function() {
                            deleteSubImage(img.sub_image_id);
                        });
                    
                    $div.append($img, $deleteBtn);
                    $container.append($div);
                });
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            $container.html(`
                <div class="error-message">
                    <p><strong>Failed to load sub images</strong></p>
                    <p>Error: ${error || 'Network error'}</p>
                    <small>Please check if get_sub_images.php exists in the same directory</small>
                </div>
            `);
        }
    });
}

// Delete Sub Image
function deleteSubImage(subImageId) {
    if (confirm('Are you sure you want to delete this sub image?')) {
        $('#delete_sub_image_id').val(subImageId);
        $('#deleteSubImageForm').submit();
    }
}

// Close Modal
function closeModal(modalId) {
    $('#' + modalId).fadeOut(300);
    // Clear preview containers
    $('#createSubPreviews').empty();
    $('#editSubPreviews').empty();
}

// Confirm Delete Product
function confirmDelete(productId) {
    if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        $('#delete_product_id').val(productId);
        $('#deleteForm').submit();
    }
}

// Preview Single Image (Main Image)
function previewImage(event, previewId) {
    const file = event.target.files[0];
    const $preview = $('#' + previewId);
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            $preview.attr('src', e.target.result).fadeIn(300);
        };
        reader.readAsDataURL(file);
    } else {
        $preview.fadeOut(300);
    }
}

// Preview Multiple Images (Sub Images)
function previewMultipleImages(event, previewId) {
    const files = event.target.files;
    const $container = $('#' + previewId);
    
    $container.empty();
    
    if (files.length === 0) return;
    
    // Preview each file
    $.each(files, function(index, file) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const $div = $('<div>').addClass('preview-item');
            const $img = $('<img>').attr('src', e.target.result);
            const fileName = file.name.length > 15 ? file.name.substring(0, 15) + '...' : file.name;
            const $label = $('<span>').addClass('preview-label').text(fileName);
            
            $div.append($img, $label);
            $container.append($div);
        };
        
        reader.readAsDataURL(file);
    });
    
    // Show count of selected files
    const $countLabel = $('<p>')
        .css({
            'margin-top': '10px',
            'color': '#4a5568',
            'font-size': '14px'
        })
        .html(`<strong>${files.length}</strong> image(s) selected`);
    
    $container.append($countLabel);
}

// Close modal when clicking outside
$(window).on('click', function(event) {
    if ($(event.target).hasClass('modal')) {
        $(event.target).fadeOut(300);
    }
});

// Prevent form submission on Enter key (except in textarea)
$('form').on('keypress', function(e) {
    if (e.which === 13 && e.target.tagName !== 'TEXTAREA') {
        e.preventDefault();
        return false;
    }
});

// Form validation on submit
$('form').on('submit', function(e) {
    const $form = $(this);
    let isValid = true;
    
    // Clear previous errors
    $form.find('.error').removeClass('error');
    $form.find('.error-message').remove();
    
    // Validate required fields
    $form.find('[required]').each(function() {
        const $field = $(this);
        const value = $field.val().trim();
        
        if (!value) {
            $field.addClass('error');
            $field.after('<span class="error-message">This field is required</span>');
            isValid = false;
        }
    });
    
    // Validate number fields
    $form.find('input[type="number"]').each(function() {
        const $field = $(this);
        const value = parseFloat($field.val());
        const min = parseFloat($field.attr('min'));
        
        if (!isNaN(min) && value < min) {
            $field.addClass('error');
            $field.after(`<span class="error-message">Value must be at least ${min}</span>`);
            isValid = false;
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        $('html, body').animate({
            scrollTop: $form.find('.error').first().offset().top - 100
        }, 500);
    }
});

// Manage Product JavaScript with jQuery

$(document).ready(function() {
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert').fadeOut('slow');
    }, 5000);
});

// Toggle Sidebar for Mobile
function toggleSidebar() {
    $('#sidebar').toggleClass('active');
}

// Open Create Modal
function openCreateModal() {
    $('#createModal').fadeIn(300);
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
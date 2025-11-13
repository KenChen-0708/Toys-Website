<?php
session_start();

// Include database connection
include '../helper/connection.php';

// Check connection
if (!$db_ok || !$conn) {
    die("Connection failed: Unable to connect to database");
}

// Initialize variables
$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // CREATE - Add new product
    if (isset($_POST['create_product'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $price = $conn->real_escape_string($_POST['price']);
        $description = $conn->real_escape_string($_POST['description']);
        $category = $conn->real_escape_string($_POST['category']);
        $stock = $conn->real_escape_string($_POST['stock']);
        $status = $conn->real_escape_string($_POST['status']);
        
        // Handle main image upload
        $main_image_path = '../assets/img/default.jpg';
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
            $target_dir = "../assets/img/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'product_' . uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['main_image']['tmp_name'], $target_file)) {
                $main_image_path = $target_file;
            }
        }
        
        // Insert product
        $sql = "INSERT INTO Product (name, price, description, category, stock, status, main_image_path) 
                VALUES ('$name', '$price', '$description', '$category', '$stock', '$status', '$main_image_path')";
        
        if ($conn->query($sql)) {
            $product_id = $conn->insert_id;
            
            // Handle sub images upload
            if (isset($_FILES['sub_images'])) {
                $total_files = count($_FILES['sub_images']['name']);
                
                for ($i = 0; $i < $total_files; $i++) {
                    if ($_FILES['sub_images']['error'][$i] == 0) {
                        $file_extension = pathinfo($_FILES['sub_images']['name'][$i], PATHINFO_EXTENSION);
                        $new_filename = 'product_sub_' . uniqid() . '.' . $file_extension;
                        $target_file = $target_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['sub_images']['tmp_name'][$i], $target_file)) {
                            $sub_image_path = $conn->real_escape_string($target_file);
                            $conn->query("INSERT INTO Product_Sub_Image (product_id, sub_image_path) VALUES ('$product_id', '$sub_image_path')");
                        }
                    }
                }
            }
            
            $message = "Product added successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }
    
    // UPDATE - Edit product
    if (isset($_POST['update_product'])) {
        $product_id = $conn->real_escape_string($_POST['product_id']);
        $name = $conn->real_escape_string($_POST['name']);
        $price = $conn->real_escape_string($_POST['price']);
        $description = $conn->real_escape_string($_POST['description']);
        $category = $conn->real_escape_string($_POST['category']);
        $stock = $conn->real_escape_string($_POST['stock']);
        $status = $conn->real_escape_string($_POST['status']);
        
        // Handle main image upload
        $main_image_path = $conn->real_escape_string($_POST['existing_main_image']);
        
        // If main image path is empty, set default
        if (empty($main_image_path)) {
            $main_image_path = '../assets/img/default.jpg';
        }
        
        if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
            $target_dir = "../assets/img/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'product_' . uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['main_image']['tmp_name'], $target_file)) {
                // Delete old image if it's not the default image
                if (!empty($main_image_path) && file_exists($main_image_path) && $main_image_path != '../assets/img/default.jpg') {
                    unlink($main_image_path);
                }
                $main_image_path = $target_file;
            }
        }
        
        $sql = "UPDATE Product SET 
                name='$name', 
                price='$price', 
                description='$description', 
                category='$category', 
                stock='$stock', 
                status='$status', 
                main_image_path='$main_image_path' 
                WHERE product_id='$product_id'";
        
        if ($conn->query($sql)) {
            // Handle new sub images upload
            if (isset($_FILES['sub_images'])) {
                $total_files = count($_FILES['sub_images']['name']);
                
                for ($i = 0; $i < $total_files; $i++) {
                    if ($_FILES['sub_images']['error'][$i] == 0) {
                        $file_extension = pathinfo($_FILES['sub_images']['name'][$i], PATHINFO_EXTENSION);
                        $new_filename = 'product_sub_' . uniqid() . '.' . $file_extension;
                        $target_file = $target_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['sub_images']['tmp_name'][$i], $target_file)) {
                            $sub_image_path = $conn->real_escape_string($target_file);
                            $conn->query("INSERT INTO Product_Sub_Image (product_id, sub_image_path) VALUES ('$product_id', '$sub_image_path')");
                        }
                    }
                }
            }
            
            $message = "Product updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }
    
    // DELETE - Remove product
    if (isset($_POST['delete_product'])) {
        $product_id = $conn->real_escape_string($_POST['product_id']);
        
        // Get main image path before deleting
        $result = $conn->query("SELECT main_image_path FROM Product WHERE product_id='$product_id'");
        if ($row = $result->fetch_assoc()) {
            // Don't delete the default image
            if (!empty($row['main_image_path']) && file_exists($row['main_image_path']) && $row['main_image_path'] != '../assets/img/default.jpg') {
                unlink($row['main_image_path']);
            }
        }
        
        // Get and delete all sub images
        $sub_images = $conn->query("SELECT sub_image_path FROM Product_Sub_Image WHERE product_id='$product_id'");
        while ($sub_img = $sub_images->fetch_assoc()) {
            if (!empty($sub_img['sub_image_path']) && file_exists($sub_img['sub_image_path'])) {
                unlink($sub_img['sub_image_path']);
            }
        }
        
        $sql = "DELETE FROM Product WHERE product_id='$product_id'";
        
        if ($conn->query($sql)) {
            $message = "Product deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }
    
    // DELETE - Remove sub image
    if (isset($_POST['delete_sub_image'])) {
        $sub_image_id = $conn->real_escape_string($_POST['sub_image_id']);
        
        // Get sub image path before deleting
        $result = $conn->query("SELECT sub_image_path FROM Product_Sub_Image WHERE sub_image_id='$sub_image_id'");
        if ($row = $result->fetch_assoc()) {
            if (!empty($row['sub_image_path']) && file_exists($row['sub_image_path'])) {
                unlink($row['sub_image_path']);
            }
        }
        
        $sql = "DELETE FROM Product_Sub_Image WHERE sub_image_id='$sub_image_id'";
        
        if ($conn->query($sql)) {
            $message = "Sub image deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }
}

// READ - Get all products
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';

$sql = "SELECT * FROM Product WHERE 1=1";
if (!empty($search)) {
    $sql .= " AND (name LIKE '%$search%' OR description LIKE '%$search%')";
}
if (!empty($category_filter)) {
    $sql .= " AND category='$category_filter'";
}
$sql .= " ORDER BY product_id DESC";

$products = $conn->query($sql);

// Get categories for filter
$categories_result = $conn->query("SELECT DISTINCT category FROM Product WHERE category IS NOT NULL AND category != ''");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/admin_panel.css">
    <link rel="stylesheet" href="../assets/css/manage_product.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰ Menu</button>

    <div class="admin-container">
       
        <?php include '../partials/admin_panel.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Manage Products</h1>
                <button class="btn btn-primary" onclick="openCreateModal()">+ Add New Product</button>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Search and Filter Bar -->
            <div class="filter-bar">
                <form method="GET" style="display: flex; gap: 15px; flex: 1; flex-wrap: wrap;">
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php while ($cat = $categories_result->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                <?php echo ($category_filter == $cat['category']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="manage_product.php" class="btn" style="background: #cbd5e0;">Clear</a>
                </form>
            </div>

            <!-- Products Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products->num_rows > 0): ?>
                            <?php while ($product = $products->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $product['product_id']; ?></td>
                                    <td>
                                        <?php if (!empty($product['main_image_path']) && $product['main_image_path'] != '../assets/img/default.jpg'): ?>
                                            <img src="<?php echo htmlspecialchars($product['main_image_path']); ?>" 
                                                 alt="Product" class="product-image">
                                        <?php else: ?>
                                            <img src="../assets/img/default.jpg" alt="Default Product" class="product-image" 
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div class="product-image" style="background: #e2e8f0; display: none; align-items: center; justify-content: center;">📦</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td><?php echo $product['stock']; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo ($product['status'] == 'Available') ? 'available' : 'out'; ?>">
                                            <?php echo $product['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-warning btn-sm" 
                                                onclick='openEditModal(<?php echo json_encode($product); ?>)'>
                                            Edit
                                        </button>
                                        <button class="btn btn-danger btn-sm" 
                                                onclick="confirmDelete(<?php echo $product['product_id']; ?>)">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px;">
                                    No products found. Add your first product!
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Product Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Product</h2>
                <button class="close" onclick="closeModal('createModal')">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Price *</label>
                    <input type="number" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description"></textarea>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category">
                </div>
                <div class="form-group">
                    <label>Stock *</label>
                    <input type="number" name="stock" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="Available">Available</option>
                        <option value="Out of Stock">Out of Stock</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Main Product Image</label>
                    <input type="file" name="main_image" accept="image/*" onchange="previewImage(event, 'createPreview')">
                    <img id="createPreview" class="image-preview" style="display: none;">
                </div>
                <div class="form-group">
                    <label>Sub Images (Multiple)</label>
                    <input type="file" name="sub_images[]" accept="image/*" multiple onchange="previewMultipleImages(event, 'createSubPreviews')">
                    <div id="createSubPreviews" class="sub-images-preview"></div>
                </div>
                <button type="submit" name="create_product" class="btn btn-primary">Create Product</button>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Product</h2>
                <button class="close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="product_id" id="edit_product_id">
                <input type="hidden" name="existing_main_image" id="edit_existing_main_image">
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Price *</label>
                    <input type="number" name="price" id="edit_price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description"></textarea>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" id="edit_category">
                </div>
                <div class="form-group">
                    <label>Stock *</label>
                    <input type="number" name="stock" id="edit_stock" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="Available">Available</option>
                        <option value="Out of Stock">Out of Stock</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Main Product Image</label>
                    <input type="file" name="main_image" accept="image/*" onchange="previewImage(event, 'editPreview')">
                    <img id="editPreview" class="image-preview">
                </div>
                <div class="form-group">
                    <label>Existing Sub Images</label>
                    <div id="existingSubImages" class="sub-images-grid"></div>
                </div>
                <div class="form-group">
                    <label>Add New Sub Images</label>
                    <input type="file" name="sub_images[]" accept="image/*" multiple onchange="previewMultipleImages(event, 'editSubPreviews')">
                    <div id="editSubPreviews" class="sub-images-preview"></div>
                </div>
                <button type="submit" name="update_product" class="btn btn-success">Update Product</button>
            </form>
        </div>
    </div>

    <!-- Delete Form (Hidden) -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="product_id" id="delete_product_id">
        <input type="hidden" name="delete_product" value="1">
    </form>

    <!-- Delete Sub Image Form (Hidden) -->
    <form method="POST" id="deleteSubImageForm" style="display: none;">
        <input type="hidden" name="sub_image_id" id="delete_sub_image_id">
        <input type="hidden" name="delete_sub_image" value="1">
    </form>

    <script src="../assets/js/manage_product.js"></script>
</body>
</html>

<?php
$conn->close();
?>
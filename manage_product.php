<?php
session_start();

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'assignment';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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
        
        // Handle image upload
        $image_path = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "uploads/products/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_path = $target_file;
            }
        }
        
        $sql = "INSERT INTO Product (name, price, description, category, stock, status, image_path) 
                VALUES ('$name', '$price', '$description', '$category', '$stock', '$status', '$image_path')";
        
        if ($conn->query($sql)) {
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
        
        // Handle image upload
        $image_path = $conn->real_escape_string($_POST['existing_image']);
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $target_dir = "uploads/products/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                // Delete old image
                if (!empty($image_path) && file_exists($image_path)) {
                    unlink($image_path);
                }
                $image_path = $target_file;
            }
        }
        
        $sql = "UPDATE Product SET 
                name='$name', 
                price='$price', 
                description='$description', 
                category='$category', 
                stock='$stock', 
                status='$status', 
                image_path='$image_path' 
                WHERE product_id='$product_id'";
        
        if ($conn->query($sql)) {
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
        
        // Get image path before deleting
        $result = $conn->query("SELECT image_path FROM Product WHERE product_id='$product_id'");
        if ($row = $result->fetch_assoc()) {
            if (!empty($row['image_path']) && file_exists($row['image_path'])) {
                unlink($row['image_path']);
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
	<link rel="stylesheet" href="assets/css/manage_product.css">
    <link rel="stylesheet" href="assets/css/admin_panel.css">
</head>
<body>
    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰ Menu</button>

    <div class="admin-container">
       
        <?php include 'partials/admin_panel.php'; ?>

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
                                        <?php if (!empty($product['image_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($product['image_path']); ?>" 
                                                 alt="Product" class="product-image">
                                        <?php else: ?>
                                            <div class="product-image" style="background: #e2e8f0; display: flex; align-items: center; justify-content: center;">📦</div>
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
                    <label>Product Image</label>
                    <input type="file" name="image" accept="image/*" onchange="previewImage(event, 'createPreview')">
                    <img id="createPreview" class="image-preview" style="display: none;">
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
                <input type="hidden" name="existing_image" id="edit_existing_image">
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
                    <label>Product Image</label>
                    <input type="file" name="image" accept="image/*" onchange="previewImage(event, 'editPreview')">
                    <img id="editPreview" class="image-preview">
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

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        function openCreateModal() {
            document.getElementById('createModal').style.display = 'block';
        }

        function openEditModal(product) {
            document.getElementById('edit_product_id').value = product.product_id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_category').value = product.category || '';
            document.getElementById('edit_stock').value = product.stock;
            document.getElementById('edit_status').value = product.status;
            document.getElementById('edit_existing_image').value = product.image_path || '';
            
            const preview = document.getElementById('editPreview');
            if (product.image_path) {
                preview.src = product.image_path;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
            }
            
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function confirmDelete(productId) {
            if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                document.getElementById('delete_product_id').value = productId;
                document.getElementById('deleteForm').submit();
            }
        }

        function previewImage(event, previewId) {
            const preview = document.getElementById(previewId);
            const file = event.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>
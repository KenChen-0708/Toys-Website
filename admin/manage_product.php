<?php
session_start();
require_once '../helper/pdo_connection.php';

// Initialize variables
$message = '';
$message_type = '';
$errors = [];

function renderInput($name, $label, $type = 'text', $value = '', $required = false, $attributes = []) {
    $req = $required ? 'required' : '';
    $attrs = '';
    foreach ($attributes as $key => $val) {
        $attrs .= " $key=\"$val\"";
    }
    $errorClass = isset($GLOBALS['errors'][$name]) ? 'error' : '';
    
    $html = "<div class='form-group'>";
    $html .= "<label>$label " . ($required ? '<span class="required">*</span>' : '') . "</label>";
    $html .= "<input type='$type' name='$name' value='" . htmlspecialchars($value) . "' $req $attrs class='$errorClass'>";
    if (isset($GLOBALS['errors'][$name])) {
        $html .= "<span class='error-message'>" . $GLOBALS['errors'][$name] . "</span>";
    }
    $html .= "</div>";
    return $html;
}

function renderTextarea($name, $label, $value = '', $required = false) {
    $req = $required ? 'required' : '';
    $errorClass = isset($GLOBALS['errors'][$name]) ? 'error' : '';
    
    $html = "<div class='form-group'>";
    $html .= "<label>$label " . ($required ? '<span class="required">*</span>' : '') . "</label>";
    $html .= "<textarea name='$name' $req class='$errorClass'>" . htmlspecialchars($value) . "</textarea>";
    if (isset($GLOBALS['errors'][$name])) {
        $html .= "<span class='error-message'>" . $GLOBALS['errors'][$name] . "</span>";
    }
    $html .= "</div>";
    return $html;
}

function renderSelect($name, $label, $options, $selected = '', $required = false) {
    $req = $required ? 'required' : '';
    $errorClass = isset($GLOBALS['errors'][$name]) ? 'error' : '';
    
    $html = "<div class='form-group'>";
    $html .= "<label>$label " . ($required ? '<span class="required">*</span>' : '') . "</label>";
    $html .= "<select name='$name' $req class='$errorClass'>";
    foreach ($options as $value => $text) {
        $sel = ($value == $selected) ? 'selected' : '';
        $html .= "<option value='$value' $sel>$text</option>";
    }
    $html .= "</select>";
    if (isset($GLOBALS['errors'][$name])) {
        $html .= "<span class='error-message'>" . $GLOBALS['errors'][$name] . "</span>";
    }
    $html .= "</div>";
    return $html;
}

function validateProduct($data, $isUpdate = false) {
    $errors = [];
    
    if (empty(trim($data['name']))) {
        $errors['name'] = 'Product name is required';
    } elseif (strlen($data['name']) > 150) {
        $errors['name'] = 'Product name must not exceed 150 characters';
    }
    
    if (empty($data['price']) || !is_numeric($data['price']) || $data['price'] < 0) {
        $errors['price'] = 'Valid price is required';
    }
    
    if (!isset($data['stock']) || !is_numeric($data['stock']) || $data['stock'] < 0) {
    $errors['stock'] = 'Valid stock quantity is required';
    }
    
    if (!empty($data['category']) && strlen($data['category']) > 100) {
        $errors['category'] = 'Category must not exceed 100 characters';
    }
    
    return $errors;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // BATCH DELETE
    if (isset($_POST['batch_delete'])) {
        if (!empty($_POST['selected_products'])) {
            try {
                $pdo->beginTransaction();
                
                $placeholders = str_repeat('?,', count($_POST['selected_products']) - 1) . '?';
                $stmt = $pdo->prepare("SELECT main_image_path FROM Product WHERE product_id IN ($placeholders)");
                $stmt->execute($_POST['selected_products']);
                $products = $stmt->fetchAll();
                
                // Delete images
                foreach ($products as $product) {
                    if (!empty($product['main_image_path']) && file_exists($product['main_image_path']) && 
                        $product['main_image_path'] != '../assets/img/default.jpg') {
                        @unlink($product['main_image_path']);
                    }
                }
                
                // Delete sub images
                $stmt = $pdo->prepare("SELECT sub_image_path FROM Product_Sub_Image WHERE product_id IN ($placeholders)");
                $stmt->execute($_POST['selected_products']);
                $subImages = $stmt->fetchAll();
                foreach ($subImages as $img) {
                    if (!empty($img['sub_image_path']) && file_exists($img['sub_image_path'])) {
                        @unlink($img['sub_image_path']);
                    }
                }
                
                // Delete products
                $stmt = $pdo->prepare("DELETE FROM Product WHERE product_id IN ($placeholders)");
                $stmt->execute($_POST['selected_products']);
                
                $pdo->commit();
                $message = count($_POST['selected_products']) . " product(s) deleted successfully!";
                $message_type = "success";
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Error deleting products: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "No products selected";
            $message_type = "error";
        }
    }
    
    // BATCH UPDATE STATUS
    if (isset($_POST['batch_update_status'])) {
        if (!empty($_POST['selected_products']) && !empty($_POST['batch_status'])) {
            try {
                $placeholders = str_repeat('?,', count($_POST['selected_products']) - 1) . '?';
                $stmt = $pdo->prepare("UPDATE Product SET status = ? WHERE product_id IN ($placeholders)");
                $params = array_merge([$_POST['batch_status']], $_POST['selected_products']);
                $stmt->execute($params);
                
                $message = count($_POST['selected_products']) . " product(s) status updated to " . $_POST['batch_status'];
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Error updating status: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "No products selected or status not specified";
            $message_type = "error";
        }
    }
    
    // BATCH UPDATE CATEGORY
    if (isset($_POST['batch_update_category'])) {
        if (!empty($_POST['selected_products']) && !empty($_POST['batch_category'])) {
            try {
                $placeholders = str_repeat('?,', count($_POST['selected_products']) - 1) . '?';
                $stmt = $pdo->prepare("UPDATE Product SET category = ? WHERE product_id IN ($placeholders)");
                $params = array_merge([$_POST['batch_category']], $_POST['selected_products']);
                $stmt->execute($params);
                
                $message = count($_POST['selected_products']) . " product(s) category updated";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Error updating category: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "No products selected or category not specified";
            $message_type = "error";
        }
    }
    
    if (isset($_POST['upload_csv']) && isset($_FILES['csv_file'])) {
        if ($_FILES['csv_file']['error'] == 0) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, 'r');
            
            if ($handle !== false) {
                $header = fgetcsv($handle); // Skip header row
                $successCount = 0;
                $errorCount = 0;
                
                try {
                    $pdo->beginTransaction();
                    
                    while (($data = fgetcsv($handle)) !== false) {
                        if (count($data) >= 5) {
                            $stmt = $pdo->prepare("INSERT INTO Product (name, price, description, category, stock, status) 
                                                   VALUES (?, ?, ?, ?, ?, ?)");
                            $status = isset($data[5]) && !empty($data[5]) ? $data[5] : 'Available';
                            $stmt->execute([
                                $data[0], // name
                                $data[1], // price
                                $data[2], // description
                                $data[3], // category
                                $data[4], // stock
                                $status
                            ]);
                            $successCount++;
                        } else {
                            $errorCount++;
                        }
                    }
                    
                    $pdo->commit();
                    $message = "$successCount product(s) imported successfully!";
                    if ($errorCount > 0) {
                        $message .= " ($errorCount row(s) skipped due to invalid data)";
                    }
                    $message_type = "success";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "Error importing CSV: " . $e->getMessage();
                    $message_type = "error";
                }
                
                fclose($handle);
            }
        } else {
            $message = "Error uploading CSV file";
            $message_type = "error";
        }
    }
    
    // CREATE PRODUCT
    if (isset($_POST['create_product'])) {
        $errors = validateProduct($_POST);
        
        if (empty($errors)) {
            try {
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
                
                $stmt = $pdo->prepare("INSERT INTO Product (name, price, description, category, stock, status, main_image_path) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['price'],
                    $_POST['description'],
                    $_POST['category'],
                    $_POST['stock'],
                    $_POST['status'],
                    $main_image_path
                ]);
                
                $product_id = $pdo->lastInsertId();
                
                // Handle sub images
                if (isset($_FILES['sub_images'])) {
                    $total_files = count($_FILES['sub_images']['name']);
                    for ($i = 0; $i < $total_files; $i++) {
                        if ($_FILES['sub_images']['error'][$i] == 0) {
                            $file_extension = pathinfo($_FILES['sub_images']['name'][$i], PATHINFO_EXTENSION);
                            $new_filename = 'product_sub_' . uniqid() . '.' . $file_extension;
                            $target_file = $target_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['sub_images']['tmp_name'][$i], $target_file)) {
                                $stmt = $pdo->prepare("INSERT INTO Product_Sub_Image (product_id, sub_image_path) VALUES (?, ?)");
                                $stmt->execute([$product_id, $target_file]);
                            }
                        }
                    }
                }
                
                $message = "Product added successfully!";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Please fix the validation errors";
            $message_type = "error";
        }
    }
    
    // UPDATE PRODUCT
    if (isset($_POST['update_product'])) {
        $errors = validateProduct($_POST, true);
        
        if (empty($errors)) {
            try {
                $main_image_path = $_POST['existing_main_image'];
                
                if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] == 0) {
                    $target_dir = "../assets/img/";
                    $file_extension = pathinfo($_FILES['main_image']['name'], PATHINFO_EXTENSION);
                    $new_filename = 'product_' . uniqid() . '.' . $file_extension;
                    $target_file = $target_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['main_image']['tmp_name'], $target_file)) {
                        if (!empty($main_image_path) && file_exists($main_image_path) && 
                            $main_image_path != '../assets/img/default.jpg') {
                            @unlink($main_image_path);
                        }
                        $main_image_path = $target_file;
                    }
                }
                
                $stmt = $pdo->prepare("UPDATE Product SET name=?, price=?, description=?, category=?, stock=?, status=?, main_image_path=? 
                                       WHERE product_id=?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['price'],
                    $_POST['description'],
                    $_POST['category'],
                    $_POST['stock'],
                    $_POST['status'],
                    $main_image_path,
                    $_POST['product_id']
                ]);
                
                // Handle new sub images
                if (isset($_FILES['sub_images'])) {
                    $total_files = count($_FILES['sub_images']['name']);
                    for ($i = 0; $i < $total_files; $i++) {
                        if ($_FILES['sub_images']['error'][$i] == 0) {
                            $file_extension = pathinfo($_FILES['sub_images']['name'][$i], PATHINFO_EXTENSION);
                            $new_filename = 'product_sub_' . uniqid() . '.' . $file_extension;
                            $target_file = $target_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['sub_images']['tmp_name'][$i], $target_file)) {
                                $stmt = $pdo->prepare("INSERT INTO Product_Sub_Image (product_id, sub_image_path) VALUES (?, ?)");
                                $stmt->execute([$_POST['product_id'], $target_file]);
                            }
                        }
                    }
                }
                
                $message = "Product updated successfully!";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Please fix the validation errors";
            $message_type = "error";
        }
    }
    
    // DELETE PRODUCT
    if (isset($_POST['delete_product'])) {
        try {
            $stmt = $pdo->prepare("SELECT main_image_path FROM Product WHERE product_id=?");
            $stmt->execute([$_POST['product_id']]);
            $product = $stmt->fetch();
            
            if ($product && !empty($product['main_image_path']) && file_exists($product['main_image_path']) && 
                $product['main_image_path'] != '../assets/img/default.jpg') {
                @unlink($product['main_image_path']);
            }
            
            $stmt = $pdo->prepare("SELECT sub_image_path FROM Product_Sub_Image WHERE product_id=?");
            $stmt->execute([$_POST['product_id']]);
            $subImages = $stmt->fetchAll();
            foreach ($subImages as $img) {
                if (!empty($img['sub_image_path']) && file_exists($img['sub_image_path'])) {
                    @unlink($img['sub_image_path']);
                }
            }
            
            $stmt = $pdo->prepare("DELETE FROM Product WHERE product_id=?");
            $stmt->execute([$_POST['product_id']]);
            
            $message = "Product deleted successfully!";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
    
    // DELETE SUB IMAGE
    if (isset($_POST['delete_sub_image'])) {
        try {
            $stmt = $pdo->prepare("SELECT sub_image_path FROM Product_Sub_Image WHERE sub_image_id=?");
            $stmt->execute([$_POST['sub_image_id']]);
            $img = $stmt->fetch();
            
            if ($img && !empty($img['sub_image_path']) && file_exists($img['sub_image_path'])) {
                @unlink($img['sub_image_path']);
            }
            
            $stmt = $pdo->prepare("DELETE FROM Product_Sub_Image WHERE sub_image_id=?");
            $stmt->execute([$_POST['sub_image_id']]);
            
            $message = "Sub image deleted successfully!";
            $message_type = "success";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$stock_alert = isset($_GET['stock_alert']) ? true : false;
$sort_by = isset($_GET['sort_by']) ? trim($_GET['sort_by']) : 'newest';

// Build query
$sql = "SELECT p.*, COALESCE(SUM(oi.quantity), 0) as total_sold 
        FROM Product p 
        LEFT JOIN Order_Item oi ON p.product_id = oi.product_id 
        WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if (!empty($category_filter)) {
    $sql .= " AND p.category = ?";
    $params[] = $category_filter;
}
if (!empty($status_filter)) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}
if ($stock_alert) {
    $sql .= " AND p.stock <= 10";
}

$sql .= " GROUP BY p.product_id";

// Apply sorting
switch ($sort_by) {
    case 'top_selling':
        $sql .= " ORDER BY total_sold DESC, p.product_id DESC";
        break;
    case 'price_low':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY p.name ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY p.name DESC";
        break;
    case 'stock_low':
        $sql .= " ORDER BY p.stock ASC";
        break;
    case 'stock_high':
        $sql .= " ORDER BY p.stock DESC";
        break;
    case 'oldest':
        $sql .= " ORDER BY p.product_id ASC";
        break;
    default: // newest
        $sql .= " ORDER BY p.product_id DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get categories
$categories = $pdo->query("SELECT DISTINCT category FROM Product WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll();

// Get statistics
$stats = [];
$stats['total_products'] = $pdo->query("SELECT COUNT(*) FROM Product")->fetchColumn();
$stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM Product WHERE stock <= 10")->fetchColumn();
$stats['out_of_stock'] = $pdo->query("SELECT COUNT(*) FROM Product WHERE stock = 0")->fetchColumn();
$stats['unavailable'] = $pdo->query("SELECT COUNT(*) FROM Product WHERE status = 'Unavailable'")->fetchColumn();
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰ Menu</button>

    <div class="admin-container">
        <?php include '../partials/admin_panel.php'; ?>

        <div class="main-content">
            <div class="header">
                <h1>Manage Products</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="openCreateModal()">+ Add Product</button>
                    <button class="btn btn-success" onclick="openCSVModal()">📁 Import CSV</button>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['total_products']; ?></h3>
                        <p>Total Products</p>
                    </div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon">⚠️</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['low_stock']; ?></h3>
                        <p>Low Stock Alert</p>
                    </div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-icon">📭</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['out_of_stock']; ?></h3>
                        <p>Out of Stock</p>
                    </div>
                </div>
                <div class="stat-card unavailable">
                    <div class="stat-icon">❌</div>
                    <div class="stat-info">
                        <h3><?php echo $stats['unavailable']; ?></h3>
                        <p>Unavailable</p>
                    </div>
                </div>
            </div>

            <!-- Top Selling Products Chart -->
            <?php
            // Get top 10 selling products for chart
            $topSellingStmt = $pdo->query("
                SELECT p.product_id, p.name, p.price, p.main_image_path, 
                       COALESCE(SUM(oi.quantity), 0) as total_sold,
                       COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue
                FROM Product p
                LEFT JOIN Order_Item oi ON p.product_id = oi.product_id
                GROUP BY p.product_id
                HAVING total_sold > 0
                ORDER BY total_sold DESC
                LIMIT 10
            ");
            $topSellingProducts = $topSellingStmt->fetchAll();
            ?>

            <?php if (count($topSellingProducts) > 0): ?>
            <div class="charts-container">
                <div class="chart-card">
                    <h2>🏆 Top Selling Products</h2>
                    <div class="chart-wrapper">
                        <canvas id="topSellingChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <h2>💰 Revenue by Product</h2>
                    <div class="chart-wrapper">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Products List -->
            <div class="top-selling-section">
                <h2>🌟 Top Performing Products</h2>
                <div class="top-products-grid">
                    <?php foreach (array_slice($topSellingProducts, 0, 5) as $index => $product): ?>
                    <div class="top-product-card">
                        <div class="rank-badge">#<?php echo $index + 1; ?></div>
                        <?php if (!empty($product['main_image_path']) && $product['main_image_path'] != '../assets/img/default.jpg'): ?>
                            <img src="<?php echo htmlspecialchars($product['main_image_path']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div style="width: 100%; height: 180px; background: rgba(255,255,255,0.2); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 48px; margin-bottom: 15px;">📦</div>
                        <?php endif; ?>
                        <div class="top-product-details">
                            <h4><?php echo htmlspecialchars($product['name']); ?></h4>
                            <div class="sales-info">
                                <span class="sales-units">📦 <?php echo $product['total_sold']; ?> units sold</span>
                                <span class="sales-revenue">💰 $<?php echo number_format($product['total_revenue'], 2); ?> revenue</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <script>
            // Chart Data
            const topSellingData = {
                labels: <?php echo json_encode(array_map(function($p) { return $p['name']; }, $topSellingProducts)); ?>,
                datasets: [{
                    label: 'Units Sold',
                    data: <?php echo json_encode(array_map(function($p) { return $p['total_sold']; }, $topSellingProducts)); ?>,
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(72, 187, 120, 0.8)',
                        'rgba(237, 137, 54, 0.8)',
                        'rgba(245, 101, 101, 0.8)',
                        'rgba(66, 153, 225, 0.8)',
                        'rgba(159, 122, 234, 0.8)',
                        'rgba(236, 201, 75, 0.8)',
                        'rgba(56, 178, 172, 0.8)',
                        'rgba(237, 100, 166, 0.8)',
                        'rgba(160, 174, 192, 0.8)'
                    ],
                    borderColor: [
                        'rgba(102, 126, 234, 1)',
                        'rgba(72, 187, 120, 1)',
                        'rgba(237, 137, 54, 1)',
                        'rgba(245, 101, 101, 1)',
                        'rgba(66, 153, 225, 1)',
                        'rgba(159, 122, 234, 1)',
                        'rgba(236, 201, 75, 1)',
                        'rgba(56, 178, 172, 1)',
                        'rgba(237, 100, 166, 1)',
                        'rgba(160, 174, 192, 1)'
                    ],
                    borderWidth: 2
                }]
            };

            const revenueData = {
                labels: <?php echo json_encode(array_map(function($p) { return $p['name']; }, $topSellingProducts)); ?>,
                datasets: [{
                    label: 'Revenue ($)',
                    data: <?php echo json_encode(array_map(function($p) { return $p['total_revenue']; }, $topSellingProducts)); ?>,
                    backgroundColor: 'rgba(72, 187, 120, 0.6)',
                    borderColor: 'rgba(72, 187, 120, 1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            };

            // Chart Configuration
            const chartConfig = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                size: 12,
                                family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14
                        },
                        bodyFont: {
                            size: 13
                        }
                    }
                }
            };

            // Initialize Charts
            $(document).ready(function() {
                // Top Selling Chart (Bar)
                const topSellingCtx = document.getElementById('topSellingChart').getContext('2d');
                new Chart(topSellingCtx, {
                    type: 'bar',
                    data: topSellingData,
                    options: {
                        ...chartConfig,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });

                // Revenue Chart (Line)
                const revenueCtx = document.getElementById('revenueChart').getContext('2d');
                new Chart(revenueCtx, {
                    type: 'line',
                    data: revenueData,
                    options: {
                        ...chartConfig,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value.toFixed(2);
                                    }
                                }
                            }
                        }
                    }
                });
            });
            </script>
            <?php endif; ?>


            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET" id="filterForm">
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                <?php echo ($category_filter == $cat['category']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="Available" <?php echo ($status_filter == 'Available') ? 'selected' : ''; ?>>Available</option>
                        <option value="Unavailable" <?php echo ($status_filter == 'Unavailable') ? 'selected' : ''; ?>>Unavailable</option>
                    </select>
                    <select name="sort_by">
                        <option value="newest" <?php echo ($sort_by == 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        <option value="oldest" <?php echo ($sort_by == 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="top_selling" <?php echo ($sort_by == 'top_selling') ? 'selected' : ''; ?>>🏆 Top Selling</option>
                        <option value="name_asc" <?php echo ($sort_by == 'name_asc') ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="name_desc" <?php echo ($sort_by == 'name_desc') ? 'selected' : ''; ?>>Name (Z-A)</option>
                        <option value="price_low" <?php echo ($sort_by == 'price_low') ? 'selected' : ''; ?>>Price (Low to High)</option>
                        <option value="price_high" <?php echo ($sort_by == 'price_high') ? 'selected' : ''; ?>>Price (High to Low)</option>
                        <option value="stock_low" <?php echo ($sort_by == 'stock_low') ? 'selected' : ''; ?>>Stock (Low to High)</option>
                        <option value="stock_high" <?php echo ($sort_by == 'stock_high') ? 'selected' : ''; ?>>Stock (High to Low)</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="manage_product.php" class="btn btn-secondary">Clear</a>
                    <button type="button" class="btn btn-warning" onclick="filterLowStock()">⚠️ Low Stock</button>
                </form>
            </div>

            <!-- Batch Actions -->
            <div class="batch-actions" id="batchActions" style="display: none;">
                <span class="selected-count">0 selected</span>
                <button type="button" class="btn btn-sm btn-success" onclick="openBatchStatusModal()">Update Status</button>
                <button type="button" class="btn btn-sm btn-info" onclick="openBatchCategoryModal()">Change Category</button>
                <button type="button" class="btn btn-sm btn-danger" onclick="batchDelete()">Delete Selected</button>
            </div>

            <!-- Products Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Category</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <?php if ($sort_by == 'top_selling'): ?>
                            <th>Sales</th>
                            <?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($products) > 0): ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><input type="checkbox" class="product-checkbox" value="<?php echo $product['product_id']; ?>"></td>
                                    <td><?php echo $product['product_id']; ?></td>
                                    <td>
                                        <?php if (!empty($product['main_image_path']) && $product['main_image_path'] != '../assets/img/default.jpg'): ?>
                                            <img src="<?php echo htmlspecialchars($product['main_image_path']); ?>" 
                                                 alt="Product" class="product-image">
                                        <?php else: ?>
                                            <div class="product-image-placeholder">📦</div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td>
                                        <span class="stock-badge <?php echo ($product['stock'] <= 10 && $product['stock'] > 0) ? 'low-stock' : ''; ?> <?php echo ($product['stock'] == 0) ? 'out-of-stock' : ''; ?>">
                                            <?php echo $product['stock']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $status = trim($product['status'] ?? '');
                                        $statusClass = (strtolower($status) == 'available') ? 'available' : 'unavailable';
                                        $statusDisplay = !empty($status) ? $status : 'Unavailable';
                                        ?>
                                        <span class="status-badge status-<?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($statusDisplay); ?>
                                        </span>
                                    </td>
                                    <?php if ($sort_by == 'top_selling'): ?>
                                    <td>
                                        <span class="sales-badge">
                                            📦 <?php echo $product['total_sold']; ?> sold
                                        </span>
                                    </td>
                                    <?php endif; ?>
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
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo ($sort_by == 'top_selling') ? '10' : '9'; ?>" style="text-align: center; padding: 40px;">
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
                <?php echo renderInput('name', 'Product Name', 'text', '', true); ?>
                <?php echo renderInput('price', 'Price', 'number', '', true, ['step' => '0.01', 'min' => '0']); ?>
                <?php echo renderTextarea('description', 'Description'); ?>
                <?php echo renderInput('category', 'Category', 'text'); ?>
                <?php echo renderInput('stock', 'Stock', 'number', '', true, ['min' => '0']); ?>
                <?php echo renderSelect('status', 'Status', ['Available' => 'Available', 'Unavailable' => 'Unavailable'], 'Available', true); ?>
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
                    <label>Product Name <span class="required">*</span></label>
                    <input type="text" name="name" id="edit_name" required>
                </div>
                <div class="form-group">
                    <label>Price <span class="required">*</span></label>
                    <input type="number" name="price" id="edit_price" step="0.01" min="0" required>
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
                    <label>Stock <span class="required">*</span></label>
                    <input type="number" name="stock" id="edit_stock" min="0" required>
                </div>
                <div class="form-group">
                    <label>Status <span class="required">*</span></label>
                    <select name="status" id="edit_status" required>
                        <option value="Available">Available</option>
                        <option value="Unavailable">Unavailable</option>
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

    <!-- CSV Upload Modal -->
    <div id="csvModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Import Products from CSV</h2>
                <button class="close" onclick="closeModal('csvModal')">&times;</button>
            </div>
            <div class="csv-instructions">
                <h3>CSV Format Instructions:</h3>
                <p>Your CSV file should have the following columns in order:</p>
                <ol>
                    <li><strong>name</strong> - Product name (required)</li>
                    <li><strong>price</strong> - Product price (required)</li>
                    <li><strong>description</strong> - Product description</li>
                    <li><strong>category</strong> - Product category</li>
                    <li><strong>stock</strong> - Stock quantity (required)</li>
                    <li><strong>status</strong> - Available or Unavailable (optional, defaults to Available)</li>
                </ol>
                <p class="csv-example"><strong>Example:</strong><br>
                Laptop,999.99,High performance laptop,Electronics,50,Available<br>
                Mouse,29.99,Wireless mouse,Electronics,100,Available</p>
                <div style="margin-top: 15px;">
                    <a href="sample_products.csv" download class="btn btn-info btn-sm">📥 Download Sample CSV</a>
                </div>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Select CSV File <span class="required">*</span></label>
                    <input type="file" name="csv_file" accept=".csv" required>
                </div>
                <button type="submit" name="upload_csv" class="btn btn-success">Import Products</button>
            </form>
        </div>
    </div>

    <!-- Batch Status Update Modal -->
    <div id="batchStatusModal" class="modal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <h2>Update Status</h2>
                <button class="close" onclick="closeModal('batchStatusModal')">&times;</button>
            </div>
            <form method="POST" id="batchStatusForm">
                <input type="hidden" name="selected_products[]" id="batchStatusProducts">
                <div class="form-group">
                    <label>Select Status <span class="required">*</span></label>
                    <select name="batch_status" required>
                        <option value="Available">Available</option>
                        <option value="Unavailable">Unavailable</option>
                    </select>
                </div>
                <button type="submit" name="batch_update_status" class="btn btn-success">Update Status</button>
            </form>
        </div>
    </div>

    <!-- Batch Category Update Modal -->
    <div id="batchCategoryModal" class="modal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <h2>Change Category</h2>
                <button class="close" onclick="closeModal('batchCategoryModal')">&times;</button>
            </div>
            <form method="POST" id="batchCategoryForm">
                <input type="hidden" name="selected_products[]" id="batchCategoryProducts">
                <div class="form-group">
                    <label>Enter Category <span class="required">*</span></label>
                    <input type="text" name="batch_category" list="categoryList" required>
                    <datalist id="categoryList">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <button type="submit" name="batch_update_category" class="btn btn-success">Update Category</button>
            </form>
        </div>
    </div>

    <!-- Hidden Forms -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="product_id" id="delete_product_id">
        <input type="hidden" name="delete_product" value="1">
    </form>

    <form method="POST" id="deleteSubImageForm" style="display: none;">
        <input type="hidden" name="sub_image_id" id="delete_sub_image_id">
        <input type="hidden" name="delete_sub_image" value="1">
    </form>

    <form method="POST" id="batchDeleteForm" style="display: none;">
        <input type="hidden" name="selected_products[]" id="batchDeleteProducts">
        <input type="hidden" name="batch_delete" value="1">
    </form>

    <script src="../assets/js/manage_product.js"></script>
</body>
</html>
l>
l>

<?php
session_start();
require_once '../helper/pdo_connection.php';
require_once '../helper/auth.php';
require_admin();

if (!$pdo) {
    die("Connection failed: Unable to connect to database");
}

$message = '';
$message_type = '';

// Handle POST actions: update status, edit address, delete order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update status
        if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['status'])) {
            $order_id = (int)$_POST['order_id'];
            $status = $_POST['status'];
            // Validate allowed statuses
            $allowed = ['Pending', 'Shipped', 'Delivered', 'Cancelled'];
            if (in_array($status, $allowed)) {
                $stmt = $pdo->prepare("UPDATE `Order` SET status = ? WHERE order_id = ?");
                if ($stmt->execute([$status, $order_id])) {
                    $message = 'Order status updated.';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating status.';
                    $message_type = 'error';
                }
            } else {
                $message = 'Invalid status value.';
                $message_type = 'error';
            }
        }

        // Edit shipping address
        if (isset($_POST['edit_address']) && isset($_POST['order_id'])) {
            $order_id = (int)$_POST['order_id'];
            
            // Get all the address fields
            $address_line1 = $_POST['address_line1'] ?? '';
            $address_line2 = $_POST['address_line2'] ?? '';
            $city = $_POST['city'] ?? '';
            $state = $_POST['state'] ?? '';
            $postal_code = $_POST['postal_code'] ?? '';
            $country = $_POST['country'] ?? '';
            
            // Validate required fields
            if (empty($address_line1) || empty($city) || empty($country)) {
                $message = 'Address Line 1, City, and Country are required.';
                $message_type = 'error';
            } else {
                // Update address directly in Order table
                $stmt = $pdo->prepare("UPDATE `Order` SET 
                    shipping_address_line1 = ?, shipping_address_line2 = ?, shipping_city = ?, 
                    shipping_state = ?, shipping_postal_code = ?, shipping_country = ?
                    WHERE order_id = ?");
                if ($stmt->execute([$address_line1, $address_line2, $city, $state, $postal_code, $country, $order_id])) {
                    $message = 'Shipping address updated.';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating address.';
                    $message_type = 'error';
                }
            }
        }

        // Optional: Delete order
        if (isset($_POST['delete_order']) && isset($_POST['order_id'])) {
            $order_id = (int)$_POST['order_id'];
            // Deleting order will cascade to Order_Item and Payment (if FK with ON DELETE CASCADE)
            $stmt = $pdo->prepare("DELETE FROM `Order` WHERE order_id = ?");
            if ($stmt->execute([$order_id])) {
                $message = 'Order deleted.';
                $message_type = 'success';
            } else {
                $message = 'Error deleting order.';
                $message_type = 'error';
            }
        }
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Filters and search/////////////////////////////////////////////////////////////
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_member = isset($_GET['member_id']) && $_GET['member_id'] !== '' ? (int)$_GET['member_id'] : null;
$filter_status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
$filter_date_from = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? $_GET['date_from'] : null;
$filter_date_to = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? $_GET['date_to'] : null;
$filter_amount_min = isset($_GET['amount_min']) && $_GET['amount_min'] !== '' ? (float)$_GET['amount_min'] : null;
$filter_amount_max = isset($_GET['amount_max']) && $_GET['amount_max'] !== '' ? (float)$_GET['amount_max'] : null;
$sort = (isset($_GET['sort']) && $_GET['sort'] === 'asc') ? 'ASC' : 'DESC';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Base query - Get orders with address info from Order table
$sql = "SELECT 
            o.order_id, o.member_id, m.username, o.order_date, 
            o.total_amount, o.status,
            o.shipping_address_line1, o.shipping_address_line2, o.shipping_city, 
            o.shipping_state, o.shipping_postal_code, o.shipping_country,
            (SELECT p.main_image_path 
             FROM Order_Item oi 
             JOIN Product p ON oi.product_id = p.product_id 
             WHERE oi.order_id = o.order_id 
             LIMIT 1) AS thumb
        FROM `Order` o
        LEFT JOIN Member m ON o.member_id = m.member_id
        WHERE 1=1";

$params = [];

// ---------- ENHANCED SEARCH ----------
if ($search !== "") {
    // If search is a pure number → exact match on order_id
    if (ctype_digit($search)) {
        $sql .= " AND o.order_id = ?";
        $params[] = (int)$search;
    } 
    // Otherwise → text search on member, address, and product names
    else {
        $sql .= " AND (m.username LIKE ? 
                  OR CONCAT_WS(' ', o.shipping_address_line1, o.shipping_address_line2, o.shipping_city, o.shipping_state, o.shipping_postal_code, o.shipping_country) LIKE ?
                  OR EXISTS (
                      SELECT 1 FROM Order_Item oi2 
                      JOIN Product p2 ON oi2.product_id = p2.product_id 
                      WHERE oi2.order_id = o.order_id 
                      AND p2.name LIKE ?
                  ))";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
}

// ---------- STATUS FILTER ----------
if ($filter_status) {
    $sql .= " AND o.status = ?";
    $params[] = $filter_status;
}

// ---------- MEMBER FILTER ----------
if ($filter_member) {
    $sql .= " AND o.member_id = ?";
    $params[] = $filter_member;
}

// ---------- DATE RANGE FILTER ----------
if ($filter_date_from) {
    $sql .= " AND o.order_date >= ?";
    $params[] = $filter_date_from;
}
if ($filter_date_to) {
    $sql .= " AND o.order_date <= ?";
    $params[] = $filter_date_to . ' 23:59:59';
}

// ---------- AMOUNT RANGE FILTER ----------
if ($filter_amount_min !== null) {
    $sql .= " AND o.total_amount >= ?";
    $params[] = $filter_amount_min;
}
if ($filter_amount_max !== null) {
    $sql .= " AND o.total_amount <= ?";
    $params[] = $filter_amount_max;
}

// Count total results before pagination
$count_sql = "SELECT COUNT(*) FROM ($sql) as count_query";
try {
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_results = $count_stmt->fetchColumn();
    $total_pages = ceil($total_results / $per_page);
} catch (PDOException $e) {
    $total_results = 0;
    $total_pages = 1;
}

// ---------- SORT & PAGINATION ----------
$sql .= " ORDER BY o.order_date $sort, o.order_id DESC LIMIT $per_page OFFSET $offset";

// ---------- PREPARE & EXECUTE ----------
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}

// Fetch members for filter dropdown
try {
    $members_stmt = $pdo->query("SELECT member_id, username FROM Member ORDER BY username ASC");
    $members = $members_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $members = [];
}
//////////////////////////////////////////////////////////////end of search
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/admin_panel.css">
    <link rel="stylesheet" href="../assets/css/manage_orders.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>

    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰ Menu</button>

    <div class="admin-container">

        <?php include '../partials/admin_panel.php'; ?>

        <div class="main-content">

            <div class="header">
                <h1>Manage Orders</h1>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="GET">
                    <!-- Basic Search Row -->
                    <div class="search-row">
                        <input type="text" name="search" class="search-input" placeholder="Search order ID, member, address or product..." value="<?php echo htmlspecialchars($search); ?>">
                        
                        <select name="status" class="filter-select">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php if ($filter_status == 'Pending') echo 'selected'; ?>>Pending</option>
                            <option value="Shipped" <?php if ($filter_status == 'Shipped') echo 'selected'; ?>>Shipped</option>
                            <option value="Delivered" <?php if ($filter_status == 'Delivered') echo 'selected'; ?>>Delivered</option>
                            <option value="Cancelled" <?php if ($filter_status == 'Cancelled') echo 'selected'; ?>>Cancelled</option>
                        </select>

                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="manage_orders.php" class="btn btn-secondary">Clear</a>
                        <button type="button" class="btn btn-outline" onclick="toggleAdvancedSearch()">
                            <span id="advancedToggleText">⚙ Advanced</span>
                        </button>
                    </div>

                    <!-- Advanced Search Section (Hidden by Default) -->
                    <div id="advancedSearch" class="advanced-search" style="display: none;">
                        <div class="advanced-row">
                            <div class="filter-group">
                                <label>Member</label>
                                <select name="member_id" class="filter-select">
                                    <option value="">All Members</option>
                                    <?php foreach ($members as $m): ?>
                                        <option value="<?php echo $m['member_id']; ?>" <?php if ($filter_member == $m['member_id']) echo 'selected'; ?>><?php echo htmlspecialchars($m['username']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label>Date From</label>
                                <input type="date" name="date_from" class="filter-input" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                            </div>

                            <div class="filter-group">
                                <label>Date To</label>
                                <input type="date" name="date_to" class="filter-input" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                            </div>

                            <div class="filter-group">
                                <label>Amount Min (RM)</label>
                                <input type="number" name="amount_min" step="0.01" class="filter-input" placeholder="0.00" value="<?php echo htmlspecialchars($filter_amount_min); ?>">
                            </div>

                            <div class="filter-group">
                                <label>Amount Max (RM)</label>
                                <input type="number" name="amount_max" step="0.01" class="filter-input" placeholder="0.00" value="<?php echo htmlspecialchars($filter_amount_max); ?>">
                            </div>

                            <div class="filter-group">
                                <label>Sort by Date</label>
                                <select name="sort" class="filter-select">
                                    <option value="desc">Newest First</option>
                                    <option value="asc" <?php if ($sort === 'ASC') echo 'selected'; ?>>Oldest First</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Member</th>
                            <th>Date</th>
                            <th>Products</th>
                            <th>Total (RM)</th>
                            <th>Status</th>
                            <th>Shipping Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $row): ?>
                                <?php
                                // Fetch products for this order
                                $product_stmt = $pdo->prepare("SELECT oi.quantity, oi.price, p.name, p.main_image_path 
                                                               FROM Order_Item oi 
                                                               JOIN Product p ON oi.product_id = p.product_id 
                                                               WHERE oi.order_id = ?");
                                $product_stmt->execute([$row['order_id']]);
                                $products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <tr class="row-<?= $row['status'] ?>">
                                    <td><?= $row['order_id'] ?></td>
                                    <td><?= htmlspecialchars($row['username']) ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($row['order_date'])) ?></td>
                                    <td>
                                        <div class="products-list">
                                            <?php foreach ($products as $product): ?>
                                                <div class="product-item">
                                                    <?php if ($product['main_image_path']): ?>
                                                        <img src="<?= htmlspecialchars($product['main_image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-thumb">
                                                    <?php else: ?>
                                                        <img src="../assets/img/default.jpg" alt="default" class="product-thumb">
                                                    <?php endif; ?>
                                                    <div class="product-details">
                                                        <span class="product-name" title="<?= htmlspecialchars($product['name']) ?>"><?= htmlspecialchars($product['name']) ?></span>
                                                        <span class="product-info">Qty: <?= $product['quantity'] ?> × RM<?= number_format($product['price'], 2) ?></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                    <td><strong>RM <?= number_format($row['total_amount'], 2) ?></strong></td>
                                    <td>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
                                            <select name="status">
                                                <option value="Pending" <?php if ($row['status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                                                <option value="Shipped" <?php if ($row['status'] == 'Shipped') echo 'selected'; ?>>Shipped</option>
                                                <option value="Delivered" <?php if ($row['status'] == 'Delivered') echo 'selected'; ?>>Delivered</option>
                                                <option value="Cancelled" <?php if ($row['status'] == 'Cancelled') echo 'selected'; ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" name="update_status" class="btn btn-sm btn-primary">Update</button>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="address-display">
                                            <div class="address-text">
                                                <?= htmlspecialchars($row['shipping_address_line1']) ?><br>
                                                <?php if ($row['shipping_address_line2']): ?>
                                                    <?= htmlspecialchars($row['shipping_address_line2']) ?><br>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($row['shipping_city']) ?>, <?= htmlspecialchars($row['shipping_state']) ?> <?= htmlspecialchars($row['shipping_postal_code']) ?><br>
                                                <?= htmlspecialchars($row['shipping_country']) ?>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAddressForm(<?= $row['order_id'] ?>)">Edit</button>
                                        </div>
                                        <form method="POST" class="address-form" id="address-form-<?= $row['order_id'] ?>" style="display:none;">
                                            <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
                                            <div class="form-grid">
                                                <div>
                                                    <input type="text" name="address_line1" value="<?= htmlspecialchars($row['shipping_address_line1'] ?? '') ?>" class="address-input" placeholder="Address Line 1*" required>
                                                </div>
                                                <div>
                                                    <input type="text" name="address_line2" value="<?= htmlspecialchars($row['shipping_address_line2'] ?? '') ?>" class="address-input" placeholder="Address Line 2">
                                                </div>
                                                <div>
                                                    <input type="text" name="city" value="<?= htmlspecialchars($row['shipping_city'] ?? '') ?>" class="address-input" placeholder="City*" required>
                                                </div>
                                                <div>
                                                    <input type="text" name="state" value="<?= htmlspecialchars($row['shipping_state'] ?? '') ?>" class="address-input" placeholder="State">
                                                </div>
                                                <div>
                                                    <input type="text" name="postal_code" value="<?= htmlspecialchars($row['shipping_postal_code'] ?? '') ?>" class="address-input" placeholder="Postal Code">
                                                </div>
                                                <div>
                                                    <input type="text" name="country" value="<?= htmlspecialchars($row['shipping_country'] ?? '') ?>" class="address-input" placeholder="Country*" required>
                                                </div>
                                            </div>
                                            <div class="btn-group" style="margin-top: 8px;">
                                                <button type="submit" name="edit_address" class="btn btn-sm btn-success">Save</button>
                                                <button type="button" class="btn btn-sm btn-secondary" onclick="toggleAddressForm(<?= $row['order_id'] ?>)">Cancel</button>
                                            </div>
                                        </form>
                                    </td>
                                    <td>
                                        <a class="btn" href="order_details.php?order_id=<?= $row['order_id'] ?>">View</a>
                                        <button class="btn btn-danger" onclick="confirmDelete(<?= $row['order_id'] ?>)">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center;padding:40px;">No orders found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <?php if ($total_pages > 1): ?>
                    <a href="manage_orders.php?page=1&search=<?php echo htmlspecialchars($search); ?>&member_id=<?php echo htmlspecialchars($filter_member); ?>&status=<?php echo htmlspecialchars($filter_status); ?>&date_from=<?php echo htmlspecialchars($filter_date_from); ?>&date_to=<?php echo htmlspecialchars($filter_date_to); ?>&amount_min=<?php echo htmlspecialchars($filter_amount_min); ?>&amount_max=<?php echo htmlspecialchars($filter_amount_max); ?>&sort=<?php echo htmlspecialchars($sort); ?>" class="btn">First</a>
                    <a href="manage_orders.php?page=<?php echo max(1, $page - 1); ?>&search=<?php echo htmlspecialchars($search); ?>&member_id=<?php echo htmlspecialchars($filter_member); ?>&status=<?php echo htmlspecialchars($filter_status); ?>&date_from=<?php echo htmlspecialchars($filter_date_from); ?>&date_to=<?php echo htmlspecialchars($filter_date_to); ?>&amount_min=<?php echo htmlspecialchars($filter_amount_min); ?>&amount_max=<?php echo htmlspecialchars($filter_amount_max); ?>&sort=<?php echo htmlspecialchars($sort); ?>" class="btn">Previous</a>
                    <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                    <a href="manage_orders.php?page=<?php echo min($total_pages, $page + 1); ?>&search=<?php echo htmlspecialchars($search); ?>&member_id=<?php echo htmlspecialchars($filter_member); ?>&status=<?php echo htmlspecialchars($filter_status); ?>&date_from=<?php echo htmlspecialchars($filter_date_from); ?>&date_to=<?php echo htmlspecialchars($filter_date_to); ?>&amount_min=<?php echo htmlspecialchars($filter_amount_min); ?>&amount_max=<?php echo htmlspecialchars($filter_amount_max); ?>&sort=<?php echo htmlspecialchars($sort); ?>" class="btn">Next</a>
                    <a href="manage_orders.php?page=<?php echo $total_pages; ?>&search=<?php echo htmlspecialchars($search); ?>&member_id=<?php echo htmlspecialchars($filter_member); ?>&status=<?php echo htmlspecialchars($filter_status); ?>&date_from=<?php echo htmlspecialchars($filter_date_from); ?>&date_to=<?php echo htmlspecialchars($filter_date_to); ?>&amount_min=<?php echo htmlspecialchars($filter_amount_min); ?>&amount_max=<?php echo htmlspecialchars($filter_amount_max); ?>&sort=<?php echo htmlspecialchars($sort); ?>" class="btn">Last</a>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- DELETE FORM -->
    <form method="POST" id="deleteForm" style="display:none;">
        <input type="hidden" name="order_id" id="delete_order_id">
        <input type="hidden" name="delete_order" value="1">
    </form>

    <script>
        function confirmDelete(id) {
            if (confirm("Are you sure you want to delete this order? This action cannot be undone.")) {
                document.getElementById('delete_order_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        function toggleAdvancedSearch() {
            var advancedSearch = document.getElementById('advancedSearch');
            var toggleText = document.getElementById('advancedToggleText');
            if (advancedSearch.style.display === 'none') {
                advancedSearch.style.display = 'block';
                toggleText.innerHTML = '▲ Hide Advanced';
            } else {
                advancedSearch.style.display = 'none';
                toggleText.innerHTML = '⚙ Advanced';
            }
        }

        function toggleAddressForm(orderId) {
            var addressForm = document.getElementById('address-form-' + orderId);
            if (addressForm.style.display === 'none') {
                addressForm.style.display = 'block';
            } else {
                addressForm.style.display = 'none';
            }
        }

        // Auto-show advanced search if any advanced filters are active
        window.addEventListener('DOMContentLoaded', function() {
            <?php if ($filter_member || $filter_date_from || $filter_date_to || $filter_amount_min !== null || $filter_amount_max !== null): ?>
                document.getElementById('advancedSearch').style.display = 'block';
                document.getElementById('advancedToggleText').innerHTML = '▲ Hide Advanced';
            <?php endif; ?>
        });
    </script>

</body>

</html>
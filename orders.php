<?php
// Start with error suppression for cleaner output
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

session_start();
require_once 'helper/pdo_connection.php';
require_once 'helper/auth.php';
is_logged_in();

// Redirect if not logged in
if (!isset($_SESSION['member'])) {
    header('Location: login.php');
    exit;
}

// Get member info
$member_id = $_SESSION['member']['id'];

// Initialize ALL variables with default values FIRST
$search = '';
$sort = 'DESC';
$filter_status = null;
$filter_date_from = null;
$filter_date_to = null;
$filter_amount_min = null;
$filter_amount_max = null;

// Now check and override if GET parameters exist
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

if (isset($_GET['sort']) && strtolower($_GET['sort']) === 'asc') {
    $sort = 'ASC';
}

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $filter_status = $_GET['status'];
}

if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
    $filter_date_from = $_GET['date_from'];
}

if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
    $filter_date_to = $_GET['date_to'];
}

if (isset($_GET['amount_min']) && $_GET['amount_min'] !== '') {
    $filter_amount_min = (float)$_GET['amount_min'];
}

if (isset($_GET['amount_max']) && $_GET['amount_max'] !== '') {
    $filter_amount_max = (float)$_GET['amount_max'];
}

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Build database query
$sql = "SELECT o.order_id, o.order_date, o.total_amount, o.status,
        o.shipping_address_line1, o.shipping_address_line2, o.shipping_city, 
        o.shipping_state, o.shipping_postal_code, o.shipping_country,
        (SELECT p.main_image_path FROM Order_Item oi JOIN Product p ON oi.product_id = p.product_id 
         WHERE oi.order_id = o.order_id LIMIT 1) AS thumb
        FROM `Order` o
        WHERE o.member_id = ?";

$params = [$member_id];

// Add search condition if search is not empty
if ($search !== '') {
    // If search is a pure number → exact match on order_id
    if (ctype_digit($search)) {
        $sql .= " AND o.order_id = ?";
        $params[] = (int)$search;
    }
    // Otherwise, text search → search in address fields and product names
    else {
        $sql .= " AND (CONCAT_WS(' ', o.shipping_address_line1, o.shipping_address_line2, o.shipping_city, o.shipping_state, o.shipping_postal_code, o.shipping_country) LIKE ?
                  OR EXISTS (
                      SELECT 1 FROM Order_Item oi2 
                      JOIN Product p2 ON oi2.product_id = p2.product_id 
                      WHERE oi2.order_id = o.order_id 
                      AND p2.name LIKE ?
                  ))";
        $like = "%$search%";
        $params[] = $like;
        $params[] = $like;
    }
}

// Add status filter
if ($filter_status) {
    $sql .= " AND o.status = ?";
    $params[] = $filter_status;
}

// Add date range filter
if ($filter_date_from) {
    $sql .= " AND o.order_date >= ?";
    $params[] = $filter_date_from;
}
if ($filter_date_to) {
    $sql .= " AND o.order_date <= ?";
    $params[] = $filter_date_to . ' 23:59:59';
}

// Add amount range filter
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

// Add sorting and pagination
$sql .= " ORDER BY o.order_date $sort, o.order_id DESC LIMIT $per_page OFFSET $offset";

// Execute query
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $orders = [];
    error_log("Database error: " . $e->getMessage());
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>My Orders</title>
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/styles.css">

</head>

<body>
    <?php
    $title = "Home";
    include 'partials/header.php';

    ?>
    <div class="user-container">

        <h1>My Orders</h1>

        <!-- Filter/Search -->
        <div class="filter-bar">
            <div class="filter-header">
                <button type="button" class="toggle-advanced" onclick="toggleAdvanced()">
                    <span id="toggle-text">Show Advanced Search</span>
                    <span id="toggle-icon">▼</span>
                </button>
            </div>

            <form method="GET" id="searchForm">
                <!-- Basic Search -->
                <div class="basic-search">
                    <input type="text" name="search" placeholder="Search order id, product or address..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" class="search-input">

                    <label>Sort</label>
                    <select name="sort">
                        <option value="desc" <?php if ($sort === 'DESC') echo ' selected'; ?>>Newest</option>
                        <option value="asc" <?php if ($sort === 'ASC') echo ' selected'; ?>>Oldest</option>
                    </select>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="orders.php" class="btn btn-secondary">Clear</a>
                    </div>
                </div>

                <!-- Advanced Search (Hidden by default) -->
                <div class="advanced-search" id="advancedSearch" style="display: none;">
                    <div class="advanced-row">
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="">All Statuses</option>
                                <option value="Pending" <?php if ($filter_status == 'Pending') echo ' selected'; ?>>Pending</option>
                                <option value="Shipped" <?php if ($filter_status == 'Shipped') echo ' selected'; ?>>Shipped</option>
                                <option value="Delivered" <?php if ($filter_status == 'Delivered') echo ' selected'; ?>>Delivered</option>
                                <option value="Cancelled" <?php if ($filter_status == 'Cancelled') echo ' selected'; ?>>Cancelled</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Date From</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                        </div>

                        <div class="filter-group">
                            <label>Date To</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                        </div>
                    </div>

                    <div class="advanced-row">
                        <div class="filter-group">
                            <label>Min Amount (RM)</label>
                            <input type="number" name="amount_min" step="0.01" placeholder="0.00" value="<?php echo htmlspecialchars($filter_amount_min); ?>">
                        </div>

                        <div class="filter-group">
                            <label>Max Amount (RM)</label>
                            <input type="number" name="amount_max" step="0.01" placeholder="0.00" value="<?php echo htmlspecialchars($filter_amount_max); ?>">
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Results Info -->
        <?php if ($total_results > 0): ?>
            <div class="results-info">
                <span>Showing <strong><?php echo ($offset + 1); ?>-<?php echo min($offset + $per_page, $total_results); ?></strong> of <strong><?php echo $total_results; ?></strong> orders</span>
            </div>
        <?php endif; ?>

        <!-- Orders Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Date</th>
                        <th>Total (RM)</th>
                        <th>Thumb</th>
                        <th>Status</th>
                        <th>Shipping Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders && count($orders) > 0): ?>
                        <?php foreach ($orders as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['order_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['order_date']); ?></td>
                                <td><?php echo number_format($row['total_amount'], 2); ?></td>
                                <td>
                                    <?php if (!empty($row['thumb'])): ?>
                                        <img src="<?php echo htmlspecialchars($row['thumb']); ?>" alt="Order thumbnail" class="thumb">
                                    <?php else: ?>
                                        <img src="../assets/img/default.jpg" alt="Default thumbnail" class="thumb">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status <?php echo htmlspecialchars($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $address_line1 = htmlspecialchars($row['shipping_address_line1'] ?? '');
                                    $address_line2 = htmlspecialchars($row['shipping_address_line2'] ?? '');
                                    $city = htmlspecialchars($row['shipping_city'] ?? '');
                                    $state = htmlspecialchars($row['shipping_state'] ?? '');
                                    $postal_code = htmlspecialchars($row['shipping_postal_code'] ?? '');
                                    $country = htmlspecialchars($row['shipping_country'] ?? '');

                                    // Build address string, skipping empty parts
                                    $address_parts = array_filter([
                                        $address_line1,
                                        $address_line2,
                                        $city,
                                        $state,
                                        $postal_code,
                                        $country
                                    ]);
                                    echo implode(', ', $address_parts);
                                    ?>
                                </td>
                                <td>
                                    <a class="btn btn-primary" href="order_details.php?order_id=<?php echo urlencode($row['order_id']); ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:40px;">No orders found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $query_params = [
                'search' => $search,
                'sort' => strtolower($sort),
                'status' => $filter_status,
                'date_from' => $filter_date_from,
                'date_to' => $filter_date_to,
                'amount_min' => $filter_amount_min,
                'amount_max' => $filter_amount_max
            ];

            function build_query($params, $page)
            {
                $params['page'] = $page;
                return '?' . http_build_query(array_filter($params, function ($val) {
                    return $val !== null && $val !== '';
                }));
            }
            ?>

            <a href="orders.php<?php echo build_query($query_params, 1); ?>" class="btn <?php if ($page == 1) echo 'disabled'; ?>">First</a>
            <a href="orders.php<?php echo build_query($query_params, max(1, $page - 1)); ?>" class="btn <?php if ($page == 1) echo 'disabled'; ?>">Previous</a>
            <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
            <a href="orders.php<?php echo build_query($query_params, min($total_pages, $page + 1)); ?>" class="btn <?php if ($page == $total_pages) echo 'disabled'; ?>">Next</a>
            <a href="orders.php<?php echo build_query($query_params, $total_pages); ?>" class="btn <?php if ($page == $total_pages) echo 'disabled'; ?>">Last</a>
        </div>
    <?php endif; ?>

    <script>
        function toggleAdvanced() {
            const advancedDiv = document.getElementById('advancedSearch');
            const toggleText = document.getElementById('toggle-text');
            const toggleIcon = document.getElementById('toggle-icon');

            if (advancedDiv.style.display === 'none') {
                advancedDiv.style.display = 'block';
                toggleText.textContent = 'Hide Advanced Search';
                toggleIcon.textContent = '▲';
            } else {
                advancedDiv.style.display = 'none';
                toggleText.textContent = 'Show Advanced Search';
                toggleIcon.textContent = '▼';
            }
        }

        // Auto-show advanced search if any advanced filters are active
        window.addEventListener('DOMContentLoaded', function() {
            const hasAdvancedFilters = <?php
                                        echo ($filter_status || $filter_date_from || $filter_date_to ||
                                            $filter_amount_min !== null || $filter_amount_max !== null) ? 'true' : 'false';
                                        ?>;

            if (hasAdvancedFilters) {
                toggleAdvanced();
            }
        });
    </script>
</body>

</html>

<?php include 'partials/footer.php'; ?>
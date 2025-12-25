<?php
// Start with error suppression for cleaner output
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

session_start();
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';
is_logged_in();

// Redirect if not logged in
if (!isset($_SESSION['member'])) {
    header('Location: ../../login.php');
    exit;
}

// Get member info
$member_id = $_SESSION['member']['id'];

// Initialize message variables
$success_message = '';
$error_message = '';

// Handle cancel order request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = (int)$_POST['order_id'];
    
    try {
        // Verify the order belongs to the logged-in member and status is Pending
        $check_sql = "SELECT status FROM `Order` WHERE order_id = ? AND member_id = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$order_id, $member_id]);
        $order = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            if ($order['status'] === 'Pending') {
                // Update status to Cancelled
                $update_sql = "UPDATE `Order` SET status = 'Cancelled' WHERE order_id = ? AND member_id = ?";
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute([$order_id, $member_id]);
                $success_message = "Order #$order_id has been cancelled successfully. Your refund will be processed within 5-7 business days.";
            } else {
                $error_message = "Only pending orders can be cancelled. This order is already {$order['status']}.";
            }
        } else {
            $error_message = "Order not found or you don't have permission to cancel this order.";
        }
    } catch (PDOException $e) {
        $error_message = "Error cancelling order: " . $e->getMessage();
        error_log("Cancel order error: " . $e->getMessage());
    }
}

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
     WHERE oi.order_id = o.order_id LIMIT 1) AS thumb,
    (SELECT COUNT(*) FROM Order_Item oi_count WHERE oi_count.order_id = o.order_id) AS item_count,
    (SELECT COUNT(*) FROM Review r
        JOIN Order_Item oi_review ON r.product_id = oi_review.product_id
        WHERE oi_review.order_id = o.order_id AND r.member_id = o.member_id) AS reviewed_count
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
    // Support semantic aliases like 'active' → Pending or Shipped
    if (strtolower($filter_status) === 'active') {
        $sql .= " AND o.status IN ('Pending','Shipped')";
    } elseif (strtolower($filter_status) === 'completed') {
        // Treat 'completed' as Delivered
        $sql .= " AND o.status = 'Delivered'";
    } else {
        $sql .= " AND o.status = ?";
        $params[] = $filter_status;
    }
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
    <link rel="stylesheet" href="../../assets/css/orders.css">
    <link rel="stylesheet" href="../../assets/css/styles.css">
    <link rel="stylesheet" href="../../assets/css/dark_mode_style.css">
    <link rel="stylesheet" href="../../assets/css/review.css">
</head>

<?php
$title = "Home";
include '../../partials/header.php';

?>
<div class="user-container">

    <h1>My Orders</h1>

    <!-- Success/Error Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            ✓ <?php echo htmlspecialchars($success_message); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            ⚠ <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

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
                    <th>Review</th>
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
                                    <img src="../../<?php echo htmlspecialchars($row['thumb']); ?>" alt="Order thumbnail" class="thumb">
                                <?php else: ?>
                                    <img src="../../assets/img/default.jpg" alt="Default thumbnail" class="thumb">
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
                                <?php if ($row['status'] === 'Pending'): ?>
                                    <form method="POST" action="orders.php" style="display:inline; margin-top: 8px;" onsubmit="return confirm('Are you sure you want to cancel Order #<?php echo htmlspecialchars($row['order_id']); ?>? This action cannot be undone.');">
                                        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($row['order_id']); ?>">
                                        <button type="submit" name="cancel_order" class="btn btn-danger">Cancel</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $item_count = (int)($row['item_count'] ?? 0);
                                $reviewed_count = (int)($row['reviewed_count'] ?? 0);
                                $all_reviewed = $item_count > 0 && $reviewed_count >= $item_count;

                                if ($row['status'] === 'Delivered') {
                                    if ($all_reviewed) {
                                        echo '<button class="btn btn-success" onclick="openReviewModal(' . (int)$row['order_id'] . ')">View Review</button>';
                                    } else {
                                        echo '<button class="btn btn-secondary" onclick="openReviewModal(' . (int)$row['order_id'] . ')">Review</button>';
                                    }
                                } elseif ($row['status'] === 'Shipped') {
                                    $tip = 'Shipped. You can leave a review once the order is Delivered.';
                                    echo '<span class="review-pill review-pill-transit has-tooltip" title="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '" data-tooltip="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '">Shipped</span>';
                                } elseif ($row['status'] === 'Pending') {
                                    $tip = 'Not delivered yet. Reviews become available when status is Delivered.';
                                    echo '<span class="review-pill review-pill-pending has-tooltip" title="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '" data-tooltip="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '">Pending</span>';
                                } else {
                                    $tip = 'Reviews are not available for this status.';
                                    echo '<span class="review-pill review-pill-disabled has-tooltip" title="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '" data-tooltip="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '">N/A</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align:center; padding:40px;">No orders found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<!-- Review Modal -->
<div id="reviewModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 9999; align-items: center; justify-content: center;">
    <div class="modal-content" style="background: #ffffff; border-radius: 16px; width: 90%; max-width: 600px; max-height: 85vh; position: relative; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3); display: flex; flex-direction: column;">
        <button class="modal-close" onclick="closeReviewModal()" style="position: absolute; top: 12px; right: 12px; background: none; border: none; font-size: 28px; cursor: pointer; color: #94a3b8; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 50%; z-index: 10;">&times;</button>
        <div id="modalBody" style="padding: 20px; overflow-y: auto; flex: 1;">
            <p>Loading...</p>
        </div>
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

    // Review Modal Functions
    function openReviewModal(orderId) {
        const modal = document.getElementById('reviewModal');
        const modalBody = document.getElementById('modalBody');
        modalBody.innerHTML = '<p style="text-align: center; padding: 20px;">Loading review...</p>';
        modal.style.display = 'flex';

        fetch('../../pages/review/review.php?order_id=' + orderId + '&ajax=1')
            .then(response => response.text())
            .then(html => {
                modalBody.innerHTML = html;
                attachFormListener();
                attachEditButtonListeners();
            })
            .catch(error => {
                modalBody.innerHTML = '<div style="color: red; padding: 20px;">Error loading review: ' + error + '</div>';
            });
    }

    function closeReviewModal() {
        document.getElementById('reviewModal').style.display = 'none';
    }

    function attachFormListener() {
        const forms = document.querySelectorAll('.review-form');
        forms.forEach(form => {
            // Remove old listener if exists
            const oldListener = form._submitHandler;
            if (oldListener) {
                form.removeEventListener('submit', oldListener);
            }
            
            const submitHandler = function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                
                fetch('../../pages/review/review_post.php?ajax=1', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modal = document.getElementById('reviewModal');
                        const modalBody = document.getElementById('modalBody');
                        modalBody.innerHTML = '<div style="text-align: center; padding: 40px;"><div style="color: #10b981; font-size: 16px; font-weight: 600; margin-bottom: 16px;">✓ ' + data.message + '</div><button class="btn btn-primary" onclick="closeReviewModal()">Close</button></div>';
                        setTimeout(() => {
                            closeReviewModal();
                            location.reload();
                        }, 2000);
                    } else {
                        const modalBody = document.getElementById('modalBody');
                        const alert = document.createElement('div');
                        alert.className = 'alert alert-error';
                        alert.style.cssText = 'background: #fee; color: #c00; padding: 12px; border-radius: 6px; margin-bottom: 12px;';
                        alert.textContent = data.message || 'An error occurred';
                        modalBody.insertBefore(alert, modalBody.firstChild);
                    }
                })
                .catch(error => {
                    const modalBody = document.getElementById('modalBody');
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-error';
                    alert.style.cssText = 'background: #fee; color: #c00; padding: 12px; border-radius: 6px; margin-bottom: 12px;';
                    alert.textContent = 'Error submitting review';
                    modalBody.insertBefore(alert, modalBody.firstChild);
                });
            };
            
            form._submitHandler = submitHandler;
            form.addEventListener('submit', submitHandler);
        });
    }

    function attachEditButtonListeners() {
        const editButtons = document.querySelectorAll('.toggle-edit-btn');
        editButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const form = document.getElementById('edit-form-' + productId);
                if (form) {
                    if (form.style.display === 'none' || form.style.display === '') {
                        form.style.display = 'flex';
                        form.style.flexDirection = 'column';
                        attachFormListener();
                    } else {
                        form.style.display = 'none';
                    }
                }
            });
        });
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(e) {
        const modal = document.getElementById('reviewModal');
        if (e.target === modal) {
            closeReviewModal();
        }
    });
</script>
</body>

</html>

<?php include '../../partials/footer.php'; ?>
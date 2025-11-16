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
        if (isset($_POST['edit_address']) && isset($_POST['order_id']) && isset($_POST['shipping_address'])) {
            $order_id = (int)$_POST['order_id'];
            $addr = $_POST['shipping_address'];
            $stmt = $pdo->prepare("UPDATE `Order` SET shipping_address = ? WHERE order_id = ?");
            if ($stmt->execute([$addr, $order_id])) {
                $message = 'Shipping address updated.';
                $message_type = 'success';
            } else {
                $message = 'Error updating address.';
                $message_type = 'error';
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

// Base query
$sql = "SELECT 
            o.order_id, o.member_id, m.username, o.order_date, 
            o.total_amount, o.status, o.shipping_address,
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
                  OR o.shipping_address LIKE ? 
                  OR EXISTS (
                      SELECT 1 FROM Order_Item oi2 
                      JOIN Product p2 ON oi2.product_id = p2.product_id 
                      WHERE oi2.order_id = o.order_id 
                      AND p2.product_name LIKE ?
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
    <!--<link rel="stylesheet" href="../assets/css/admin_panel.css">-->
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
                <form method="GET" style="display:flex; gap:15px; flex:1; align-items:center;">
                    <input type="text" name="search" placeholder="Search order id, member, address or product..." value="<?php echo htmlspecialchars($search); ?>">

                    <label>Member</label>
                    <select name="member_id" onchange="this.form.submit()">
                        <option value="">-- All Members --</option>
                        <?php foreach ($members as $m): ?>
                            <option value="<?php echo $m['member_id']; ?>" <?php if ($filter_member == $m['member_id']) echo 'selected'; ?>><?php echo htmlspecialchars($m['username']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label>Status</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">-- All Statuses --</option>
                        <option value="Pending" <?php if ($filter_status == 'Pending') echo 'selected'; ?>>Pending</option>
                        <option value="Shipped" <?php if ($filter_status == 'Shipped') echo 'selected'; ?>>Shipped</option>
                        <option value="Delivered" <?php if ($filter_status == 'Delivered') echo 'selected'; ?>>Delivered</option>
                        <option value="Cancelled" <?php if ($filter_status == 'Cancelled') echo 'selected'; ?>>Cancelled</option>
                    </select>

                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>">

                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>">

                    <label>Amount Min</label>
                    <input type="number" name="amount_min" step="0.01" value="<?php echo htmlspecialchars($filter_amount_min); ?>">

                    <label>Amount Max</label>
                    <input type="number" name="amount_max" step="0.01" value="<?php echo htmlspecialchars($filter_amount_max); ?>">

                    <label>Sort by date</label>
                    <select name="sort" onchange="this.form.submit();">
                        <option value="desc">Newest</option>
                        <option value="asc" <?php if ($sort === 'ASC') echo 'selected'; ?>>Oldest</option>
                    </select>

                    <button type="submit" class="btn btn-primary">Apply</button>
                    <a href="manage_orders.php" class="btn" style="background:#cbd5e0;">Clear</a>
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
                            <th>Total (RM)</th>
                            <th>Thumb</th>
                            <th>Status</th>
                            <th>Shipping Address</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $row): ?>
                                <tr>
                                    <td><?= $row['order_id'] ?></td>
                                    <td><?= htmlspecialchars($row['username']) ?></td>
                                    <td><?= $row['order_date'] ?></td>
                                    <td><?= number_format($row['total_amount'], 2) ?></td>
                                    <td>
                                        <?php if ($row['thumb']): ?>
                                            <img src="<?= htmlspecialchars($row['thumb']) ?>" alt="thumb" class="thumb">
                                        <?php else: ?>
                                            <img src="../assets/img/default.jpg" alt="thumb" class="thumb">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
                                            <select name="status">
                                                <option value="Pending" <?php if ($row['status'] == 'Pending') echo 'selected'; ?>>Pending</option>
                                                <option value="Shipped" <?php if ($row['status'] == 'Shipped') echo 'selected'; ?>>Shipped</option>
                                                <option value="Delivered" <?php if ($row['status'] == 'Delivered') echo 'selected'; ?>>Delivered</option>
                                                <option value="Cancelled" <?php if ($row['status'] == 'Cancelled') echo 'selected'; ?>>Cancelled</option>
                                            </select>
                                            <button type="submit" name="update_status" class="btn btn-primary">Save</button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" class="inline-form">
                                            <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
                                            <input type="text" name="shipping_address" value="<?= htmlspecialchars($row['shipping_address']) ?>" class="address-input">
                                            <button type="submit" name="edit_address" class="btn btn-success">Save</button>
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
    </script>

</body>

</html>

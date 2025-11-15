
<?php
// Start with error suppression for cleaner output
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

session_start();
require_once 'helper/connection.php';
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

// Now check and override if GET parameters exist
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

if (isset($_GET['sort']) && strtolower($_GET['sort']) === 'asc') {
    $sort = 'ASC';
}

// Build database query
$sql = "SELECT o.order_id, o.order_date, o.total_amount, o.status, o.shipping_address,
        (SELECT p.main_image_path FROM Order_Item oi JOIN Product p ON oi.product_id = p.product_id 
         WHERE oi.order_id = o.order_id LIMIT 1) AS thumb
        FROM `Order` o
        WHERE o.member_id = ?";

$params = [$member_id];
$types = 'i';

// Add search condition if search is not empty
if ($search !== '') {
    $sql .= " AND (o.order_id = ? OR o.shipping_address LIKE ?)";
    $params[] = $search;
    $params[] = "%$search%";
    $types .= 'is';
}

// Add sorting
$sql .= " ORDER BY o.order_date $sort, o.order_id DESC";

// Execute query
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<?php 
  $title = "Home";
  include 'partials/header.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>My Orders</title>
    <link rel="stylesheet" href="../assets/css/orders.css">
</head>
<body>
    <div class="user-container" style="padding:30px; max-width:1200px; margin:auto;">

        <h1>My Orders</h1>

        <!-- Filter/Search -->
        <div class="filter-bar">
            <form method="GET" style="display:flex; flex-wrap:wrap; gap:15px; align-items:center;">
                <input type="text" name="search" placeholder="Search order id, product or address..." value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">

                <label>Sort by date</label>
                <select name="sort" onchange="this.form.submit();">
                    <option value="desc"<?php if ($sort === 'DESC') echo ' selected'; ?>>Newest</option>
                    <option value="asc"<?php if ($sort === 'ASC') echo ' selected'; ?>>Oldest</option>
                </select>

                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="orders.php" class="btn" style="background:#cbd5e0;">Clear</a>
            </form>
        </div>

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
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
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
                                <td><?php echo htmlspecialchars($row['shipping_address']); ?></td>
                                <td>
                                    <a class="btn btn-primary" href="order_details.php?order_id=<?php echo urlencode($row['order_id']); ?>">View</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:40px;">No orders found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</body>
</html>
<?php
if (isset($stmt)) {
    $stmt->close();
}
if (isset($conn)) {
    $conn->close();
}
?>
<?php include 'partials/footer.php'; ?>
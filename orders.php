<?php
session_start();
require_once 'helper/connection.php';
require_once 'helper/auth.php';
is_logged_in(); // Make sure user is logged in

$user_id = $_SESSION['user_id']; // Assuming user_id is stored in session

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$sort = (isset($_GET['sort']) && $_GET['sort'] === 'asc') ? 'ASC' : 'DESC';

// Fetch user's orders
$sql = "SELECT o.order_id, o.order_date, o.total_amount, o.status, o.shipping_address,
               (SELECT p.main_image_path 
                FROM Order_Item oi 
                JOIN Product p ON oi.product_id = p.product_id 
                WHERE oi.order_id = o.order_id LIMIT 1) AS thumb
        FROM `Order` o
        WHERE o.member_id = ?";
$params = [$user_id];
$types = 'i';

if (!empty($search)) {
    $sql .= " AND (o.order_id = ? OR o.shipping_address LIKE ?)";
    $like = "%$search%";
    $params[] = $search;
    $params[] = $like;
    $types .= 'is';
}

$sql .= " ORDER BY o.order_date $sort, o.order_id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>My Orders</title>
    <link rel="stylesheet" href="../assets/css/manage_orders.css"> <!-- reuse admin CSS -->
</head>
<body>
    <div class="user-container" style="padding:30px; max-width:1200px; margin:auto;">

        <h1>My Orders</h1>

        <!-- Filter/Search -->
        <div class="filter-bar">
            <form method="GET" style="display:flex; flex-wrap:wrap; gap:15px; align-items:center;">
                <input type="text" name="search" placeholder="Search order id or address..." value="<?= htmlspecialchars($search) ?>">
                
                <label>Sort by date</label>
                <select name="sort" onchange="this.form.submit();">
                    <option value="desc" <?= $sort === 'DESC' ? 'selected' : '' ?>>Newest</option>
                    <option value="asc" <?= $sort === 'ASC' ? 'selected' : '' ?>>Oldest</option>
                </select>

                <button type="submit" class="btn btn-primary">Apply</button>
                <a href="user_orders.php" class="btn" style="background:#cbd5e0;">Clear</a>
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
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['order_id'] ?></td>
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
                                    <span class="status <?= $row['status'] ?>"><?= $row['status'] ?></span>
                                </td>
                                <td><?= htmlspecialchars($row['shipping_address']) ?></td>
                                <td>
                                    <a class="btn btn-primary" href="order_details.php?order_id=<?= $row['order_id'] ?>">View</a>
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
$stmt->close();
$conn->close();
?>
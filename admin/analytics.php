<?php
session_start();
include '../helper/pdo_connection.php';
require_once '../helper/auth.php';

// Check if user has permission to view analytics
if (!can_access_page('analytics.php')) {
    header('Location: admin_dashboard.php?error=access_denied');
    exit;
}

// Date range filter
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today

// Get total revenue
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as total_revenue,
           COUNT(*) as total_orders
    FROM `Order`
    WHERE order_date BETWEEN ? AND ?
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$revenueData = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total revenue for previous period (for comparison)
$dateDiff = (strtotime($endDate) - strtotime($startDate)) / 86400; // Days difference
$prevStartDate = date('Y-m-d', strtotime($startDate . " -" . ($dateDiff + 1) . " days"));
$prevEndDate = date('Y-m-d', strtotime($startDate . " -1 day"));

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount), 0) as total_revenue
    FROM `Order`
    WHERE order_date BETWEEN ? AND ?
");
$stmt->execute([$prevStartDate . ' 00:00:00', $prevEndDate . ' 23:59:59']);
$prevRevenueData = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate revenue change percentage
$revenueChange = 0;
if ($prevRevenueData['total_revenue'] > 0) {
    $revenueChange = (($revenueData['total_revenue'] - $prevRevenueData['total_revenue']) / $prevRevenueData['total_revenue']) * 100;
}

// Get new members count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as new_members
    FROM Member
    WHERE register_date BETWEEN ? AND ?
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$memberData = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total members
$stmt = $pdo->query("SELECT COUNT(*) as total_members FROM Member WHERE is_blocked = 0");
$totalMembersData = $stmt->fetch(PDO::FETCH_ASSOC);

// Get products sold
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(oi.quantity), 0) as products_sold
    FROM Order_Item oi
    INNER JOIN `Order` o ON oi.order_id = o.order_id
    WHERE o.order_date BETWEEN ? AND ?
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$productData = $stmt->fetch(PDO::FETCH_ASSOC);

// Get average order value
$avgOrderValue = $revenueData['total_orders'] > 0 ? $revenueData['total_revenue'] / $revenueData['total_orders'] : 0;

// Get revenue by day (for chart)
$stmt = $pdo->prepare("
    SELECT DATE(order_date) as date, 
           COALESCE(SUM(total_amount), 0) as revenue,
           COUNT(*) as orders
    FROM `Order`
    WHERE order_date BETWEEN ? AND ?
    GROUP BY DATE(order_date)
    ORDER BY date ASC
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$dailyRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order status distribution
$stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count
    FROM `Order`
    WHERE order_date BETWEEN ? AND ?
    GROUP BY status
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$orderStatus = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top selling products
$stmt = $pdo->prepare("
    SELECT p.product_id, p.name, p.category, p.main_image_path,
           COALESCE(SUM(oi.quantity), 0) as total_sold,
           COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue
    FROM Product p
    LEFT JOIN Order_Item oi ON p.product_id = oi.product_id
    LEFT JOIN `Order` o ON oi.order_id = o.order_id
    WHERE o.order_date BETWEEN ? AND ?
    GROUP BY p.product_id
    ORDER BY total_sold DESC
    LIMIT 10
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get revenue by category
$stmt = $pdo->prepare("
    SELECT p.category, 
           COALESCE(SUM(oi.quantity * oi.price), 0) as revenue,
           COALESCE(SUM(oi.quantity), 0) as items_sold
    FROM Product p
    LEFT JOIN Order_Item oi ON p.product_id = oi.product_id
    LEFT JOIN `Order` o ON oi.order_id = o.order_id
    WHERE o.order_date BETWEEN ? AND ?
    GROUP BY p.category
    ORDER BY revenue DESC
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$categoryRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment method distribution
$stmt = $pdo->prepare("
    SELECT p.payment_method, COUNT(*) as count, COALESCE(SUM(p.amount), 0) as total_amount
    FROM Payment p
    INNER JOIN `Order` o ON p.order_id = o.order_id
    WHERE o.order_date BETWEEN ? AND ?
    GROUP BY p.payment_method
    ORDER BY count DESC
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get customer insights
$stmt = $pdo->prepare("
    SELECT m.member_id, m.username, m.email,
           COUNT(o.order_id) as total_orders,
           COALESCE(SUM(o.total_amount), 0) as total_spent
    FROM Member m
    LEFT JOIN `Order` o ON m.member_id = o.member_id
    WHERE o.order_date BETWEEN ? AND ?
    GROUP BY m.member_id
    ORDER BY total_spent DESC
    LIMIT 10
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$topCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get feedback statistics
$stmt = $pdo->prepare("
    SELECT category, 
           COUNT(*) as count,
           AVG(rating) as avg_rating
    FROM Feedback
    WHERE created_at BETWEEN ? AND ?
    GROUP BY category
    ORDER BY count DESC
");
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$feedbackStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Reports - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/admin_panel.css">
    <link rel="stylesheet" href="../assets/css/analytics.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰ Menu</button>
    <div class="admin-container">
        <?php include '../partials/admin_panel.php'; ?>

        <div class="main-content">
            <div class="header">
                <h1>📈 Analytics & Reports</h1>
                <div class="header-actions">
                    <button class="btn btn-secondary" onclick="window.print()">🖨️ Print Report</button>
                    <button class="btn btn-primary" onclick="exportReport()">📥 Export Data</button>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="filter-section">
                <form method="GET" action="" class="date-filter-form">
                    <div class="filter-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
                    </div>
                    <div class="filter-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                    <button type="button" class="btn btn-secondary" onclick="resetDateFilter()">Reset</button>
                </form>
                <div class="quick-filters">
                    <button class="quick-filter-btn" onclick="setDateRange('today')">Today</button>
                    <button class="quick-filter-btn" onclick="setDateRange('week')">This Week</button>
                    <button class="quick-filter-btn" onclick="setDateRange('month')">This Month</button>
                    <button class="quick-filter-btn" onclick="setDateRange('year')">This Year</button>
                </div>
            </div>

            <!-- Key Metrics Cards -->
            <div class="metrics-grid">
                <div class="metric-card revenue-card">
                    <div class="metric-icon">💰</div>
                    <div class="metric-content">
                        <h3>Total Revenue</h3>
                        <div class="metric-value">RM <?= number_format($revenueData['total_revenue'], 2) ?></div>
                        <div class="metric-change <?= $revenueChange >= 0 ? 'positive' : 'negative' ?>">
                            <?= $revenueChange >= 0 ? '▲' : '▼' ?> <?= abs(number_format($revenueChange, 1)) ?>% vs previous period
                        </div>
                    </div>
                </div>

                <div class="metric-card orders-card">
                    <div class="metric-icon">🛒</div>
                    <div class="metric-content">
                        <h3>Total Orders</h3>
                        <div class="metric-value"><?= number_format($revenueData['total_orders']) ?></div>
                        <div class="metric-subtitle">Average: RM <?= number_format($avgOrderValue, 2) ?></div>
                    </div>
                </div>

                <div class="metric-card members-card">
                    <div class="metric-icon">👥</div>
                    <div class="metric-content">
                        <h3>New Members</h3>
                        <div class="metric-value"><?= number_format($memberData['new_members']) ?></div>
                        <div class="metric-subtitle">Total: <?= number_format($totalMembersData['total_members']) ?> active</div>
                    </div>
                </div>

                <div class="metric-card products-card">
                    <div class="metric-icon">📦</div>
                    <div class="metric-content">
                        <h3>Products Sold</h3>
                        <div class="metric-value"><?= number_format($productData['products_sold']) ?></div>
                        <div class="metric-subtitle">Units</div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-card large-chart">
                    <div class="chart-header">
                        <h3>📊 Revenue Trend</h3>
                        <div class="chart-controls">
                            <button class="chart-btn active" data-chart="revenue">Revenue</button>
                            <button class="chart-btn" data-chart="orders">Orders</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3>📋 Order Status</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="orderStatusChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3>🏷️ Revenue by Category</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3>💳 Payment Methods</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Products Table -->
            <div class="data-section">
                <div class="section-header">
                    <h2>🏆 Top Selling Products</h2>
                </div>
                <div class="table-container">
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Units Sold</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topProducts)): ?>
                                <tr>
                                    <td colspan="5" class="no-data">No product data available for the selected period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($topProducts as $index => $product): ?>
                                    <tr>
                                        <td class="rank-cell">
                                            <span class="rank-badge rank-<?= $index + 1 ?>">#<?= $index + 1 ?></span>
                                        </td>
                                        <td class="product-cell">
                                            <div class="product-info">
                                                <?php if (!empty($product['main_image_path'])): ?>
                                                    <img src="<?= htmlspecialchars($product['main_image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-thumb">
                                                <?php endif; ?>
                                                <span><?= htmlspecialchars($product['name']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $product['category']))) ?></td>
                                        <td class="number-cell"><?= number_format($product['total_sold']) ?></td>
                                        <td class="revenue-cell">RM <?= number_format($product['total_revenue'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Customers Table -->
            <div class="data-section">
                <div class="section-header">
                    <h2>⭐ Top Customers</h2>
                </div>
                <div class="table-container">
                    <table class="analytics-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Customer</th>
                                <th>Email</th>
                                <th>Orders</th>
                                <th>Total Spent</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($topCustomers)): ?>
                                <tr>
                                    <td colspan="5" class="no-data">No customer data available for the selected period</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($topCustomers as $index => $customer): ?>
                                    <tr>
                                        <td class="rank-cell">
                                            <span class="rank-badge rank-<?= $index + 1 ?>">#<?= $index + 1 ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($customer['username']) ?></td>
                                        <td><?= htmlspecialchars($customer['email']) ?></td>
                                        <td class="number-cell"><?= number_format($customer['total_orders']) ?></td>
                                        <td class="revenue-cell">RM <?= number_format($customer['total_spent'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Feedback Statistics -->
            <?php if (!empty($feedbackStats)): ?>
                <div class="data-section">
                    <div class="section-header">
                        <h2>💬 Feedback Statistics</h2>
                    </div>
                    <div class="feedback-stats-grid">
                        <?php foreach ($feedbackStats as $feedback): ?>
                            <div class="feedback-stat-card">
                                <div class="feedback-category"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $feedback['category']))) ?></div>
                                <div class="feedback-count"><?= number_format($feedback['count']) ?> feedback</div>
                                <div class="feedback-rating">
                                    <span class="rating-stars">
                                        <?php
                                        $rating = round($feedback['avg_rating']);
                                        for ($i = 1; $i <= 5; $i++) {
                                            echo $i <= $rating ? '⭐' : '☆';
                                        }
                                        ?>
                                    </span>
                                    <span class="rating-value"><?= number_format($feedback['avg_rating'], 1) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/analytics.js"></script>
    <script>
        // Pass PHP data to JavaScript
        const analyticsData = {
            dailyRevenue: <?= json_encode($dailyRevenue) ?>,
            orderStatus: <?= json_encode($orderStatus) ?>,
            categoryRevenue: <?= json_encode($categoryRevenue) ?>,
            paymentMethods: <?= json_encode($paymentMethods) ?>
        };
    </script>
</body>

</html>
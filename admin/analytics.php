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
$period = isset($_GET['period']) ? $_GET['period'] : 'this_month';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Helper for date presets
if ($period !== 'custom') {
    switch ($period) {
        case 'today':
            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d');
            break;
        case 'yesterday':
            $startDate = date('Y-m-d', strtotime('-1 day'));
            $endDate = date('Y-m-d', strtotime('-1 day'));
            break;
        case 'this_week':
            $startDate = date('Y-m-d', strtotime('monday this week'));
            $endDate = date('Y-m-d');
            break;
        case 'this_month':
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-d');
            break;
        case 'this_year':
            $startDate = date('Y-01-01');
            $endDate = date('Y-m-d');
            break;
    }
}

$categoryFilter = isset($_GET['category']) ? $_GET['category'] : 'all';

// Fetch Categories for Filter
$stmt = $pdo->query("SELECT DISTINCT category FROM Product WHERE category IS NOT NULL AND category != ''");
$allCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// --- 1. CORE METRICS ---
// Get total revenue & orders for selected period
$coreQuery = "
    SELECT COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue,
           COUNT(DISTINCT o.order_id) as total_orders
    FROM `Order` o
    JOIN Order_Item oi ON o.order_id = oi.order_id
    JOIN Product p ON oi.product_id = p.product_id
    WHERE o.order_date BETWEEN ? AND ?
    AND o.status != 'Cancelled'
";
if ($categoryFilter != 'all') {
    $coreQuery .= " AND p.category = " . $pdo->quote($categoryFilter);
}

$params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
$stmt = $pdo->prepare($coreQuery);
$stmt->execute($params);
$revenueData = $stmt->fetch(PDO::FETCH_ASSOC);

// Comparison period
$dateDiff = (strtotime($endDate) - strtotime($startDate)) / 86400;
$prevStartDate = date('Y-m-d', strtotime($startDate . " -" . ($dateDiff + 1) . " days"));
$prevEndDate = date('Y-m-d', strtotime($startDate . " -1 day"));
$stmt = $pdo->prepare($coreQuery);
$stmt->execute([$prevStartDate . ' 00:00:00', $prevEndDate . ' 23:59:59']);
$prevRevenueData = $stmt->fetch(PDO::FETCH_ASSOC);

$revenueChange = 0;
if ($prevRevenueData['total_revenue'] > 0) {
    $revenueChange = (($revenueData['total_revenue'] - $prevRevenueData['total_revenue']) / $prevRevenueData['total_revenue']) * 100;
}

// --- 2. DEEP ANALYTICS ---

// Revenue by Month (Last 12 Months) - Comparative
$trendQuery = "
    SELECT DATE_FORMAT(o.order_date, '%Y-%m') as month,
           SUM(oi.quantity * oi.price) as revenue
    FROM `Order` o
    JOIN Order_Item oi ON o.order_id = oi.order_id
    JOIN Product p ON oi.product_id = p.product_id
    WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    AND o.status != 'Cancelled'
";
if ($categoryFilter != 'all') {
    $trendQuery .= " AND p.category = " . $pdo->quote($categoryFilter);
}
$trendQuery .= " GROUP BY month ORDER BY month ASC";
$stmt = $pdo->query($trendQuery);
$monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top Selling Categories (Detailed)
$catQuery = "
    SELECT p.category, 
           COUNT(oi.order_item_id) as total_items,
           SUM(oi.quantity * oi.price) as revenue,
           AVG(oi.price) as avg_price
    FROM Product p
    JOIN Order_Item oi ON p.product_id = oi.product_id
    JOIN `Order` o ON oi.order_id = o.order_id
    WHERE o.order_date BETWEEN ? AND ?
    AND o.status != 'Cancelled'
";
if ($categoryFilter != 'all') {
    $catQuery .= " AND p.category = " . $pdo->quote($categoryFilter);
}
$catQuery .= " GROUP BY p.category ORDER BY revenue DESC";
$stmt = $pdo->prepare($catQuery);
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$categoryStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Customer Segments (Spending behavior)
$segmentQuery = "
    SELECT 
        CASE 
            WHEN total_spent > 1000 THEN 'High Spenders (>1000)'
            WHEN total_spent > 500 THEN 'Medium Spenders (500-1000)'
            ELSE 'Regular Spenders (<500)'
        END as segment,
        COUNT(*) as customer_count
    FROM (
        SELECT o.member_id, SUM(oi.quantity * oi.price) as total_spent
        FROM `Order` o
        JOIN Order_Item oi ON o.order_id = oi.order_id
        JOIN Product p ON oi.product_id = p.product_id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status != 'Cancelled'
";
if ($categoryFilter != 'all') {
    $segmentQuery .= " AND p.category = " . $pdo->quote($categoryFilter);
}
$segmentQuery .= "
        GROUP BY o.member_id
    ) as t
    GROUP BY segment
";
$stmt = $pdo->prepare($segmentQuery);
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$customerSegments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top Products (Rich Table)
$prodQuery = "
    SELECT p.name, p.category, p.stock,
           COALESCE(SUM(sub.units), 0) as units_sold,
           COALESCE(SUM(sub.rev), 0) as revenue
    FROM Product p
    LEFT JOIN (
        SELECT oi.product_id, oi.quantity as units, (oi.quantity * oi.price) as rev
        FROM Order_Item oi
        JOIN `Order` o ON oi.order_id = o.order_id
        WHERE o.order_date BETWEEN ? AND ?
        AND o.status != 'Cancelled'
    ) as sub ON p.product_id = sub.product_id
    WHERE 1=1
";
if ($categoryFilter != 'all') {
    $prodQuery .= " AND p.category = " . $pdo->quote($categoryFilter);
}
$prodQuery .= " GROUP BY p.product_id ORDER BY units_sold DESC LIMIT 15";
$stmt = $pdo->prepare($prodQuery);
$stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$detailedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Shared JS Data
$dailyRevenueQuery = "
    SELECT DATE(o.order_date) as date, SUM(oi.quantity * oi.price) as revenue
    FROM `Order` o
    JOIN Order_Item oi ON o.order_id = oi.order_id
    JOIN Product p ON oi.product_id = p.product_id
    WHERE o.order_date BETWEEN ? AND ? AND o.status != 'Cancelled'
";
if ($categoryFilter != 'all') {
    $dailyRevenueQuery .= " AND p.category = " . $pdo->quote($categoryFilter);
}
$dailyRevenueQuery .= " GROUP BY date ORDER BY date ASC";
$dailyRevenueStmt = $pdo->prepare($dailyRevenueQuery);
$dailyRevenueStmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$dailyRevenue = $dailyRevenueStmt->fetchAll(PDO::FETCH_ASSOC);

$orderStatusQuery = "
    SELECT o.status, COUNT(DISTINCT o.order_id) as count 
    FROM `Order` o
    JOIN Order_Item oi ON o.order_id = oi.order_id
    JOIN Product p ON oi.product_id = p.product_id
    WHERE o.order_date BETWEEN ? AND ?
";
if ($categoryFilter != 'all') {
    $orderStatusQuery .= " AND p.category = " . $pdo->quote($categoryFilter);
}
$orderStatusQuery .= " GROUP BY o.status";
$orderStatusStmt = $pdo->prepare($orderStatusQuery);
$orderStatusStmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
$orderStatus = $orderStatusStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Insights - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/admin_panel.css">
    <link rel="stylesheet" href="../assets/css/analytics.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="admin-container">
        <?php include '../partials/admin_panel.php'; ?>

        <div class="main-content">
            <div class="analytics-container">
                <div class="header">
                    <h1><i class="fas fa-chart-pie"></i> Analytics & Reports</h1>
                    <div class="header-actions">
                        <button class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print Report</button>
                        <button class="btn btn-primary" onclick="exportDeepData()"><i class="fas fa-download"></i> Export CSV</button>
                    </div>
                </div>

                <div class="page-intro">
                    <div class="intro-text">
                        <h2>Dashboard Overview</h2>
                        <p>Real-time insights and performance metrics for your clothing store.</p>
                    </div>
                </div>

                <form action="" method="GET" class="filter-bar" id="analyticsFilterForm">
                    <div class="filter-group-main">
                        <div class="filter-item">
                            <label><i class="fas fa-calendar-alt"></i> Time Period</label>
                            <select name="period" id="periodSelect" onchange="toggleCustomDates()">
                                <option value="today" <?= $period == 'today' ? 'selected' : '' ?>>Today</option>
                                <option value="yesterday" <?= $period == 'yesterday' ? 'selected' : '' ?>>Yesterday</option>
                                <option value="this_week" <?= $period == 'this_week' ? 'selected' : '' ?>>This Week</option>
                                <option value="this_month" <?= $period == 'this_month' ? 'selected' : '' ?>>This Month</option>
                                <option value="this_year" <?= $period == 'this_year' ? 'selected' : '' ?>>This Year</option>
                                <option value="custom" <?= $period == 'custom' ? 'selected' : '' ?>>Custom Range</option>
                            </select>
                        </div>
                        <div class="filter-item custom-date" style="<?= $period != 'custom' ? 'display: none;' : '' ?>">
                            <label>Start Date</label>
                            <input type="date" name="start_date" value="<?= $startDate ?>">
                        </div>
                        <div class="filter-item custom-date" style="<?= $period != 'custom' ? 'display: none;' : '' ?>">
                            <label>End Date</label>
                            <input type="date" name="end_date" value="<?= $endDate ?>">
                        </div>
                        <div class="filter-item">
                            <label><i class="fas fa-tags"></i> Category</label>
                            <select name="category" onchange="this.form.submit()">
                                <option value="all">All Categories</option>
                                <?php foreach ($allCategories as $cat): ?>
                                    <option value="<?= $cat ?>" <?= $categoryFilter == $cat ? 'selected' : '' ?>><?= e(ucwords($cat)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-apply" style="<?= $period != 'custom' ? 'display: none;' : '' ?>">
                        <i class="fas fa-sync-alt"></i> Apply
                    </button>
                </form>

                <div class="metric-mini-grid">
                    <div class="metric-card">
                        <div class="metric-icon" style="background: rgba(79, 70, 229, 0.1); color: var(--primary);">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="metric-content">
                            <h3>Total Revenue</h3>
                            <div class="metric-value">RM <?= number_format($revenueData['total_revenue'], 2) ?></div>
                            <div class="metric-change <?= $revenueChange >= 0 ? 'positive' : 'negative' ?>">
                                <i class="fas fa-caret-<?= $revenueChange >= 0 ? 'up' : 'down' ?>"></i>
                                <span><?= abs(round($revenueChange, 1)) ?>% vs prev.</span>
                            </div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon" style="background: rgba(139, 92, 246, 0.1); color: var(--violet);">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="metric-content">
                            <h3>Total Orders</h3>
                            <div class="metric-value"><?= number_format($revenueData['total_orders']) ?></div>
                            <div class="metric-subtitle">Avg. Order: RM <?= $revenueData['total_orders'] > 0 ? number_format($revenueData['total_revenue'] / $revenueData['total_orders'], 2) : '0' ?></div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="metric-content">
                            <h3>Best Selling Category</h3>
                            <div class="metric-value"><?= !empty($categoryStats) ? e(ucwords($categoryStats[0]['category'])) : 'N/A' ?></div>
                            <div class="metric-subtitle">RM <?= !empty($categoryStats) ? number_format($categoryStats[0]['revenue'], 2) : '0' ?> revenue</div>
                        </div>
                    </div>
                </div>

                <div class="deep-grid">
                    <div class="report-section">
                        <h3><i class="fas fa-chart-line"></i> Revenue Performance (Selected Period)</h3>
                        <div id="revenueChartContainer" class="custom-chart-container">
                            <!-- Custom SVG Line Chart will be injected here -->
                        </div>
                    </div>
                    <div class="report-section">
                        <h3><i class="fas fa-users"></i> Customer Segmentation</h3>
                        <div id="segmentChartContainer" class="custom-chart-container">
                            <!-- Custom CSS Pie Chart will be injected here -->
                        </div>
                    </div>
                </div>

                <div class="deep-grid">
                    <div class="report-section">
                        <h3><i class="fas fa-tags"></i> Revenue by Category Distribution</h3>
                        <div id="categoryChartContainer" class="custom-chart-container">
                            <!-- Custom Bar Chart will be injected here -->
                        </div>
                    </div>
                    <div class="report-section">
                        <h3><i class="fas fa-tasks"></i> Order Pipeline</h3>
                        <div id="pipelineChartContainer" class="custom-chart-container">
                            <!-- Custom Doughnut Chart will be injected here -->
                        </div>
                    </div>
                </div>

                <div class="report-section">
                    <h3><i class="fas fa-list-ol"></i> Top Performing Products (Detailed)</h3>
                    <div class="table-container" style="overflow-x: auto;">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Rank</th>
                                    <th>Product Details</th>
                                    <th>Category</th>
                                    <th>Sales Data</th>
                                    <th>Inventory</th>
                                    <th style="width: 200px;">Performance Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detailedProducts as $index => $p): ?>
                                    <tr>
                                        <td>
                                            <span class="rank-badge <?= $index < 3 ? 'top-rank' : '' ?>">#<?= $index + 1 ?></span>
                                        </td>
                                        <td>
                                            <div class="product-info">
                                                <span class="product-name"><?= e($p['name']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="category-tag"><?= e(ucwords($p['category'])) ?></span></td>
                                        <td>
                                            <div class="sales-info">
                                                <span class="units"><?= number_format($p['units_sold']) ?> sold</span>
                                                <span class="rev">RM <?= number_format($p['revenue'], 2) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($p['stock'] <= 5): ?>
                                                <span class="stock-badge low-stock"><i class="fas fa-exclamation-triangle"></i> Low (<?= $p['stock'] ?>)</span>
                                            <?php else: ?>
                                                <span class="stock-badge healthy-stock"><i class="fas fa-check-circle"></i> Healthy (<?= $p['stock'] ?>)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $max_rev = 0;
                                            if (!empty($detailedProducts)) {
                                                $revenues = array_column($detailedProducts, 'revenue');
                                                $max_rev = max($revenues);
                                            }
                                            $share = ($max_rev > 0) ? ($p['revenue'] / $max_rev) * 100 : 0;
                                            ?>
                                            <div class="performance-track">
                                                <div class="performance-fill" style="--final-width: <?= $share ?>%;"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const analyticsData = {
            dailyRevenue: <?= json_encode($dailyRevenue) ?>,
            customerSegments: <?= json_encode($customerSegments) ?>,
            categoryStats: <?= json_encode($categoryStats) ?>,
            orderStatus: <?= json_encode($orderStatus) ?>
        };

        function toggleCustomDates() {
            const period = document.getElementById('periodSelect').value;
            const customDateItems = document.querySelectorAll('.custom-date');
            const applyBtn = document.querySelector('.btn-apply');

            if (period === 'custom') {
                customDateItems.forEach(item => item.style.display = 'flex');
                if (applyBtn) applyBtn.style.display = 'inline-block';
            } else {
                customDateItems.forEach(item => item.style.display = 'none');
                if (applyBtn) applyBtn.style.display = 'none';
                document.getElementById('analyticsFilterForm').submit();
            }
        }
    </script>
    <div id="chartTooltip" class="chart-tooltip">
        <span class="tooltip-title"></span>
        <span class="tooltip-value"></span>
    </div>
    <script src="../assets/js/analytics.js"></script>
</body>

</html>
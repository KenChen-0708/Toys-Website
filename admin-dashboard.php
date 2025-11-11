<?php
session_start();

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'assignment'; 

// Create connection
// Disable mysqli exceptions locally and catch constructor failures to keep UI rendering
mysqli_report(MYSQLI_REPORT_OFF);
$conn = null;
$db_ok = true;
try {
    $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
} catch (mysqli_sql_exception $e) {
    $db_ok = false;
}

// Check connection
if (!$conn || $conn->connect_error) {
    $db_ok = false;
}

// Simulate login check
if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_name'] = 'Admin User';
}

// Get statistics
$total_users = 0;
$total_orders = 0;
$total_revenue = 0;
$pending_orders = 0;

// Total Members
if ($db_ok) {
    $result = $conn->query("SELECT COUNT(*) as count FROM Member");
    if ($result) {
        $row = $result->fetch_assoc();
        $total_users = $row['count'];
    }
}

// Total Orders
if ($db_ok) {
    $result = $conn->query("SELECT COUNT(*) as count FROM `Order`");
    if ($result) {
        $row = $result->fetch_assoc();
        $total_orders = $row['count'];
    }
}

// Total Revenue
if ($db_ok) {
    $result = $conn->query("SELECT SUM(total_amount) as total FROM `Order` WHERE status != 'Cancelled'");
    if ($result) {
        $row = $result->fetch_assoc();
        $total_revenue = $row['total'] ?? 0;
    }
}

// Pending Orders
if ($db_ok) {
    $result = $conn->query("SELECT COUNT(*) as count FROM `Order` WHERE status = 'Pending'");
    if ($result) {
        $row = $result->fetch_assoc();
        $pending_orders = $row['count'];
    }
}

// Recent Users
$recent_users = [];
if ($db_ok) {
    $result = $conn->query("SELECT member_id, name, email FROM Member ORDER BY member_id DESC LIMIT 5");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_users[] = $row;
        }
    }
}

// Recent Orders
$recent_orders = [];
if ($db_ok) {
$result = $conn->query("
    SELECT o.order_id, m.name as customer, o.total_amount, o.status, o.order_date 
    FROM `Order` o 
    JOIN Member m ON o.member_id = m.member_id 
    ORDER BY o.order_date DESC 
    LIMIT 5
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_orders[] = $row;
    }
}
}

// Chart Data: Orders by Status
$order_status_data = [];
if ($db_ok) {
$result = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM `Order` 
    GROUP BY status
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $order_status_data[] = $row;
    }
}
}

// Chart Data: Revenue by Month (Last 6 months)
$revenue_by_month = [];
if ($db_ok) {
$result = $conn->query("
    SELECT 
        DATE_FORMAT(order_date, '%Y-%m') as month,
        SUM(total_amount) as revenue
    FROM `Order`
    WHERE status != 'Cancelled'
        AND order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(order_date, '%Y-%m')
    ORDER BY month ASC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $revenue_by_month[] = $row;
    }
}
}

// Chart Data: New Members by Month (Last 6 months)
$members_by_month = [];
if ($db_ok) {
$result = $conn->query("
    SELECT 
        DATE_FORMAT(member_id, '%Y-%m') as month,
        COUNT(*) as count
    FROM Member
    GROUP BY DATE_FORMAT(member_id, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $members_by_month[] = array_reverse($row);
    }
    $members_by_month = array_reverse($members_by_month);
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/admin-dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'partials/admin_panel.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <h1>Dashboard</h1>
            </div>
            <div class="header-right">
                <div class="user-info">
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
                    <div class="avatar">A</div>
                </div>
            </div>
        </header>

        <!-- Stats Cards -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">👥</div>
                <div class="stat-details">
                    <h3><?php echo number_format($total_users); ?></h3>
                    <p>Total Members</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">🛒</div>
                <div class="stat-details">
                    <h3><?php echo number_format($total_orders); ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">💰</div>
                <div class="stat-details">
                    <h3>$<?php echo number_format($total_revenue, 2); ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">📋</div>
                <div class="stat-details">
                    <h3><?php echo $pending_orders; ?></h3>
                    <p>Pending Orders</p>
                </div>
            </div>
        </section>

        <!-- Charts Section -->
        <section class="charts-section">
            <div class="chart-container">
                <div class="chart-header">
                    <h2>Revenue Trend (Last 6 Months)</h2>
                </div>
                <canvas id="revenueChart"></canvas>
            </div>

            <div class="chart-container">
                <div class="chart-header">
                    <h2>Orders by Status</h2>
                </div>
                <canvas id="statusChart"></canvas>
            </div>
        </section>

        <!-- Tables Section -->
        <section class="tables-section">
            <!-- Recent Users -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Recent Members</h2>
                    <a href="#" class="btn-link">View All</a>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_users)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No members found</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td><?php echo $user['member_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <button class="btn-action">Edit</button>
                                    <button class="btn-action danger">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Recent Orders -->
            <div class="table-container">
                <div class="table-header">
                    <h2>Recent Orders</h2>
                    <a href="#" class="btn-link">View All</a>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_orders)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No orders found</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($order['customer']); ?></td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <span class="badge <?php echo strtolower($order['status']); ?>">
                                        <?php echo $order['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                <td>
                                    <button class="btn-action">View</button>
                                    <button class="btn-action">Process</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        // Revenue Chart
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        const revenueData = <?php echo json_encode($revenue_by_month); ?>;
        
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: revenueData.map(item => item.month),
                datasets: [{
                    label: 'Revenue ($)',
                    data: revenueData.map(item => item.revenue),
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusData = <?php echo json_encode($order_status_data); ?>;
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusData.map(item => item.status),
                datasets: [{
                    data: statusData.map(item => item.count),
                    backgroundColor: [
                        '#3498db',
                        '#f39c12',
                        '#9b59b6',
                        '#2ecc71',
                        '#e74c3c'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>
<?php
if (isset($db_ok) && $db_ok) {
    $conn->close();
}
?>
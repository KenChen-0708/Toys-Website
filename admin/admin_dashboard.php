<?php
session_start();

include '../helper/pdo_connection.php';
require_once '../helper/auth.php';
require_admin();

// Helper to show initials
function admin_initials($username) {
    $parts = preg_split('/\s+/', trim((string)$username));
    $first  = mb_substr($parts[0] ?? '', 0, 1);
    $second = mb_substr($parts[1] ?? '', 0, 1);
    $initials = $first . ($second ?: '');
    return mb_strtoupper($initials ?: 'AD');
}

// ======================
// GET STATISTICS
// ======================
$total_users = $total_orders = $total_revenue = $pending_orders = 0;

// Total Members
try {
    $res = $pdo->query("SELECT COUNT(*) AS c FROM Member");
    $total_users = (int)$res->fetch(PDO::FETCH_ASSOC)['c'];
} catch (PDOException $e) {
    $total_users = 0;
}

// Total Orders
try {
    $res = $pdo->query("SELECT COUNT(*) AS c FROM `Order`");
    $total_orders = (int)$res->fetch(PDO::FETCH_ASSOC)['c'];
} catch (PDOException $e) {
    $total_orders = 0;
}

// Total Revenue
try {
    $res = $pdo->query("SELECT SUM(total_amount) AS t FROM `Order` WHERE status != 'Cancelled'");
    $row = $res->fetch(PDO::FETCH_ASSOC);
    $total_revenue = $row && $row['t'] !== null ? (float)$row['t'] : 0;
} catch (PDOException $e) {
    $total_revenue = 0;
}

// Pending Orders
try {
    $res = $pdo->query("SELECT COUNT(*) AS c FROM `Order` WHERE status = 'Pending'");
    $pending_orders = (int)$res->fetch(PDO::FETCH_ASSOC)['c'];
} catch (PDOException $e) {
    $pending_orders = 0;
}

// Recent Members
$recent_users = [];
try {
    $res = $pdo->query("SELECT member_id, username, email FROM Member ORDER BY member_id DESC LIMIT 5");
    $recent_users = $res->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_users = [];
}

// Recent Orders
$recent_orders = [];
try {
    $res = $pdo->query("
        SELECT 
            o.order_id, m.username AS customer, o.total_amount, o.status, o.order_date 
        FROM `Order` o 
        JOIN Member m ON o.member_id = m.member_id 
        ORDER BY o.order_date DESC 
        LIMIT 5
    ");
    $recent_orders = $res->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_orders = [];
}

// Orders by Status
$order_status_data = [];
try {
    $res = $pdo->query("SELECT status, COUNT(*) AS count FROM `Order` GROUP BY status");
    $order_status_data = $res->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $order_status_data = [];
}

// Revenue by Month (6 months)
$revenue_by_month = [];
try {
    $res = $pdo->query("
        SELECT DATE_FORMAT(order_date,'%Y-%m') AS month, SUM(total_amount) AS revenue
        FROM `Order`
        WHERE status!='Cancelled'
          AND order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(order_date,'%Y-%m')
        ORDER BY month ASC
    ");
    $revenue_by_month = $res->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $revenue_by_month = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>

    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin_panel.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
      /* ============================
         ADMIN PROFILE HOVER MENU
         (hover only, no click)
         ============================ */

      .header__user-profile {
        position: relative;
        display: inline-block;
      }

      .header__user-profile .avatar-btn {
        border: 0;
        background: transparent;
        cursor: default;      /* no hand cursor */
        padding: 0;
        line-height: 1;
        pointer-events: none; /* disables clicking */
      }

      .header__user-profile .avatar-circle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #3b82f6;  /* Blue instead of black */
        color: #fff;
        font-weight: 700;
        font-size: 0.9rem;
      }

      /* Dropdown container */
      .header__user-profile .profile-menu {
        position: absolute;
        right: 0;
        top: 100%; /* sits directly under avatar */
        min-width: 230px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.16);
        padding: 0.4rem 0;
        display: none;
        z-index: 9999;
        color: #111827;
        font-size: 0.9rem;
      }

      /* Hover bridge to avoid flicker between avatar & menu */
      .header__user-profile .profile-menu::before {
        content: "";
        position: absolute;
        left: 0;
        right: 0;
        top: -10px;
        height: 12px;
      }

      /* default hidden */
      .header__user-profile .profile-menu {
        display: none !important;
      }

      /* Show on hover (and focus inside if ever needed) */
      .header__user-profile:hover .profile-menu,
      .header__user-profile:focus-within .profile-menu {
        display: block !important;
      }

      /* Header inside dropdown – SINGLE divider line here */
      .header__user-profile .profile-menu__header {
        padding: 0.6rem 0.85rem 0.5rem;
        border-bottom: 1px solid #f3f4f6; /* only one divider */
        margin-bottom: 0.25rem;
      }

      .header__user-profile .profile-menu__header strong {
        display: block;
        font-size: 0.92rem;
        margin-bottom: 0.1rem;
      }

      .header__user-profile .profile-menu__header small {
        font-size: 0.8rem;
        color: #6b7280;
      }

      /* Menu items (links + logout button) */
      .header__user-profile .profile-menu a,
      .header__user-profile .profile-menu .logout-btn {
        display: block;
        width: 100%;
        text-align: left;
        padding: 0.45rem 0.85rem;
        border-radius: 6px;
        background: transparent;
        border: 0;
        color: inherit;
        cursor: pointer;
        font: inherit;
        line-height: 1.4;
        text-decoration: none;
      }

      /* Hover/focus states */
      .header__user-profile .profile-menu a:hover,
      .header__user-profile .profile-menu a:focus-visible,
      .header__user-profile .profile-menu .logout-btn:hover,
      .header__user-profile .profile-menu .logout-btn:focus-visible {
        background: #f3f4f6;
        outline: none;
      }

      /* Logout line: NO extra divider (we already have one above) */
      .header__user-profile .profile-menu .logout-btn {
        margin-top: 0.25rem;
        color: #b91c1c;
      }
    </style>

</head>
<body>

<?php include '../partials/admin_panel.php'; ?>

<div class="main-content">

    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <h1>Dashboard</h1>
        </div>

        <div class="header-right">
            <div class="header__user-profile">
                <button class="avatar-btn" aria-haspopup="true">
                    <span class="avatar-circle">
                        <?= htmlspecialchars(admin_initials($_SESSION['admin']['username'])) ?>
                    </span>
                </button>

                <div class="profile-menu">
                    <div class="profile-menu__header">
                        <strong><?= htmlspecialchars($_SESSION['admin']['username']) ?></strong>
                        <small><?= htmlspecialchars($_SESSION['admin']['email']) ?></small>
                    </div>

                    <form action="../auth/admin_logout.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <button type="submit" class="logout-btn">Log Out</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Stats Cards -->
    <section class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">👥</div>
            <div class="stat-details">
                <h3><?= number_format($total_users); ?></h3>
                <p>Total Members</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon green">🛒</div>
            <div class="stat-details">
                <h3><?= number_format($total_orders); ?></h3>
                <p>Total Orders</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon purple">💰</div>
            <div class="stat-details">
                <h3>$<?= number_format($total_revenue, 2); ?></h3>
                <p>Total Revenue</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon orange">📋</div>
            <div class="stat-details">
                <h3><?= $pending_orders; ?></h3>
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
                            <td><?= $user['member_id']; ?></td>
                            <td><?= htmlspecialchars($user['username']); ?></td>
                            <td><?= htmlspecialchars($user['email']); ?></td>
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
                            <td>#<?= $order['order_id']; ?></td>
                            <td><?= htmlspecialchars($order['customer']); ?></td>
                            <td>$<?= number_format($order['total_amount'], 2); ?></td>
                            <td>
                                <span class="badge <?= strtolower($order['status']); ?>">
                                    <?= $order['status']; ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($order['order_date'])); ?></td>
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
    const revenueData = <?= json_encode($revenue_by_month); ?>;

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
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback(value) {
                            return '$' + value.toLocaleString();
                        }
                    }
                }
            }
        }
    });

    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusData = <?= json_encode($order_status_data); ?>;

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
            plugins: { legend: { position: 'bottom' } }
        }
    });
</script>

</body>
</html>

<?php
session_start();
include __DIR__ . '/../helper/pdo_connection.php';
require_once __DIR__ . '/../helper/auth.php';

// Check if user has permission to view dashboard (analytics permission used here as proxy or general admin access)
if (!can_access_page('admin_dashboard.php')) {
    header('Location: ../index.php');
    exit;
}

// Fetch basic stats for the "Control Center"
$stats = [
    'pending_orders' => 0,
    'low_stock' => 0,
    'new_members_7d' => 0,
    'revenue_month' => 0,
    'total_products' => 0,
    'total_members' => 0
];

try {
    // Pending Orders
    $stmt = $pdo->query("SELECT COUNT(*) FROM `Order` WHERE status = 'Pending'");
    $stats['pending_orders'] = $stmt->fetchColumn();

    // Low Stock
    $stmt = $pdo->query("SELECT COUNT(*) FROM Product WHERE stock <= 5 AND status = 'Available'");
    $stats['low_stock'] = $stmt->fetchColumn();

    // New Members (7 days)
    $stmt = $pdo->query("SELECT COUNT(*) FROM Member WHERE register_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['new_members_7d'] = $stmt->fetchColumn();

    // Revenue (Current Month)
    $stmt = $pdo->query("SELECT SUM(total_amount) FROM `Order` WHERE status != 'Cancelled' AND MONTH(order_date) = MONTH(CURRENT_DATE()) AND YEAR(order_date) = YEAR(CURRENT_DATE())");
    $stats['revenue_month'] = $stmt->fetchColumn() ?: 0;

    // Total Products
    $stmt = $pdo->query("SELECT COUNT(*) FROM Product");
    $stats['total_products'] = $stmt->fetchColumn();

    // Total Members
    $stmt = $pdo->query("SELECT COUNT(*) FROM Member");
    $stats['total_members'] = $stmt->fetchColumn();

    // Recent Orders (Latest 5)
    $stmt = $pdo->query("SELECT o.*, m.username FROM `Order` o JOIN Member m ON o.member_id = m.member_id ORDER BY o.order_date DESC LIMIT 5");
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent Feedback (Latest 5)
    $stmt = $pdo->query("SELECT f.*, m.username FROM Feedback f LEFT JOIN Member m ON f.member_id = m.member_id ORDER BY f.created_at DESC LIMIT 5");
    $recent_feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silently continue or log error
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Control Center</title>
    <link rel="stylesheet" href="../assets/css/admin_panel.css">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius: 12px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-body);
            color: var(--text-main);
        }

        .main-content {
            padding: 30px;
            min-height: 100vh;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Hero Section */
        .welcome-section {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border-radius: var(--radius);
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.4);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::after {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .welcome-section h1 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 10px 0;
        }

        .welcome-section p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
        }

        /* Quick Shortcuts Grid */
        .shortcuts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .shortcut-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            text-align: center;
            text-decoration: none;
            color: var(--text-main);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .shortcut-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .shortcut-icon {
            width: 56px;
            height: 56px;
            background: #f1f5f9;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--primary);
            transition: all 0.3s ease;
        }

        .shortcut-card:hover .shortcut-icon {
            background: var(--primary);
            color: white;
        }

        .shortcut-label {
            font-weight: 600;
            font-size: 0.95rem;
        }

        /* Summary Stats Area */
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-box {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid var(--border);
        }

        .stat-box-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-box.pending .stat-box-icon {
            background: #fff7ed;
            color: #ea580c;
        }

        .stat-box.revenue .stat-box-icon {
            background: #f0fdf4;
            color: #16a34a;
        }

        .stat-box.stock .stat-box-icon {
            background: #fef2f2;
            color: #dc2626;
        }

        .stat-box.members .stat-box-icon {
            background: #eff6ff;
            color: #2563eb;
        }

        .stat-box-info h3 {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin: 0 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .stat-box-info .value {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        /* Content Sections */
        .dashboard-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
        }

        .section-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .section-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-header h2 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }

        .section-header a {
            font-size: 0.85rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }

        .section-header a:hover {
            text-decoration: underline;
        }

        /* Tables & Lists */
        .activity-table {
            width: 100%;
            border-collapse: collapse;
        }

        .activity-table th {
            text-align: left;
            padding: 12px 24px;
            background: #f8fafc;
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
        }

        .activity-table td {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }

        .activity-table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-pending {
            background: #ffedd5;
            color: #9a3412;
        }

        .status-delivered {
            background: #dcfce7;
            color: #166534;
        }

        .status-shipped {
            background: #dbeafe;
            color: #1e40af;
        }

        .feedback-item {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 16px;
        }

        .feedback-item:last-child {
            border-bottom: none;
        }

        .feedback-avatar {
            width: 40px;
            height: 40px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--text-muted);
            flex-shrink: 0;
        }

        .feedback-content {
            flex: 1;
        }

        .feedback-user {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }

        .feedback-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.4;
            margin-bottom: 8px;
        }

        .feedback-time {
            font-size: 0.75rem;
            color: #94a3b8;
        }

        .rating-stars {
            color: #fbbf24;
            font-size: 0.8rem;
        }

        @media (max-width: 768px) {
            .dashboard-sections {
                grid-template-columns: 1fr;
            }

            .shortcuts-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }

            .main-content {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <?php include __DIR__ . '/../partials/admin_panel.php'; ?>

        <div class="main-content">
            <div class="dashboard-container">
                <div class="welcome-section">
                    <h1>Welcome back, <?= e($adminName) ?>!</h1>
                    <p>Manage your store effectively from the command center. Here's what's happening today.</p>
                </div>

                <!-- Quick Shortcuts Section -->
                <div class="shortcuts-grid">
                    <?php if (can_access_page('manage_orders.php')): ?>
                        <a href="manage_orders.php" class="shortcut-card">
                            <div class="shortcut-icon"><i class="fas fa-shopping-cart"></i></div>
                            <span class="shortcut-label">Orders</span>
                        </a>
                    <?php endif; ?>

                    <?php if (can_access_page('manage_product.php')): ?>
                        <a href="manage_product.php" class="shortcut-card">
                            <div class="shortcut-icon"><i class="fas fa-box"></i></div>
                            <span class="shortcut-label">Products</span>
                        </a>
                    <?php endif; ?>

                    <?php if (can_access_page('manage_member.php')): ?>
                        <a href="manage_member.php" class="shortcut-card">
                            <div class="shortcut-icon"><i class="fas fa-users"></i></div>
                            <span class="shortcut-label">Members</span>
                        </a>
                    <?php endif; ?>

                    <?php if (can_access_page('feedback_management.php')): ?>
                        <a href="feedback_management.php" class="shortcut-card">
                            <div class="shortcut-icon"><i class="fas fa-comments"></i></div>
                            <span class="shortcut-label">Feedback</span>
                        </a>
                    <?php endif; ?>

                    <?php if (can_access_page('manage_voucher.php')): ?>
                        <a href="manage_voucher.php" class="shortcut-card">
                            <div class="shortcut-icon"><i class="fas fa-ticket-alt"></i></div>
                            <span class="shortcut-label">Vouchers</span>
                        </a>
                    <?php endif; ?>

                    <?php if (can_access_page('analytics.php')): ?>
                        <a href="analytics.php" class="shortcut-card">
                            <div class="shortcut-icon"><i class="fas fa-chart-line"></i></div>
                            <span class="shortcut-label">Analytics</span>
                        </a>
                    <?php endif; ?>

                    <?php if (can_access_page('manage_email.php')): ?>
                        <a href="manage_email.php" class="shortcut-card">
                            <div class="shortcut-icon"><i class="fas fa-envelope"></i></div>
                            <span class="shortcut-label">Email Notifications</span>
                        </a>
                    <?php endif; ?>

                    <?php if (get_admin_role() === 'super admin'): ?>
                        <a href="manage_permissions.php" class="shortcut-card">
                            <div class="shortcut-icon"><i class="fas fa-shield-alt"></i></div>
                            <span class="shortcut-label">Permissions</span>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Summary Stats -->
                <div class="stats-summary">
                    <div class="stat-box pending">
                        <div class="stat-box-icon"><i class="fas fa-clock"></i></div>
                        <div class="stat-box-info">
                            <h3>Pending Orders</h3>
                            <p class="value"><?= number_format($stats['pending_orders']) ?></p>
                        </div>
                    </div>
                    <div class="stat-box revenue">
                        <div class="stat-box-icon"><i class="fas fa-dollar-sign"></i></div>
                        <div class="stat-box-info">
                            <h3>Revenue (This Month)</h3>
                            <p class="value">RM <?= number_format($stats['revenue_month'], 2) ?></p>
                        </div>
                    </div>
                    <div class="stat-box stock">
                        <div class="stat-box-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-box-info">
                            <h3>Low Stock Items</h3>
                            <p class="value"><?= number_format($stats['low_stock']) ?></p>
                        </div>
                    </div>
                    <div class="stat-box members">
                        <div class="stat-box-icon"><i class="fas fa-user-plus"></i></div>
                        <div class="stat-box-info">
                            <h3>New Members (7d)</h3>
                            <p class="value"><?= number_format($stats['new_members_7d']) ?></p>
                        </div>
                    </div>
                </div>

                <div class="dashboard-sections">
                    <!-- Recent Orders -->
                    <div class="section-card">
                        <div class="section-header">
                            <h2>Recent Orders</h2>
                            <a href="manage_orders.php">View All</a>
                        </div>
                        <div class="section-body">
                            <?php if (empty($recent_orders)): ?>
                                <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                                    <i class="fas fa-shopping-bag" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                                    <p>No recent orders found.</p>
                                </div>
                            <?php else: ?>
                                <table class="activity-table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td style="font-weight: 500;">#<?= $order['order_id'] ?></td>
                                                <td><?= e($order['username']) ?></td>
                                                <td style="font-weight: 600;">RM <?= number_format($order['total_amount'], 2) ?></td>
                                                <td>
                                                    <span class="status-badge status-<?= strtolower($order['status']) ?>">
                                                        <?= e($order['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Feedback -->
                    <div class="section-card">
                        <div class="section-header">
                            <h2>Recent Feedback</h2>
                            <a href="feedback_management.php">View All</a>
                        </div>
                        <div class="section-body">
                            <?php if (empty($recent_feedback)): ?>
                                <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                                    <i class="fas fa-comment-slash" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                                    <p>No feedback received recently.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($recent_feedback as $feedback): ?>
                                    <div class="feedback-item">
                                        <div class="feedback-avatar">
                                            <?= strtoupper(substr($feedback['username'] ?: 'U', 0, 1)) ?>
                                        </div>
                                        <div class="feedback-content">
                                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                <div class="feedback-user"><?= e($feedback['username'] ?: 'Guest') ?></div>
                                                <div class="rating-stars">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="<?= $i <= $feedback['rating'] ? 'fas' : 'far' ?> fa-star"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <div class="feedback-text"><?= e(substr($feedback['content'], 0, 100)) ?><?= strlen($feedback['content']) > 100 ? '...' : '' ?></div>
                                            <div class="feedback-time"><?= date('M j, Y H:i', strtotime($feedback['created_at'])) ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
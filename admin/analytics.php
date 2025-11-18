<?php
session_start();
include __DIR__ . '/../helper/pdo_connection.php';
require_once __DIR__ . '/../helper/auth.php';
require_permission('analytics', 'view');

function admin_initials($username) {
    $username = trim($username);
    if (empty($username)) return 'AD';
    $parts = preg_split('/\s+/', $username);
    $first = strtoupper(mb_substr($parts[0], 0, 1));
    $second = isset($parts[1]) ? strtoupper(mb_substr($parts[1], 0, 1)) : '';
    $initials = $first . $second;
    return $initials ?: 'AD';
}

// Fetch current admin info from session (example keys used in other pages)
$admin_username = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin User';
$admin_email = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : 'admin@example.com';

// Initialize datasets
$totalRevenue = 0.0;
$totalOrders = 0;
$avgOrderValue = 0.0;
$newMembers30 = 0;
$totalProducts = 0;
$lowStock = 0;
$revenue_by_month = [];
$orders_by_status = [];
$top_products_by_revenue = [];
$revenue_by_category = [];
$new_members_by_month = [];
$payment_methods = [];
$voucher_usage = [];
$avg_rating = null;
$feedback_statistics = [];
$feedback_status = [];

try {
    // Total Revenue (All Time)
    $stmt = $pdo->query("SELECT SUM(total_amount) AS t FROM `Order` WHERE status != 'Cancelled'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalRevenue = $row && $row['t'] !== null ? (float)$row['t'] : 0.0;

    // Total Orders
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM `Order`");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalOrders = $row ? (int)$row['c'] : 0;

    // Average Order Value
    $stmt = $pdo->query("SELECT SUM(total_amount) AS total, COUNT(*) AS cnt FROM `Order` WHERE status != 'Cancelled'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $row && $row['total'] !== null ? (float)$row['total'] : 0.0;
    $cnt = $row ? (int)$row['cnt'] : 0;
    $avgOrderValue = $cnt > 0 ? ($total / $cnt) : 0.0;

    // New Members (Last 30 Days)
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM Member WHERE register_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $newMembers30 = $row ? (int)$row['c'] : 0;

    // Total Products
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM Product");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalProducts = $row ? (int)$row['c'] : 0;

    // Low Stock Products
    $stmt = $pdo->query("SELECT COUNT(*) AS c FROM Product WHERE stock <= 5 AND status = 'Available'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $lowStock = $row ? (int)$row['c'] : 0;

    // Revenue by month (last 12 months)
    $stmt = $pdo->query("SELECT DATE_FORMAT(order_date, '%Y-%m') AS month, SUM(total_amount) AS revenue FROM `Order` WHERE status != 'Cancelled' AND order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(order_date, '%Y-%m') ORDER BY month ASC");
    $revenue_by_month = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Orders by status
    $stmt = $pdo->query("SELECT status, COUNT(*) AS count FROM `Order` GROUP BY status");
    $orders_by_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top products by revenue
    $stmt = $pdo->query("SELECT p.product_id, p.name, SUM(oi.quantity * oi.price) AS revenue FROM Order_Item oi JOIN `Order` o ON oi.order_id = o.order_id JOIN Product p ON oi.product_id = p.product_id WHERE o.status != 'Cancelled' GROUP BY p.product_id, p.name ORDER BY revenue DESC LIMIT 10");
    $top_products_by_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Revenue by category
    $stmt = $pdo->query("SELECT p.category, SUM(oi.quantity * oi.price) AS revenue FROM Order_Item oi JOIN `Order` o ON oi.order_id = o.order_id JOIN Product p ON oi.product_id = p.product_id WHERE o.status != 'Cancelled' GROUP BY p.category ORDER BY revenue DESC");
    $revenue_by_category = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // New members by month (last 6 months)
    $stmt = $pdo->query("SELECT DATE_FORMAT(register_date, '%Y-%m') AS month, COUNT(*) AS count FROM Member WHERE register_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(register_date, '%Y-%m') ORDER BY month ASC");
    $new_members_by_month = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Payment methods
    $stmt = $pdo->query("SELECT payment_method, COUNT(*) AS count FROM Payment WHERE status = 'Completed' GROUP BY payment_method");
    $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Voucher usage
    $stmt = $pdo->query("SELECT v.voucher_id, v.code, COUNT(vu.voucher_usage_id) AS times_used FROM Voucher_Usage vu JOIN Voucher v ON vu.voucher_id = v.voucher_id GROUP BY v.voucher_id, v.code ORDER BY times_used DESC LIMIT 10");
    $voucher_usage = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Avg rating
    $stmt = $pdo->query("SELECT AVG(rating) AS avg_rating FROM Review");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $avg_rating = $row && $row['avg_rating'] !== null ? (float)$row['avg_rating'] : null;

    // Feedback statistics (view)
    $stmt = $pdo->query("SELECT * FROM feedback_statistics");
    $feedback_statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Feedback status breakdown
    $stmt = $pdo->query("SELECT status, COUNT(*) AS count FROM Feedback GROUP BY status");
    $feedback_status = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // On error, leave defaults and continue; optionally log $e->getMessage()
}

// Helper for safe output
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analytics</title>
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin_panel.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        :root{
            --bg-main: #f6f9fc;
            --bg-card: #ffffff;
            --accent: #4f46e5;
            --accent-soft: rgba(79,70,229,0.08);
            --muted: #6b7280;
            --text: #0f172a;
            --glass: rgba(255,255,255,0.6);
            --shadow: 0 14px 40px rgba(15,23,42,0.10);
            --card-radius: 14px;
        }
        body{background: linear-gradient(180deg, #fbfdff 0%, var(--bg-main) 100%);font-family: Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; color:var(--text);}
        .main-content{margin-left:260px;padding:28px 32px;}
        .topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
        .topbar h1{margin:0;font-size:1.5rem;font-weight:600}
        .topbar .subtitle{color:var(--muted);font-size:0.95rem}
        
        /* Copied header style from manage_member.php */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-primary {
            background: white;
            color: #667eea;
        }

        .filters{display:flex;gap:12px;align-items:center;margin:18px 0}
        .filter-pill{background:var(--bg-card);padding:8px 12px;border-radius:999px;box-shadow:0 6px 18px rgba(15,23,42,0.03);display:flex;gap:8px;align-items:center}
        .filter-select{border:0;background:transparent;font-weight:600;color:var(--text)}
        .filter-btn{background:linear-gradient(90deg,var(--accent),#06b6d4);color:#fff;padding:8px 12px;border-radius:999px;border:0;cursor:pointer}
        .analytics-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:18px}
        .card{background:var(--bg-card);border-radius:var(--card-radius);padding:18px;box-shadow:var(--shadow);transition:transform .18s ease,box-shadow .18s ease}
        .card:hover{transform:translateY(-4px);box-shadow:0 20px 48px rgba(15,23,42,0.12)}
        .kpi-label{font-size:0.85rem;color:var(--muted)}
        .kpi-value{font-size:1.35rem;font-weight:700;margin-top:6px}
        .kpi-trend{font-size:0.85rem;color:var(--muted);margin-top:6px}
        .kpi-icon{width:44px;height:44px;border-radius:10px;background:var(--accent-soft);display:flex;align-items:center;justify-content:center;color:var(--accent);font-weight:700}
        .analytics-grid-main{display:grid;grid-template-columns:2fr 1fr;gap:18px}
        @media (max-width:900px){.main-content{margin-left:0;padding:14px}.analytics-grid-main{grid-template-columns:1fr} .avatar .circle{width:40px;height:40px}}
        /* charts */
        .chart-area{height:220px}
        .svg-chart{width:100%;height:100%}
        .donut-legend{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
        .legend-item{display:flex;gap:8px;align-items:center;font-size:0.9rem;color:var(--muted)}
        .legend-dot{width:12px;height:12px;border-radius:3px}
        /* tables */
        .table{width:100%;border-collapse:collapse;font-size:0.95rem}
        .table th,.table td{padding:10px 8px;text-align:left}
        .table thead th{font-size:0.85rem;color:var(--muted);border-bottom:1px solid #eef2f7}
        .inline-bar{height:10px;background:#eef2f7;border-radius:999px;overflow:hidden}
        .inline-bar > span{display:block;height:100%;background:linear-gradient(90deg,var(--accent),#06b6d4);transition:width .6s ease}
        .small-muted{color:var(--muted);font-size:0.85rem}
        .empty-state{display:flex;align-items:center;justify-content:center;height:160px;color:var(--muted)}
        .mini-columns{display:grid;grid-auto-flow:column;grid-auto-columns:1fr;gap:8px;align-items:end;height:120px}
        .mini-column{background:linear-gradient(180deg,#e6eefc,#eef7ff);border-radius:6px;display:flex;align-items:flex-end;justify-content:center;padding-bottom:6px;transition:height .6s ease}
        /* KPI card clickable */
        .stats-grid .card{cursor:pointer}
        .stats-grid .card:hover{box-shadow:0 24px 56px rgba(79,70,229,0.15)}
        .analytics-grid-main .card{cursor:pointer}
        .analytics-grid-main .card:hover{box-shadow:0 24px 56px rgba(79,70,229,0.15)}
        section:last-of-type .card{cursor:pointer}
        section:last-of-type .card:hover{box-shadow:0 24px 56px rgba(79,70,229,0.15)}
        /* Modal */
        .modal-overlay{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;padding:20px}
        .modal-overlay.active{display:flex}
        .modal-content{background:var(--bg-card);border-radius:16px;padding:32px;max-width:850px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 25px 50px rgba(15,23,42,0.2);animation:slideUp .3s ease}
        @keyframes slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
        .modal-close{position:absolute;top:16px;right:16px;width:32px;height:32px;border:0;background:var(--accent-soft);color:var(--accent);border-radius:8px;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;transition:all .18s ease;z-index:10001}
        .modal-close:hover{background:var(--accent);color:#fff}
        .modal-title{font-size:1.5rem;font-weight:700;margin-bottom:6px}
        .modal-subtitle{color:var(--muted);margin-bottom:24px;font-size:0.95rem}
        .modal-stat{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #eef2f7}
        .modal-stat:last-child{border-bottom:0}
        .modal-stat-label{color:var(--muted);font-size:0.95rem}
        .modal-stat-value{font-size:1.25rem;font-weight:700;color:var(--accent)}
        .modal-chart-container{width:100%;height:100%;margin:24px 0;background:#fafbfc;border-radius:12px;padding:16px;box-sizing:border-box}
        .modal-chart-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin:24px 0}
        .modal-chart-mini{width:100%;height:200px;background:#fafbfc;border-radius:12px;padding:12px;box-sizing:border-box}
    </style>
</head>
<body>
<?php include __DIR__ . '/../partials/admin_panel.php'; ?>
<div class="main-content">
    <div class="header">
        <h1 style="color:#fff;margin:0">Analytics</h1>
        <div style="display:flex; gap:10px; align-items:center;">
            <!-- Export button removed -->
        </div>
    </div>

    <!-- Filters removed: filtering functionality disabled -->

    <section class="stats-grid analytics-grid">
        <div class="card" data-kpi="revenue">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div>
                    <div class="kpi-label">Total Revenue</div>
                    <div class="kpi-value"><?php echo 'RM ' . number_format($totalRevenue,2); ?></div>
                    <div class="kpi-trend small-muted">vs. last 30 days</div>
                </div>
                <div class="kpi-icon"><i class="fas fa-dollar-sign"></i></div>
            </div>
        </div>
        <div class="card" data-kpi="orders">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div>
                    <div class="kpi-label">Total Orders</div>
                    <div class="kpi-value"><?php echo number_format($totalOrders); ?></div>
                    <div class="kpi-trend small-muted">all time</div>
                </div>
                <div class="kpi-icon"><i class="fas fa-receipt"></i></div>
            </div>
        </div>
        <div class="card" data-kpi="avgorder">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div>
                    <div class="kpi-label">Avg Order Value</div>
                    <div class="kpi-value"><?php echo 'RM ' . number_format($avgOrderValue,2); ?></div>
                    <div class="kpi-trend small-muted">orders (paid)</div>
                </div>
                <div class="kpi-icon"><i class="fas fa-balance-scale"></i></div>
            </div>
        </div>
        <div class="card" data-kpi="members">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div>
                    <div class="kpi-label">New Members (30d)</div>
                    <div class="kpi-value"><?php echo number_format($newMembers30); ?></div>
                    <div class="kpi-trend small-muted">recent signups</div>
                </div>
                <div class="kpi-icon"><i class="fas fa-users"></i></div>
            </div>
        </div>
        <div class="card" data-kpi="products">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div>
                    <div class="kpi-label">Total Products</div>
                    <div class="kpi-value"><?php echo number_format($totalProducts); ?></div>
                    <div class="kpi-trend small-muted">catalog size</div>
                </div>
                <div class="kpi-icon"><i class="fas fa-cube"></i></div>
            </div>
        </div>
        <div class="card" data-kpi="lowstock">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div>
                    <div class="kpi-label">Low Stock</div>
                    <div class="kpi-value"><?php echo number_format($lowStock); ?></div>
                    <div class="kpi-trend small-muted">stock &le; 5</div>
                </div>
                <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
    </section>

    <section class="analytics-grid-main">
        <div>
            <div class="card" data-card="revenue-trend" style="margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <div>
                        <div style="font-weight:700">Revenue Trend (Last 12 Months)</div>
                        <div class="small-muted">Area: revenue by month</div>
                    </div>
                    <div class="small-muted">Total: <?php echo 'RM ' . number_format($totalRevenue,2); ?></div>
                </div>
                <div class="chart-area" id="revenueAreaCard">
                    <?php if (empty($revenue_by_month)) { ?>
                        <div class="empty-state">No data available for this period</div>
                    <?php } else { ?>
                        <svg class="svg-chart" id="revenueAreaSvg" viewBox="0 0 800 240" preserveAspectRatio="none"></svg>
                    <?php } ?>
                </div>
            </div>

            <div class="card" data-card="orders-status" style="margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <div>
                        <div style="font-weight:700">Orders by Status</div>
                        <div class="small-muted">Distribution across statuses</div>
                    </div>
                    <div class="small-muted">Total: <?php echo number_format($totalOrders); ?></div>
                </div>
                <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
                    <div style="flex:1;min-width:220px">
                        <?php if (empty($orders_by_status)) { ?>
                            <div class="empty-state">No data available</div>
                        <?php } else { ?>
                            <svg id="ordersDonut" width="180" height="180"></svg>
                        <?php } ?>
                    </div>
                    <div style="flex:1;min-width:220px">
                        <div class="donut-legend" id="ordersLegend"></div>
                    </div>
                </div>
            </div>

            <div class="card" data-card="top-products">
                <div style="font-weight:700;margin-bottom:8px">Top Products by Revenue</div>
                <?php if (empty($top_products_by_revenue)) { ?>
                    <div class="empty-state">No data</div>
                <?php } else { ?>
                    <table class="table">
                        <thead><tr><th>#</th><th>Product</th><th>Revenue</th><th style="width:40%">Share</th></tr></thead>
                        <tbody>
                        <?php
                            $maxRev = 0; foreach ($top_products_by_revenue as $r) { if ($r['revenue'] > $maxRev) $maxRev = $r['revenue']; }
                            $i=0; foreach ($top_products_by_revenue as $row) { $i++; $share = $maxRev > 0 ? ($row['revenue'] / $maxRev) * 100 : 0; ?>
                            <tr>
                                <td><?php echo $i; ?></td>
                                <td title="<?php echo e($row['name']); ?>"><?php echo e($row['name']); ?></td>
                                <td><?php echo 'RM ' . number_format($row['revenue'],2); ?></td>
                                <td>
                                    <div class="inline-bar"><span style="width:<?php echo round($share,2); ?>%"></span></div>
                                    <div class="small-muted" style="margin-top:6px"><?php echo round($share,1); ?>% of top</div>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>
        </div>

        <div>
            <div class="card" data-card="revenue-category" style="margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <div>
                        <div style="font-weight:700">Revenue by Category</div>
                        <div class="small-muted">Which categories earn the most</div>
                    </div>
                </div>
                <?php if (empty($revenue_by_category)) { ?>
                    <div class="empty-state">No data</div>
                <?php } else { ?>
                    <div style="display:flex;flex-direction:column;gap:10px">
                        <?php $max = 0; foreach ($revenue_by_category as $c) if ($c['revenue']>$max) $max=$c['revenue'];
                        foreach ($revenue_by_category as $c) { $pct = $max>0?($c['revenue']/$max)*100:0; ?>
                            <div style="display:flex;align-items:center;gap:8px">
                                <div style="flex:1;min-width:0">
                                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                                        <div style="font-weight:600"><?php echo e($c['category']); ?></div>
                                        <div class="small-muted"><?php echo 'RM ' . number_format($c['revenue'],2); ?></div>
                                    </div>
                                    <div class="inline-bar"><span style="width:<?php echo round($pct,2); ?>%"></span></div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

            <div class="card" data-card="new-members" style="margin-bottom:16px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <div>
                        <div style="font-weight:700">New Members (Last 6 Months)</div>
                        <div class="small-muted">Monthly signups</div>
                    </div>
                    <div class="small-muted">Total recent: <?php echo array_sum(array_column($new_members_by_month,'count')) ?: 0; ?></div>
                </div>
                <?php if (empty($new_members_by_month)) { ?>
                    <div class="empty-state">No data</div>
                <?php } else { ?>
                    <div>
                        <div class="mini-columns" id="membersMiniCols">
                            <?php $mmax=0; foreach ($new_members_by_month as $m) if ($m['count']>$mmax) $mmax=$m['count'];
                            foreach ($new_members_by_month as $m) { $h = $mmax>0?($m['count']/$mmax)*100:0; ?>
                                <div class="mini-column" data-month="<?php echo e($m['month']); ?>" style="height:<?php echo round($h,1); ?>%" title="<?php echo e($m['month']); ?>: <?php echo e($m['count']); ?>">
                                    <div style="font-size:0.78rem;color:var(--muted);transform:translateY(-6px);"><?php echo e($m['month']); ?></div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="card" data-card="payment-methods">
                <div style="font-weight:700;margin-bottom:8px">Payment Methods</div>
                <?php if (empty($payment_methods)) { ?>
                    <div class="empty-state">No data</div>
                <?php } else { ?>
                    <?php $pmax=0; foreach ($payment_methods as $p) if ($p['count']>$pmax) $pmax=$p['count']; ?>
                    <table class="table">
                        <thead><tr><th>Method</th><th>Completed</th><th>Share</th></tr></thead>
                        <tbody>
                        <?php foreach ($payment_methods as $p) { $share = $pmax>0?($p['count']/$pmax)*100:0; ?>
                            <tr>
                                <td><?php echo e($p['payment_method']); ?></td>
                                <td><?php echo e($p['count']); ?></td>
                                <td>
                                    <div class="inline-bar"><span style="width:<?php echo round($share,1); ?>%"></span></div>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>

        </div>
    </section>

    <section style="margin-top:18px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div class="card" data-card="vouchers">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <div style="font-weight:700">Top Voucher Codes</div>
                <div class="small-muted">Usage count</div>
            </div>
            <?php if (empty($voucher_usage)) { ?>
                <div class="empty-state">No data</div>
            <?php } else { ?>
                <?php $vmax=0; foreach ($voucher_usage as $v) if ($v['times_used']>$vmax) $vmax=$v['times_used']; ?>
                <table class="table">
                    <thead><tr><th>Code</th><th>Times Used</th><th>Bar</th></tr></thead>
                    <tbody>
                        <?php foreach ($voucher_usage as $v) { $pct = $vmax>0?($v['times_used']/$vmax)*100:0; ?>
                            <tr>
                                <td><?php echo e($v['code']); ?></td>
                                <td><?php echo e($v['times_used']); ?></td>
                                <td><div class="inline-bar"><span style="width:<?php echo round($pct,1); ?>%"></span></div></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>
        </div>

        <div class="card" data-card="feedback">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                <div style="font-weight:700">Feedback & Ratings</div>
                <div class="small-muted">Overview</div>
            </div>
            <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px">
                <div style="font-size:1.4rem;font-weight:700"><?php echo $avg_rating!==null?number_format($avg_rating,2):''; ?> / 5</div>
                <div class="small-muted">Avg Product Rating</div>
            </div>
            <?php if (empty($feedback_statistics)) { ?>
                <div class="empty-state">No feedback data</div>
            <?php } else { ?>
                <table class="table">
                    <thead><tr><th>Category</th><th>Total</th><th>Avg Rating</th><th>Pending</th><th>Reviewed</th><th>Resolved</th></tr></thead>
                    <tbody>
                        <?php foreach ($feedback_statistics as $f) { ?>
                            <tr>
                                <td><?php echo e($f['category']); ?></td>
                                <td><?php echo e($f['total_feedback']); ?></td>
                                <td><?php echo e(number_format($f['average_rating'],2)); ?></td>
                                <td><?php echo e($f['pending_count']); ?></td>
                                <td><?php echo e($f['reviewed_count']); ?></td>
                                <td><?php echo e($f['resolved_count']); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } ?>

            <?php if (empty($feedback_status)) { ?>
                <!-- no status -->
            <?php } else { ?>
                <div style="margin-top:12px">
                    <div style="font-weight:700;margin-bottom:8px">Feedback Status</div>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <?php $fs_total = array_sum(array_column($feedback_status,'count')); foreach ($feedback_status as $fs) { $pct = $fs_total>0?($fs['count']/$fs_total)*100:0; ?>
                            <div style="min-width:140px">
                                <div style="display:flex;justify-content:space-between"><div><?php echo e($fs['status']); ?></div><div class="small-muted"><?php echo e($fs['count']); ?></div></div>
                                <div class="inline-bar" style="margin-top:6px"><span style="width:<?php echo round($pct,1); ?>%"></span></div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </section>

</div>

<!-- KPI Details Modal with Charts -->
<div class="modal-overlay" id="kpiModal">
    <div class="modal-content">
        <button class="modal-close" id="modalClose">&times;</button>
        <div class="modal-title" id="modalTitle">Total Revenue</div>
        <div class="modal-subtitle" id="modalSubtitle">All-time revenue from completed orders</div>
        <div id="modalChartArea" class="modal-chart-container" style="display:none"></div>
        <div id="modalStats"></div>
    </div>
</div>

<script>
    // Pass PHP datasets to JS
    const revenueByMonth = <?php echo json_encode($revenue_by_month ?: []); ?>;
    const ordersByStatus = <?php echo json_encode($orders_by_status ?: []); ?>;
    const topProducts = <?php echo json_encode($top_products_by_revenue ?: []); ?>;
    const revenueByCategory = <?php echo json_encode($revenue_by_category ?: []); ?>;
    const newMembersByMonth = <?php echo json_encode($new_members_by_month ?: []); ?>;
    const paymentMethods = <?php echo json_encode($payment_methods ?: []); ?>;
    const voucherUsage = <?php echo json_encode($voucher_usage ?: []); ?>;
    const avgRating = <?php echo json_encode($avg_rating); ?>;
    const feedbackStats = <?php echo json_encode($feedback_statistics ?: []); ?>;
    const feedbackStatus = <?php echo json_encode($feedback_status ?: []); ?>;

    (function($){
        // Profile toggle
        $('#profileCircle').on('mouseenter', function(){ $('#profilePop').stop(true,true).fadeIn(180); });
        $('.profile-pop').on('mouseleave', function(){ $(this).fadeOut(160); });
        $('#profileCircle').on('mouseleave', function(){ setTimeout(function(){ if(!$('.profile-pop:hover').length) $('#profilePop').fadeOut(160); }, 200); });

        // KPI modal details
        const kpiDetails = {
            revenue: {
                title: 'Total Revenue',
                subtitle: 'All-time revenue from completed orders',
                stats: [
                    ['All-time Total', `RM ${Number(<?php echo $totalRevenue; ?>).toFixed(2)}`],
                    ['From Completed Orders', '<?php echo $totalOrders; ?> orders'],
                    ['Avg per Order', `RM ${Number(<?php echo $avgOrderValue; ?>).toFixed(2)}`]
                ]
            },
            orders: {
                title: 'Total Orders',
                subtitle: 'Complete order history',
                stats: [
                    ['Total Orders', '<?php echo $totalOrders; ?>'],
                    ['Avg Order Value', `RM ${Number(<?php echo $avgOrderValue; ?>).toFixed(2)}`],
                    ['Unique Members', '<?php echo $totalProducts; ?> customer base']
                ]
            },
            avgorder: {
                title: 'Avg Order Value',
                subtitle: 'Average revenue per paid order',
                stats: [
                    ['Average Value', `RM ${Number(<?php echo $avgOrderValue; ?>).toFixed(2)}`],
                    ['Total Revenue', `RM ${Number(<?php echo $totalRevenue; ?>).toFixed(2)}`],
                    ['Total Paid Orders', '<?php echo $totalOrders; ?>']
                ]
            },
            members: {
                title: 'New Members (Last 30 Days)',
                subtitle: 'Recent account registrations',
                stats: [
                    ['New Members (30d)', '<?php echo $newMembers30; ?>'],
                    ['Total Members', '<?php echo $totalProducts; ?> registered'],
                    ['Growth Rate', 'Last 30 days period']
                ]
            },
            products: {
                title: 'Total Products',
                subtitle: 'Catalog size',
                stats: [
                    ['Total Products', '<?php echo $totalProducts; ?>'],
                    ['Low Stock Items', '<?php echo $lowStock; ?>'],
                    ['Available for Sale', `${<?php echo $totalProducts; ?> - <?php echo $lowStock; ?>} items`]
                ]
            },
            lowstock: {
                title: 'Low Stock Products',
                subtitle: 'Items with stock ≤ 5 units',
                stats: [
                    ['Low Stock Items', '<?php echo $lowStock; ?>'],
                    ['Total Catalog', '<?php echo $totalProducts; ?> products'],
                    ['Alert Status', '<?php echo $lowStock; ?> items need restocking']
                ]
            }
        };

        // Card details for chart/data cards
        const cardDetails = {
            'revenue-trend': {
                title: 'Revenue Trend',
                subtitle: 'Monthly revenue over the last 12 months',
                getStats: () => {
                    if (!revenueByMonth || revenueByMonth.length === 0) return [['Status', 'No data available']];
                    const total = revenueByMonth.reduce((s, m) => s + parseFloat(m.revenue || 0), 0);
                    const avg = total / revenueByMonth.length;
                    const max = Math.max(...revenueByMonth.map(m => parseFloat(m.revenue || 0)));
                    return [
                        ['Total Revenue (12m)', `RM ${Number(total).toFixed(2)}`],
                        ['Avg Monthly', `RM ${Number(avg).toFixed(2)}`],
                        ['Peak Month', `RM ${Number(max).toFixed(2)}`],
                        ['Data Points', `${revenueByMonth.length} months`]
                    ];
                }
            },
            'orders-status': {
                title: 'Orders by Status',
                subtitle: 'Distribution of orders across different statuses',
                getStats: () => {
                    if (!ordersByStatus || ordersByStatus.length === 0) return [['Status', 'No data available']];
                    const total = ordersByStatus.reduce((s, o) => s + Number(o.count), 0);
                    return ordersByStatus.map(o => [
                        `${o.status}`,
                        `${o.count} (${Math.round((Number(o.count)/total)*100)}%)`
                    ]);
                }
            },
            'top-products': {
                title: 'Top Products by Revenue',
                subtitle: 'Best performing products by sales',
                getStats: () => {
                    if (!topProducts || topProducts.length === 0) return [['Status', 'No data available']];
                    const total = topProducts.reduce((s, p) => s + parseFloat(p.revenue || 0), 0);
                    return topProducts.slice(0, 5).map((p, i) => [
                        `${i+1}. ${p.name.substring(0, 20)}${p.name.length > 20 ? '...' : ''}`,
                        `RM ${Number(p.revenue).toFixed(2)} (${Math.round((parseFloat(p.revenue)/total)*100)}%)`
                    ]);
                }
            },
            'revenue-category': {
                title: 'Revenue by Category',
                subtitle: 'Sales performance across product categories',
                getStats: () => {
                    if (!revenueByCategory || revenueByCategory.length === 0) return [['Status', 'No data available']];
                    const total = revenueByCategory.reduce((s, c) => s + parseFloat(c.revenue || 0), 0);
                    return revenueByCategory.map(c => [
                        c.category,
                        `RM ${Number(c.revenue).toFixed(2)} (${Math.round((parseFloat(c.revenue)/total)*100)}%)`
                    ]);
                }
            },
            'new-members': {
                title: 'New Members',
                subtitle: 'Monthly account registrations over the last 6 months',
                getStats: () => {
                    if (!newMembersByMonth || newMembersByMonth.length === 0) return [['Status', 'No data available']];
                    const total = newMembersByMonth.reduce((s, m) => s + Number(m.count), 0);
                    const avg = Math.round(total / newMembersByMonth.length);
                    return [
                        ['Total (6m)', total],
                        ['Avg Monthly', avg],
                        ['Peak Month', Math.max(...newMembersByMonth.map(m => Number(m.count)))],
                        ['Data Points', `${newMembersByMonth.length} months`]
                    ];
                }
            },
            'payment-methods': {
                title: 'Payment Methods',
                subtitle: 'Distribution of completed payments by method',
                getStats: () => {
                    if (!paymentMethods || paymentMethods.length === 0) return [['Status', 'No data available']];
                    const total = paymentMethods.reduce((s, p) => s + Number(p.count), 0);
                    return paymentMethods.map(p => [
                        p.payment_method,
                        `${p.count} (${Math.round((Number(p.count)/total)*100)}%)`
                    ]);
                }
            },
            'vouchers': {
                title: 'Top Voucher Codes',
                subtitle: 'Most frequently used promotional codes',
                getStats: () => {
                    if (!voucherUsage || voucherUsage.length === 0) return [['Status', 'No data available']];
                    const total = voucherUsage.reduce((s, v) => s + Number(v.times_used), 0);
                    return voucherUsage.slice(0, 5).map((v, i) => [
                        `${i+1}. ${v.code}`,
                        `${v.times_used} uses (${Math.round((Number(v.times_used)/total)*100)}%)`
                    ]);
                }
            },
            'feedback': {
                title: 'Feedback & Ratings',
                subtitle: 'Customer feedback and product ratings overview',
                getStats: () => {
                    const stats = [];
                    if (avgRating !== null) {
                        stats.push(['Avg Product Rating', `${Number(avgRating).toFixed(2)} / 5`]);
                    }
                    if (feedbackStats && feedbackStats.length > 0) {
                        stats.push(['Feedback Categories', feedbackStats.length]);
                        const totalFeedback = feedbackStats.reduce((s, f) => s + Number(f.total_feedback), 0);
                        stats.push(['Total Feedback', totalFeedback]);
                    }
                    if (feedbackStatus && feedbackStatus.length > 0) {
                        feedbackStatus.forEach(fs => {
                            stats.push([`${fs.status}`, fs.count]);
                        });
                    }
                    return stats.length > 0 ? stats : [['Status', 'No data available']];
                }
            }
        };

        // Modal chart functions
        function drawModalAreaChart(data, containerId) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.innerHTML = '';
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('viewBox', '0 0 800 300');
            svg.setAttribute('style', 'width:100%;height:100%');
            
            if (!data || data.length === 0) {
                svg.innerHTML = '<text x="400" y="150" text-anchor="middle" fill="#999">No data</text>';
                container.appendChild(svg);
                return;
            }
            
            const values = data.map(d => parseFloat(d.revenue || 0));
            const max = Math.max(...values, 1);
            const w = 800, h = 300;
            const points = values.map((v, i) => {
                const x = (i / (values.length - 1)) * (w - 80) + 40;
                const y = h - 40 - ((v / max) * (h - 80));
                return { x, y, v };
            });
            
            const defs = document.createElementNS('http://www.w3.org/2000/svg','defs');
            defs.innerHTML = `<linearGradient id="g2" x1="0" x2="0" y1="0" y2="1">
                <stop offset="0%" stop-color="#4f46e5" stop-opacity="0.15" />
                <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
            </linearGradient>`;
            svg.appendChild(defs);
            
            const pathD = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');
            const areaD = pathD + ` L ${points[points.length-1].x} ${h-40} L ${points[0].x} ${h-40} Z`;
            
            const area = document.createElementNS('http://www.w3.org/2000/svg','path');
            area.setAttribute('d', areaD);
            area.setAttribute('fill','url(#g2)');
            svg.appendChild(area);
            
            const line = document.createElementNS('http://www.w3.org/2000/svg','path');
            line.setAttribute('d', pathD);
            line.setAttribute('fill','none');
            line.setAttribute('stroke','#4f46e5');
            line.setAttribute('stroke-width','3');
            line.setAttribute('stroke-linejoin','round');
            line.setAttribute('stroke-linecap','round');
            svg.appendChild(line);
            
            points.forEach(p => {
                const c = document.createElementNS('http://www.w3.org/2000/svg','circle');
                c.setAttribute('cx', p.x);
                c.setAttribute('cy', p.y);
                c.setAttribute('r', '5');
                c.setAttribute('fill', '#fff');
                c.setAttribute('stroke', '#4f46e5');
                c.setAttribute('stroke-width', '2');
                svg.appendChild(c);
            });
            
            container.appendChild(svg);
        }

        function drawModalBarChart(data, containerId, labelKey, valueKey) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.innerHTML = '';
            
            if (!data || data.length === 0) {
                container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#999">No data</div>';
                return;
            }
            
            const topN = data.slice(0, 8);
            const maxVal = Math.max(...topN.map(d => Number(d[valueKey] || 0)));
            
            let html = '<div style="display:flex;flex-direction:column;gap:12px;padding:12px">';
            topN.forEach(item => {
                const val = Number(item[valueKey] || 0);
                const pct = (val / maxVal) * 100;
                const label = String(item[labelKey] || '').substring(0, 20);
                html += `
                    <div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:0.9rem">
                            <span>${label}</span>
                            <span style="font-weight:700;color:#4f46e5">${val.toFixed(0)}</span>
                        </div>
                        <div style="height:8px;background:#eef2f7;border-radius:4px;overflow:hidden">
                            <div style="height:100%;background:linear-gradient(90deg,#4f46e5,#06b6d4);width:${pct}%"></div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        }

        function drawModalDonutChart(data, containerId) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.innerHTML = '';
            
            if (!data || data.length === 0) {
                container.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#999">No data</div>';
                return;
            }
            
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('viewBox', '0 0 300 300');
            svg.setAttribute('style', 'width:100%;height:300px');
            
            const total = data.reduce((s, d) => s + Number(d.count || 0), 0);
            if (total === 0) {
                svg.innerHTML = '<text x="150" y="150" text-anchor="middle" fill="#999">No data</text>';
                container.appendChild(svg);
                return;
            }
            
            const colors = ['#06b6d4','#4f46e5','#f59e0b','#ef4444','#10b981','#8b5cf6'];
            const r = 60;
            const cx = 150;
            const cy = 150;
            const circumference = 2 * Math.PI * r;
            let offset = 0;
            
            data.forEach((d, i) => {
                const frac = Number(d.count || 0) / total;
                const dash = frac * circumference;
                const circle = document.createElementNS('http://www.w3.org/2000/svg','circle');
                circle.setAttribute('r', r);
                circle.setAttribute('cx', cx);
                circle.setAttribute('cy', cy);
                circle.setAttribute('fill', 'none');
                circle.setAttribute('stroke', colors[i % colors.length]);
                circle.setAttribute('stroke-width', '20');
                circle.setAttribute('stroke-dasharray', `${dash} ${circumference - dash}`);
                circle.setAttribute('transform', `rotate(-90 ${cx} ${cy})`);
                circle.setAttribute('stroke-linecap', 'round');
                circle.style.strokeDashoffset = -offset;
                svg.appendChild(circle);
                offset += dash;
            });
            
            // Center text
            const text = document.createElementNS('http://www.w3.org/2000/svg','text');
            text.setAttribute('x', cx);
            text.setAttribute('y', cy);
            text.setAttribute('text-anchor', 'middle');
            text.setAttribute('dy', '0.3em');
            text.setAttribute('font-size', '20');
            text.setAttribute('font-weight', '700');
            text.setAttribute('fill', '#4f46e5');
            text.textContent = total;
            svg.appendChild(text);
            
            container.appendChild(svg);
        }

        // Universal card click handler for KPI and data cards
        $('[data-kpi], [data-card]').on('click', function(){
            const kpiType = $(this).attr('data-kpi');
            const cardType = $(this).attr('data-card');
            const type = kpiType || cardType;
            
            let detail;
            if (kpiType) {
                detail = kpiDetails[kpiType];
            } else if (cardType && cardDetails[cardType]) {
                detail = cardDetails[cardType];
            }
            
            if (!detail) return;

            $('#modalTitle').text(detail.title);
            $('#modalSubtitle').text(detail.subtitle);
            
            // Show chart for data cards
            if (cardType) {
                $('#modalChartArea').show();
                if (cardType === 'revenue-trend') {
                    drawModalAreaChart(revenueByMonth, 'modalChartArea');
                } else if (cardType === 'orders-status') {
                    drawModalDonutChart(ordersByStatus, 'modalChartArea');
                } else if (cardType === 'top-products') {
                    drawModalBarChart(topProducts, 'modalChartArea', 'name', 'revenue');
                } else if (cardType === 'revenue-category') {
                    drawModalBarChart(revenueByCategory, 'modalChartArea', 'category', 'revenue');
                } else if (cardType === 'new-members') {
                    drawModalAreaChart(newMembersByMonth.map(m => ({...m, revenue: m.count})), 'modalChartArea');
                } else if (cardType === 'payment-methods') {
                    drawModalBarChart(paymentMethods, 'modalChartArea', 'payment_method', 'count');
                } else if (cardType === 'vouchers') {
                    drawModalBarChart(voucherUsage, 'modalChartArea', 'code', 'times_used');
                } else if (cardType === 'feedback') {
                    if (feedbackStatus && feedbackStatus.length > 0) {
                        drawModalDonutChart(feedbackStatus, 'modalChartArea');
                    } else {
                        $('#modalChartArea').hide();
                    }
                }
            } else {
                $('#modalChartArea').hide();
            }
            
            // Show stats
            let stats = detail.getStats ? detail.getStats() : detail.stats;
            let statsHtml = '';
            stats.forEach(s => {
                statsHtml += `<div class="modal-stat"><div class="modal-stat-label">${s[0]}</div><div class="modal-stat-value">${s[1]}</div></div>`;
            });
            $('#modalStats').html(statsHtml);
            $('#kpiModal').addClass('active');
        });

        // Modal close
        $('#modalClose, #kpiModal').on('click', function(e){
            if (e.target.id === 'kpiModal' || e.target.id === 'modalClose') {
                $('#kpiModal').removeClass('active');
            }
        });

        // Filtering removed: charts use server-rendered data and are not updated via AJAX

        // Revenue area chart
        function drawAreaChart(data, svgSelector) {
            const svg = document.querySelector(svgSelector);
            if(!svg) return;
            const w = svg.viewBox.baseVal.width || 800;
            const h = svg.viewBox.baseVal.height || 240;
            svg.innerHTML = '';
            if (!data || data.length === 0) return;
            const values = data.map(d=>parseFloat(d.revenue));
            const labels = data.map(d=>d.month);
            const max = Math.max(...values, 1);
            const points = values.map((v,i)=>{
                const x = (i/(values.length-1)) * (w-40) + 20;
                const y = h - 20 - ((v/max) * (h-60));
                return {x,y,v, label: labels[i]};
            });
            // defs gradient
            const defs = document.createElementNS('http://www.w3.org/2000/svg','defs');
            defs.innerHTML = `
                <linearGradient id="g1" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="${getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#4f46e5'}" stop-opacity="0.12" />
                    <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
                </linearGradient>
            `;
            svg.appendChild(defs);
            // build path
            const pathD = points.map((p,i)=> `${i===0? 'M':'L'} ${p.x} ${p.y}`).join(' ');
            const areaD = pathD + ` L ${points[points.length-1].x} ${h-20} L ${points[0].x} ${h-20} Z`;
            const area = document.createElementNS('http://www.w3.org/2000/svg','path');
            area.setAttribute('d', areaD);
            area.setAttribute('fill','url(#g1)');
            area.setAttribute('opacity','0.95');
            svg.appendChild(area);
            const line = document.createElementNS('http://www.w3.org/2000/svg','path');
            line.setAttribute('d', pathD);
            line.setAttribute('fill','none');
            line.setAttribute('stroke',getComputedStyle(document.documentElement).getPropertyValue('--accent').trim()||'#4f46e5');
            line.setAttribute('stroke-width','2.5');
            line.setAttribute('stroke-linejoin','round');
            line.setAttribute('stroke-linecap','round');
            svg.appendChild(line);
            // points
            points.forEach(p=>{
                const c = document.createElementNS('http://www.w3.org/2000/svg','circle');
                c.setAttribute('cx',p.x); c.setAttribute('cy',p.y); c.setAttribute('r',4);
                c.setAttribute('fill','#fff'); c.setAttribute('stroke',getComputedStyle(document.documentElement).getPropertyValue('--accent').trim()||'#4f46e5');
                c.setAttribute('stroke-width',2);
                c.style.transition = 'r .12s ease';
                $(c).on('mouseenter', function(e){
                    $(this).attr('r',6);
                    showTooltip(`${p.label}: RM ${Number(p.v).toFixed(2)}`, e.pageX, e.pageY);
                }).on('mouseleave', function(){
                    $(this).attr('r',4);
                    hideTooltip();
                });
                svg.appendChild(c);
            });
        }

        // Donut chart
        function drawDonutChart(data, svgSelector, legendSelector) {
            const svg = document.querySelector(svgSelector);
            const legend = document.querySelector(legendSelector);
            if(!svg) return;
            svg.innerHTML = '';
            const total = data.reduce((s,d)=>s+Number(d.count),0);
            if (total === 0) { if(legend) legend.innerHTML='No data'; return; }
            const colors = ['#06b6d4','#4f46e5','#f59e0b','#ef4444','#10b981','#8b5cf6'];
            const r = 70; const cx=90; const cy=90; const circumference = 2*Math.PI*r;
            let offset = 0;
            data.forEach((d,i)=>{
                const frac = Number(d.count)/total;
                const dash = frac * circumference;
                const circle = document.createElementNS('http://www.w3.org/2000/svg','circle');
                circle.setAttribute('r',r);
                circle.setAttribute('cx',cx);
                circle.setAttribute('cy',cy);
                circle.setAttribute('fill','none');
                circle.setAttribute('stroke',colors[i%colors.length]);
                circle.setAttribute('stroke-width',18);
                circle.setAttribute('stroke-dasharray',`${dash} ${circumference-dash}`);
                circle.setAttribute('transform',`rotate(-90 ${cx} ${cy})`);
                circle.setAttribute('stroke-linecap','round');
                circle.style.strokeDashoffset = -offset;
                circle.style.transition = 'stroke-width .18s ease, stroke-dasharray .6s ease';
                $(circle).on('mouseenter', function(e){
                    $(this).attr('stroke-width',24);
                    showTooltip(`${d.status || 'Status'}: ${d.count} (${Math.round(frac*100)}%)`, e.pageX, e.pageY);
                }).on('mouseleave', function(){
                    $(this).attr('stroke-width',18);
                    hideTooltip();
                });
                svg.appendChild(circle);
                offset += dash;
            });
            // legend
            if(legend) {
                legend.innerHTML = '';
                data.forEach((d,i)=>{
                    const frac = Number(d.count)/total;
                    const el = document.createElement('div'); el.className='legend-item';
                    const dot = document.createElement('span'); dot.className='legend-dot'; dot.style.background = colors[i%colors.length];
                    el.appendChild(dot);
                    const t = document.createElement('div'); t.innerHTML = `${d.status || 'Status'} <span style="color:var(--muted);margin-left:6px">${d.count} (${Math.round(frac*100)}%)</span>`;
                    el.appendChild(t);
                    legend.appendChild(el);
                });
            }
        }

        // Tooltip
        const tip = $('<div>').css({position:'absolute',padding:'8px 10px','background':'#0f172a',color:'#fff','border-radius':'6px','font-size':'0.85rem',display:'none','pointer-events':'none','z-index':9999}).appendTo('body');
        function showTooltip(text,x,y){ tip.text(text).css({left:x+12,top:y+12}).fadeIn(120); }
        function hideTooltip(){ tip.fadeOut(80); }

        // Draw on load
        $(function(){
            drawAreaChart(revenueByMonth, '#revenueAreaSvg');
            drawDonutChart(ordersByStatus, '#ordersDonut', '#ordersLegend');
            // animate inline bars
            $('.inline-bar > span').each(function(i,el){ const w = $(el).width(); $(el).css('width','0%'); setTimeout(()=>$(el).css('width',$(el).attr('style')?$(el).attr('style').match(/width:(.+)%/)?$(el).css('width'):$(el).css('width'):w), 60*i); });
            // mini column hover tooltips
            $('.mini-column').on('mouseenter', function(e){ showTooltip($(this).attr('title'), e.pageX, e.pageY); }).on('mouseleave', hideTooltip);
        });

        // Header button handlers
        // Refresh removed per request

        // Export removed

    })(jQuery);
</script>
</body>
</html>

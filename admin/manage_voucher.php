<?php
session_start();

// Include database connection
require_once '../helper/pdo_connection.php';
require_once '../helper/auth.php';

// Ensure only admin can access with proper permissions
// Using 'orders' as vouchers related to checkout/money
require_permission('orders', 'view');

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';

// Clear session messages after fetching
unset($_SESSION['message']);
unset($_SESSION['message_type']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /**
     * Common helper to sanitize checkbox-selected IDs.
     */
    $sanitizeIds = function ($input) {
        $ids = [];
        if (is_array($input)) {
            foreach ($input as $rawId) {
                if (ctype_digit((string)$rawId)) {
                    $ids[] = (int)$rawId;
                }
            }
        }
        return array_values(array_unique($ids));
    };

    /** BATCH DELETE VOUCHERS **/
    if (isset($_POST['batch_delete_vouchers'])) {
        $selectedIds = $sanitizeIds($_POST['selected_vouchers'] ?? []);

        if (!empty($selectedIds)) {
            try {
                if (!has_permission('orders', 'delete')) {
                    throw new Exception("You don't have permission to delete vouchers.");
                }
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $stmt = $pdo->prepare("DELETE FROM Voucher WHERE voucher_id IN ($placeholders)");
                $stmt->execute($selectedIds);

                $_SESSION['message'] = count($selectedIds) . " voucher(s) deleted successfully!";
                $_SESSION['message_type'] = "success";
            } catch (Exception $e) {
                $_SESSION['message'] = "Error deleting selected vouchers: " . $e->getMessage();
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "Please select at least one voucher before deleting.";
            $_SESSION['message_type'] = "error";
        }
        header("Location: manage_voucher.php");
        exit;
    }

    /** CREATE VOUCHER **/
    if (isset($_POST['create_voucher'])) {
        if (!has_permission('orders', 'create')) {
            $message = "You don't have permission to create vouchers.";
            $message_type = 'error';
        } else {
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $discount_value = (float)($_POST['discount_value'] ?? 0);
            $discount_type = $_POST['discount_type'] ?? 'fixed';
            $min_spend = (float)($_POST['min_spend'] ?? 0);
            $max_discount = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : NULL;
            $usage_limit = (int)($_POST['usage_limit'] ?? 1);
            $usage_per_user = (int)($_POST['usage_per_user'] ?? 1);
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $status = $_POST['status'] ?? 'active';

            $errors = [];

            if (empty($code)) $errors[] = "Voucher Code is required.";
            if ($discount_value <= 0) $errors[] = "Discount value must be greater than 0.";
            if (empty($start_date) || empty($end_date)) $errors[] = "Start and End dates are required.";

            // Check uniqueness
            $check = $pdo->prepare("SELECT 1 FROM Voucher WHERE code = ?");
            $check->execute([$code]);
            if ($check->fetch()) {
                $errors[] = "Voucher code '$code' already exists.";
            }

            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO Voucher (code, discount_value, discount_type, min_spend, max_discount, usage_limit, usage_per_user, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$code, $discount_value, $discount_type, $min_spend, $max_discount, $usage_limit, $usage_per_user, $start_date, $end_date, $status]);

                    $_SESSION['message'] = "Voucher created successfully!";
                    $_SESSION['message_type'] = "success";
                    header("Location: manage_voucher.php");
                    exit;
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $message_type = "error";
                }
            } else {
                $message = implode(' ', $errors);
                $message_type = 'error';
            }
        }
    }

    /** UPDATE VOUCHER **/
    if (isset($_POST['update_voucher'])) {
        if (!has_permission('orders', 'update')) {
            $message = "You don't have permission to update vouchers.";
            $message_type = 'error';
        } else {
            $id = (int)($_POST['voucher_id'] ?? 0);
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $discount_value = (float)($_POST['discount_value'] ?? 0);
            $discount_type = $_POST['discount_type'] ?? 'fixed';
            $min_spend = (float)($_POST['min_spend'] ?? 0);
            $max_discount = !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : NULL;
            $usage_limit = (int)($_POST['usage_limit'] ?? 1);
            $usage_per_user = (int)($_POST['usage_per_user'] ?? 1);
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $status = $_POST['status'] ?? 'active';

            $errors = [];
            if (empty($code)) $errors[] = "Voucher Code is required.";

            // Check uniqueness excluding self
            $check = $pdo->prepare("SELECT 1 FROM Voucher WHERE code = ? AND voucher_id <> ?");
            $check->execute([$code, $id]);
            if ($check->fetch()) {
                $errors[] = "Voucher code '$code' already exists.";
            }

            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("UPDATE Voucher SET code=?, discount_value=?, discount_type=?, min_spend=?, max_discount=?, usage_limit=?, usage_per_user=?, start_date=?, end_date=?, status=? WHERE voucher_id=?");
                    $stmt->execute([$code, $discount_value, $discount_type, $min_spend, $max_discount, $usage_limit, $usage_per_user, $start_date, $end_date, $status, $id]);

                    $_SESSION['message'] = "Voucher updated successfully!";
                    $_SESSION['message_type'] = "success";
                    header("Location: manage_voucher.php");
                    exit;
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $message_type = "error";
                }
            } else {
                $message = implode(' ', $errors);
                $message_type = 'error';
            }
        }
    }

    /** DELETE VOUCHER **/
    if (isset($_POST['delete_voucher'])) {
        if (!has_permission('orders', 'delete')) {
            $message = "You don't have permission to delete vouchers.";
            $message_type = 'error';
        } else {
            $id = (int)$_POST['voucher_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM Voucher WHERE voucher_id = ?");
                $stmt->execute([$id]);
                $_SESSION['message'] = "Voucher deleted successfully!";
                $_SESSION['message_type'] = "success";
                header("Location: manage_voucher.php");
                exit;
            } catch (PDOException $e) {
                $message = "Error deleting voucher: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

/** READ VOUCHERS **/
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sql = "SELECT * FROM Voucher WHERE 1=1";
$params = [];
if (!empty($search)) {
    $sql .= " AND code LIKE ?";
    $params[] = "%$search%";
}
$sql .= " ORDER BY voucher_id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$total_vouchers = $pdo->query("SELECT COUNT(*) FROM Voucher")->fetchColumn();
$active_vouchers = $pdo->query("SELECT COUNT(*) FROM Voucher WHERE status='active' AND NOW() BETWEEN start_date AND end_date")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Vouchers - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/admin_panel.css">
    <link rel="stylesheet" href="../assets/css/manage_product.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/manage_voucher.js"></script>
    <style>
        /* Reusing styles from manage_member */
        .bulk-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .enhanced-checkbox {
            width: 18px;
            height: 18px;
            transform: scale(1.2);
            cursor: pointer;
        }

        .selectable-row {
            cursor: pointer;
            transition: background-color 0.18s ease;
        }

        .selectable-row:hover {
            background-color: #f3f4f6;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-active {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-expired {
            background-color: #f3f4f6;
            color: #1f2937;
        }

        /* Sorting Styles */
        th.sortable {
            cursor: pointer;
            user-select: none;
        }

        th.sortable::after {
            content: '▲▼';
            margin-left: 4px;
            font-size: 0.7rem;
            opacity: 0.5;
        }

        th.sortable[data-order="asc"]::after {
            content: '▲';
            opacity: 1;
        }

        th.sortable[data-order="desc"]::after {
            content: '▼';
            opacity: 1;
        }

        .selectable-row.row-selected {
            background-color: #fef3c7;
        }
    </style>
</head>

<body>

    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰ Menu</button>

    <div class="admin-container">
        <?php include '../partials/admin_panel.php'; ?>

        <div class="main-content">

            <div class="header">
                <h1>Manage Vouchers</h1>
                <button class="btn btn-primary" onclick="openCreateModal()">+ Add Voucher</button>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-container" style="margin-bottom:12px;">
                <div class="stat-card">
                    <div class="stat-icon">🎟️</div>
                    <div class="stat-info">
                        <h3><?php echo $total_vouchers; ?></h3>
                        <p>Total Vouchers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">✅</div>
                    <div class="stat-info">
                        <h3><?php echo $active_vouchers; ?></h3>
                        <p>Active & Running</p>
                    </div>
                </div>
            </div>

            <!-- Search -->
            <div class="filter-bar">
                <form method="GET" style="display:flex; gap:15px; flex:1;">
                    <input type="text" name="search" id="mv-search" placeholder="Search by code..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="manage_voucher.php" class="btn" style="background:#cbd5e0;">Clear</a>
                </form>
            </div>

            <!-- Table -->
            <form method="POST" id="voucherBulkForm">
                <div class="bulk-actions">
                    <!-- Checkbox moved to header -->
                    <div></div>
                    <button type="submit" name="batch_delete_vouchers" id="deleteSelectedVouchersBtn" class="btn btn-danger" disabled data-confirm="Delete selected vouchers?">Delete Selected</button>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:40px;">
                                    <input type="checkbox" id="select_all_vouchers" class="enhanced-checkbox">
                                </th>
                                <th class="sortable" data-column="1" data-type="number">ID</th>
                                <th class="sortable" data-column="2" data-type="string">Code</th>
                                <th class="sortable" data-column="3" data-type="string">Discount</th>
                                <th class="sortable" data-column="4" data-type="string">Min Spend</th>
                                <th class="sortable" data-column="5" data-type="string">Usage</th>
                                <th class="sortable" data-column="6" data-type="string">Dates</th>
                                <th class="sortable" data-column="7" data-type="string">Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($vouchers) > 0): ?>
                                <?php foreach ($vouchers as $v): ?>
                                    <?php $rowCheckboxId = 'voucher-select-' . $v['voucher_id']; ?>
                                    <tr class="selectable-row" data-checkbox-id="<?= $rowCheckboxId ?>">
                                        <td>
                                            <input type="checkbox" name="selected_vouchers[]" value="<?= $v['voucher_id'] ?>" class="row-checkbox enhanced-checkbox" id="<?= $rowCheckboxId ?>">
                                        </td>
                                        <td><?= $v['voucher_id'] ?></td>
                                        <td style="font-weight:bold;"><?= htmlspecialchars($v['code']) ?></td>
                                        <td>
                                            <?= $v['discount_type'] === 'fixed' ? 'RM' : '' ?>
                                            <?= number_format($v['discount_value'], 2) ?>
                                            <?= $v['discount_type'] === 'percent' ? '%' : '' ?>
                                        </td>
                                        <td>RM <?= number_format($v['min_spend'], 2) ?></td>
                                        <td><?= $v['usage_limit'] ?> (<?= $v['usage_per_user'] ?>/user)</td>
                                        <td>
                                            <small>
                                                Start: <?= date('Y-m-d', strtotime($v['start_date'])) ?><br>
                                                End: <?= date('Y-m-d', strtotime($v['end_date'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php
                                            // Determine display status (active in DB but expired by date?)
                                            $now = date('Y-m-d H:i:s');
                                            $displayStatus = $v['status'];
                                            if ($v['status'] == 'active' && ($now < $v['start_date'] || $now > $v['end_date'])) {
                                                // Technically it's active in DB but outside valid range
                                                // Keep as active generally, or mark expired if end date passed
                                                if ($now > $v['end_date']) $displayStatus = 'expired';
                                            }
                                            $badgeClass = 'badge-' . $displayStatus;
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= ucfirst($displayStatus) ?></span>
                                        </td>
                                        <td>
                                            <div style="display:flex; gap:5px;">
                                                <button type="button" class="btn btn-warning btn-sm" onclick='mvOpenEdit(<?= json_encode($v) ?>)'>Edit</button>
                                                <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteVoucher(<?= $v['voucher_id'] ?>)">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align:center;">No vouchers found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <!-- DELETE Single Form (Hidden) -->
            <!-- We need logic to delete single item. The simplest way in this structure is to have a small form inside the TD or outside. 
                  Let's re-render the action column properly. -->
            <?php /* Redoing the loop part for actions to include form */ ?>
        </div>
    </div>

    <!-- Create Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content" style="max-width:600px;">
            <div class="modal-header">
                <h2>Create Voucher</h2>
                <span class="close" onclick="closeCreateModal()">&times;</span>
            </div>
            <form method="POST">

                <div class="form-group">
                    <label>Voucher Code</label>
                    <input type="text" name="code" required style="text-transform:uppercase;">
                </div>

                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Discount Type</label>
                        <select name="discount_type">
                            <option value="fixed">Fixed Amount (RM)</option>
                            <option value="percent">Percentage (%)</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Discount Value</label>
                        <input type="number" step="0.01" name="discount_value" required>
                    </div>
                </div>

                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Min Spend (RM)</label>
                        <input type="number" step="0.01" name="min_spend" value="0">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Max Discount (RM) (Optional)</label>
                        <input type="number" step="0.01" name="max_discount">
                    </div>
                </div>

                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Total Usage Limit</label>
                        <input type="number" name="usage_limit" value="100">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Usage Per User</label>
                        <input type="number" name="usage_per_user" value="1">
                    </div>
                </div>

                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Start Date</label>
                        <input type="datetime-local" name="start_date" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>End Date</label>
                        <input type="datetime-local" name="end_date" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <button type="submit" name="create_voucher" class="btn btn-primary" style="width:100%;">Create Voucher</button>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width:600px;">
            <div class="modal-header">
                <h2>Edit Voucher</h2>
                <span class="close" onclick="mvCloseEdit()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="voucher_id" id="edit_voucher_id">

                <div class="form-group">
                    <label>Voucher Code</label>
                    <input type="text" name="code" id="edit_code" required style="text-transform:uppercase;">
                </div>

                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Discount Type</label>
                        <select name="discount_type" id="edit_discount_type">
                            <option value="fixed">Fixed Amount (RM)</option>
                            <option value="percent">Percentage (%)</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Discount Value</label>
                        <input type="number" step="0.01" name="discount_value" id="edit_discount_value" required>
                    </div>
                </div>

                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Min Spend (RM)</label>
                        <input type="number" step="0.01" name="min_spend" id="edit_min_spend">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Max Discount (RM)</label>
                        <input type="number" step="0.01" name="max_discount" id="edit_max_discount">
                    </div>
                </div>

                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Total Usage Limit</label>
                        <input type="number" name="usage_limit" id="edit_usage_limit">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Usage Per User</label>
                        <input type="number" name="usage_per_user" id="edit_usage_per_user">
                    </div>
                </div>

                <div style="display:flex; gap:15px;">
                    <div class="form-group" style="flex:1;">
                        <label>Start Date</label>
                        <input type="datetime-local" name="start_date" id="edit_start_date" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>End Date</label>
                        <input type="datetime-local" name="end_date" id="edit_end_date" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>

                <div style="display:flex; gap:10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="mvCloseEdit()">Cancel</button>
                    <button type="submit" name="update_voucher" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden Form for Single Delete -->
    <form id="singleDeleteForm" method="POST" style="display:none;">
        <input type="hidden" name="delete_voucher" value="1">
        <input type="hidden" name="voucher_id" id="delete_id_input">
    </form>

</body>

</html>
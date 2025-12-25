<?php
session_start();

require_once '../helper/pdo_connection.php';
require_once '../helper/auth.php';

// Ensure admin has permission to view reviews
require_permission('reviews', 'view');

$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? '';
unset($_SESSION['message'], $_SESSION['message_type']);

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

    /** BATCH DELETE REVIEWS **/
    if (isset($_POST['batch_delete_reviews'])) {
        if (!has_permission('reviews', 'delete')) {
            $message = "You don't have permission to delete reviews.";
            $message_type = 'error';
        } else {
            $selectedIds = $sanitizeIds($_POST['selected_reviews'] ?? []);

            if (!empty($selectedIds)) {
                try {
                    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                    $stmt = $pdo->prepare("DELETE FROM Review WHERE review_id IN ($placeholders)");
                    $stmt->execute($selectedIds);

                    $message = count($selectedIds) . " review(s) deleted successfully!";
                    $message_type = "success";
                } catch (PDOException $e) {
                    $message = "Error deleting selected reviews: " . $e->getMessage();
                    $message_type = "error";
                }
            } else {
                $message = "Please select at least one review before deleting.";
                $message_type = "error";
            }
        }
    }

    /** EXPORT SELECTED REVIEWS **/
    if (isset($_POST['export_selected_reviews'])) {
        $selectedIds = $sanitizeIds($_POST['selected_reviews'] ?? []);

        if (!empty($selectedIds)) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=reviews_selected_export.csv');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['review_id', 'product_name', 'username', 'rating', 'review_text', 'created_date']);

            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $stmt = $pdo->prepare("SELECT r.review_id, p.name AS product_name, m.username, r.rating, r.review_text, r.created_date
                                   FROM Review r
                                   JOIN Member m ON r.member_id = m.member_id
                                   JOIN Product p ON r.product_id = p.product_id
                                   WHERE r.review_id IN ($placeholders) ORDER BY r.review_id ASC");
            $stmt->execute($selectedIds);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['review_id'],
                    $row['product_name'],
                    $row['username'],
                    $row['rating'],
                    $row['review_text'],
                    $row['created_date']
                ]);
            }

            fclose($output);
            exit;
        } else {
            $message = "Please select at least one review before exporting.";
            $message_type = "error";
        }
    }

    /** DELETE REVIEW **/
    if (isset($_POST['delete_review'])) {
        if (!has_permission('reviews', 'delete')) {
            $message = "You don't have permission to delete reviews.";
            $message_type = 'error';
        } else {
            $review_id = (int)$_POST['review_id'];
            try {
                $stmt = $pdo->prepare("DELETE FROM Review WHERE review_id = ?");
                $stmt->execute([$review_id]);
                $message = "Review deleted successfully!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Error deleting review: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// EXPORT REVIEWS TO CSV
if (isset($_GET['export']) && $_GET['export'] === 'reviews') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=reviews_export.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['review_id', 'product_name', 'username', 'rating', 'review_text', 'created_date']);

    $stmt = $pdo->query("SELECT r.review_id, p.name AS product_name, m.username, r.rating, r.review_text, r.created_date
                        FROM Review r
                        JOIN Member m ON r.member_id = m.member_id
                        JOIN Product p ON r.product_id = p.product_id
                        ORDER BY r.review_id ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['review_id'], $row['product_name'], $row['username'], $row['rating'], $row['review_text'], $row['created_date']]);
    }

    fclose($output);
    exit;
}

/** READ reviews with search **/
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT r.review_id, r.product_id, p.name AS product_name, r.member_id, m.username, r.rating, r.review_text, r.created_date
        FROM Review r
        JOIN Member m ON r.member_id = m.member_id
        JOIN Product p ON r.product_id = p.product_id WHERE 1=1";
$params = [];
if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR m.username LIKE ? OR r.review_text LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}
$sql .= " ORDER BY r.created_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// review statistics
$review_stats = [];
$review_stats['total_reviews'] = $pdo->query("SELECT COUNT(*) FROM Review")->fetchColumn();
$review_stats['recent_7_days'] = $pdo->query("SELECT COUNT(*) FROM Review WHERE created_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$review_stats['average_rating'] = $pdo->query("SELECT ROUND(AVG(rating), 1) FROM Review")->fetchColumn();
$review_stats['five_star_reviews'] = $pdo->query("SELECT COUNT(*) FROM Review WHERE rating = 5")->fetchColumn();

?>
<!DOCTYPE html>
<html lang=\"en\">

<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Review Management - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/admin_panel.css">
    <link rel="stylesheet" href="../assets/css/manage_product.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        th.sortable {
            cursor: pointer;
            user-select: none;
        }

        /* Always show a neutral up/down icon by default */
        th.sortable::after {
            content: '▲▼';
            /* or '⇅' / '↕' if you prefer a single icon */
            margin-left: 4px;
            font-size: 0.7rem;
            opacity: 0.5;
            /* slightly lighter when not active */
        }

        /* When sorted ascending – override with single arrow */
        th.sortable[data-order="asc"]::after {
            content: '▲';
            opacity: 1;
        }

        /* When sorted descending – override with single arrow */
        th.sortable[data-order="desc"]::after {
            content: '▼';
            opacity: 1;
        }

        .bulk-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .bulk-actions label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: #374151;
        }

        .bulk-actions .btn {
            min-width: 160px;
        }

        .bulk-actions .btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .enhanced-checkbox {
            width: 18px;
            height: 18px;
            transform: scale(1.2);
            transform-origin: center;
            cursor: pointer;
        }

        .row-checkbox {
            accent-color: #2563eb;
        }

        .selectable-row {
            cursor: pointer;
            transition: background-color 0.18s ease;
        }

        .selectable-row:hover {
            background-color: #f3f4f6;
        }

        .selectable-row.row-selected {
            background-color: #fef3c7;
        }

        .review-text {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>

<body>

    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰ Menu</button>

    <div class="admin-container">

        <?php include '../partials/admin_panel.php'; ?>

        <div class="main-content">

            <div class="header">
                <h1>Review Management</h1>
                <div style="display:flex; gap:10px; align-items:center;">
                    <?php if (has_permission('reviews', 'view')): ?>
                        <a href="?export=reviews" class="btn btn-success">📁 Export All CSV</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-container" style="margin-bottom:12px;">
                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-info">
                        <h3><?php echo $review_stats['total_reviews']; ?></h3>
                        <p>Total Reviews</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🆕</div>
                    <div class="stat-info">
                        <h3><?php echo $review_stats['recent_7_days']; ?></h3>
                        <p>New (7d)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">⭐</div>
                    <div class="stat-info">
                        <h3><?php echo $review_stats['average_rating']; ?>/5</h3>
                        <p>Avg Rating</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🌟</div>
                    <div class="stat-info">
                        <h3><?php echo $review_stats['five_star_reviews']; ?></h3>
                        <p>5-Star Reviews</p>
                    </div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="filter-bar">
                <form method="GET" style="display:flex; gap:15px; flex:1;">
                    <input type="text" name="search" placeholder="Search reviews..."
                        value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="review_management.php" class="btn" style="background:#cbd5e0;">Clear</a>
                </form>
            </div>

            <!-- Reviews Table -->
            <form method="POST" id="reviewBulkForm">
                <div class="bulk-actions" style="justify-content: flex-end;">
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <?php if (has_permission('reviews', 'delete')): ?>
                            <button type="submit" name="batch_delete_reviews" id="deleteSelectedReviewsBtn" class="btn btn-danger" disabled>Delete Selected</button>
                        <?php endif; ?>
                        <button type="submit" name="export_selected_reviews" id="exportSelectedReviewsBtn" class="btn btn-info" disabled>Export Selected CSV</button>
                    </div>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:40px; text-align:center;">
                                    <input type="checkbox" id="select_all_reviews" class="enhanced-checkbox">
                                </th>
                                <!-- data-column index must match the <td> order -->
                                <th class="sortable" data-column="1" data-type="number">ID</th>
                                <th class="sortable" data-column="2" data-type="string">Product</th>
                                <th class="sortable" data-column="3" data-type="string">Member</th>
                                <th class="sortable" data-column="4" data-type="number">Rating</th>
                                <th class="sortable" data-column="5" data-type="string">Review</th>
                                <th class="sortable" data-column="6" data-type="string">Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($reviews) > 0): ?>
                                <?php foreach ($reviews as $r): ?>
                                    <?php $rowCheckboxId = 'review-select-' . $r['review_id']; ?>
                                    <tr class="selectable-row" data-checkbox-id="<?= $rowCheckboxId ?>">
                                        <td style="text-align:center;">
                                            <input
                                                type="checkbox"
                                                name="selected_reviews[]"
                                                value="<?= $r['review_id'] ?>"
                                                class="row-checkbox enhanced-checkbox"
                                                id="<?= $rowCheckboxId ?>">
                                        </td>
                                        <td><?= $r['review_id'] ?></td>
                                        <td><?= htmlspecialchars($r['product_name']) ?></td>
                                        <td><?= htmlspecialchars($r['username']) ?></td>
                                        <td>
                                            <span class="badge badge-success"><?= $r['rating'] ?>/5</span>
                                        </td>
                                        <td class="review-text" title="<?= htmlspecialchars($r['review_text']) ?>"><?= htmlspecialchars($r['review_text']) ?></td>
                                        <td><?= $r['created_date'] ?></td>

                                        <td>
                                            <button type="button" class="btn btn-warning btn-sm"
                                                onclick='openViewModal(<?= json_encode($r) ?>)'>
                                                View
                                            </button>
                                            <?php if (has_permission('reviews', 'delete')): ?>
                                                <button type="button" class="btn btn-danger btn-sm"
                                                    onclick="confirmDelete(<?= $r['review_id'] ?>)">
                                                    Delete
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align:center;padding:40px;">No reviews found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>

        </div>
    </div>

    <!-- VIEW REVIEW MODAL -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>View Review</h2>
                <button class="close" onclick="closeModal('viewModal')">&times;</button>
            </div>

            <div id="viewReviewContent">
                <div class="form-group">
                    <label>Review ID:</label>
                    <p id="view_review_id"></p>
                </div>
                <div class="form-group">
                    <label>Product:</label>
                    <p id="view_product_name"></p>
                </div>
                <div class="form-group">
                    <label>Member:</label>
                    <p id="view_username"></p>
                </div>
                <div class="form-group">
                    <label>Rating:</label>
                    <p id="view_rating"></p>
                </div>
                <div class="form-group">
                    <label>Review Text:</label>
                    <p id="view_review_text"></p>
                </div>
                <div class="form-group">
                    <label>Created Date:</label>
                    <p id="view_created_date"></p>
                </div>
            </div>
        </div>
    </div>

    <!-- DELETE FORM -->
    <form method="POST" id="deleteForm" style="display:none;">
        <input type="hidden" name="review_id" id="delete_review_id">
        <input type="hidden" name="delete_review" value="1">
    </form>

    <script>
        window.onbeforeunload = function() {
            window.scrollTo(0, 0);
        };

        function openViewModal(data) {
            document.getElementById('view_review_id').textContent = data.review_id;
            document.getElementById('view_product_name').textContent = data.product_name;
            document.getElementById('view_username').textContent = data.username;
            document.getElementById('view_rating').textContent = data.rating + '/5';
            document.getElementById('view_review_text').textContent = data.review_text;
            document.getElementById('view_created_date').textContent = data.created_date;

            document.getElementById('viewModal').style.display = 'block';
        }

        function confirmDelete(id) {
            if (confirm("Are you sure you want to delete this review?")) {
                document.getElementById('delete_review_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function setupBulkSelect(config) {
            const form = document.getElementById(config.formId);
            if (!form) return;

            const selectAll = document.getElementById(config.selectAllId);
            const deleteBtn = document.getElementById(config.deleteButtonId);
            const exportBtn = document.getElementById(config.exportButtonId);
            const checkboxSelector = `input[name="${config.checkboxName}"]`;

            const getRowCheckboxes = () =>
                Array.from(form.querySelectorAll(checkboxSelector)).filter(cb => !cb.disabled);

            function updateRowHighlights() {
                getRowCheckboxes().forEach(cb => {
                    const row = cb.closest('.selectable-row');
                    if (row) {
                        row.classList.toggle('row-selected', cb.checked);
                    }
                });
            }

            function updateButtons() {
                const anyChecked = getRowCheckboxes().some(cb => cb.checked);
                [deleteBtn, exportBtn].forEach(btn => {
                    if (btn) {
                        btn.disabled = !anyChecked;
                    }
                });
                updateRowHighlights();
            }

            function syncSelectAll() {
                if (!selectAll) return;
                const checkboxes = getRowCheckboxes();
                if (checkboxes.length === 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                    return;
                }
                const checkedCount = checkboxes.filter(cb => cb.checked).length;
                if (checkedCount === 0) {
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                } else if (checkedCount === checkboxes.length) {
                    selectAll.checked = true;
                    selectAll.indeterminate = false;
                } else {
                    selectAll.checked = false;
                    selectAll.indeterminate = true;
                }
            }

            selectAll?.addEventListener('change', () => {
                const checkboxes = getRowCheckboxes();
                checkboxes.forEach(cb => {
                    cb.checked = selectAll.checked;
                });
                selectAll.indeterminate = false;
                updateButtons();
            });

            form.addEventListener('change', (event) => {
                if (event.target.matches(checkboxSelector)) {
                    updateButtons();
                    syncSelectAll();
                }
            });

            updateButtons();
            syncSelectAll();
        }

        function setupRowClickSelection(rowSelector) {
            document.querySelectorAll(rowSelector).forEach(row => {
                const checkboxId = row.getAttribute('data-checkbox-id');
                if (!checkboxId) return;
                const checkbox = document.getElementById(checkboxId);
                if (!checkbox) return;

                row.addEventListener('click', (event) => {
                    if (event.target === checkbox) {
                        return;
                    }
                    const interactive = event.target.closest('a, button, input, label, select, textarea');
                    if (interactive) {
                        return;
                    }
                    if (checkbox.disabled) return;
                    checkbox.checked = !checkbox.checked;
                    checkbox.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                });
            });
        }

        /* ===== TABLE SORTING (CLIENT-SIDE) ===== */
        document.addEventListener('DOMContentLoaded', function() {
            const headers = document.querySelectorAll('th.sortable');
            headers.forEach(function(header) {
                header.addEventListener('click', function() {
                    const table = header.closest('table');
                    const tbody = table.querySelector('tbody');
                    const colIdx = parseInt(header.getAttribute('data-column'), 10);
                    const type = header.getAttribute('data-type') || 'string';

                    // toggle sort direction
                    const currentOrder = header.getAttribute('data-order') || 'asc';
                    const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
                    header.setAttribute('data-order', newOrder);

                    // clear order on other headers
                    headers.forEach(h => {
                        if (h !== header) h.removeAttribute('data-order');
                    });

                    const rows = Array.from(tbody.querySelectorAll('tr'));

                    rows.sort(function(rowA, rowB) {
                        const cellA = rowA.children[colIdx].innerText.trim();
                        const cellB = rowB.children[colIdx].innerText.trim();

                        let valA = cellA;
                        let valB = cellB;

                        if (type === 'number') {
                            valA = parseFloat(cellA) || 0;
                            valB = parseFloat(cellB) || 0;
                        } else {
                            valA = cellA.toLowerCase();
                            valB = cellB.toLowerCase();
                        }

                        if (valA < valB) return newOrder === 'asc' ? -1 : 1;
                        if (valA > valB) return newOrder === 'asc' ? 1 : -1;
                        return 0;
                    });

                    // re-attach sorted rows
                    rows.forEach(row => tbody.appendChild(row));
                });
            });

            setupBulkSelect({
                formId: 'reviewBulkForm',
                checkboxName: 'selected_reviews[]',
                selectAllId: 'select_all_reviews',
                deleteButtonId: 'deleteSelectedReviewsBtn',
                exportButtonId: 'exportSelectedReviewsBtn'
            });

            // Confirmation for batch delete
            document.getElementById('deleteSelectedReviewsBtn')?.addEventListener('click', function(e) {
                const count = document.querySelectorAll('input[name="selected_reviews[]"]:checked').length;
                if (!confirm(`Are you sure you want to delete ${count} selected review(s)? This action cannot be undone.`)) {
                    e.preventDefault();
                }
            });

            setupRowClickSelection('#reviewBulkForm .selectable-row');
        });
    </script>

</body>

</html>
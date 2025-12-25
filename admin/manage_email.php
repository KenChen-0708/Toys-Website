<?php
session_start();
require_once '../helper/pdo_connection.php';
require_once '../helper/auth.php';
require_once '../helper/mailer.php';

// Ensure permissions
require_permission('emails', 'view');

$message = $message ?? '';
$message_type = $message_type ?? '';

// Handle Sending Email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {

    // Permission check for sending
    if (!has_permission('emails', 'create')) {
        $message = "You do not have permission to send notifications.";
        $message_type = "error";
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if (empty($subject) || empty($body)) {
            $message = "Subject and Body are required.";
            $message_type = "error";
        } else {
            // Determine recipients
            $recipient_ids = [];
            if (!empty($_POST['recipient_ids'])) {
                $recipient_ids = array_filter(array_map('intval', explode(',', $_POST['recipient_ids'])));
            }

            try {
                if (!empty($recipient_ids)) {
                    // Send to selected users (ensure they are still subscribed)
                    $placeholders = implode(',', array_fill(0, count($recipient_ids), '?'));
                    $sql = "
                        SELECT m.email, m.username 
                        FROM Member m 
                        JOIN Member_Settings ms ON m.member_id = ms.member_id 
                        WHERE ms.email_notifications = 1 AND m.member_id IN ($placeholders)
                    ";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($recipient_ids);
                } else {
                    // Send to ALL subscribed users
                    $stmt = $pdo->prepare("
                        SELECT m.email, m.username 
                        FROM Member m 
                        JOIN Member_Settings ms ON m.member_id = ms.member_id 
                        WHERE ms.email_notifications = 1
                    ");
                    $stmt->execute();
                }

                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $count = 0;
                if ($recipients) {
                    foreach ($recipients as $user) {
                        $toEmail = $user['email'];
                        $toName = $user['username'];

                        // Prepare content
                        $htmlBody = nl2br(htmlspecialchars($body));
                        $altBody = $body;

                        // Send email using helper
                        if (send_app_mail($toEmail, $toName, $subject, $htmlBody, $altBody)) {
                            $count++;
                        }
                    }

                    // LOG SENT EMAIL
                    if ($count > 0) {
                        try {
                            $admin_id = $_SESSION['admin']['id'] ?? 1; // Default to 1 if not set
                            $stmtLog = $pdo->prepare("INSERT INTO Sent_Email (admin_id, subject, message, recipient_count, sent_at) VALUES (?, ?, ?, ?, NOW())");
                            $stmtLog->execute([$admin_id, $subject, $body, $count]);
                        } catch (PDOException $logError) {
                            // Silent error for logging or handle as needed
                        }
                    }

                    $message = "Notification sent successfully to $count users!";
                    $message_type = "success";
                } else {
                    $message = "No users found based on selection.";
                    $message_type = "warning";
                }
            } catch (PDOException $e) {
                $message = "Database error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Fetch Subscribers for display
$subscribers = [];
try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sql = "
        SELECT m.member_id, m.username, m.email, ms.updated_at, m.profile_photo_path
        FROM Member m 
        JOIN Member_Settings ms ON m.member_id = ms.member_id 
        WHERE ms.email_notifications = 1
    ";

    if (!empty($search)) {
        $sql .= " AND (m.username LIKE :search OR m.email LIKE :search)";
    }

    $stmt = $pdo->prepare($sql);
    if (!empty($search)) {
        $stmt->bindValue(':search', "%$search%");
    }
    $stmt->execute();
    $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error
    $subscribers = [];
}

// Stats
$total_subscribers = count($subscribers);

// Get unsubscribed count
$stmt = $pdo->query("SELECT COUNT(*) FROM Member_Settings WHERE email_notifications = 0");
$unsubscribed_count = $stmt->fetchColumn();

// EXPORT SELECTED SENT EMAILS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_selected_emails'])) {
    if (!empty($_POST['selected_emails'])) {
        $selectedIds = array_filter(array_map('intval', $_POST['selected_emails']));
        if (!empty($selectedIds)) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=sent_emails_export.csv');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Email ID', 'Sender Admin', 'Subject', 'Recipient Count', 'Sent At', 'Message Snippet']);

            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $sql = "
                SELECT e.email_id, a.username as admin_name, e.subject, e.recipient_count, e.sent_at, e.message 
                FROM Sent_Email e
                JOIN Admin a ON e.admin_id = a.admin_id
                WHERE e.email_id IN ($placeholders)
                ORDER BY e.sent_at DESC
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($selectedIds);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['email_id'],
                    $row['admin_name'],
                    $row['subject'],
                    $row['recipient_count'],
                    $row['sent_at'],
                    substr($row['message'], 0, 100) . (strlen($row['message']) > 100 ? '...' : '')
                ]);
            }
            fclose($output);
            exit;
        }
    }
}

// Fetch Sent History
$sent_history = [];
try {
    $h_search = isset($_GET['h_search']) ? trim($_GET['h_search']) : '';
    $sql_history = "
        SELECT e.*, a.username as admin_name 
        FROM Sent_Email e 
        JOIN Admin a ON e.admin_id = a.admin_id 
        WHERE 1=1
    ";

    if (!empty($h_search)) {
        $sql_history .= " AND (e.subject LIKE :h_search OR a.username LIKE :h_search OR e.message LIKE :h_search)";
    }

    $sql_history .= " ORDER BY e.sent_at DESC";

    $stmt = $pdo->prepare($sql_history);
    if (!empty($h_search)) {
        $stmt->bindValue(':h_search', "%$h_search%");
    }
    $stmt->execute();
    $sent_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $sent_history = [];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Email Notifications - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/admin_panel.css">
    <link rel="stylesheet" href="../assets/css/manage_product.css">
    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fff;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative;
        }

        .close-btn {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
        }

        .form-group textarea {
            resize: vertical;
        }

        .profile-photo-thumb {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #e5e7eb;
        }

        /* Checkbox Styles */
        .enhanced-checkbox {
            width: 18px;
            height: 18px;
            transform: scale(1.2);
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
            background-color: #fef3c7 !important;
        }

        th.sortable {
            cursor: pointer;
            user-select: none;
        }

        /* Always show a neutral up/down icon by default */
        th.sortable::after {
            content: '▲▼';
            margin-left: 4px;
            font-size: 0.7rem;
            opacity: 0.5;
        }

        /* When sorted ascending – override with single arrow */
        th.sortable[data-order="asc"]::after {
            content: '▲';
            opacity: 1;
        }

        th.sortable[data-order="desc"]::after {
            content: '▼';
            opacity: 1;
        }

        /* Collapsible Section Styles */
        .collapsible-header {
            background-color: #f8fafc;
            padding: 1.25rem 1.5rem;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.25s ease;
            margin-bottom: 1rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        .collapsible-header:hover {
            background-color: #f1f5f9;
            border-color: #cbd5e0;
        }

        .collapsible-header h3 {
            margin: 0;
            font-size: 1.1rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .collapsible-header .toggle-icon {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border-radius: 50%;
            border: 1px solid #e2e8f0;
            font-size: 0.75rem;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            color: #64748b;
        }

        .collapsible-content {
            display: none;
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .collapsible-content.active {
            display: block;
            opacity: 1;
            transform: translateY(0);
        }

        .collapsible-header.active .toggle-icon {
            transform: rotate(180deg);
            background: #f1f5f9;
        }
    </style>

</head>

<body>
    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰ Menu</button>
    <div class="admin-container">
        <?php include '../partials/admin_panel.php'; ?>
        <div class="main-content">
            <div class="header">
                <h1>Manage Email Notifications</h1>
                <?php if (has_permission('emails', 'create')): ?>
                    <button class="btn btn-primary" onclick="openEmailModal()">+ Send Notification</button>
                <?php endif; ?>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">📧</div>
                    <div class="stat-info">
                        <h3><?php echo $total_subscribers; ?></h3>
                        <p>Subscribed Users</p>
                    </div>
                </div>
                <!-- Unsubscribed Users Stat Card -->
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #fee2e2; color: #ef4444;">🔕</div>
                    <div class="stat-info">
                        <h3><?php echo $unsubscribed_count; ?></h3>
                        <p>Unsubscribed Users</p>
                    </div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="filter-bar">
                <form method="GET" style="display:flex; gap:15px; flex:1;">
                    <input type="hidden" name="h_search" value="<?php echo htmlspecialchars($h_search ?? ''); ?>">
                    <input type="text" name="search" placeholder="Search subscribed users..."
                        value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="manage_email.php<?php echo !empty($h_search) ? '?h_search=' . urlencode($h_search) : ''; ?>" class="btn" style="background:#cbd5e0;">Clear</a>
                </form>
            </div>

            <div class="collapsible-header <?php echo !empty($search) ? 'active' : ''; ?>" id="subscribersToggle">
                <h3><span>👥</span> Select Subscribed Users <small style="margin-left: 8px; color: #94a3b8; font-weight: normal; font-size: 0.85rem; background: #f1f5f9; padding: 2px 8px; border-radius: 12px; border: 1px solid #e2e8f0;"><?php echo $total_subscribers; ?></small></h3>
                <div class="toggle-icon">▼</div>
            </div>

            <div class="collapsible-content <?php echo !empty($search) ? 'active' : ''; ?>" id="subscribersContent">
                <form id="emailBulkForm">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 40px;"><input type="checkbox" id="selectAll" class="enhanced-checkbox"></th>
                                    <th class="sortable" data-column="1" data-type="number" style="width: 50px;">ID</th>
                                    <th style="width: 80px;">Photo</th>
                                    <th class="sortable" data-column="3" data-type="string">Username</th>
                                    <th class="sortable" data-column="4" data-type="string">Email</th>
                                    <th class="sortable" data-column="5" data-type="string">Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($subscribers) > 0): ?>
                                    <?php foreach ($subscribers as $s): ?>
                                        <?php $checkboxId = 'user_check_' . $s['member_id']; ?>
                                        <tr class="selectable-row" data-checkbox-id="<?php echo $checkboxId; ?>">
                                            <td>
                                                <input type="checkbox" name="selected_users[]" id="<?php echo $checkboxId; ?>" class="enhanced-checkbox row-checkbox" value="<?php echo $s['member_id']; ?>">
                                            </td>
                                            <td><?php echo $s['member_id']; ?></td>
                                            <td>
                                                <?php
                                                $photo = !empty($s['profile_photo_path']) ? $s['profile_photo_path'] : '/assets/img/profile_photo/test.jpg';
                                                ?>
                                                <img src="<?php echo htmlspecialchars($photo); ?>" alt="Profile" class="profile-photo-thumb">
                                            </td>
                                            <td><?php echo htmlspecialchars($s['username']); ?></td>
                                            <td><?php echo htmlspecialchars($s['email']); ?></td>
                                            <td><?php echo $s['updated_at']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; padding: 2rem;">No subscribed users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>

            <form method="POST" id="historyBulkForm">
                <div style="margin-top: 3rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <h3 style="margin: 0;">Sent Notifications History</h3>
                    <div style="display: flex; gap: 10px; align-items: center; flex: 1; justify-content: flex-end;">
                        <input type="text" form="historySearchForm" name="h_search" placeholder="Search history..."
                            value="<?php echo htmlspecialchars($h_search ?? ''); ?>"
                            style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; max-width: 250px;">
                        <button type="submit" form="historySearchForm" class="btn btn-primary">Search</button>
                        <?php if (!empty($h_search)): ?>
                            <a href="manage_email.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" class="btn" style="background:#cbd5e0;">Clear</a>
                        <?php endif; ?>
                        <button type="submit" name="export_selected_emails" id="exportEmailsBtn" class="btn btn-info" disabled>Export Selected CSV</button>
                    </div>
                </div>
            </form>
            <form id="historySearchForm" method="GET" style="display: none;">
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search ?? ''); ?>">
            </form>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="selectAllEmails" class="enhanced-checkbox"></th>
                            <th class="sortable" data-column="1" data-type="number" style="width: 50px;">ID</th>
                            <th class="sortable" data-column="2" data-type="string">Subject</th>
                            <th class="sortable" data-column="3" data-type="string">Sent By</th>
                            <th class="sortable" data-column="4" data-type="number">Recipients</th>
                            <th class="sortable" data-column="5" data-type="string">Sent At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($sent_history) > 0): ?>
                            <?php foreach ($sent_history as $h): ?>
                                <?php $historyCheckboxId = 'email_check_' . $h['email_id']; ?>
                                <tr class="selectable-row" data-checkbox-id="<?php echo $historyCheckboxId; ?>">
                                    <td>
                                        <input type="checkbox" name="selected_emails[]" id="<?php echo $historyCheckboxId; ?>" class="enhanced-checkbox email-row-checkbox" value="<?php echo $h['email_id']; ?>">
                                    </td>
                                    <td><?php echo $h['email_id']; ?></td>
                                    <td><?php echo htmlspecialchars($h['subject']); ?></td>
                                    <td><?php echo htmlspecialchars($h['admin_name']); ?></td>
                                    <td><?php echo $h['recipient_count']; ?></td>
                                    <td><?php echo $h['sent_at']; ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" onclick='viewEmailDetails(<?php echo json_encode($h, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>View Details</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align:center; padding: 2rem;">No sent history found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            </form>
        </div>
    </div>

    <!-- Email Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeDetailsModal()">&times;</span>
            <h2 id="detailsSubjectHeader" style="margin-bottom: 1rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem;">Email Details</h2>
            <div style="margin-bottom: 1rem;">
                <p><strong>Subject:</strong> <span id="detailsSubject"></span></p>
                <p><strong>Sent By:</strong> <span id="detailsAdmin"></span></p>
                <p><strong>Sent At:</strong> <span id="detailsAt"></span></p>
                <p><strong>Recipient Count:</strong> <span id="detailsCount"></span></p>
            </div>
            <div class="form-group">
                <label>Message Content</label>
                <div id="detailsMessage" style="padding: 1rem; background: #f9fafb; border-radius: 6px; border: 1px solid #d1d5db; white-space: pre-wrap; max-height: 300px; overflow-y: auto;"></div>
            </div>
            <div style="text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="closeDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Email Modal -->
    <div id="emailModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeEmailModal()">&times;</span>
            <h2 style="margin-bottom: 1rem; border-bottom: 1px solid #e5e7eb; padding-bottom: 0.5rem;">Send Email Broadcast</h2>
            <p style="margin-bottom: 1.5rem; color: #6b7280;" id="modalMessage">
                You are about to send an email to <strong><?php echo $total_subscribers; ?></strong> subscribed users.
            </p>
            <form method="POST">
                <input type="hidden" name="recipient_ids" id="recipientIds">
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" placeholder="Enter email subject" required>
                </div>
                <div class="form-group">
                    <label for="body">Message</label>
                    <textarea id="body" name="body" rows="6" placeholder="Enter your message here..." required></textarea>
                </div>
                <div style="text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeEmailModal()">Cancel</button>
                    <button type="submit" name="send_notification" class="btn btn-primary">Send Notification</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('emailModal');
            const recipientIdsInput = document.getElementById('recipientIds');
            const modalMessage = document.getElementById('modalMessage');
            const totalSubscribers = <?php echo $total_subscribers; ?>;

            /* ===== TABLE SORTING (CLIENT-SIDE) ===== */
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

            function setupBulkSelect(config) {
                const form = document.getElementById(config.formId);
                if (!form) return;

                const selectAll = document.getElementById(config.selectAllId);
                const checkboxSelector = `input[name="${config.checkboxName}"]`;

                const getRowCheckboxes = () =>
                    Array.from(form.querySelectorAll(checkboxSelector)).filter(cb => !cb.disabled);

                function updateRowHighlights() {
                    getRowCheckboxes().forEach(cb => {
                        const row = cb.closest('.selectable-row');
                        if (row) {
                            if (cb.checked) {
                                row.classList.add('row-selected');
                            } else {
                                row.classList.remove('row-selected');
                            }
                        }
                    });
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

                function updateState() {
                    updateRowHighlights();
                    syncSelectAll();
                    if (config.btnId) {
                        const btn = document.getElementById(config.btnId);
                        if (btn) {
                            btn.disabled = getRowCheckboxes().filter(cb => cb.checked).length === 0;
                        }
                    }
                }

                selectAll?.addEventListener('change', () => {
                    const checkboxes = getRowCheckboxes();
                    checkboxes.forEach(cb => {
                        cb.checked = selectAll.checked;
                    });
                    updateState();
                });

                form.addEventListener('change', (event) => {
                    if (event.target.matches(checkboxSelector)) {
                        updateState();
                    }
                });

                // Initial Sync
                updateState();
            }

            function setupRowClickSelection(rowSelector) {
                document.querySelectorAll(rowSelector).forEach(row => {
                    const checkboxId = row.getAttribute('data-checkbox-id');
                    if (!checkboxId) return;
                    const checkbox = document.getElementById(checkboxId);
                    if (!checkbox) return;

                    row.addEventListener('click', (event) => {
                        if (event.target === checkbox) return;
                        const interactive = event.target.closest('a, button, input, label, select, textarea');
                        if (interactive) return;

                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event('change', {
                            bubbles: true
                        }));
                    });
                });
            }

            setupBulkSelect({
                formId: 'emailBulkForm',
                checkboxName: 'selected_users[]',
                selectAllId: 'selectAll'
            });

            setupBulkSelect({
                formId: 'historyBulkForm',
                checkboxName: 'selected_emails[]',
                selectAllId: 'selectAllEmails',
                btnId: 'exportEmailsBtn'
            });

            setupRowClickSelection('.selectable-row');

            // Modal Open Logic
            window.openEmailModal = function() {
                // Get selected from the form
                const checkboxes = document.querySelectorAll('input[name="selected_users[]"]:checked');
                const selected = Array.from(checkboxes).map(cb => cb.value);

                if (selected.length > 0) {
                    recipientIdsInput.value = selected.join(',');
                    modalMessage.innerHTML = `You are about to send an email to <strong>${selected.length}</strong> selected user(s).`;
                } else {
                    recipientIdsInput.value = ''; // Empty implies ALL
                    modalMessage.innerHTML = `You are about to send an email to <strong>${totalSubscribers}</strong> subscribed users (ALL).`;
                }

                modal.style.display = "flex";
            }

            window.closeEmailModal = function() {
                modal.style.display = "none";
            }

            window.viewEmailDetails = function(data) {
                document.getElementById('detailsSubject').innerText = data.subject;
                document.getElementById('detailsAdmin').innerText = data.admin_name;
                document.getElementById('detailsAt').innerText = data.sent_at;
                document.getElementById('detailsCount').innerText = data.recipient_count;
                document.getElementById('detailsMessage').innerText = data.message;
                document.getElementById('detailsModal').style.display = 'flex';
            }

            window.closeDetailsModal = function() {
                document.getElementById('detailsModal').style.display = 'none';
            }

            window.onclick = function(event) {
                if (event.target == modal) {
                    closeEmailModal();
                }
                if (event.target == document.getElementById('detailsModal')) {
                    closeDetailsModal();
                }
            }

            // Collapsible Toggle Logic
            const subsToggle = document.getElementById('subscribersToggle');
            const subsContent = document.getElementById('subscribersContent');

            if (subsToggle && subsContent) {
                subsToggle.addEventListener('click', function() {
                    const isActive = subsToggle.classList.toggle('active');
                    subsContent.classList.toggle('active');

                    // Add/Remove aria-expanded for screen readers if needed
                    subsToggle.setAttribute('aria-expanded', isActive);
                });
            }
        });
    </script>
</body>

</html>
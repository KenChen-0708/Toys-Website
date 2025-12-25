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

        /* When sorted descending – override with single arrow */
        th.sortable[data-order="desc"]::after {
            content: '▼';
            opacity: 1;
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
                <button class="btn btn-primary" onclick="openEmailModal()">+ Send Notification</button>
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
                    <input type="text" name="search" placeholder="Search subscribed users..."
                        value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="manage_email.php" class="btn" style="background:#cbd5e0;">Clear</a>
                </form>
            </div>

            <div style="margin-bottom: 1rem;">
                <h3>Subscribed Users</h3>
            </div>

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

                selectAll?.addEventListener('change', () => {
                    const checkboxes = getRowCheckboxes();
                    checkboxes.forEach(cb => {
                        cb.checked = selectAll.checked;
                    });
                    updateRowHighlights();
                });

                form.addEventListener('change', (event) => {
                    if (event.target.matches(checkboxSelector)) {
                        syncSelectAll();
                        updateRowHighlights();
                    }
                });

                // Initial Sync
                updateRowHighlights();
                syncSelectAll();
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

            // Initialize Selection
            setupBulkSelect({
                formId: 'emailBulkForm',
                checkboxName: 'selected_users[]',
                selectAllId: 'selectAll'
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

            window.onclick = function(event) {
                if (event.target == modal) {
                    closeEmailModal();
                }
            }
        });
    </script>
</body>

</html>
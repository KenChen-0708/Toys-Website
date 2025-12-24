<?php
session_start();
require_once '../helper/pdo_connection.php';
require_once '../helper/auth.php';
require_once '../helper/mailer.php';

// Ensure permissions
// Using 'members' view permission as a baseline
require_permission('members', 'view');

$message = $message ?? '';
$message_type = $message_type ?? '';

// Handle Sending Email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {

    // Permission check for sending
    if (!has_permission('members', 'update')) {
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

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="selectAll" class="enhanced-checkbox"></th>
                            <th style="width: 50px;">ID</th>
                            <th style="width: 80px;">Photo</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($subscribers) > 0): ?>
                            <?php foreach ($subscribers as $s): ?>
                                <?php $checkboxId = 'user_check_' . $s['member_id']; ?>
                                <tr class="selectable-row" data-checkbox-id="<?php echo $checkboxId; ?>">
                                    <td>
                                        <input type="checkbox" id="<?php echo $checkboxId; ?>" class="enhanced-checkbox row-checkbox" value="<?php echo $s['member_id']; ?>">
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
                                <td colspan="5" style="text-align:center; padding: 2rem;">No subscribed users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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
            const selectAllCheckbox = document.getElementById('selectAll');
            const rowCheckboxes = document.querySelectorAll('.row-checkbox');
            const recipientIdsInput = document.getElementById('recipientIds');
            const modalMessage = document.getElementById('modalMessage');
            const totalSubscribers = <?php echo $total_subscribers; ?>;

            function updateRowHighlights() {
                rowCheckboxes.forEach(cb => {
                    const row = cb.closest('tr');
                    if (row) {
                        if (cb.checked) {
                            row.classList.add('row-selected');
                        } else {
                            row.classList.remove('row-selected');
                        }
                    }
                });
            }

            function updateSelectAllState() {
                if (!selectAllCheckbox) return;
                const allChecked = Array.from(rowCheckboxes).every(c => c.checked);
                const anyChecked = Array.from(rowCheckboxes).some(c => c.checked);

                if (rowCheckboxes.length === 0) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                } else if (allChecked) {
                    selectAllCheckbox.checked = true;
                    selectAllCheckbox.indeterminate = false;
                } else if (anyChecked) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = true;
                } else {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                }
            }

            // Select All Logic
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    const isChecked = this.checked;
                    rowCheckboxes.forEach(cb => cb.checked = isChecked);
                    updateRowHighlights();
                });
            }

            // Row checkbox logic
            rowCheckboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    updateSelectAllState();
                    updateRowHighlights();
                });
            });

            // Row Click Logic
            document.querySelectorAll('.selectable-row').forEach(row => {
                row.addEventListener('click', function(e) {
                    // Prevent triggering if clicked directly on the checkbox or other interactive elements
                    if (e.target.type === 'checkbox' || e.target.tagName === 'A' || e.target.tagName === 'BUTTON') {
                        return;
                    }

                    const checkboxId = this.getAttribute('data-checkbox-id');
                    const checkbox = document.getElementById(checkboxId);
                    if (checkbox) {
                        checkbox.checked = !checkbox.checked;
                        // Trigger change event manually
                        checkbox.dispatchEvent(new Event('change'));
                    }
                });
            });

            // Modal Open Logic
            window.openEmailModal = function() {
                // Calculate selected
                const selected = Array.from(rowCheckboxes).filter(cb => cb.checked).map(cb => cb.value);

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

            // Initial Sync
            updateSelectAllState();
            updateRowHighlights();
        });
    </script>
</body>

</html>
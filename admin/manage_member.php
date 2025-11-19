<?php
session_start();

// Include database connection
require_once '../helper/pdo_connection.php';
require_once '../helper/auth.php';

// Ensure only admin can access with proper permissions
require_permission('members', 'view');

$message = '';
$message_type = '';

$passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$/';
$phonePattern    = '/^0\d{1,2}-?\d{6,8}$/';

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

    /** BATCH DELETE MEMBERS **/
    if (isset($_POST['batch_delete_members'])) {
        $selectedIds = $sanitizeIds($_POST['selected_members'] ?? []);

        if (!empty($selectedIds)) {
            try {
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $stmt = $pdo->prepare("DELETE FROM Member WHERE member_id IN ($placeholders)");
                $stmt->execute($selectedIds);

                $message = count($selectedIds) . " member(s) deleted successfully!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Error deleting selected members: " . $e->getMessage();
                $message_type = "error";
            }
        } else {
            $message = "Please select at least one member before deleting.";
            $message_type = "error";
        }
    }

    /** EXPORT SELECTED MEMBERS **/
    if (isset($_POST['export_selected_members'])) {
        $selectedIds = $sanitizeIds($_POST['selected_members'] ?? []);

        if (!empty($selectedIds)) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=members_selected_export.csv');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['member_id', 'username', 'email', 'phone_number', 'address', 'register_date']);

            $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
            $stmt = $pdo->prepare("SELECT member_id, username, email, phone_number, address, register_date FROM Member WHERE member_id IN ($placeholders) ORDER BY member_id ASC");
            $stmt->execute($selectedIds);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($output, [
                    $row['member_id'],
                    $row['username'],
                    $row['email'],
                    $row['phone_number'],
                    $row['address'],
                    $row['register_date']
                ]);
            }

            fclose($output);
            exit;
        } else {
            $message = "Please select at least one member before exporting.";
            $message_type = "error";
        }
    }

    /** CREATE MEMBER **/
    if (isset($_POST['create_member'])) {
        if (!has_permission('members', 'create')) {
            $message = "You don't have permission to create members.";
            $message_type = 'error';
        } else {
        $username         = trim($_POST['username'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $phone        = trim($_POST['phone'] ?? '');
        $address      = trim($_POST['address'] ?? '');
        $password_raw = $_POST['password'] ?? '';

        $errors = [];

        // Name validation (same style as register)
        if (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        } elseif (preg_match('/\s/', $username)) {
            $errors[] = 'Username cannot contain spaces. Use "_" instead.';
        } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, and underscores.';
        }

        // Email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email.';
        }

        // Phone (optional)
        if ($phone !== '' && !preg_match($phonePattern, $phone)) {
            $errors[] = 'Please enter a valid phone format, e.g. 012-3456789.';
        }

        // Password (required, strong)
        if (!preg_match($passwordPattern, $password_raw)) {
            $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, number and symbol.';
        }

        // Uniqueness checks (username, email, phone)
        if (empty($errors)) {
            // Name unique (case-sensitive)
            $checkName = $pdo->prepare("SELECT 1 FROM Member WHERE BINARY username = ?");
            $checkName->execute([$username]);
            if ($checkName->fetch()) {
                $errors[] = 'This username is already taken.';
            }

            // Email unique
            $checkEmail = $pdo->prepare("SELECT 1 FROM Member WHERE email = ?");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetch()) {
                $errors[] = 'This email is already registered.';
            }

            // Phone unique (only if provided)
            if ($phone !== '') {
                $checkPhone = $pdo->prepare("SELECT 1 FROM Member WHERE phone_number = ?");
                $checkPhone->execute([$phone]);
                if ($checkPhone->fetch()) {
                    $errors[] = 'This phone number is already in use.';
                }
            }
        }

        if (empty($errors)) {
            $password = password_hash($password_raw, PASSWORD_BCRYPT);

            try {
                $stmt = $pdo->prepare("INSERT INTO Member (username, email, phone_number, address, password, register_date)
                                        VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$username, $email, $phone, $address, $password]);
                $message = "Member created successfully!";
                $message_type = "success";
            } catch (PDOException $e) {
                // Fallback if UNIQUE constraints exist in DB
                if ($e->getCode() == 23000) {
                    $message = "Username, email, or phone already exists.";
                } else {
                    $message = "Error: " . $e->getMessage();
                }
                $message_type = "error";
            }
        } else {
            $message = implode(' ', $errors);
            $message_type = 'error';
        }
        }
    }

    /** UPDATE MEMBER **/
    if (isset($_POST['update_member'])) {
        if (!has_permission('members', 'update')) {
            $message = "You don't have permission to update members.";
            $message_type = 'error';
        } else {
        $id       = (int)($_POST['member_id'] ?? 0);
        $username     = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        $new_pass = $_POST['new_password'] ?? '';

        $errors = [];

        // Fetch existing member data for fallback (profile photo etc.)
        $currentMemberStmt = $pdo->prepare("SELECT profile_photo_path FROM Member WHERE member_id = ?");
        $currentMemberStmt->execute([$id]);
        $currentMemberData = $currentMemberStmt->fetch(PDO::FETCH_ASSOC);
        if (!$currentMemberData) {
            $errors[] = 'Member not found.';
        }

        // Name validation
        if (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        } elseif (preg_match('/\s/', $username)) {
            $errors[] = 'Username cannot contain spaces. Use "_" instead.';
        } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, and underscores.';
        }

        // Email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email.';
        }

        // Phone (optional)
        if ($phone !== '' && !preg_match($phonePattern, $phone)) {
            $errors[] = 'Please enter a valid phone format, e.g. 012-3456789.';
        }

        // New password (optional but strong if provided)
        if ($new_pass !== '' && !preg_match($passwordPattern, $new_pass)) {
            $errors[] = 'New password must be at least 8 characters and include uppercase, lowercase, number and symbol.';
        }

        $finalPhotoPath = $currentMemberData['profile_photo_path'] ?? null;
        $pendingPhotoUpload = null;
        $maxPhotoSize = 2 * 1024 * 1024; // 2MB

        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $photoFile = $_FILES['profile_photo'];

            if ($photoFile['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Error uploading profile photo. Please try again.';
            } elseif ($photoFile['size'] > $maxPhotoSize) {
                $errors[] = 'Profile photo must be 2MB or smaller.';
            } else {
                $allowedMime = [
                    'image/jpeg' => 'jpg',
                    'image/png'  => 'png',
                    'image/gif'  => 'gif',
                    'image/webp' => 'webp'
                ];

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo ? finfo_file($finfo, $photoFile['tmp_name']) : null;
                if ($finfo) {
                    finfo_close($finfo);
                }

                if (!$detectedMime || !isset($allowedMime[$detectedMime])) {
                    $errors[] = 'Profile photo must be JPG, PNG, GIF, or WebP.';
                } else {
                    $uploadDir = __DIR__ . '/../assets/img/profile_photo/';
                    if (!is_dir($uploadDir)) {
                        if (!mkdir($uploadDir, 0777, true)) {
                            $errors[] = 'Unable to create profile photo directory.';
                        }
                    }

                    if (empty($errors)) {
                        $extension = $allowedMime[$detectedMime];
                        $safeName = 'member_' . $id . '_' . time() . '.' . $extension;
                        $absolutePath = $uploadDir . $safeName;
                        $relativePath = '/assets/img/profile_photo/' . $safeName;

                        $pendingPhotoUpload = [
                            'tmp_name' => $photoFile['tmp_name'],
                            'destination' => $absolutePath
                        ];
                        $finalPhotoPath = $relativePath;
                    }
                }
            }
        }

        // Uniqueness checks (exclude current member)
        if (empty($errors)) {
            // Name unique except current
            $checkName = $pdo->prepare("SELECT 1 FROM Member WHERE BINARY username = ? AND member_id <> ?");
            $checkName->execute([$username, $id]);
            if ($checkName->fetch()) {
                $errors[] = 'This username is already taken.';
            }

            // Email unique except current
            $checkEmail = $pdo->prepare("SELECT 1 FROM Member WHERE email = ? AND member_id <> ?");
            $checkEmail->execute([$email, $id]);
            if ($checkEmail->fetch()) {
                $errors[] = 'This email is already registered.';
            }

            // Phone unique except current (only if provided)
            if ($phone !== '') {
                $checkPhone = $pdo->prepare("SELECT 1 FROM Member WHERE phone_number = ? AND member_id <> ?");
                $checkPhone->execute([$phone, $id]);
                if ($checkPhone->fetch()) {
                    $errors[] = 'This phone number is already in use.';
                }
            }
        }

        if (empty($errors) && $pendingPhotoUpload) {
            if (!move_uploaded_file($pendingPhotoUpload['tmp_name'], $pendingPhotoUpload['destination'])) {
                $errors[] = 'Failed to save profile photo.';
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE Member SET 
                        username = ?,
                        email = ?,
                        phone_number = ?,
                        address = ?,
                        profile_photo_path = ?
                        WHERE member_id = ?");
                $stmt->execute([$username, $email, $phone, $address, $finalPhotoPath, $id]);

                // Optional password update
                if ($new_pass !== '') {
                    $new_password = password_hash($new_pass, PASSWORD_BCRYPT);
                    $passStmt = $pdo->prepare("UPDATE Member SET password = ? WHERE member_id = ?");
                    $passStmt->execute([$new_password, $id]);
                }

                $message = "Member updated successfully!";
                $message_type = "success";
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

    /** DELETE MEMBER **/
    if (isset($_POST['delete_member'])) {
        if (!has_permission('members', 'delete')) {
            $message = "You don't have permission to delete members.";
            $message_type = 'error';
        } else {
        $id = (int)$_POST['member_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM Member WHERE member_id = ?");
            $stmt->execute([$id]);
            $message = "Member deleted successfully!";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Error deleting member: " . $e->getMessage();
            $message_type = "error";
        }
        }
    }

    /** IMPORT MEMBERS FROM CSV **/
    if (isset($_POST['upload_member_csv']) && isset($_FILES['member_csv_file'])) {
        if ($_FILES['member_csv_file']['error'] == 0) {
            $file = $_FILES['member_csv_file']['tmp_name'];
            $handle = fopen($file, 'r');

            if ($handle !== false) {
                $header = fgetcsv($handle); // skip header
                $successCount = 0;
                $errorCount = 0;

                try {
                    $pdo->beginTransaction();

                    while (($data = fgetcsv($handle)) !== false) {
                        // Expect username,email,password,phone,address (password REQUIRED)
                        if (count($data) >= 3) {
                            $username = trim($data[0]);
                            $email = trim($data[1]);
                            $password_raw = isset($data[2]) ? trim($data[2]) : '';
                            $phone = isset($data[3]) ? trim($data[3]) : '';
                            $address = isset($data[4]) ? trim($data[4]) : '';

                            if (empty($username) || empty($email) || $password_raw === '') {
                                // missing required field (password required)
                                $errorCount++;
                                continue;
                            }

                            // Skip if username or email or phone exists
                            $check = $pdo->prepare("SELECT 1 FROM Member WHERE BINARY username = ? OR email = ? OR phone_number = ?");
                            $check->execute([$username, $email, $phone]);
                            if ($check->fetch()) {
                                $errorCount++;
                                continue;
                            }

                            $password_hash = password_hash($password_raw, PASSWORD_BCRYPT);

                            $stmt = $pdo->prepare("INSERT INTO Member (username, email, phone_number, address, password, register_date) VALUES (?, ?, ?, ?, ?, NOW())");
                            $stmt->execute([$username, $email, $phone, $address, $password_hash]);
                            $successCount++;
                        } else {
                            $errorCount++;
                        }
                    }

                    $pdo->commit();
                    $message = "$successCount member(s) imported successfully!";
                    if ($errorCount > 0) $message .= " ($errorCount row(s) skipped).";
                    $message_type = 'success';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "Error importing CSV: " . $e->getMessage();
                    $message_type = 'error';
                }

                fclose($handle);
            }
        } else {
            $message = "Error uploading CSV file";
            $message_type = 'error';
        }
    }
}

// EXPORT MEMBERS TO CSV
if (isset($_GET['export']) && $_GET['export'] === 'members') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=members_export.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['member_id', 'username', 'email', 'phone_number', 'address', 'register_date']);

    $stmt = $pdo->query("SELECT member_id, username, email, phone_number, address, register_date FROM Member ORDER BY member_id ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [$row['member_id'], $row['username'], $row['email'], $row['phone_number'], $row['address'], $row['register_date']]);
    }

    fclose($output);
    exit;
}

/** READ members with search **/
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT * FROM Member WHERE 1=1";
$params = [];
if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}
$sql .= " ORDER BY member_id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// member statistics
$member_stats = [];
$member_stats['total_members'] = $pdo->query("SELECT COUNT(*) FROM Member")->fetchColumn();
$member_stats['recent_7_days'] = $pdo->query("SELECT COUNT(*) FROM Member WHERE register_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$member_stats['with_phone'] = $pdo->query("SELECT COUNT(*) FROM Member WHERE phone_number IS NOT NULL AND phone_number != ''")->fetchColumn();

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members - Admin Panel</title>
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
            content: '▲▼';        /* or '⇅' / '↕' if you prefer a single icon */
            margin-left: 4px;
            font-size: 0.7rem;
            opacity: 0.5;          /* slightly lighter when not active */
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

        /* Password eye toggle */
        .input-with-icon {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-toggle {
            position: absolute;
            right: 0.75rem;
            border: none;
            background: transparent;
            cursor: pointer;
            font-size: 1rem;
            line-height: 1;
            padding: 0;
        }

        /* Member profile photo thumbnail */
        .profile-photo-thumb {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.12);
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

        .profile-photo-preview {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 8px;
        }
        .profile-photo-preview img {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.18);
        }

        /* Hints & error formatting (similar to register/login) */
        .field-hint {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.25rem;
            line-height: 1.3;
            opacity: 0;
            max-height: 0;
            transform: translateY(-2px);
            transition: opacity 0.18s ease, max-height 0.18s ease, transform 0.18s ease;
        }
        .field-hint--visible {
            opacity: 1;
            max-height: 80px;
            transform: translateY(0);
        }
        .hint--error {
            color: #b91c1c;
        }
        .modal .form-group input.input--error,
        .modal .form-group textarea.input--error {
            border-color: #b91c1c;
            box-shadow: 0 0 0 1px rgba(185, 28, 28, 0.15);
        }
    </style>
</head>
<body>

<button class="mobile-menu-btn" onclick="toggleSidebar()">☰ Menu</button>

<div class="admin-container">

    <?php include '../partials/admin_panel.php'; ?>

    <div class="main-content">

        <div class="header">
            <h1>Manage Members</h1>
            <div style="display:flex; gap:10px; align-items:center;">
                <button class="btn btn-primary" onclick="openCreateModal()">+ Add Member</button>
                <button class="btn btn-success" onclick="openCSVModalMember()">📁 Import CSV</button>
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
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h3><?php echo $member_stats['total_members']; ?></h3>
                    <p>Total Members</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🆕</div>
                <div class="stat-info">
                    <h3><?php echo $member_stats['recent_7_days']; ?></h3>
                    <p>New (7d)</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📞</div>
                <div class="stat-info">
                    <h3><?php echo $member_stats['with_phone']; ?></h3>
                    <p>With Phone</p>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="filter-bar">
            <form method="GET" style="display:flex; gap:15px; flex:1;">
                <input type="text" name="search" placeholder="Search members..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="manage_member.php" class="btn" style="background:#cbd5e0;">Clear</a>
            </form>
        </div>

        <!-- Members Table -->
        <form method="POST" id="memberBulkForm">
            <div class="bulk-actions">
                <label>
                    <input type="checkbox" id="select_all_members" class="enhanced-checkbox">
                    Select All
                </label>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <button type="submit" name="batch_delete_members" id="deleteSelectedMembersBtn" class="btn btn-danger" disabled>Delete Selected</button>
                    <button type="submit" name="export_selected_members" id="exportSelectedMembersBtn" class="btn btn-info" disabled>Export Selected CSV</button>
                </div>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px;"></th>
                            <!-- data-column index must match the <td> order -->
                            <th class="sortable" data-column="1" data-type="number">ID</th>
                            <th data-column="2">Profile Photo</th>
                            <th class="sortable" data-column="3" data-type="string">Name</th>
                            <th class="sortable" data-column="4" data-type="string">Email</th>
                            <th class="sortable" data-column="5" data-type="string">Phone</th>
                            <th class="sortable" data-column="6" data-type="string">Address</th>
                            <th class="sortable" data-column="7" data-type="string">Register Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($members) > 0): ?>
                        <?php foreach ($members as $m): ?>
                        <?php $rowCheckboxId = 'member-select-' . $m['member_id']; ?>
                        <tr class="selectable-row" data-checkbox-id="<?= $rowCheckboxId ?>">
                                <td>
                                    <input
                                        type="checkbox"
                                        name="selected_members[]"
                                        value="<?= $m['member_id'] ?>"
                                        class="row-checkbox enhanced-checkbox"
                                        id="<?= $rowCheckboxId ?>"
                                    >
                                </td>
                                <td><?= $m['member_id'] ?></td>
                                <td>
                                    <?php
                                        $profilePhotoPath = !empty($m['profile_photo_path'])
                                            ? $m['profile_photo_path']
                                            : '/assets/img/profile_photo/test.jpg';
                                    ?>
                                    <img
                                        src="<?= htmlspecialchars($profilePhotoPath) ?>"
                                        alt="Profile photo of <?= htmlspecialchars($m['username']) ?>"
                                        class="profile-photo-thumb"
                                    >
                                </td>
                                <td><?= htmlspecialchars($m['username']) ?></td>
                                <td><?= htmlspecialchars($m['email']) ?></td>
                                <td><?= htmlspecialchars($m['phone_number']) ?></td>
                                <td><?= htmlspecialchars($m['address']) ?></td>
                                <td><?= $m['register_date'] ?></td>

                                <td>
                                <button type="button" class="btn btn-warning btn-sm"
                                    onclick='openEditModal(<?= json_encode($m) ?>)'>
                                        Edit
                                    </button>
                                <button type="button" class="btn btn-danger btn-sm"
                                        onclick="confirmDelete(<?= $m['member_id'] ?>)">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" style="text-align:center;padding:40px;">No members found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

    </div>
</div>

<!-- CREATE MEMBER MODAL -->
<!-- CSV Upload Modal -->
<div id="csvModalMember" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Import Members from CSV</h2>
            <button class="close" onclick="closeModal('csvModalMember')">&times;</button>
        </div>
        <div class="csv-instructions" style="border:1px solid #e5e7eb;background:#f8fafc;padding:12px;border-radius:6px;">
            <h3 style="margin:0 0 8px 0;font-size:1rem;">CSV Import - Instructions</h3>
            <ul style="margin:0 0 8px 18px;color:#374151;">
                <li><strong>Required columns:</strong> <code>username,email,password,phone,address</code></li>
                <li>If you don't have phone or address values include empty placeholders so the column order remains correct.</li>
                <li>Rows with existing usernames, emails or phone numbers will be skipped (they are not overwritten).</li>
            </ul>

            <div style="display:flex;gap:10px;align-items:center;margin-top:8px;">
                <a href="sample_csv/sample_members.csv" download class="btn btn-sm btn-info" style="padding:6px 10px;">📥 Download Sample CSV</a>
                <span style="color:#6b7280;font-size:0.9rem;">Example rows (header + 2 sample lines):</span>
            </div>

            <pre style="background:#0f172a;color:#e6eef8;padding:8px;border-radius:4px;margin-top:8px;overflow:auto;font-size:0.9rem;">username,email,password,phone,address
john_doe,john@example.com,Secret123!,012-3456789,"12 Market St, City"
alice,alice@example.com,AlicePass1!,,"34 Rose Ave, City"
</pre>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Select CSV File <span class="required">*</span></label>
                <input type="file" name="member_csv_file" accept=".csv" required>
            </div>
            <button type="submit" name="upload_member_csv" class="btn btn-success">Import Members</button>
        </form>
    </div>
</div>
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Member</h2>
            <button class="close" onclick="closeModal('createModal')">&times;</button>
        </div>

        <form method="POST" id="createMemberForm" novalidate>
            <div class="form-group">
                <label>Name *</label>
                <input
                    type="text"
                    name="username"
                    id="create_name"
                    data-hint="create_name_hint"
                >
                <p id="create_name_hint" class="field-hint">
                    3+ characters. Letters, numbers, and "_" only. No spaces.
                </p>
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input
                    type="email"
                    name="email"
                    id="create_email"
                    required
                    data-hint="create_email_hint"
                >
                <p id="create_email_hint" class="field-hint">
                    Use a valid email like name@example.com.
                </p>
            </div>

            <div class="form-group">
                <label>Password *</label>
                <div class="input-with-icon">
                    <input
                        type="password"
                        name="password"
                        id="create_member_password"
                        required
                        minlength="8"
                        data-hint="create_password_hint"
                    >
                    <button
                        type="button"
                        id="toggleCreateMemberPwd"
                        class="password-toggle"
                        aria-label="Toggle password visibility"
                    >👁</button>
                </div>
                <p id="create_password_hint" class="field-hint">
                    At least 8 characters with uppercase, lowercase, number and symbol.
                </p>
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input
                    type="text"
                    name="phone"
                    id="create_phone"
                    data-hint="create_phone_hint"
                >
                <p id="create_phone_hint" class="field-hint">
                    Optional. Use 012-3456789 or 0123456789 format.
                </p>
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea
                    name="address"
                    id="create_address"
                    data-hint="create_address_hint"
                ></textarea>
                <p id="create_address_hint" class="field-hint">
                    Optional. Apartment, street, city, and postcode.
                </p>
            </div>

            <button type="submit" name="create_member" class="btn btn-primary">Create</button>
        </form>
    </div>
</div>

<!-- EDIT MEMBER MODAL -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Member</h2>
            <button class="close" onclick="closeModal('editModal')">&times;</button>
        </div>

        <form method="POST" id="editMemberForm" novalidate enctype="multipart/form-data">
            <input type="hidden" name="member_id" id="edit_member_id">

            <div class="form-group">
                <label>Name *</label>
                <input
                    type="text"
                    name="username"
                    id="edit_name"
                    required
                    data-hint="edit_name_hint"
                >
                <p id="edit_name_hint" class="field-hint">
                    3+ characters. Letters, numbers, and "_" only. No spaces.
                </p>
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input
                    type="email"
                    name="email"
                    id="edit_email"
                    required
                    data-hint="edit_email_hint"
                >
                <p id="edit_email_hint" class="field-hint">
                    Use a valid email like name@example.com.
                </p>
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input
                    type="text"
                    name="phone"
                    id="edit_phone"
                    data-hint="edit_phone_hint"
                >
                <p id="edit_phone_hint" class="field-hint">
                    Optional. Use 012-3456789 or 0123456789 format.
                </p>
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea
                    name="address"
                    id="edit_address"
                    data-hint="edit_address_hint"
                ></textarea>
                <p id="edit_address_hint" class="field-hint">
                    Optional. Apartment, street, city, and postcode.
                </p>
            </div>

            <div class="form-group">
                <label>New Password (optional)</label>
                <div class="input-with-icon">
                    <input
                        type="password"
                        name="new_password"
                        id="edit_member_password"
                        placeholder="Leave blank to keep old password"
                        data-hint="edit_password_hint"
                    >
                    <button
                        type="button"
                        id="toggleEditMemberPwd"
                        class="password-toggle"
                        aria-label="Toggle new password visibility"
                    >👁</button>
                </div>
                <p id="edit_password_hint" class="field-hint">
                    Leave blank to keep current password. If provided, must be at least 8 characters with uppercase, lowercase, number and symbol.
                </p>
            </div>

            <div class="form-group">
                <label>Profile Photo (optional)</label>
                <div class="profile-photo-preview">
                    <img id="edit_profile_photo_preview" src="/assets/img/profile_photo/test.jpg" alt="Profile photo preview">
                    <div>
                        <input
                            type="file"
                            name="profile_photo"
                            id="edit_profile_photo_input"
                            accept="image/png, image/jpeg, image/jpg, image/webp, image/gif"
                        >
                        <p class="field-hint field-hint--visible" style="margin:6px 0 0 0;">
                            Max 2MB. Accepted formats: JPG, PNG, GIF, WebP.
                        </p>
                    </div>
                </div>
            </div>

            <button type="submit" name="update_member" class="btn btn-success">Update</button>
        </form>
    </div>
</div>

<!-- DELETE FORM -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="member_id" id="delete_member_id">
    <input type="hidden" name="delete_member" value="1">
</form>

<script>
function openCreateModal() {
    document.getElementById('createModal').style.display = 'block';
}
function openCSVModalMember() {
    document.getElementById('csvModalMember').style.display = 'block';
}

function openEditModal(data) {
    document.getElementById('edit_member_id').value = data.member_id;
    document.getElementById('edit_name').value = data.username;
    document.getElementById('edit_email').value = data.email;
    document.getElementById('edit_phone').value = data.phone_number;
    document.getElementById('edit_address').value = data.address;

    const preview = document.getElementById('edit_profile_photo_preview');
    if (preview) {
        const fallbackPhoto = '/assets/img/profile_photo/test.jpg';
        const src = data.profile_photo_path && data.profile_photo_path.trim() !== ''
            ? data.profile_photo_path
            : fallbackPhoto;
        preview.src = src;
        preview.alt = `Profile photo of ${data.username}`;
    }
    const fileInput = document.getElementById('edit_profile_photo_input');
    if (fileInput) {
        fileInput.value = '';
    }

    document.getElementById('editModal').style.display = 'block';
}

function confirmDelete(id) {
    if (confirm("Are you sure you want to delete this member?")) {
        document.getElementById('delete_member_id').value = id;
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
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
}

/* ===== TABLE SORTING (CLIENT-SIDE) ===== */
document.addEventListener('DOMContentLoaded', function () {
    const headers = document.querySelectorAll('th.sortable');
    const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$/;
    const phonePattern = /^0\d{1,2}-?\d{6,8}$/;

    headers.forEach(function (header) {
        header.addEventListener('click', function () {
            const table   = header.closest('table');
            const tbody   = table.querySelector('tbody');
            const colIdx  = parseInt(header.getAttribute('data-column'), 10);
            const type    = header.getAttribute('data-type') || 'string';

            // toggle sort direction
            const currentOrder = header.getAttribute('data-order') || 'asc';
            const newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
            header.setAttribute('data-order', newOrder);

            // clear order on other headers
            headers.forEach(h => {
                if (h !== header) h.removeAttribute('data-order');
            });

            const rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort(function (rowA, rowB) {
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
                if (valA > valB) return newOrder === 'asc' ?  1 : -1;
                return 0;
            });

            // re-attach sorted rows
            rows.forEach(row => tbody.appendChild(row));
        });
    });

    // 👁 Password eye toggles for Members page
    const createPwd = document.getElementById('create_member_password');
    const toggleCreatePwdBtn = document.getElementById('toggleCreateMemberPwd');

    toggleCreatePwdBtn?.addEventListener('click', () => {
        const show = createPwd.type === 'password';
        createPwd.type = show ? 'text' : 'password';
        toggleCreatePwdBtn.textContent = show ? '🙈' : '👁';
    });

    const editPwd = document.getElementById('edit_member_password');
    const toggleEditPwdBtn = document.getElementById('toggleEditMemberPwd');

    toggleEditPwdBtn?.addEventListener('click', () => {
        const show = editPwd.type === 'password';
        editPwd.type = show ? 'text' : 'password';
        toggleEditPwdBtn.textContent = show ? '🙈' : '👁';
    });

    // ===== HINTS + VALIDATION FOR CREATE / EDIT =====

    const hintDefaults = {};
    document.querySelectorAll('.field-hint').forEach(function (hint) {
        hintDefaults[hint.id] = hint.textContent;
    });

    function setError(field, hintEl, message) {
        if (field) {
            field.classList.add('input--error');
        }
        if (hintEl) {
            hintEl.textContent = message;
            hintEl.classList.add('field-hint--visible', 'hint--error');
        }
    }

    function clearError(field, hintEl) {
        if (field) {
            field.classList.remove('input--error');
        }
        if (hintEl && hintDefaults[hintEl.id]) {
            hintEl.textContent = hintDefaults[hintEl.id];
            hintEl.classList.remove('hint--error');
        }
    }

    // Focus-based hints (only for the focused input)
    document.querySelectorAll('[data-hint]').forEach(function (field) {
        const hintId = field.getAttribute('data-hint');
        const hintEl = document.getElementById(hintId);
        if (!hintEl) return;

        field.addEventListener('focus', function () {
            hintEl.classList.add('field-hint--visible');
        });

        field.addEventListener('blur', function () {
            if (hintEl.classList.contains('hint--error')) return;
            hintEl.classList.remove('field-hint--visible');
        });
    });

    // ===== CREATE MEMBER FORM VALIDATION =====
    const createForm = document.getElementById('createMemberForm');
    if (createForm) {
        createForm.addEventListener('submit', function (e) {
            let valid = true;

            const nameField    = document.getElementById('create_name');
            const emailField   = document.getElementById('create_email');
            const phoneField   = document.getElementById('create_phone');
            const addressField = document.getElementById('create_address');
            const pwdField     = document.getElementById('create_member_password');

            const nameHint    = document.getElementById('create_name_hint');
            const emailHint   = document.getElementById('create_email_hint');
            const phoneHint   = document.getElementById('create_phone_hint');
            const addressHint = document.getElementById('create_address_hint');
            const pwdHint     = document.getElementById('create_password_hint');

            const username  = nameField.value.trim();
            const email = emailField.value.trim();
            const phone = phoneField.value.trim();
            const addr  = addressField.value.trim();
            const pwd   = pwdField.value.trim();

            // Name
            clearError(nameField, nameHint);
            if (!username) {
                setError(nameField, nameHint, 'Username is required.');
                valid = false;
            } else if (username.length < 3) {
                setError(nameField, nameHint, 'Username must be at least 3 characters.');
                valid = false;
            } else if (/\s/.test(username)) {
                setError(nameField, nameHint, 'Username cannot contain spaces. Use "_" instead.');
                valid = false;
            } else if (!/^[A-Za-z0-9_]+$/.test(username)) {
                setError(nameField, nameHint, 'Username can only contain letters, numbers, and underscores.');
                valid = false;
            }

            // Email
            clearError(emailField, emailHint);
            if (!email) {
                setError(emailField, emailHint, 'Email is required.');
                valid = false;
            } else if (!emailField.checkValidity()) {
                setError(emailField, emailHint, 'Please enter a valid email address.');
                valid = false;
            }

            // Phone (optional)
            clearError(phoneField, phoneHint);
            if (phone && !phonePattern.test(phone)) {
                setError(phoneField, phoneHint, 'Please enter a valid phone format, e.g. 012-3456789.');
                valid = false;
            }

            // Address (optional)
            clearError(addressField, addressHint);
            if (addr && addr.length < 5) {
                setError(addressField, addressHint, 'Address looks too short. Please add more details.');
                valid = false;
            }

            // Password
            clearError(pwdField, pwdHint);
            if (!pwd) {
                setError(pwdField, pwdHint, 'Password is required.');
                valid = false;
            } else if (!passwordPattern.test(pwd)) {
                setError(pwdField, pwdHint, 'Password must be at least 8 characters and include uppercase, lowercase, number and symbol.');
                valid = false;
            }

            if (!valid) {
                e.preventDefault();
            }
        });
    }

    // ===== EDIT MEMBER FORM VALIDATION =====
    const editForm = document.getElementById('editMemberForm');
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            let valid = true;

            const nameField    = document.getElementById('edit_name');
            const emailField   = document.getElementById('edit_email');
            const phoneField   = document.getElementById('edit_phone');
            const addressField = document.getElementById('edit_address');
            const pwdField     = document.getElementById('edit_member_password');

            const nameHint    = document.getElementById('edit_name_hint');
            const emailHint   = document.getElementById('edit_email_hint');
            const phoneHint   = document.getElementById('edit_phone_hint');
            const addressHint = document.getElementById('edit_address_hint');
            const pwdHint     = document.getElementById('edit_password_hint');

            const username  = nameField.value.trim();
            const email = emailField.value.trim();
            const phone = phoneField.value.trim();
            const addr  = addressField.value.trim();
            const pwd   = pwdField.value.trim();

            // Name
            clearError(nameField, nameHint);
            if (!username) {
                setError(nameField, nameHint, 'Username is required.');
                valid = false;
            } else if (username.length < 3) {
                setError(nameField, nameHint, 'Username must be at least 3 characters.');
                valid = false;
            } else if (/\s/.test(username)) {
                setError(nameField, nameHint, 'Username cannot contain spaces. Use "_" instead.');
                valid = false;
            } else if (!/^[A-Za-z0-9_]+$/.test(username)) {
                setError(nameField, nameHint, 'Username can only contain letters, numbers, and underscores.');
                valid = false;
            }

            // Email
            clearError(emailField, emailHint);
            if (!email) {
                setError(emailField, emailHint, 'Email is required.');
                valid = false;
            } else if (!emailField.checkValidity()) {
                setError(emailField, emailHint, 'Please enter a valid email address.');
                valid = false;
            }

            // Phone
            clearError(phoneField, phoneHint);
            if (phone && !phonePattern.test(phone)) {
                setError(phoneField, phoneHint, 'Please enter a valid phone format, e.g. 012-3456789.');
                valid = false;
            }

            // Address
            clearError(addressField, addressHint);
            if (addr && addr.length < 5) {
                setError(addressField, addressHint, 'Address looks too short. Please add more details.');
                valid = false;
            }

            // New password (optional)
            clearError(pwdField, pwdHint);
            if (pwd && !passwordPattern.test(pwd)) {
                setError(pwdField, pwdHint, 'New password must be at least 8 characters and include uppercase, lowercase, number and symbol.');
                valid = false;
            }

            if (!valid) {
                e.preventDefault();
            }
        });
    }
    
    setupBulkSelect({
        formId: 'memberBulkForm',
        checkboxName: 'selected_members[]',
        selectAllId: 'select_all_members',
        deleteButtonId: 'deleteSelectedMembersBtn',
        exportButtonId: 'exportSelectedMembersBtn'
    });

    setupRowClickSelection('#memberBulkForm .selectable-row');
});
</script>

</body>
</html>


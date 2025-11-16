<?php
session_start();
include '../helper/pdo_connection.php';
require_once '../helper/auth.php';
require_admin();

$message = '';
$message_type = '';

// Current admin ID from session (fix: key is 'id', not 'admin_id')
$currentAdminId = $_SESSION['admin']['id'] ?? 0;

// Common password pattern (same style as register/reset)
$passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$/';

/* ========== SORTING SETUP ========== */
$allowedSortColumns = ['admin_id', 'username', 'email'];   // columns you allow sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'admin_id';
$order = isset($_GET['order']) ? strtolower($_GET['order']) : 'asc';

// Validate sort column
if (!in_array($sort, $allowedSortColumns)) {
    $sort = 'admin_id';
}

// Normalize order
$order = ($order === 'desc') ? 'DESC' : 'ASC';  // only ASC or DESC

/* ========== HANDLE FORM SUBMISSION ========== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $errors = [];

    /** CREATE ADMIN **/
    if (isset($_POST['create_admin'])) {
        $username         = trim($_POST['username'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $password_raw = $_POST['password'] ?? '';

        // SERVER-SIDE VALIDATION (username/email/password)
        // Name – same style as member register
        if (strlen($username) < 3) {
            $errors[] = 'Admin username must be at least 3 characters.';
        } elseif (preg_match('/\s/', $username)) {
            $errors[] = 'Admin username cannot contain spaces. Use "_" instead.';
        } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
            $errors[] = 'Admin username can only contain letters, numbers, and underscores.';
        }

        // Email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid admin email.';
        }

        // Password – strong rule like reset/register
        if (!preg_match($passwordPattern, $password_raw)) {
            $errors[] = 'Password must be at least 8 characters and include uppercase, lowercase, number and symbol.';
        }

        // Uniqueness checks (username + email) if no basic errors
        if (!$errors) {
            // Check username uniqueness
            $checkName = $pdo->prepare("SELECT 1 FROM Admin WHERE BINARY username = ?");
            $checkName->execute([$username]);
            if ($checkName->fetch()) {
                $errors[] = 'This admin username is already taken.';
            }

            // Check email uniqueness
            $checkEmail = $pdo->prepare("SELECT 1 FROM Admin WHERE email = ?");
            $checkEmail->execute([$email]);
            if ($checkEmail->fetch()) {
                $errors[] = 'This admin email is already registered.';
            }
        }

        if ($errors) {
            $message = implode(' ', $errors);
            $message_type = 'error';
        } else {
            $password = password_hash($password_raw, PASSWORD_BCRYPT);

            try {
                $stmt = $pdo->prepare("INSERT INTO Admin (username, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $password]);
                $message = "Admin created successfully!";
                $message_type = "success";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    // Fallback if DB has UNIQUE constraints
                    $message = "Admin username or email already exists.";
                } else {
                    $message = "Error creating admin: " . $e->getMessage();
                }
                $message_type = "error";
            }
        }
    }

    /** UPDATE ADMIN **/
    if (isset($_POST['update_admin'])) {
        $id              = (int)($_POST['admin_id'] ?? 0);
        $username            = trim($_POST['username'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $new_password_raw = $_POST['new_password'] ?? '';

        // SERVER-SIDE VALIDATION (username/email)
        if (strlen($username) < 3) {
            $errors[] = 'Admin username must be at least 3 characters.';
        } elseif (preg_match('/\s/', $username)) {
            $errors[] = 'Admin username cannot contain spaces. Use "_" instead.';
        } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
            $errors[] = 'Admin username can only contain letters, numbers, and underscores.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid admin email.';
        }

        // Optional password update
        if (!empty($new_password_raw) && !preg_match($passwordPattern, $new_password_raw)) {
            $errors[] = 'New password must be at least 8 characters and include uppercase, lowercase, number and symbol.';
        }

        // Uniqueness check for username/email among other admins
        if (!$errors) {
            // Name must be unique excluding current admin
            $checkName = $pdo->prepare("SELECT 1 FROM Admin WHERE BINARY username = ? AND admin_id <> ?");
            $checkName->execute([$username, $id]);
            if ($checkName->fetch()) {
                $errors[] = 'This admin username is already taken.';
            }

            // Email must be unique excluding current admin
            $checkEmail = $pdo->prepare("SELECT 1 FROM Admin WHERE email = ? AND admin_id <> ?");
            $checkEmail->execute([$email, $id]);
            if ($checkEmail->fetch()) {
                $errors[] = 'This admin email is already registered.';
            }
        }

        if ($errors) {
            $message = implode(' ', $errors);
            $message_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE Admin SET username = ?, email = ? WHERE admin_id = ?");
                $stmt->execute([$username, $email, $id]);

                // Update password if provided
                if (!empty($new_password_raw)) {
                    $new_password = password_hash($new_password_raw, PASSWORD_BCRYPT);
                    $passStmt = $pdo->prepare("UPDATE Admin SET password = ? WHERE admin_id = ?");
                    $passStmt->execute([$new_password, $id]);
                }

                $message = "Admin updated successfully!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Error updating admin: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }

    /** DELETE ADMIN **/
    if (isset($_POST['delete_admin'])) {
        $id = (int)($_POST['admin_id'] ?? 0);

        if ($id === $currentAdminId) {
            $message = "You cannot delete your own admin account!";
            $message_type = "error";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM Admin WHERE admin_id = ?");
                $stmt->execute([$id]);
                $message = "Admin deleted successfully!";
                $message_type = "success";
            } catch (PDOException $e) {
                $message = "Error deleting admin: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

/* ========== READ admins with search & sort ========== */
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$sql = "SELECT * FROM Admin WHERE 1=1";
$params = [];
if (!empty($search)) {
    $sql .= " AND (username LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}
$sql .= " ORDER BY $sort $order";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - Admin Panel</title>
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
            <h1>Manage Admin Accounts</h1>
            <button class="btn btn-primary" onclick="openCreateModal()">+ Add Admin</button>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="filter-bar">
            <form method="GET" style="display:flex; gap:15px; flex:1;">
                <input type="text" name="search" placeholder="Search admins..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="manage_admin.php" class="btn" style="background:#cbd5e0;">Clear</a>
            </form>
        </div>

        <!-- Admins Table -->
        <div class="table-container">
            <table>
               <thead>
                    <tr>
                        <th class="sortable" data-column="0" data-type="number">ID</th>
                        <th class="sortable" data-column="1" data-type="string">Name</th>
                        <th class="sortable" data-column="2" data-type="string">Email</th>
                        <th>Actions</th>
                    </tr>
               </thead>

                <tbody>
                    <?php if ($admins && count($admins) > 0): ?>
                        <?php foreach ($admins as $a): ?>
                        <tr>
                            <td><?= $a['admin_id'] ?></td>
                            <td><?= htmlspecialchars($a['username']) ?></td>
                            <td><?= htmlspecialchars($a['email']) ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm"
                                    onclick='openEditModal(<?= json_encode($a) ?>)'>Edit</button>
                                <?php if ((int)$a['admin_id'] !== (int)$currentAdminId): ?>
                                    <button class="btn btn-danger btn-sm"
                                        onclick="confirmDelete(<?= (int)$a['admin_id'] ?>)">Delete</button>
                                <?php else: ?>
                                    <span style="color:#777; font-size:0.85rem;">(You)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;padding:40px;">No admins found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- CREATE ADMIN MODAL -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Admin</h2>
            <button class="close" onclick="closeModal('createModal')">&times;</button>
        </div>

        <form method="POST" id="createAdminForm" novalidate>
            <div class="form-group">
                <label>Name *</label>
                <input
                    type="text"
                    name="username"
                    id="create_name"
                    required
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
                        id="create_admin_password"
                        required
                        minlength="8"
                        data-hint="create_password_hint"
                    >
                    <button
                        type="button"
                        id="toggleCreateAdminPwd"
                        class="password-toggle"
                        aria-label="Toggle password visibility"
                    >👁</button>
                </div>
                <p id="create_password_hint" class="field-hint">
                    At least 8 characters with uppercase, lowercase, number and symbol.
                </p>
            </div>

            <button type="submit" name="create_admin" class="btn btn-primary">Create</button>
        </form>
    </div>
</div>

<!-- EDIT ADMIN MODAL -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Admin</h2>
            <button class="close" onclick="closeModal('editModal')">&times;</button>
        </div>

        <form method="POST" id="editAdminForm" novalidate>
            <input type="hidden" name="admin_id" id="edit_admin_id">

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
                <label>New Password (optional)</label>
                <div class="input-with-icon">
                    <input
                        type="password"
                        name="new_password"
                        id="edit_admin_password"
                        placeholder="Leave blank to keep old password"
                        data-hint="edit_password_hint"
                    >
                    <button
                        type="button"
                        id="toggleEditAdminPwd"
                        class="password-toggle"
                        aria-label="Toggle new password visibility"
                    >👁</button>
                </div>
                <p id="edit_password_hint" class="field-hint">
                    Leave blank to keep current password. If provided, must be at least 8 characters with uppercase, lowercase, number and symbol.
                </p>
            </div>

            <button type="submit" name="update_admin" class="btn btn-success">Update</button>
        </form>
    </div>
</div>

<!-- DELETE FORM -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="admin_id" id="delete_admin_id">
    <input type="hidden" name="delete_admin" value="1">
</form>

<script>
function openCreateModal() {
    document.getElementById('createModal').style.display = 'block';
}
function openEditModal(data) {
    document.getElementById('edit_admin_id').value = data.admin_id;
    document.getElementById('edit_name').value = data.username;
    document.getElementById('edit_email').value = data.email;
    document.getElementById('editModal').style.display = 'block';
}
function confirmDelete(id) {
    if (confirm("Are you sure you want to delete this admin?")) {
        document.getElementById('delete_admin_id').value = id;
        document.getElementById('deleteForm').submit();
    }
}
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

/* ===== TABLE SORTING (CLIENT-SIDE) + password toggles + FORM VALIDATION ===== */
document.addEventListener('DOMContentLoaded', function () {
    const headers = document.querySelectorAll('th.sortable');

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

            // remove sort class from other headers
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

            rows.forEach(row => tbody.appendChild(row));
        });
    });

    // 👁 Password eye toggles for Admins page
    const createPwd = document.getElementById('create_admin_password');
    const toggleCreatePwdBtn = document.getElementById('toggleCreateAdminPwd');

    toggleCreatePwdBtn?.addEventListener('click', () => {
        const show = createPwd.type === 'password';
        createPwd.type = show ? 'text' : 'password';
        toggleCreatePwdBtn.textContent = show ? '🙈' : '👁';
    });

    const editPwd = document.getElementById('edit_admin_password');
    const toggleEditPwdBtn = document.getElementById('toggleEditAdminPwd');

    toggleEditPwdBtn?.addEventListener('click', () => {
        const show = editPwd.type === 'password';
        editPwd.type = show ? 'text' : 'password';
        toggleEditPwdBtn.textContent = show ? '🙈' : '👁';
    });

    // ===== HINTS SUPPORT =====
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

    // Show hints only when that particular input is focused
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

    const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$/;

    // ===== CREATE ADMIN FORM VALIDATION =====
    const createForm = document.getElementById('createAdminForm');
    if (createForm) {
        createForm.addEventListener('submit', function (e) {
            let valid = true;

            const nameField  = document.getElementById('create_name');
            const emailField = document.getElementById('create_email');
            const pwdField   = document.getElementById('create_admin_password');

            const nameHint  = document.getElementById('create_name_hint');
            const emailHint = document.getElementById('create_email_hint');
            const pwdHint   = document.getElementById('create_password_hint');

            const username  = nameField.value.trim();
            const email = emailField.value.trim();
            const pwd   = pwdField.value.trim();

            // Name
            clearError(nameField, nameHint);
            if (!username) {
                setError(nameField, nameHint, 'Admin username is required.');
                valid = false;
            } else if (username.length < 3) {
                setError(nameField, nameHint, 'Admin username must be at least 3 characters.');
                valid = false;
            } else if (/\s/.test(username)) {
                setError(nameField, nameHint, 'Admin username cannot contain spaces. Use "_" instead.');
                valid = false;
            } else if (!/^[A-Za-z0-9_]+$/.test(username)) {
                setError(nameField, nameHint, 'Admin username can only contain letters, numbers, and underscores.');
                valid = false;
            }

            // Email
            clearError(emailField, emailHint);
            if (!email) {
                setError(emailField, emailHint, 'Admin email is required.');
                valid = false;
            } else if (!emailField.checkValidity()) {
                setError(emailField, emailHint, 'Please enter a valid admin email.');
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

    // ===== EDIT ADMIN FORM VALIDATION =====
    const editForm = document.getElementById('editAdminForm');
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            let valid = true;

            const nameField  = document.getElementById('edit_name');
            const emailField = document.getElementById('edit_email');
            const pwdField   = document.getElementById('edit_admin_password');

            const nameHint  = document.getElementById('edit_name_hint');
            const emailHint = document.getElementById('edit_email_hint');
            const pwdHint   = document.getElementById('edit_password_hint');

            const username  = nameField.value.trim();
            const email = emailField.value.trim();
            const pwd   = pwdField.value.trim();

            // Name
            clearError(nameField, nameHint);
            if (!username) {
                setError(nameField, nameHint, 'Admin username is required.');
                valid = false;
            } else if (username.length < 3) {
                setError(nameField, nameHint, 'Admin username must be at least 3 characters.');
                valid = false;
            } else if (/\s/.test(username)) {
                setError(nameField, nameHint, 'Admin username cannot contain spaces. Use "_" instead.');
                valid = false;
            } else if (!/^[A-Za-z0-9_]+$/.test(username)) {
                setError(nameField, nameHint, 'Admin username can only contain letters, numbers, and underscores.');
                valid = false;
            }

            // Email
            clearError(emailField, emailHint);
            if (!email) {
                setError(emailField, emailHint, 'Admin email is required.');
                valid = false;
            } else if (!emailField.checkValidity()) {
                setError(emailField, emailHint, 'Please enter a valid admin email.');
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
});
</script>

</body>
</html>


<?php
session_start();
require_once '../helper/connection.php';
require_once '../helper/auth.php';

// Ensure only admin can access
require_admin();

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$currentAdminId = $_SESSION['admin']['admin_id'];
$errors = [];
$success = "";

// -----------------------------------------------------
// HANDLE FORM ACTIONS (Add / Edit / Delete)
// -----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF Check
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $action = $_POST['action'] ?? '';

        // ----------------------------------------------
        // ADD ADMIN
        // ----------------------------------------------
        if ($action === 'add_admin') {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];

            if (!$name || !$email || !$password) {
                $errors[] = "All fields are required.";
            } elseif (strlen($password) < 6) {
                $errors[] = "Password must be at least 6 characters.";
            }

            if (!$errors) {
                $hashed = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO Admin (name, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $name, $email, $hashed);
                $stmt->execute();

                $success = "Admin added successfully.";
            }
        }

        // ----------------------------------------------
        // EDIT ADMIN
        // ----------------------------------------------
        if ($action === 'edit_admin') {
            $id = intval($_POST['admin_id']);
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];

            if (!$name || !$email) {
                $errors[] = "Name and Email are required.";
            } else {
                if ($password) {
                    if (strlen($password) < 6) {
                        $errors[] = "Password must be at least 6 characters.";
                    } else {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare(
                            "UPDATE Admin SET name=?, email=?, password=? WHERE admin_id=?"
                        );
                        $stmt->bind_param("sssi", $name, $email, $hashed, $id);
                    }
                } else {
                    $stmt = $conn->prepare(
                        "UPDATE Admin SET name=?, email=? WHERE admin_id=?"
                    );
                    $stmt->bind_param("ssi", $name, $email, $id);
                }

                if (!$errors) {
                    $stmt->execute();
                    $success = "Admin updated successfully.";
                }
            }
        }

        // ----------------------------------------------
        // DELETE ADMIN
        // ----------------------------------------------
        if ($action === 'delete_admin') {
            $id = intval($_POST['admin_id']);

            if ($id == $currentAdminId) {
                $errors[] = "You cannot delete your own admin account.";
            } else {
                $stmt = $conn->prepare("DELETE FROM Admin WHERE admin_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $success = "Admin deleted successfully.";
            }
        }
    }

    // Store session messages
    $_SESSION['errors'] = $errors;
    $_SESSION['success'] = $success;

    header("Location: manage_admin.php");
    exit;
}

// -----------------------------------------------------
// RETRIEVE ADMINS
// -----------------------------------------------------
$search = trim($_GET['search'] ?? '');
if ($search) {
    $like = "%{$search}%";
    $stmt = $conn->prepare("SELECT * FROM Admin WHERE name LIKE ? OR email LIKE ?");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $admins = $stmt->get_result();
} else {
    $admins = $conn->query("SELECT * FROM Admin ORDER BY admin_id DESC");
}

$title = "Manage Admin";
include '../partials/admin_header.php';
?>

<main class="main container">
    <h1 class="page-title">Manage Admin Accounts</h1>

    <!-- Success Message -->
    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert--success"><?= $_SESSION['success'] ?></div>
    <?php unset($_SESSION['success']); endif; ?>

    <!-- Error Message -->
    <?php if (!empty($_SESSION['errors'])): ?>
        <div class="alert alert--danger">
            <?php foreach ($_SESSION['errors'] as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php unset($_SESSION['errors']); endif; ?>

    <!-- Search + Add -->
    <div class="manage-header">
        <form method="GET">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search admin...">
            <button class="btn">Search</button>
        </form>

        <button class="btn btn--primary" onclick="openAddModal()">+ Add Admin</button>
    </div>

    <!-- Table -->
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Admin Name</th>
                    <th>Email</th>
                    <th style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($admins->num_rows > 0): ?>
                    <?php while ($row = $admins->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['admin_id'] ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td>
                                <button onclick="openEditModal(
                                    <?= $row['admin_id'] ?>,
                                    '<?= htmlspecialchars($row['name'], ENT_QUOTES) ?>',
                                    '<?= htmlspecialchars($row['email'], ENT_QUOTES) ?>'
                                )" class="btn btn--small">Edit</button>

                                <?php if ($row['admin_id'] != $currentAdminId): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                        <input type="hidden" name="action" value="delete_admin">
                                        <input type="hidden" name="admin_id" value="<?= $row['admin_id'] ?>">
                                        <button onclick="return confirm('Delete this admin?')" class="btn btn--danger btn--small">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color:#777; font-size:0.85rem;">(You)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="4">No admin accounts found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- ADD MODAL -->
<div id="addModal" class="modal-overlay">
    <div class="modal">
        <h2>Add Admin</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="add_admin">

            <label>Name</label>
            <input type="text" name="name" required>

            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required minlength="6">

            <div class="modal__footer">
                <button type="button" class="btn" onclick="closeAddModal()">Cancel</button>
                <button class="btn btn--primary">Add</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div id="editModal" class="modal-overlay">
    <div class="modal">
        <h2>Edit Admin</h2>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="action" value="edit_admin">
            <input type="hidden" name="admin_id" id="edit_id">

            <label>Name</label>
            <input type="text" name="name" id="edit_name" required>

            <label>Email</label>
            <input type="email" name="email" id="edit_email" required>

            <label>New Password (optional)</label>
            <input type="password" name="password" minlength="6">

            <div class="modal__footer">
                <button type="button" class="btn" onclick="closeEditModal()">Cancel</button>
                <button class="btn btn--primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById("addModal").classList.add("is-visible");
}
function closeAddModal() {
    document.getElementById("addModal").classList.remove("is-visible");
}

function openEditModal(id, name, email) {
    document.getElementById("edit_id").value = id;
    document.getElementById("edit_name").value = name;
    document.getElementById("edit_email").value = email;
    document.getElementById("editModal").classList.add("is-visible");
}
function closeEditModal() {
    document.getElementById("editModal").classList.remove("is-visible");
}
</script>

<?php include '../partials/admin_footer.php'; ?>

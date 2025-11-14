<?php
session_start();

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'online_store';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$message_type = '';

// Current admin ID from session
$currentAdminId = $_SESSION['admin']['admin_id'] ?? 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /** CREATE ADMIN **/
    if (isset($_POST['create_admin'])) {
        $name     = $conn->real_escape_string($_POST['name']);
        $email    = $conn->real_escape_string($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

        $sql = "INSERT INTO Admin (name, email, password) VALUES ('$name', '$email', '$password')";
        if ($conn->query($sql)) {
            $message = "Admin created successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }

    /** UPDATE ADMIN **/
    if (isset($_POST['update_admin'])) {
        $id    = $conn->real_escape_string($_POST['admin_id']);
        $name  = $conn->real_escape_string($_POST['name']);
        $email = $conn->real_escape_string($_POST['email']);

        $sql = "UPDATE Admin SET name='$name', email='$email' WHERE admin_id='$id'";
        if ($conn->query($sql)) {

            if (!empty($_POST['new_password'])) {
                $new_password = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
                $conn->query("UPDATE Admin SET password='$new_password' WHERE admin_id='$id'");
            }

            $message = "Admin updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }

    /** DELETE ADMIN **/
    if (isset($_POST['delete_admin'])) {
        $id = $conn->real_escape_string($_POST['admin_id']);

        if ($id == $currentAdminId) {
            $message = "You cannot delete your own admin account!";
            $message_type = "error";
        } else {
            $sql = "DELETE FROM Admin WHERE admin_id='$id'";
            if ($conn->query($sql)) {
                $message = "Admin deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Error deleting admin: " . $conn->error;
                $message_type = "error";
            }
        }
    }
}

// READ admins with search
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$sql = "SELECT * FROM Admin WHERE 1=1";
if (!empty($search)) {
    $sql .= " AND (name LIKE '%$search%' OR email LIKE '%$search%')";
}
$sql .= " ORDER BY admin_id DESC";
$admins = $conn->query($sql);

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
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
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
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($admins->num_rows > 0): ?>
                        <?php while ($a = $admins->fetch_assoc()): ?>
                        <tr>
                            <td><?= $a['admin_id'] ?></td>
                            <td><?= htmlspecialchars($a['name']) ?></td>
                            <td><?= htmlspecialchars($a['email']) ?></td>
                            <td>
                                <button class="btn btn-warning btn-sm"
                                    onclick='openEditModal(<?= json_encode($a) ?>)'>Edit</button>
                                <?php if ($a['admin_id'] != $currentAdminId): ?>
                                    <button class="btn btn-danger btn-sm"
                                        onclick="confirmDelete(<?= $a['admin_id'] ?>)">Delete</button>
                                <?php else: ?>
                                    <span style="color:#777; font-size:0.85rem;">(You)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
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

        <form method="POST">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" required minlength="6">
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

        <form method="POST">
            <input type="hidden" name="admin_id" id="edit_admin_id">

            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" id="edit_name" required>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" id="edit_email" required>
            </div>
            <div class="form-group">
                <label>New Password (optional)</label>
                <input type="password" name="new_password" id="edit_password" placeholder="Leave blank to keep old password">
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
    document.getElementById('edit_name').value = data.name;
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
</script>

</body>
</html>

<?php $conn->close(); ?>

<?php
session_start();

// Include database connection
require_once '../helper/connection.php';
require_once '../helper/auth.php';

// Ensure only admin can access
require_admin();

// Check connection
if (!$db_ok || !$conn) {
    die("Connection failed: Unable to connect to database");
}

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /** CREATE MEMBER **/
    if (isset($_POST['create_member'])) {
        $name         = $conn->real_escape_string($_POST['name']);
        $email        = $conn->real_escape_string($_POST['email']);
        $phone        = $conn->real_escape_string($_POST['phone']);
        $address      = $conn->real_escape_string($_POST['address']);
        $password_raw = $_POST['password'];
        $password     = password_hash($password_raw, PASSWORD_BCRYPT);

        $sql = "INSERT INTO Member (name, email, phone_number, address, password, register_date)
                VALUES ('$name', '$email', '$phone', '$address', '$password', NOW())";

        if ($conn->query($sql)) {
            $message = "Member created successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }

    /** UPDATE MEMBER **/
    if (isset($_POST['update_member'])) {
        $id      = $conn->real_escape_string($_POST['member_id']);
        $name    = $conn->real_escape_string($_POST['name']);
        $email   = $conn->real_escape_string($_POST['email']);
        $phone   = $conn->real_escape_string($_POST['phone']);
        $address = $conn->real_escape_string($_POST['address']);

        $sql = "UPDATE Member SET 
                name='$name',
                email='$email',
                phone_number='$phone',
                address='$address'
                WHERE member_id='$id'";

        if ($conn->query($sql)) {

            // Optional password update
            if (!empty($_POST['new_password'])) {
                $new_password = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
                $conn->query("UPDATE Member SET password='$new_password' WHERE member_id='$id'");
            }

            $message = "Member updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error: " . $conn->error;
            $message_type = "error";
        }
    }

    /** DELETE MEMBER **/
    if (isset($_POST['delete_member'])) {
        $id = $conn->real_escape_string($_POST['member_id']);
        $sql = "DELETE FROM Member WHERE member_id='$id'";

        if ($conn->query($sql)) {
            $message = "Member deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting member: " . $conn->error;
            $message_type = "error";
        }
    }
}

/** READ members with search **/
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$sql = "SELECT * FROM Member WHERE 1=1";
if (!empty($search)) {
    $sql .= " AND (name LIKE '%$search%' OR email LIKE '%$search%')";
}
$sql .= " ORDER BY member_id ASC";

$members = $conn->query($sql);

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
        /* Optional: nicer cursor + arrows */
        th.sortable {
            cursor: pointer;
            user-select: none;
        }
        th.sortable::after {
            content: '';
            margin-left: 4px;
            font-size: 0.7rem;
        }
        th.sortable[data-order="asc"]::after {
            content: '▲';
        }
        th.sortable[data-order="desc"]::after {
            content: '▼';
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
            <button class="btn btn-primary" onclick="openCreateModal()">+ Add Member</button>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

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
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <!-- data-column index must match the <td> order -->
                        <th class="sortable" data-column="0" data-type="number">ID</th>
                        <th class="sortable" data-column="1" data-type="string">Name</th>
                        <th class="sortable" data-column="2" data-type="string">Email</th>
                        <th class="sortable" data-column="3" data-type="string">Phone</th>
                        <th class="sortable" data-column="4" data-type="string">Address</th>
                        <th class="sortable" data-column="5" data-type="string">Register Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($members->num_rows > 0): ?>
                        <?php while ($m = $members->fetch_assoc()): ?>
                        <tr>
                            <td><?= $m['member_id'] ?></td>
                            <td><?= htmlspecialchars($m['name']) ?></td>
                            <td><?= htmlspecialchars($m['email']) ?></td>
                            <td><?= htmlspecialchars($m['phone_number']) ?></td>
                            <td><?= htmlspecialchars($m['address']) ?></td>
                            <td><?= $m['register_date'] ?></td>

                            <td>
                                <button class="btn btn-warning btn-sm"
                                    onclick='openEditModal(<?= json_encode($m) ?>)'>
                                    Edit
                                </button>
                                <button class="btn btn-danger btn-sm"
                                    onclick="confirmDelete(<?= $m['member_id'] ?>)">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center;padding:40px;">No members found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<!-- CREATE MEMBER MODAL -->
<div id="createModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Member</h2>
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
                <label>Phone</label>
                <input type="text" name="phone">
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea name="address"></textarea>
            </div>

            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" required minlength="6">
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

        <form method="POST">
            <input type="hidden" name="member_id" id="edit_member_id">

            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" id="edit_name" required>
            </div>

            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" id="edit_email" required>
            </div>

            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" id="edit_phone">
            </div>

            <div class="form-group">
                <label>Address</label>
                <textarea name="address" id="edit_address"></textarea>
            </div>

            <div class="form-group">
                <label>New Password (optional)</label>
                <input type="password" name="new_password" id="edit_password" placeholder="Leave blank to keep old password">
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

function openEditModal(data) {
    document.getElementById('edit_member_id').value = data.member_id;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_email').value = data.email;
    document.getElementById('edit_phone').value = data.phone_number;
    document.getElementById('edit_address').value = data.address;

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

/* ===== TABLE SORTING (CLIENT-SIDE) ===== */
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
});
</script>

</body>
</html>

<?php $conn->close(); ?>

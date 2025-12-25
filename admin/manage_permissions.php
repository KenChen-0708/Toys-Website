<?php
session_start();
include '../helper/pdo_connection.php';
require_once '../helper/auth.php';

// Only Super Admin can manage permissions
if (get_admin_role() !== 'super admin') {
    header('Location: admin_dashboard.php?error=access_denied');
    exit;
}

$message = '';
$message_type = '';

if (isset($_SESSION['permission_message'])) {
    $message = $_SESSION['permission_message'];
    $message_type = $_SESSION['permission_message_type'];
    unset($_SESSION['permission_message']);
    unset($_SESSION['permission_message_type']);
}
$selectedAdminId = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
$selectedRole = isset($_GET['role']) ? trim($_GET['role']) : '';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'roles'; // 'roles' or 'admins'

// Define all available resources and actions (must be defined early)
$resources = [
    'members' => ['view', 'create', 'update', 'delete'],
    'admins' => ['view', 'create', 'update', 'delete'],
    'orders' => ['view', 'create', 'update', 'delete'],
    'products' => ['view', 'create', 'update', 'delete'],
    'feedback' => ['view', 'create', 'update', 'delete'],
    'analytics' => ['view'],
    'vouchers' => ['view', 'create', 'update', 'delete'],
    'emails' => ['view', 'create'],
];

$resourceLabels = [
    'members' => 'Members',
    'admins' => 'Admins',
    'orders' => 'Orders',
    'products' => 'Products',
    'feedback' => 'Feedback',
    'analytics' => 'Analytics',
    'vouchers' => 'Vouchers',
    'emails' => 'Email Notifications',
];

$actionLabels = [
    'view' => 'View',
    'create' => 'Create',
    'update' => 'Update',
    'delete' => 'Delete',
];

// Get all admins (except super admin for security)
$admins = [];
$stmt = $pdo->query("SELECT admin_id, username, email, role FROM Admin WHERE role != 'super admin' ORDER BY username");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all unique roles from Role table
$roles = [];
$stmt = $pdo->query("SELECT role_name FROM Role WHERE role_name != 'super admin' ORDER BY role_name");
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get selected admin info
$selectedAdmin = null;
if ($selectedAdminId > 0) {
    $stmt = $pdo->prepare("SELECT admin_id, username, email, role FROM Admin WHERE admin_id = ?");
    $stmt->execute([$selectedAdminId]);
    $selectedAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle role creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_role'])) {
    $newRole = trim($_POST['new_role_name'] ?? '');

    if (!empty($newRole)) {
        // Check if role already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Role WHERE role_name = ?");
        $stmt->execute([$newRole]);

        if ($stmt->fetchColumn() > 0) {
            $message = "Role '$newRole' already exists!";
            $message_type = "error";
        } else {
            $stmt = $pdo->prepare("INSERT INTO Role (role_name) VALUES (?)");
            $stmt->execute([$newRole]);
            $_SESSION['permission_message'] = "Role '$newRole' created successfully! You can now assign permissions to it.";
            $_SESSION['permission_message_type'] = "success";
            header("Location: manage_permissions.php?tab=roles&role=" . urlencode($newRole));
            exit;
        }
    } else {
        $message = "Role name cannot be empty!";
        $message_type = "error";
    }
}

// Handle role deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_role'])) {
    $roleToDelete = trim($_POST['role_to_delete'] ?? '');

    if (!empty($roleToDelete) && $roleToDelete !== 'super admin') {
        try {
            // Check if any admins have this role
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Admin WHERE role = ?");
            $stmt->execute([$roleToDelete]);
            $adminCount = $stmt->fetchColumn();

            if ($adminCount > 0) {
                $message = "Cannot delete role '$roleToDelete' because $adminCount admin(s) are using it. Please reassign them first.";
                $message_type = "error";
            } else {
                $pdo->beginTransaction();

                // Delete role permissions
                $stmt = $pdo->prepare("DELETE FROM Role_Permission WHERE role = ?");
                $stmt->execute([$roleToDelete]);

                // Delete the role from Role table
                $stmt = $pdo->prepare("DELETE FROM Role WHERE role_name = ?");
                $stmt->execute([$roleToDelete]);

                $pdo->commit();
                $message = "Role '$roleToDelete' deleted successfully!";
                $message_type = "success";
                $selectedRole = '';
                $activeTab = 'roles';

                // Refresh roles list
                $stmt = $pdo->query("SELECT role_name FROM Role WHERE role_name != 'super admin' ORDER BY role_name");
                $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error deleting role: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "Invalid role or cannot delete super admin role!";
        $message_type = "error";
    }
}

// Handle role permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_role_permissions'])) {
    $role = trim($_POST['role'] ?? '');

    if (!empty($role)) {
        try {
            $pdo->beginTransaction();

            // Delete existing permissions for this role
            $stmt = $pdo->prepare("DELETE FROM Role_Permission WHERE role = ?");
            $stmt->execute([$role]);

            // Insert new permissions
            if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                $insertStmt = $pdo->prepare("INSERT INTO Role_Permission (role, resource, action) VALUES (?, ?, ?)");

                foreach ($_POST['permissions'] as $permission) {
                    list($resource, $action) = explode(':', $permission);
                    $insertStmt->execute([$role, $resource, $action]);
                }
            }

            $pdo->commit();
            $message = "Role permissions updated successfully!";
            $message_type = "success";
            $selectedRole = $role;
            $activeTab = 'roles';
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error updating role permissions: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Handle admin permission updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_admin_permissions'])) {
    $adminId = (int)($_POST['admin_id'] ?? 0);

    if ($adminId > 0 && $selectedAdmin) {
        try {
            $pdo->beginTransaction();

            // Get all permissions that should be checked (from form)
            $checkedPermissions = [];
            if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                foreach ($_POST['permissions'] as $permission) {
                    $checkedPermissions[] = $permission;
                }
            }

            // Get role permissions for this admin
            $rolePermissions = [];
            $stmt = $pdo->prepare("SELECT resource, action FROM Role_Permission WHERE role = ?");
            $stmt->execute([$selectedAdmin['role']]);
            $rolePerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rolePerms as $perm) {
                $rolePermissions[] = $perm['resource'] . ':' . $perm['action'];
            }

            // Process each possible permission
            foreach ($resources as $resource => $actions) {
                foreach ($actions as $action) {
                    $permissionKey = $resource . ':' . $action;
                    $isChecked = in_array($permissionKey, $checkedPermissions);
                    $isInRole = in_array($permissionKey, $rolePermissions);



                    if ($isChecked) {
                        // Permission is checked
                        if ($isInRole) {
                            // It's in role permissions, so remove from denied (if exists)
                            $stmt = $pdo->prepare("DELETE FROM Admin_Permission_Denied WHERE admin_id = ? AND resource = ? AND action = ?");
                            $stmt->execute([$adminId, $resource, $action]);
                        } else {
                            // It's not in role permissions, so add to explicit permissions
                            $stmt = $pdo->prepare("INSERT IGNORE INTO Admin_Permission (admin_id, resource, action) VALUES (?, ?, ?)");
                            $stmt->execute([$adminId, $resource, $action]);
                            // Also remove from denied if exists
                            $stmt = $pdo->prepare("DELETE FROM Admin_Permission_Denied WHERE admin_id = ? AND resource = ? AND action = ?");
                            $stmt->execute([$adminId, $resource, $action]);
                        }
                    } else {
                        // Permission is unchecked
                        if ($isInRole) {
                            // It's in role permissions, so deny it
                            $stmt = $pdo->prepare("INSERT IGNORE INTO Admin_Permission_Denied (admin_id, resource, action) VALUES (?, ?, ?)");
                            $stmt->execute([$adminId, $resource, $action]);
                        } else {
                            // It's not in role permissions, so remove from explicit permissions
                            $stmt = $pdo->prepare("DELETE FROM Admin_Permission WHERE admin_id = ? AND resource = ? AND action = ?");
                            $stmt->execute([$adminId, $resource, $action]);
                            // Also remove from denied if exists
                            $stmt = $pdo->prepare("DELETE FROM Admin_Permission_Denied WHERE admin_id = ? AND resource = ? AND action = ?");
                            $stmt->execute([$adminId, $resource, $action]);
                        }
                    }
                }
            }

            $pdo->commit();
            $message = "Admin permissions updated successfully!";
            $message_type = "success";
            $selectedAdminId = $adminId;
            $activeTab = 'admins';

            // Reload admin info
            $stmt = $pdo->prepare("SELECT admin_id, username, email, role FROM Admin WHERE admin_id = ?");
            $stmt->execute([$adminId]);
            $selectedAdmin = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $pdo->rollBack();
            $message = "Error updating admin permissions: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Get current role permissions
$currentRolePermissions = [];
if (!empty($selectedRole)) {
    $stmt = $pdo->prepare("SELECT resource, action FROM Role_Permission WHERE role = ?");
    $stmt->execute([$selectedRole]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($permissions as $perm) {
        $currentRolePermissions[] = $perm['resource'] . ':' . $perm['action'];
    }
}

// Get current admin explicit permissions
$currentAdminPermissions = [];
if ($selectedAdminId > 0) {
    $stmt = $pdo->prepare("SELECT resource, action FROM Admin_Permission WHERE admin_id = ?");
    $stmt->execute([$selectedAdminId]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($permissions as $perm) {
        $currentAdminPermissions[] = $perm['resource'] . ':' . $perm['action'];
    }
}

// Get denied permissions for selected admin
$currentDeniedPermissions = [];
if ($selectedAdminId > 0) {
    $stmt = $pdo->prepare("SELECT resource, action FROM Admin_Permission_Denied WHERE admin_id = ?");
    $stmt->execute([$selectedAdminId]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($permissions as $perm) {
        $currentDeniedPermissions[] = $perm['resource'] . ':' . $perm['action'];
    }
}

// Get role permissions for selected admin (to show base permissions)
$adminRolePermissions = [];
if ($selectedAdmin) {
    $stmt = $pdo->prepare("SELECT resource, action FROM Role_Permission WHERE role = ?");
    $stmt->execute([$selectedAdmin['role']]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($permissions as $perm) {
        $adminRolePermissions[] = $perm['resource'] . ':' . $perm['action'];
    }
}

// Determine which permissions should be checked in the form
// A permission is checked if:
// 1. It's in role permissions AND not denied, OR
// 2. It's explicitly granted (in Admin_Permission)
// Build checked/denied lists for UI based on role, explicit grants, and denials
$checkedPermissions = [];
$deniedPermissions = []; // Track denied permissions for display
if ($selectedAdmin) {
    foreach ($resources as $resource => $actions) {
        foreach ($actions as $action) {
            $permissionKey = $resource . ':' . $action;
            $isInRole = in_array($permissionKey, $adminRolePermissions);
            $isExplicit = in_array($permissionKey, $currentAdminPermissions);
            $isDenied = in_array($permissionKey, $currentDeniedPermissions);

            if (($isInRole && !$isDenied) || $isExplicit) {
                $checkedPermissions[] = $permissionKey;
            } elseif ($isInRole && $isDenied) {
                // Track denied permissions that were inherited from role
                $deniedPermissions[] = $permissionKey;
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Permissions - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/admin_panel.css">
    <link rel="stylesheet" href="../assets/css/manage_product.css">
    <style>
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }

        .tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            color: #6b7280;
            transition: all 0.2s;
        }

        .tab:hover {
            color: #1f2937;
            background: #f9fafb;
        }

        .tab.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .permission-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            margin-top: 20px;
        }

        .list-panel {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .list-panel h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #1f2937;
        }

        .list-item {
            padding: 12px;
            margin-bottom: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
            border: 2px solid transparent;
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .list-item:hover {
            background-color: #f3f4f6;
        }

        .list-item.active {
            background-color: #dbeafe;
            border-color: #3b82f6;
        }

        .list-item .item-name {
            font-weight: 600;
            color: #1f2937;
        }

        .list-item .item-role {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 4px;
        }

        .permissions-form {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .permissions-form h3 {
            margin-top: 0;
            color: #1f2937;
        }

        .info-box {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 20px;
            color: #0c4a6e;
            font-size: 0.9rem;
        }

        .resource-group {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }

        .resource-group:last-child {
            border-bottom: none;
        }

        .resource-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #1f2937;
            margin-bottom: 12px;
        }

        .actions-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .action-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
        }

        .action-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .action-checkbox label {
            cursor: pointer;
            user-select: none;
            color: #374151;
        }

        .action-checkbox .role-permission-badge {
            position: absolute;
            top: -12px;
            right: -8px;
            background: #10b981;
            color: white;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 10px;
            font-weight: 600;
        }

        .action-checkbox.denied {
            opacity: 0.7;
        }

        .action-checkbox.denied input[type="checkbox"] {
            position: relative;
            border-color: #dc2626;
        }

        .action-checkbox.denied input[type="checkbox"]:not(:checked) {
            background-color: #fee2e2;
            border-color: #dc2626;
        }

        .denied-mark {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: #dc2626;
            /* red background */
            color: #000;
            /* black X */
            font-size: 13px;
            font-weight: 900;
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 3px;
            pointer-events: none;
            z-index: 2;
        }

        .action-checkbox input[type="checkbox"]:disabled {
            cursor: not-allowed;
            opacity: 0.7;
        }

        .no-selection {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .no-selection-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰ Menu</button>
    <div class="admin-container">
        <?php include '../partials/admin_panel.php'; ?>

        <div class="main-content">
            <div class="header">
                <h1>Manage Permissions & Roles</h1>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="tabs">
                <button class="tab <?= $activeTab === 'roles' ? 'active' : '' ?>"
                    onclick="switchTab('roles')">Role Permissions</button>
                <button class="tab <?= $activeTab === 'admins' ? 'active' : '' ?>"
                    onclick="switchTab('admins')">Admin Permissions</button>
            </div>

            <!-- Role Permissions Tab -->
            <div id="roles-tab" class="tab-content <?= $activeTab === 'roles' ? 'active' : '' ?>">
                <div class="permission-container">
                    <!-- Role List -->
                    <div class="list-panel">
                        <h3>Select Role</h3>

                        <!-- Role Management Buttons -->
                        <div style="margin-bottom: 15px; display: flex; gap: 8px; flex-direction: column;">
                            <button class="btn btn-primary" onclick="openCreateRoleModal()" style="width: 100%; padding: 8px; font-size: 0.9rem;">+ Add New Role</button>
                        </div>

                        <?php if (empty($roles)): ?>
                            <p style="color: #6b7280;">No roles available.</p>
                        <?php else: ?>
                            <?php foreach ($roles as $role): ?>
                                <a href="?tab=roles&role=<?= urlencode($role) ?>"
                                    class="list-item <?= $selectedRole === $role ? 'active' : '' ?>">
                                    <div class="item-name"><?= htmlspecialchars(ucwords($role)) ?></div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Role Permissions Form -->
                    <div class="permissions-form">
                        <?php if (!empty($selectedRole)): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f3f4f6;">
                                <h3 style="margin: 0;">Permissions for Role: <?= htmlspecialchars(ucwords($selectedRole)) ?></h3>
                                <button type="button" class="btn" onclick="confirmDeleteRole('<?= htmlspecialchars($selectedRole) ?>')"
                                    style="background: #dc2626; color: white; padding: 6px 12px; font-size: 0.85rem; display: flex; align-items: center; gap: 5px;">
                                    🗑 Delete Role
                                </button>
                            </div>

                            <form method="POST">
                                <input type="hidden" name="role" value="<?= htmlspecialchars($selectedRole) ?>">

                                <?php foreach ($resources as $resource => $actions): ?>
                                    <div class="resource-group">
                                        <div class="resource-title"><?= htmlspecialchars($resourceLabels[$resource]) ?></div>
                                        <div class="actions-list">
                                            <?php foreach ($actions as $action): ?>
                                                <?php $permissionKey = $resource . ':' . $action; ?>
                                                <div class="action-checkbox">
                                                    <input type="checkbox"
                                                        name="permissions[]"
                                                        value="<?= htmlspecialchars($permissionKey) ?>"
                                                        id="role_perm_<?= htmlspecialchars($resource) ?>_<?= htmlspecialchars($action) ?>"
                                                        data-initial-checked="<?= in_array($permissionKey, $currentRolePermissions) ? '1' : '0' ?>"
                                                        <?= in_array($permissionKey, $currentRolePermissions) ? 'checked' : '' ?>>
                                                    <label for="role_perm_<?= htmlspecialchars($resource) ?>_<?= htmlspecialchars($action) ?>">
                                                        <?= htmlspecialchars($actionLabels[$action]) ?>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <div style="margin-top: 30px; display: flex; gap: 10px;">
                                    <button type="submit" id="role_save_btn" name="save_role_permissions" class="btn btn-primary" disabled style="opacity:0.6;cursor:not-allowed;">Save Role Permissions</button>
                                    <button type="button" id="role_clear_btn" class="btn" disabled style="background: #cbd5e0;opacity:0.6;cursor:not-allowed;">Clear Selection</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="no-selection">
                                <div class="no-selection-icon">👥</div>
                                <h3>Select a role to manage permissions</h3>
                                <p>Choose a role from the list on the left to set base permissions.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Admin Permissions Tab -->
            <div id="admins-tab" class="tab-content <?= $activeTab === 'admins' ? 'active' : '' ?>">
                <div class="permission-container">
                    <!-- Admin List -->
                    <div class="list-panel">
                        <h3>Select Admin</h3>
                        <?php if (empty($admins)): ?>
                            <p style="color: #6b7280;">No admins available.</p>
                        <?php else: ?>
                            <?php foreach ($admins as $admin): ?>
                                <a href="?tab=admins&admin_id=<?= $admin['admin_id'] ?>"
                                    class="list-item <?= $selectedAdminId == $admin['admin_id'] ? 'active' : '' ?>">
                                    <div class="item-name"><?= htmlspecialchars($admin['username']) ?></div>
                                    <div class="item-role"><?= htmlspecialchars(ucwords($admin['role'])) ?></div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Admin Permissions Form -->
                    <div class="permissions-form">
                        <?php if ($selectedAdmin): ?>
                            <h3>Permissions for: <?= htmlspecialchars($selectedAdmin['username']) ?></h3>
                            <div class="info-box">
                                <strong>Note:</strong> Permissions inherited from role are shown as checked. Unchecking a permission will revoke it even if it's granted by the role (shown with ✕). Checking additional permissions will grant them beyond the role permissions.
                            </div>

                            <form method="POST">
                                <input type="hidden" name="admin_id" value="<?= $selectedAdmin['admin_id'] ?>">

                                <?php foreach ($resources as $resource => $actions): ?>
                                    <div class="resource-group">
                                        <div class="resource-title"><?= htmlspecialchars($resourceLabels[$resource]) ?></div>
                                        <div class="actions-list">
                                            <?php foreach ($actions as $action): ?>
                                                <?php
                                                $permissionKey = $resource . ':' . $action;
                                                $hasRolePermission = in_array($permissionKey, $adminRolePermissions);
                                                $isChecked = in_array($permissionKey, $checkedPermissions);
                                                $isExplicit = in_array($permissionKey, $currentAdminPermissions);
                                                $isDenied = in_array($permissionKey, $deniedPermissions);
                                                ?>
                                                <div class="action-checkbox <?= $isDenied ? 'denied' : '' ?>" style="position: relative;">
                                                    <span class="denied-mark" style="<?= $isDenied && !$isChecked ? '' : 'display:none;' ?>">✕</span>
                                                    <input type="checkbox"
                                                        name="permissions[]"
                                                        value="<?= htmlspecialchars($permissionKey) ?>"
                                                        id="admin_perm_<?= htmlspecialchars($resource) ?>_<?= htmlspecialchars($action) ?>"
                                                        data-initial-checked="<?= $isChecked ? '1' : '0' ?>"
                                                        data-has-role="<?= $hasRolePermission ? '1' : '0' ?>"
                                                        data-is-explicit="<?= $isExplicit ? '1' : '0' ?>"
                                                        data-is-denied="<?= $isDenied ? '1' : '0' ?>"
                                                        <?= $isChecked ? 'checked' : '' ?>
                                                        style="<?= $isDenied && !$isChecked ? 'opacity: 0.3;' : '' ?>">
                                                    <label for="admin_perm_<?= htmlspecialchars($resource) ?>_<?= htmlspecialchars($action) ?>">
                                                        <?= htmlspecialchars($actionLabels[$action]) ?>
                                                    </label>
                                                    <?php if ($hasRolePermission && !$isExplicit && !$isDenied): ?>
                                                        <span class="role-permission-badge" title="Inherited from role">ROLE</span>
                                                    <?php elseif ($isExplicit): ?>
                                                        <span class="role-permission-badge" style="background: #3b82f6;" title="Explicitly granted">EXTRA</span>
                                                    <?php elseif ($isDenied): ?>
                                                        <span class="role-permission-badge" style="background: #dc2626;" title="Denied (inherited from role but revoked)">DENIED</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <div style="margin-top: 30px; display: flex; gap: 10px;">
                                    <button type="submit" id="admin_save_btn" name="save_admin_permissions" class="btn btn-primary" disabled style="opacity:0.6;cursor:not-allowed;">Save Admin Permissions</button>
                                    <button type="button" id="admin_clear_btn" class="btn" disabled style="background: #cbd5e0;opacity:0.6;cursor:not-allowed;">Clear Selection</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="no-selection">
                                <div class="no-selection-icon">👤</div>
                                <h3>Select an admin to manage additional permissions</h3>
                                <p>Choose an admin from the list on the left to add custom permissions on top of their role permissions.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Role Modal -->
    <div id="createRoleModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: white; margin: 10% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <h2 style="margin-top: 0; color: #1f2937;">Create New Role</h2>
            <form method="POST">
                <div style="margin-bottom: 20px;">
                    <input type="text" id="new_role_name" name="new_role_name" required
                        style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 1rem; margin-top: 15px;"
                        placeholder="Role Name">
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeCreateRoleModal()" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" name="create_role" class="btn btn-primary">Create Role</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Role Modal (Confirmation) -->
    <div id="deleteRoleModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: white; margin: 10% auto; padding: 30px; border-radius: 8px; width: 90%; max-width: 500px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <h2 style="margin-top: 0; color: #dc2626;">Confirm Role Deletion</h2>
            <form method="POST">
                <input type="hidden" id="role_to_delete" name="role_to_delete" value="">
                <div style="margin-bottom: 20px;">
                    <p id="delete_confirmation_message" style="font-size: 1.1rem; color: #374151;"></p>
                    <p style="margin-top: 15px; color: #dc2626; font-size: 0.9rem; background: #fee2e2; padding: 10px; border-radius: 6px; border: 1px solid #fecaca;">
                        <strong>⚠️ Warning:</strong> This will delete all base permissions associated with this role.
                    </p>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeDeleteRoleModal()" class="btn" style="background: #6b7280; color: white;">Cancel</button>
                    <button type="submit" name="delete_role" class="btn" style="background: #dc2626; color: white;">Yes, Delete Role</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Update URL without reload
            const url = new URL(window.location);
            url.searchParams.set('tab', tab);
            window.location.href = url.toString();
        }

        function openCreateRoleModal() {
            document.getElementById('createRoleModal').style.display = 'block';
        }

        function closeCreateRoleModal() {
            document.getElementById('createRoleModal').style.display = 'none';
        }

        function openDeleteRoleModal() {
            document.getElementById('deleteRoleModal').style.display = 'block';
        }

        function closeDeleteRoleModal() {
            document.getElementById('deleteRoleModal').style.display = 'none';
        }

        function confirmDeleteRole(role) {
            document.getElementById('role_to_delete').value = role;
            document.getElementById('delete_confirmation_message').innerHTML = `Are you sure you want to delete the role <strong>${role.toUpperCase()}</strong>?`;
            document.getElementById('deleteRoleModal').style.display = 'block';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById('createRoleModal');
            const deleteModal = document.getElementById('deleteRoleModal');
            if (event.target === createModal) {
                closeCreateRoleModal();
            }
            if (event.target === deleteModal) {
                closeDeleteRoleModal();
            }
        }
    </script>

    <script>
        // Dynamic UI: show ✕ and update badge when a role-inherited permission is unchecked
        document.addEventListener('DOMContentLoaded', function() {
            // Attach change handler to all permission checkboxes in both Role and Admin forms
            const allPermissionCheckboxes = document.querySelectorAll('.permissions-form input[type="checkbox"]');
            allPermissionCheckboxes.forEach(function(cb) {
                cb.addEventListener('change', function(e) {
                    const input = e.target;
                    const parent = input.closest('.action-checkbox');
                    if (!parent) return;

                    const isAdminCheckbox = input.hasAttribute('data-has-role'); // only admin tab has this

                    // ============================
                    // ROLE TAB CHECKBOXES
                    // ============================
                    if (!isAdminCheckbox) {
                        // For role permissions we DON'T show ROLE / EXTRA / DENIED badges at all.
                        // Just update Save / Clear button state based on dirty check.
                        const formContainer = input.closest('.permissions-form');
                        if (formContainer) {
                            updateButtonsForContainer(formContainer);
                        }
                        return;
                    }

                    // ============================
                    // ADMIN TAB CHECKBOXES (with inheritance logic)
                    // ============================
                    const hasRole = input.getAttribute('data-has-role') === '1';

                    if (hasRole && !input.checked) {
                        // inherited from role and user unchecks it -> DENIED
                        parent.classList.add('denied');
                        input.style.opacity = '0.3';

                        let mark = parent.querySelector('.denied-mark');
                        if (!mark) {
                            mark = document.createElement('span');
                            mark.className = 'denied-mark';
                            mark.textContent = '✕';
                            parent.insertBefore(mark, parent.firstChild);
                        }
                        mark.style.display = '';

                        let badge = parent.querySelector('.role-permission-badge');
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'role-permission-badge';
                            parent.appendChild(badge);
                        }
                        badge.textContent = 'DENIED';
                        badge.style.background = '#dc2626';
                        badge.title = 'Denied (inherited from role but revoked)';
                    } else {
                        // remove denied visuals
                        parent.classList.remove('denied');
                        input.style.opacity = '';
                        const mark = parent.querySelector('.denied-mark');
                        if (mark) mark.style.display = 'none';

                        const badge = parent.querySelector('.role-permission-badge');
                        const hasRoleNow = input.getAttribute('data-has-role') === '1';

                        if (!input.checked && !hasRoleNow) {
                            // nothing to show
                            if (badge) badge.remove();
                        } else if (input.checked && !hasRoleNow) {
                            // EXTRA explicit permission
                            if (!badge) {
                                const b = document.createElement('span');
                                b.className = 'role-permission-badge';
                                b.style.background = '#3b82f6';
                                b.textContent = 'EXTRA';
                                b.title = 'Explicitly granted';
                                parent.appendChild(b);
                            } else {
                                badge.textContent = 'EXTRA';
                                badge.style.background = '#3b82f6';
                                badge.title = 'Explicitly granted';
                            }
                        } else if (hasRoleNow) {
                            // inherited from role
                            if (!badge) {
                                const b = document.createElement('span');
                                b.className = 'role-permission-badge';
                                b.textContent = 'ROLE';
                                b.title = 'Inherited from role';
                                parent.appendChild(b);
                            } else {
                                badge.textContent = 'ROLE';
                                badge.style.background = '';
                                badge.title = 'Inherited from role';
                            }
                        }
                    }

                    // After visuals update, update Save/Clear button states
                    const formContainer = input.closest('.permissions-form');
                    if (formContainer) {
                        updateButtonsForContainer(formContainer);
                    }
                });
            });


            // Determine whether a container has unsaved changes compared to data-initial-checked
            function containerIsDirty(container) {
                if (!container) return false;
                const inputs = container.querySelectorAll('input[type="checkbox"][data-initial-checked]');
                for (let i = 0; i < inputs.length; i++) {
                    const inp = inputs[i];
                    const initial = inp.getAttribute('data-initial-checked') === '1';
                    if ((inp.checked ? true : false) !== initial) return true;
                }
                return false;
            }

            // Update Save/Clear button states for a given container (.permissions-form)
            function updateButtonsForContainer(container) {
                if (!container) return;
                // select appropriate buttons
                const saveBtn = container.querySelector('#admin_save_btn') || document.getElementById('role_save_btn');
                const clearBtn = container.querySelector('#admin_clear_btn') || document.getElementById('role_clear_btn');
                const dirty = containerIsDirty(container);

                if (saveBtn) {
                    if (dirty) {
                        saveBtn.removeAttribute('disabled');
                        saveBtn.style.opacity = '';
                        saveBtn.style.cursor = '';
                        // active save style: blue background, light font
                        saveBtn.style.background = '#2563eb';
                        saveBtn.style.color = '#fff';
                        saveBtn.style.borderColor = '';
                    } else {
                        saveBtn.setAttribute('disabled', 'disabled');
                        saveBtn.style.opacity = '0.6';
                        saveBtn.style.cursor = 'not-allowed';
                        // revert to default button styling
                        saveBtn.style.background = '';
                        saveBtn.style.color = '';
                        saveBtn.style.borderColor = '';
                    }
                }

                if (clearBtn) {
                    if (dirty) {
                        clearBtn.removeAttribute('disabled');
                        clearBtn.style.opacity = '';
                        clearBtn.style.cursor = '';
                        // active color for clear when dirty: red background, light font
                        clearBtn.style.background = '#dc2626';
                        clearBtn.style.color = '#fff';
                        clearBtn.style.borderColor = '';
                    } else {
                        clearBtn.setAttribute('disabled', 'disabled');
                        clearBtn.style.opacity = '0.6';
                        clearBtn.style.cursor = 'not-allowed';
                        clearBtn.style.background = '#cbd5e0';
                        clearBtn.style.color = '';
                        clearBtn.style.borderColor = '';
                    }
                }
            }

            // Helper to reset checkboxes within a container to their initial saved state
            function resetContainerToInitial(container) {
                if (!container) return;
                container.querySelectorAll('input[type="checkbox"][data-initial-checked]').forEach(function(input) {
                    const initial = input.getAttribute('data-initial-checked') === '1';
                    input.checked = initial;
                    // restore or dim checkbox based on denied state and initial checked
                    const isDenied = input.getAttribute('data-is-denied') === '1';
                    if (isDenied && !initial) {
                        input.style.opacity = '0.3';
                    } else {
                        input.style.opacity = '';
                    }
                    // trigger change so UI updates (badges, denied-mark)
                    input.dispatchEvent(new Event('change', {
                        bubbles: true
                    }));
                });
            }

            // Role Clear button: reset role form to saved role permissions
            const roleClearBtn = document.getElementById('role_clear_btn');
            if (roleClearBtn) {
                roleClearBtn.addEventListener('click', function() {
                    const rolesTab = document.getElementById('roles-tab');
                    const roleContainer = rolesTab ? rolesTab.querySelector('.permissions-form') : null;
                    resetContainerToInitial(roleContainer);
                });
            }

            // Admin Clear button: reset admin form to saved admin permissions/denials
            const adminClearBtn = document.getElementById('admin_clear_btn');
            if (adminClearBtn) {
                adminClearBtn.addEventListener('click', function() {
                    const adminsTab = document.getElementById('admins-tab');
                    const adminContainer = adminsTab ? adminsTab.querySelector('.permissions-form') : null;
                    resetContainerToInitial(adminContainer);
                });
            }

            // Initialize button states on load for both role and admin containers
            const rolesTab = document.getElementById('roles-tab');
            const roleContainer = rolesTab ? rolesTab.querySelector('.permissions-form') : null;
            updateButtonsForContainer(roleContainer);
            const adminsTab = document.getElementById('admins-tab');
            const adminContainer = adminsTab ? adminsTab.querySelector('.permissions-form') : null;
            updateButtonsForContainer(adminContainer);
        });
    </script>

</body>

</html>
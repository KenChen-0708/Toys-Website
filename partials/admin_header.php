<?php
session_start();

// Database configuration
// Create connection
include '../helper/pdo_connection.php';
require_once '../helper/auth.php';
require_admin();

// Helper to show initials in avatar
function admin_initials($name) {
    $parts = preg_split('/\s+/', trim((string)$name));
    $first  = mb_substr($parts[0] ?? '', 0, 1);
    $second = mb_substr($parts[1] ?? '', 0, 1);
    $initials = $first . ($second ?: '');
    return mb_strtoupper($initials ?: 'AD');
}
?>
<style>
  .admin-user-profile {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }

  .admin-user-profile .avatar-btn {
    border: none;
    background: transparent;
    padding: 0;
    cursor: pointer;
  }

  .admin-user-profile .avatar-circle {
    width: 36px;
    height: 36px;
    border-radius: 999px;
    background: #111827;
    color: #f9fafb;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.9rem;
    text-transform: uppercase;
  }

  .admin-user-profile .profile-menu {
    position: absolute;
    right: 0;
    top: 120%;
    min-width: 220px;
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
    padding: 0.75rem 0.85rem;
    display: none;
    z-index: 20;
  }

  .admin-user-profile .profile-menu__header {
    border-bottom: 1px solid #e5e7eb;
    padding-bottom: 0.5rem;
    margin-bottom: 0.5rem;
  }

  .admin-user-profile .profile-menu__header strong {
    display: block;
    font-size: 0.9rem;
  }

  .admin-user-profile .profile-menu__header small {
    display: block;
    font-size: 0.78rem;
    color: #6b7280;
  }

  .admin-user-profile .profile-menu a,
  .admin-user-profile .profile-menu button.logout-btn {
    display: block;
    width: 100%;
    text-align: left;
    padding: 0.35rem 0;
    font-size: 0.85rem;
    border: none;
    background: transparent;
    cursor: pointer;
    color: #111827;
  }

  .admin-user-profile .profile-menu a:hover,
  .admin-user-profile .profile-menu button.logout-btn:hover {
    color: #2563eb;
  }

  .admin-user-profile .profile-menu button.logout-btn {
    margin-top: 0.35rem;
    border-top: 1px solid #e5e7eb;
    padding-top: 0.45rem;
    color: #b91c1c;
  }
</style>


<header class="header">
    <div class="header-left">
        <h1>Dashboard</h1>
    </div>
    <div class="header-right">
        <div class="admin-user-profile">
            <button class="avatar-btn" aria-haspopup="true" aria-expanded="false">
                <span class="avatar-circle">
                    <?= htmlspecialchars(admin_initials($_SESSION['admin']['name'] ?? 'Admin')) ?>
                </span>
            </button>
            <div class="profile-menu">
                <div class="profile-menu__header">
                    <strong><?= htmlspecialchars($_SESSION['admin']['name'] ?? 'Administrator') ?></strong>
                    <small><?= htmlspecialchars($_SESSION['admin']['email'] ?? '') ?></small>
                </div>
                <a href="./manage_admin.php">Manage Admins</a>
                <a href="./manage_member.php">Manage Members</a>
                <a href="./manage_product.php">Manage Products</a>
                <form action="../auth/admin_logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <button type="submit" class="logout-btn">Log Out</button>
                </form>
            </div>
        </div>
    </div>
</header>


<script>
  document.addEventListener('click', function(e) {
    const prof = e.target.closest('.admin-user-profile');

    // Close all menus first
    document.querySelectorAll('.admin-user-profile .profile-menu').forEach(m => {
      m.style.display = 'none';
    });

    // If clicked on avatar inside a profile, toggle that one
    if (prof && e.target.closest('.avatar-btn')) {
      const menu = prof.querySelector('.profile-menu');
      if (menu) {
        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
      }
    }
  });
</script>

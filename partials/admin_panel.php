<?php 
// Make sure session is started somewhere before this file is included
// session_start();

require_once __DIR__ . '/../helper/auth.php';

$current_page = basename($_SERVER['PHP_SELF']); 

// Prepare admin data safely
$adminData   = $_SESSION['admin'] ?? [];
$adminName   = $adminData['username'] ?? $adminData['name'] ?? 'Admin';
$adminEmail  = $adminData['email'] ?? '';
$adminRole   = get_admin_role();
$adminInitial = strtoupper(substr($adminName, 0, 1));
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
	<div class="sidebar-header">
		<div class="logo">
			<h2>Admin Panel</h2>
		</div>
		<button class="sidebar-toggle" id="sidebarToggle" onclick="toggleSidebarCollapse()">
			<span class="toggle-icon">◀</span>
		</button>
	</div>

	<nav class="nav">
		<?php if (can_access_page('admin_dashboard.php')): ?>
		<a href="admin_dashboard.php" 
		   class="nav-item <?= ($current_page == 'admin_dashboard.php') ? 'active' : '' ?>"
		   title="Dashboard">
			<span class="icon">📊</span>
			<span class="nav-text">Dashboard</span>
		</a>
		<?php endif; ?>

		<?php if (can_access_page('manage_member.php')): ?>
		<a href="manage_member.php" 
		   class="nav-item <?= ($current_page == 'manage_member.php') ? 'active' : '' ?>"
		   title="Members">
			<span class="icon">👥</span>
			<span class="nav-text">Members</span>
		</a>
		<?php endif; ?>

		<?php if (can_access_page('manage_admin.php')): ?>
		<a href="manage_admin.php" 
		   class="nav-item <?= ($current_page == 'manage_admin.php') ? 'active' : '' ?>"
		   title="Admins">
			<span class="icon">🧑‍💼</span>
			<span class="nav-text">Admins</span>
		</a>
		<?php endif; ?>

		<?php if (can_access_page('manage_orders.php')): ?>
		<a href="manage_orders.php"
		   class="nav-item <?= ($current_page == 'manage_orders.php') ? 'active' : '' ?>"
		   title="Orders">
			<span class="icon">🛒</span>
			<span class="nav-text">Orders</span>
		</a>
		<?php endif; ?>

		<?php if (can_access_page('manage_product.php')): ?>
		<a href="manage_product.php" 
		   class="nav-item <?= ($current_page == 'manage_product.php') ? 'active' : '' ?>"
		   title="Products">
			<span class="icon">📦</span>
			<span class="nav-text">Products</span>
		</a>
		<?php endif; ?>

		<?php if (can_access_page('feedback_management.php')): ?>
		<a href="feedback_management.php"
		   class="nav-item <?= ($current_page == 'feedback_management.php') ? 'active' : '' ?>"
		   title="Feedback">
			<span class="icon">💬</span>
			<span class="nav-text">Feedback</span>
		</a>
		<?php endif; ?>

		<?php if (can_access_page('analytics.php')): ?>
		<a href="analytics.php"
		   class="nav-item <?= ($current_page == 'analytics.php') ? 'active' : '' ?>"
		   title="Analytics">
			<span class="icon">📈</span>
			<span class="nav-text">Analytics</span>
		</a>
		<?php endif; ?>

		<?php if (get_admin_role() === 'super admin'): ?>
		<a href="manage_permissions.php"
		   class="nav-item <?= ($current_page == 'manage_permissions.php') ? 'active' : '' ?>"
		   title="Permissions">
			<span class="icon">🔐</span>
			<span class="nav-text">Permissions</span>
		</a>
		<?php endif; ?>

		<!-- Admin Info & Logout Section -->
		<hr class="nav-separator">

		<div class="admin-info-section">
			<div class="admin-avatar" title="<?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>">
				<?= htmlspecialchars($adminInitial, ENT_QUOTES, 'UTF-8') ?>
			</div>
			<div class="admin-meta">
				<div class="admin-name">
					<?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?>
				</div>

				<?php if (!empty($adminEmail)) : ?>
					<div class="admin-email">
						<?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') ?>
					</div>
				<?php endif; ?>

				<div class="admin-role">
					<?= htmlspecialchars(ucwords($adminRole), ENT_QUOTES, 'UTF-8') ?>
				</div>
			</div>
		</div>

		<a href="javascript:void(0)" id="logoutLink" 
		   class="nav-item logout-item"
		   title="Logout"
		   onclick="document.getElementById('logoutForm').submit(); return false;">
			<span class="icon">🔒</span>
			<span class="nav-text">Logout</span>
		</a>

		<form id="logoutForm" method="POST" action="../auth/admin_logout.php" style="display:none;">
			<input type="hidden" name="csrf_token" 
				   value="<?php echo htmlspecialchars(function_exists('csrf_token') ? csrf_token() : '', ENT_QUOTES, 'UTF-8'); ?>">
		</form>

		<noscript>
			<form method="POST" action="../auth/admin_logout.php" style="margin:8px 0 0 0;">
				<input type="hidden" name="csrf_token" 
					   value="<?php echo htmlspecialchars(function_exists('csrf_token') ? csrf_token() : '', ENT_QUOTES, 'UTF-8'); ?>">
				<button type="submit" class="nav-item" style="background:none;border:none;padding:15px 25px;cursor:pointer;width:100%;text-align:left;color:#ecf0f1;">
					<span class="icon">🔒</span> <span class="nav-text">Logout</span>
				</button>
			</form>
		</noscript>
	</nav>
</aside>

<script>
// Toggle sidebar collapse/expand
function toggleSidebarCollapse() {
	const sidebar = document.getElementById('sidebar');
	const toggleIcon = document.querySelector('.toggle-icon');
	const isCollapsed = sidebar.classList.toggle('collapsed');
	
	// Update toggle icon
	toggleIcon.textContent = isCollapsed ? '▶' : '◀';
	
	// Save state to localStorage
	localStorage.setItem('sidebarCollapsed', isCollapsed);
}

// Mobile sidebar toggle (existing function)
function toggleSidebar() {
	const sidebar = document.getElementById('sidebar');
	sidebar.classList.toggle('mobile-open');
}

// Restore sidebar state on page load
document.addEventListener('DOMContentLoaded', function() {
	const sidebar = document.getElementById('sidebar');
	const toggleIcon = document.querySelector('.toggle-icon');
	const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
	
	if (isCollapsed) {
		sidebar.classList.add('collapsed');
		toggleIcon.textContent = '▶';
	}
});

// Close mobile sidebar when clicking outside
document.addEventListener('click', function(event) {
	const sidebar = document.getElementById('sidebar');
	const mobileBtn = document.querySelector('.mobile-menu-btn');
	
	if (window.innerWidth <= 768 && 
	    !sidebar.contains(event.target) && 
	    mobileBtn && !mobileBtn.contains(event.target) &&
	    sidebar.classList.contains('mobile-open')) {
		sidebar.classList.remove('mobile-open');
	}
});
</script>

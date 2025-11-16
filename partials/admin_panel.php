<?php 
// Make sure session is started somewhere before this file is included
// session_start();

$current_page = basename($_SERVER['PHP_SELF']); 

// Prepare admin data safely
$adminData   = $_SESSION['admin'] ?? [];
$adminName   = $adminData['username'] ?? $adminData['name'] ?? 'Admin';
$adminEmail  = $adminData['email'] ?? '';
$adminInitial = strtoupper(substr($adminName, 0, 1));
?>

<!-- You can move this <style> block into your main CSS file -->
<style>
	.sidebar .admin-info-section {
		padding: 12px;
		margin: 10px 12px 15px;
		border-radius: 8px;
		background: rgba(255, 255, 255, 0.05);
		display: flex;
		align-items: center;
		gap: 10px;
	}

	.sidebar .admin-avatar {
		width: 40px;
		height: 40px;
		border-radius: 50%;
		background: linear-gradient(135deg, #4e73df, #1cc88a);
		display: flex;
		align-items: center;
		justify-content: center;
		font-weight: 700;
		font-size: 18px;
		color: #fff;
		flex-shrink: 0;
	}

	.sidebar .admin-meta {
		text-align: left;
		overflow: hidden;
	}

	.sidebar .admin-name {
		color: #fff;
		font-size: 14px;
		font-weight: 600;
		white-space: nowrap;
		text-overflow: ellipsis;
		overflow: hidden;
	}

	.sidebar .admin-email {
		color: #aaa;
		font-size: 11px;
		white-space: nowrap;
		text-overflow: ellipsis;
		overflow: hidden;
	}

	.sidebar .admin-role {
		color: #7fd6a4;
		font-size: 11px;
		margin-top: 2px;
		text-transform: uppercase;
		letter-spacing: 0.05em;
	}
</style>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
	<div class="logo">
		<h2>Admin Panel</h2>
	</div>

	<nav class="nav">
		<a href="admin_dashboard.php" 
		   class="nav-item <?= ($current_page == 'admin_dashboard.php') ? 'active' : '' ?>">
			<span class="icon">📊</span><span>Dashboard</span>
		</a>

		<a href="manage_member.php" 
		   class="nav-item <?= ($current_page == 'manage_member.php') ? 'active' : '' ?>">
			<span class="icon">👥</span><span>Members</span>
		</a>

		<a href="manage_admin.php" 
		   class="nav-item <?= ($current_page == 'manage_admin.php') ? 'active' : '' ?>">
			<span class="icon">🧑‍💼</span><span>Admins</span>
		</a>

		<a href="manage_orders.php"
		   class="nav-item <?= ($current_page == 'manage_orders.php') ? 'active' : '' ?>">
			<span class="icon">🛒</span><span>Orders</span>
		</a>

		<a href="manage_product.php" 
		   class="nav-item <?= ($current_page == 'manage_product.php') ? 'active' : '' ?>">
			<span class="icon">📦</span><span>Products</span>
		</a>

		<a href="analytics.php"
		   class="nav-item <?= ($current_page == 'analytics.php') ? 'active' : '' ?>">
			<span class="icon">📈</span><span>Analytics</span>
		</a>

		<a href="settings.php"
		   class="nav-item <?= ($current_page == 'settings.php') ? 'active' : '' ?>">
			<span class="icon">⚙️</span><span>Settings</span>
		</a>

		<!-- Admin Info & Logout Section -->
		<hr style="margin:15px 0; border:none; border-top:1px solid #444;">

		<div class="admin-info-section">
			<div class="admin-avatar">
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
					Admin
				</div>
			</div>
		</div>

		<a href="javascript:void(0)" id="logoutLink" class="nav-item"
		   onclick="document.getElementById('logoutForm').submit(); return false;">
			<span class="icon">🔒</span><span>Logout</span>
		</a>

		<form id="logoutForm" method="POST" action="../auth/admin_logout.php" style="display:none;">
			<input type="hidden" name="csrf_token" 
				   value="<?php echo htmlspecialchars(function_exists('csrf_token') ? csrf_token() : '', ENT_QUOTES, 'UTF-8'); ?>">
		</form>

		<noscript>
			<form method="POST" action="../auth/admin_logout.php" style="margin:8px 0 0 0;">
				<input type="hidden" name="csrf_token" 
					   value="<?php echo htmlspecialchars(function_exists('csrf_token') ? csrf_token() : '', ENT_QUOTES, 'UTF-8'); ?>">
				<button type="submit" class="nav-item" style="background:none;border:none;padding:0;cursor:pointer;width:100%;text-align:left;">
					🔒 Logout
				</button>
			</form>
		</noscript>
	</nav>
</aside>

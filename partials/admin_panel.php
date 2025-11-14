<?php 
$current_page = basename($_SERVER['PHP_SELF']); 
?>
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
	</nav>
</aside>

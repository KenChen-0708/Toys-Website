<?php
session_start();

// Basic admin gate (reuse simulated login like dashboard)
if (!isset($_SESSION['admin_logged_in'])) {
	$_SESSION['admin_logged_in'] = true;
	$_SESSION['admin_name'] = 'Admin User';
}

// Include database connection
include '../helper/connection.php';

// Helpers
function sanitize_string($value) {
	return trim((string)$value);
}

function respond_redirect($location) {
	header("Location: {$location}");
	exit;
}

// Flash messages
if (!isset($_SESSION['flash'])) {
	$_SESSION['flash'] = [];
}
function flash_add($type, $message) {
	$_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}
function flash_take_all() {
	$all = $_SESSION['flash'] ?? [];
	$_SESSION['flash'] = [];
	return $all;
}

// CRUD actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$now = date('Y-m-d H:i:s');

if ($db_ok) {
	// Create
	if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
		$name = sanitize_string($_POST['name'] ?? '');
		$email = sanitize_string($_POST['email'] ?? '');
		$phone = sanitize_string($_POST['phone_number'] ?? '');
		$address = sanitize_string($_POST['address'] ?? '');
		$password_raw = $_POST['password'] ?? '';

		if ($name === '' || $email === '' || $password_raw === '') {
			flash_add('error', 'Name, Email and Password are required.');
		} else {
			$password_hashed = password_hash($password_raw, PASSWORD_DEFAULT);
			$stmt = $conn->prepare("INSERT INTO Member (name, password, email, phone_number, address, register_date) VALUES (?, ?, ?, ?, ?, ?)");
			if ($stmt) {
				$stmt->bind_param('ssssss', $name, $password_hashed, $email, $phone, $address, $now);
				if ($stmt->execute()) {
					flash_add('success', 'Member created successfully.');
				} else {
					flash_add('error', 'Failed to create member: ' . htmlspecialchars($conn->error));
				}
				$stmt->close();
			} else {
				flash_add('error', 'Failed to prepare statement.');
			}
		}
		respond_redirect('manage_member.php');
	}

	// Update
	if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
		$member_id = intval($_POST['member_id'] ?? 0);
		$name = sanitize_string($_POST['name'] ?? '');
		$email = sanitize_string($_POST['email'] ?? '');
		$phone = sanitize_string($_POST['phone_number'] ?? '');
		$address = sanitize_string($_POST['address'] ?? '');
		$password_raw = $_POST['password'] ?? '';

		if ($member_id <= 0 || $name === '' || $email === '') {
			flash_add('error', 'Member ID, Name and Email are required.');
		} else {
			if ($password_raw !== '') {
				$password_hashed = password_hash($password_raw, PASSWORD_DEFAULT);
				$stmt = $conn->prepare("UPDATE Member SET name=?, email=?, phone_number=?, address=?, password=? WHERE member_id=?");
				if ($stmt) {
					$stmt->bind_param('sssssi', $name, $email, $phone, $address, $password_hashed, $member_id);
					if ($stmt->execute()) {
						flash_add('success', 'Member updated successfully.');
					} else {
						flash_add('error', 'Failed to update member: ' . htmlspecialchars($conn->error));
					}
					$stmt->close();
				} else {
					flash_add('error', 'Failed to prepare statement.');
				}
			} else {
				$stmt = $conn->prepare("UPDATE Member SET name=?, email=?, phone_number=?, address=? WHERE member_id=?");
				if ($stmt) {
					$stmt->bind_param('ssssi', $name, $email, $phone, $address, $member_id);
					if ($stmt->execute()) {
						flash_add('success', 'Member updated successfully.');
					} else {
						flash_add('error', 'Failed to update member: ' . htmlspecialchars($conn->error));
					}
					$stmt->close();
				} else {
					flash_add('error', 'Failed to prepare statement.');
				}
			}
		}
		respond_redirect('manage_member.php');
	}

	// Delete
	if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
		$member_id = intval($_POST['member_id'] ?? 0);
		if ($member_id <= 0) {
			flash_add('error', 'Invalid member ID.');
		} else {
			$stmt = $conn->prepare("DELETE FROM Member WHERE member_id=?");
			if ($stmt) {
				$stmt->bind_param('i', $member_id);
				if ($stmt->execute()) {
					flash_add('success', 'Member deleted.');
				} else {
					flash_add('error', 'Failed to delete member: ' . htmlspecialchars($conn->error));
				}
				$stmt->close();
			} else {
				flash_add('error', 'Failed to prepare statement.');
			}
		}
		respond_redirect('manage_member.php');
	}
}

// List, search, pagination
$members = [];
$total_members = 0;
$q = sanitize_string($_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
// per_page: allow 10,25,50,100 or 'all'
$per_page_raw = sanitize_string($_GET['per_page'] ?? '10');
$per_page = 10;
if (in_array($per_page_raw, ['10','25','50','100'], true)) {
	$per_page = intval($per_page_raw);
} elseif (strtolower($per_page_raw) === 'all') {
	$per_page = -1; // sentinel for all
}
$total_pages = 1;
$offset = 0;

if ($db_ok) {
	// Count total (with optional search)
	if ($q !== '') {
		$like = '%' . $q . '%';
		$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM Member WHERE name LIKE ? OR email LIKE ? OR phone_number LIKE ?");
		if ($stmt) {
			$stmt->bind_param('sss', $like, $like, $like);
			if ($stmt->execute()) {
				$res = $stmt->get_result()->fetch_assoc();
				$total_members = intval($res['c'] ?? 0);
			}
			$stmt->close();
		}
	} else {
		$res = $conn->query("SELECT COUNT(*) AS c FROM Member");
		if ($res) {
			$row = $res->fetch_assoc();
			$total_members = intval($row['c'] ?? 0);
		}
	}
	if ($per_page > 0) {
		$total_pages = max(1, (int)ceil($total_members / $per_page));
		$offset = ($page - 1) * $per_page;
		if ($offset >= $total_members) {
			$page = 1;
			$offset = 0;
		}
	} else {
		// 'all' case
		$total_pages = 1;
		$page = 1;
		$offset = 0;
	}

	// Fetch paginated list
	if ($q !== '') {
		$like = '%' . $q . '%';
		if ($per_page > 0) {
			$stmt = $conn->prepare("
				SELECT member_id, name, email, phone_number, address, register_date
				FROM Member
				WHERE name LIKE ? OR email LIKE ? OR phone_number LIKE ?
				ORDER BY member_id DESC
				LIMIT ? OFFSET ?
			");
			if ($stmt) {
				$stmt->bind_param('sssii', $like, $like, $like, $per_page, $offset);
				if ($stmt->execute()) {
					$result = $stmt->get_result();
					while ($row = $result->fetch_assoc()) {
						$members[] = $row;
					}
				}
				$stmt->close();
			}
		} else {
			$stmt = $conn->prepare("
				SELECT member_id, name, email, phone_number, address, register_date
				FROM Member
				WHERE name LIKE ? OR email LIKE ? OR phone_number LIKE ?
				ORDER BY member_id DESC
			");
			if ($stmt) {
				$stmt->bind_param('sss', $like, $like, $like);
				if ($stmt->execute()) {
					$result = $stmt->get_result();
					while ($row = $result->fetch_assoc()) {
						$members[] = $row;
					}
				}
				$stmt->close();
			}
		}
	} else {
		if ($per_page > 0) {
			$stmt = $conn->prepare("
				SELECT member_id, name, email, phone_number, address, register_date
				FROM Member
				ORDER BY member_id DESC
				LIMIT ? OFFSET ?
			");
			if ($stmt) {
				$stmt->bind_param('ii', $per_page, $offset);
				if ($stmt->execute()) {
					$result = $stmt->get_result();
					while ($row = $result->fetch_assoc()) {
						$members[] = $row;
					}
				}
				$stmt->close();
			}
		} else {
			$result = $conn->query("
				SELECT member_id, name, email, phone_number, address, register_date
				FROM Member
				ORDER BY member_id DESC
			");
			if ($result) {
				while ($row = $result->fetch_assoc()) {
					$members[] = $row;
				}
			}
		}
	}
}

$flashes = flash_take_all();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Manage Members</title>
	<link rel="stylesheet" href="../assets/css/admin_panel.css">
	<link rel="stylesheet" href="../assets/css/admin_dashboard.css"> <!-- Overlap, need separte css file -->
	<link rel="stylesheet" href="../assets/css/manage_member.css">
</head>
<body>
	<?php include '../partials/admin_panel.php'; ?>
	<div class="main-content">
		<header class="header">
			<div class="header-left">
				<h1>Manage Members</h1>
				<p class="subtitle">View, create, update and delete members.</p>
			</div>
			<div class="header-right">
				<div class="user-info">
					<span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></span>
					<div class="avatar">A</div>
				</div>
			</div>
		</header>

		<div class="mm-container">
			<section class="mm-section" style="margin-top:0;">
				<div class="mm-stat-grid">
					<div class="mm-stat-card">
						<div class="mm-stat-icon">👥</div>
						<div>
							<div class="mm-stat-value"><?php echo number_format($total_members); ?></div>
							<div class="mm-stat-label">Total Members</div>
						</div>
					</div>
					<div class="mm-stat-card">
						<div class="mm-stat-icon">🆕</div>
						<div>
							<div class="mm-stat-value"><?php echo number_format(count($members)); ?></div>
							<div class="mm-stat-label">On This Page</div>
						</div>
					</div>
				</div>
			</section>
			<?php if (!$db_ok): ?>
				<div class="mm-flash error">Database connection failed. You can still view the page, but data is unavailable.</div>
			<?php endif; ?>

			<?php foreach ($flashes as $msg): ?>
				<div class="mm-flash <?php echo htmlspecialchars($msg['type']); ?>"><?php echo htmlspecialchars($msg['message']); ?></div>
			<?php endforeach; ?>

			<section class="mm-section">
				<div class="mm-page-header">
					<h2>Add New Member</h2>
				</div>
				<form method="post" action="manage_member.php">
					<input type="hidden" name="action" value="create">
					<div class="mm-form-grid">
						<div>
							<label>Name</label>
							<input class="mm-input" type="text" name="name" required>
						</div>
						<div>
							<label>Email</label>
							<input class="mm-input" type="email" name="email" required>
						</div>
						<div>
							<label>Password</label>
							<input class="mm-input" type="password" name="password" required>
						</div>
						<div>
							<label>Phone Number</label>
							<input class="mm-input" type="text" name="phone_number">
						</div>
						<div class="full">
							<label>Address</label>
							<textarea class="mm-textarea" name="address" rows="2"></textarea>
						</div>
					</div>
					<div style="margin-top: 12px;">
						<button class="mm-btn primary" type="submit" <?php echo !$db_ok ? 'disabled' : ''; ?>>Create Member</button>
						<a class="mm-btn light" href="admin-dashboard.php">Back to Dashboard</a>
					</div>
				</form>
			</section>

			<section class="mm-section">
				<div class="mm-page-header">
					<h2>Members</h2>
					<div class="mm-actions">
						<input id="mm-search" class="mm-input" type="search" placeholder="Search members..." value="<?php echo htmlspecialchars($q); ?>">
						<select id="mm-per-page" class="mm-select">
							<?php
								$opts = ['10'=>'10 / page','25'=>'25 / page','50'=>'50 / page','100'=>'100 / page','all'=>'All'];
								foreach ($opts as $key=>$label) {
									$sel = ((string)$per_page_raw === (string)$key) ? 'selected' : '';
									echo "<option value=\"{$key}\" {$sel}>{$label}</option>";
								}
							?>
						</select>
					</div>
				</div>
				<div class="mm-table-wrap">
					<table class="mm-table">
						<thead>
							<tr>
								<th>ID</th>
								<th>Name</th>
								<th>Email</th>
								<th>Phone</th>
								<th>Address</th>
								<th>Registered</th>
								<th>Actions</th>
							</tr>
						</thead>
						<tbody>
						<?php if (!$db_ok): ?>
							<tr><td colspan="7" style="text-align:center;">Data unavailable</td></tr>
						<?php elseif (empty($members)): ?>
							<tr><td colspan="7" style="text-align:center;">No members found</td></tr>
						<?php else: ?>
							<?php foreach ($members as $m): ?>
							<tr>
								<td><?php echo (int)$m['member_id']; ?></td>
								<td>
									<div class="mm-user">
										<div class="mm-avatar"><?php echo strtoupper(substr($m['name'], 0, 1)); ?></div>
										<div class="mm-user-meta">
											<div class="mm-user-name"><?php echo htmlspecialchars($m['name']); ?></div>
										</div>
									</div>
								</td>
								<td><?php echo htmlspecialchars($m['email']); ?></td>
								<td><?php echo htmlspecialchars($m['phone_number']); ?></td>
								<td><?php echo htmlspecialchars($m['address']); ?></td>
								<td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($m['register_date']))); ?></td>
								<td>
									<div class="mm-actions">
										<button class="mm-btn light" type="button" onclick="mmOpenEdit({ id: <?php echo (int)$m['member_id']; ?>, name: '<?php echo htmlspecialchars(addslashes($m['name'])); ?>', email: '<?php echo htmlspecialchars(addslashes($m['email'])); ?>', phone: '<?php echo htmlspecialchars(addslashes($m['phone_number'])); ?>', address: `<?php echo htmlspecialchars($m['address']); ?>` })">Edit</button>
										<form method="post" action="manage_member.php" data-confirm="Delete this member?">
											<input type="hidden" name="action" value="delete">
											<input type="hidden" name="member_id" value="<?php echo (int)$m['member_id']; ?>">
											<button class="mm-btn danger" type="submit" <?php echo !$db_ok ? 'disabled' : ''; ?>>Delete</button>
										</form>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
				<?php if ($db_ok): ?>
				<div class="mm-pagination">
					<?php
						$qs_parts = [];
						if ($q !== '') $qs_parts[] = 'q=' . urlencode($q);
						$qs_parts[] = 'per_page=' . urlencode($per_page_raw);
						$qs = $qs_parts ? '&' . implode('&', $qs_parts) : '';
						$prev = max(1, $page - 1);
						$next = min($total_pages, $page + 1);
					?>
					<a class="mm-page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="manage_member.php?page=<?php echo $prev . $qs; ?>">Prev</a>
					<span class="mm-page-status">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
					<a class="mm-page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="manage_member.php?page=<?php echo $next . $qs; ?>">Next</a>
				</div>
				<?php endif; ?>
			</section>
		</div>
	</div>

	<!-- Simple Edit Modal -->
	<div id="editModal" class="mm-modal-overlay">
		<div class="mm-modal">
			<h3>Edit Member</h3>
			<form method="post" action="manage_member.php">
				<input type="hidden" name="action" value="update">
				<input type="hidden" id="edit_member_id" name="member_id" value="">
				<div class="mm-form-grid">
					<div>
						<label>Name</label>
						<input class="mm-input" id="edit_name" type="text" name="name" required>
					</div>
					<div>
						<label>Email</label>
						<input class="mm-input" id="edit_email" type="email" name="email" required>
					</div>
					<div>
						<label>New Password (leave blank to keep)</label>
						<input class="mm-input" id="edit_password" type="password" name="password">
					</div>
					<div>
						<label>Phone Number</label>
						<input class="mm-input" id="edit_phone" type="text" name="phone_number">
					</div>
					<div class="full">
						<label>Address</label>
						<textarea class="mm-textarea" id="edit_address" name="address" rows="2"></textarea>
					</div>
				</div>
				<div class="mm-actions" style="margin-top:12px;">
					<button class="mm-btn primary" type="submit" <?php echo !$db_ok ? 'disabled' : ''; ?>>Save</button>
					<button class="mm-btn light" type="button" onclick="mmCloseEdit()">Cancel</button>
				</div>
			</form>
		</div>
	</div>

	<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
	<script src="assets/js/manage_member.js"></script>
</body>
</html>
<?php
if ($db_ok && $conn) {
	$conn->close();
}
?>


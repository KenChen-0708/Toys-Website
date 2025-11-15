<?php
session_start();
require_once '../helper/connection.php';
require_once '../helper/auth.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

$success_message = '';
$error_message = '';

// Check if Feedback table exists
$table_check = $conn->query("SHOW TABLES LIKE 'Feedback'");
if ($table_check && $table_check->num_rows == 0) {
    // Try lowercase
    $table_check = $conn->query("SHOW TABLES LIKE 'feedback'");
    if ($table_check && $table_check->num_rows == 0) {
        $error_message = 'Feedback table does not exist. Please run the SQL script to create it: /sql/create_feedback_table.sql';
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $feedback_id = intval($_POST['feedback_id']);
    $new_status = $_POST['status'];
    $admin_response = trim($_POST['admin_response'] ?? '');
    
    $sql = "UPDATE Feedback SET status = ?, admin_response = ?, updated_at = NOW() WHERE feedback_id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        $error_message = 'Database error: ' . $conn->error;
    } else {
        $stmt->bind_param('ssi', $new_status, $admin_response, $feedback_id);
        
        if ($stmt->execute()) {
            $success_message = 'Feedback updated successfully!';
        } else {
            $error_message = 'Failed to update feedback: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_feedback'])) {
    $feedback_id = intval($_POST['feedback_id']);
    
    $sql = "DELETE FROM Feedback WHERE feedback_id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        $error_message = 'Database error: ' . $conn->error;
    } else {
        $stmt->bind_param('i', $feedback_id);
        
        if ($stmt->execute()) {
            $success_message = 'Feedback deleted successfully!';
        } else {
            $error_message = 'Failed to delete feedback: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Filters
$status_filter = $_GET['status'] ?? 'all';
$category_filter = $_GET['category'] ?? 'all';
$rating_filter = $_GET['rating'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build query - Detect which Member table columns exist
$member_name_col = 'name'; // default
$member_columns_result = $conn->query("SHOW COLUMNS FROM Member");
if ($member_columns_result) {
    $member_columns = [];
    while ($col = $member_columns_result->fetch_assoc()) {
        $member_columns[] = $col['Field'];
    }
    
    // Check for common name column variations
    if (in_array('member_name', $member_columns)) {
        $member_name_col = 'member_name';
    } elseif (in_array('name', $member_columns)) {
        $member_name_col = 'name';
    } elseif (in_array('full_name', $member_columns)) {
        $member_name_col = 'full_name';
    } elseif (in_array('username', $member_columns)) {
        $member_name_col = 'username';
    }
    
    // Check for email column
    $member_email_col = 'email';
    if (in_array('member_email', $member_columns)) {
        $member_email_col = 'member_email';
    } elseif (!in_array('email', $member_columns) && in_array('email_address', $member_columns)) {
        $member_email_col = 'email_address';
    }
}

$sql = "SELECT f.*, m.{$member_name_col} as member_name, m.{$member_email_col} as member_email 
        FROM Feedback f 
        LEFT JOIN Member m ON f.member_id = m.member_id 
        WHERE 1=1";

$params = [];
$types = '';

if ($status_filter !== 'all') {
    $sql .= " AND f.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($category_filter !== 'all') {
    $sql .= " AND f.category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

if ($rating_filter !== 'all') {
    $sql .= " AND f.rating = ?";
    $params[] = intval($rating_filter);
    $types .= 'i';
}

if (!empty($search)) {
    $sql .= " AND (f.subject LIKE ? OR f.message LIKE ? OR m.{$member_name_col} LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$sql .= " ORDER BY f.created_at DESC";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    $error_message = 'Database query error: ' . $conn->error . '<br>Please make sure the Feedback table exists and is properly configured.';
    $feedback_list = null;
} else {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if ($stmt->execute()) {
        $feedback_list = $stmt->get_result();
    } else {
        $error_message = 'Error executing query: ' . $stmt->error;
        $feedback_list = null;
    }
}

// Get statistics
$stats = ['total' => 0, 'pending' => 0, 'reviewed' => 0, 'resolved' => 0, 'avg_rating' => 0];
$stats_sql = "SELECT 
    COUNT(*) as total,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'reviewed' THEN 1 END) as reviewed,
    COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved,
    COALESCE(AVG(rating), 0) as avg_rating
    FROM Feedback";
$stats_result = $conn->query($stats_sql);
if ($stats_result) {
    $stats = $stats_result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - Admin Panel</title>
    <link rel="stylesheet" href="../assets/css/feedback_management.css">


</head>
<body>
    <div class="admin-header">
        <h1>💬 Feedback Management</h1>
        <p>Manage and respond to customer feedback</p>
    </div>

    <div class="container">
        <?php if ($success_message): ?>
            <div class="alert alert-success">✓ <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">⚠ <?php echo $error_message; ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total Feedback</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['reviewed']; ?></div>
                <div class="stat-label">Reviewed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['resolved']; ?></div>
                <div class="stat-label">Resolved</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['avg_rating'], 1); ?>⭐</div>
                <div class="stat-label">Average Rating</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" class="filters-form">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search feedback..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="reviewed" <?php echo $status_filter === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                        <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Category</label>
                    <select name="category" class="form-control">
                        <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                        <option value="product_quality" <?php echo $category_filter === 'product_quality' ? 'selected' : ''; ?>>Product Quality</option>
                        <option value="customer_service" <?php echo $category_filter === 'customer_service' ? 'selected' : ''; ?>>Customer Service</option>
                        <option value="website" <?php echo $category_filter === 'website' ? 'selected' : ''; ?>>Website</option>
                        <option value="delivery" <?php echo $category_filter === 'delivery' ? 'selected' : ''; ?>>Delivery</option>
                        <option value="pricing" <?php echo $category_filter === 'pricing' ? 'selected' : ''; ?>>Pricing</option>
                        <option value="suggestion" <?php echo $category_filter === 'suggestion' ? 'selected' : ''; ?>>Suggestion</option>
                        <option value="complaint" <?php echo $category_filter === 'complaint' ? 'selected' : ''; ?>>Complaint</option>
                        <option value="other" <?php echo $category_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Rating</label>
                    <select name="rating" class="form-control">
                        <option value="all" <?php echo $rating_filter === 'all' ? 'selected' : ''; ?>>All Ratings</option>
                        <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Stars</option>
                        <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Stars</option>
                        <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Stars</option>
                        <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Stars</option>
                        <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Star</option>
                    </select>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>

                <div class="form-group">
                    <a href="feedback_management.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </form>
        </div>

        <!-- Feedback List -->
        <div class="feedback-table">
            <div class="table-header">
                <h2>Feedback List <?php echo ($feedback_list) ? '(' . $feedback_list->num_rows . ' items)' : ''; ?></h2>
            </div>

            <?php if ($feedback_list && $feedback_list->num_rows > 0): ?>
                <?php while ($feedback = $feedback_list->fetch_assoc()): ?>
                    <div class="feedback-item">
                        <div class="feedback-header">
                            <div class="feedback-title">
                                <h3><?php echo htmlspecialchars($feedback['subject']); ?></h3>
                                <div class="feedback-meta">
                                    <span>👤 <?php echo htmlspecialchars($feedback['member_name'] ?? 'Unknown'); ?></span>
                                    <span>📧 <?php echo htmlspecialchars($feedback['member_email'] ?? 'N/A'); ?></span>
                                    <span>📅 <?php echo date('M d, Y h:i A', strtotime($feedback['created_at'])); ?></span>
                                </div>
                            </div>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <span class="badge badge-<?php echo htmlspecialchars($feedback['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($feedback['status'])); ?>
                                </span>
                                <span class="badge badge-category">
                                    <?php echo htmlspecialchars(str_replace('_', ' ', ucwords($feedback['category'], '_'))); ?>
                                </span>
                                <span class="rating-stars">
                                    <?php echo str_repeat('⭐', $feedback['rating']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="feedback-message">
                            <strong>Customer Feedback:</strong><br>
                            <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                        </div>

                        <?php if (!empty($feedback['admin_response'])): ?>
                            <div class="admin-response-box">
                                <h4>✓ Admin Response:</h4>
                                <p><?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="feedback-actions">
                            <button class="expand-btn" onclick="toggleResponseForm(<?php echo $feedback['feedback_id']; ?>)">
                                💬 Respond & Update Status
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                                <input type="hidden" name="feedback_id" value="<?php echo $feedback['feedback_id']; ?>">
                                <button type="submit" name="delete_feedback" class="btn btn-danger">🗑️ Delete</button>
                            </form>
                        </div>

                        <div class="response-form" id="form-<?php echo $feedback['feedback_id']; ?>">
                            <form method="POST">
                                <input type="hidden" name="feedback_id" value="<?php echo $feedback['feedback_id']; ?>">
                                
                                <div class="form-group">
                                    <label>Admin Response</label>
                                    <textarea name="admin_response" placeholder="Type your response to the customer..."><?php echo htmlspecialchars($feedback['admin_response'] ?? ''); ?></textarea>
                                </div>

                                <div class="response-form-actions">
                                    <div class="form-group" style="flex: 1;">
                                        <label>Update Status</label>
                                        <select name="status" class="form-control" required>
                                            <option value="pending" <?php echo $feedback['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="reviewed" <?php echo $feedback['status'] === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                            <option value="resolved" <?php echo $feedback['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="update_status" class="btn btn-primary">💾 Update</button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-feedback">
                    <p style="font-size: 48px;">📭</p>
                    <h3>No Feedback Found</h3>
                    <p>
                        <?php if (strpos($error_message, 'table') !== false): ?>
                            Please create the Feedback table first by running: <br>
                            <code>/sql/create_feedback_table.sql</code>
                        <?php else: ?>
                            No feedback matches your current filters or no feedback has been submitted yet.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleResponseForm(feedbackId) {
            const form = document.getElementById('form-' + feedbackId);
            form.classList.toggle('active');
        }
    </script>
</body>
</html>

<?php
if (isset($stmt)) {
    $stmt->close();
}
if (isset($conn)) {
    $conn->close();
}
?>
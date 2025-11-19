<?php
session_start();
require_once 'helper/pdo_connection.php';
require_once 'helper/auth.php';
is_logged_in();

// Redirect if not logged in
if (!isset($_SESSION['member'])) {
    header('Location: login.php');
    exit;
}

$member_id = $_SESSION['member']['id'];
$member_name = $_SESSION['member']['name'] ?? 'Member';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $subject = trim($_POST['subject'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $rating = intval($_POST['rating'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($subject)) {
        $error_message = 'Please provide a subject for your feedback.';
    } elseif (empty($category)) {
        $error_message = 'Please select a feedback category.';
    } elseif ($rating < 1 || $rating > 5) {
        $error_message = 'Please provide a rating between 1 and 5.';
    } elseif (empty($message)) {
        $error_message = 'Please provide your feedback message.';
    } elseif (strlen($message) < 10) {
        $error_message = 'Feedback message must be at least 10 characters long.';
    } else {
        // Insert feedback into database
        try {
            $sql = "INSERT INTO Feedback (member_id, subject, category, rating, message, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
            
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$member_id, $subject, $category, $rating, $message])) {
                $success_message = 'Thank you for your feedback! We appreciate your input and will review it shortly.';
                // Clear form data
                $_POST = array();
            } else {
                $error_message = 'Failed to submit feedback. Please try again later.';
            }
        } catch (PDOException $e) {
            $error_message = 'Failed to submit feedback. Please try again later.';
        }
    }
}

// Fetch user's previous feedback
try {
    $feedback_history_sql = "SELECT * FROM Feedback WHERE member_id = ? ORDER BY created_at DESC LIMIT 10";
    $stmt = $pdo->prepare($feedback_history_sql);
    $stmt->execute([$member_id]);
    $feedback_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback_history = [];
}

$title = "Feedback";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Feedback - Evara</title>
    <link rel="stylesheet" href="assets/css/styles.css">

    <link rel="stylesheet" href="assets/css/feedback.css">

</head>
<body>
    <?php include 'partials/header.php'; ?>
    <div class="feedback-page">
        <div class="feedback-container">
            <!-- Page Header -->
            <div class="page-header">
                <h1>💬 We Value Your Feedback</h1>
                <p>Help us improve by sharing your thoughts and experiences</p>
            </div>

            <div class="content-grid">
                <!-- Feedback Form -->
                <div class="feedback-form-section">
                    <div class="form-header">
                        <h2>Submit Your Feedback</h2>
                        <p>Tell us what you think about our products and services</p>
                    </div>

                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <span>✓</span>
                            <span><?php echo htmlspecialchars($success_message); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-error">
                            <span>⚠</span>
                            <span><?php echo htmlspecialchars($error_message); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="subject">
                                Subject <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="subject" 
                                name="subject" 
                                class="form-control"
                                placeholder="Brief summary of your feedback"
                                value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>"
                                required
                            />
                        </div>

                        <div class="form-group">
                            <label for="category">
                                Category <span class="required">*</span>
                            </label>
                            <select id="category" name="category" class="form-control" required>
                                <option value="">Select a category</option>
                                <option value="product_quality" <?php echo (isset($_POST['category']) && $_POST['category'] === 'product_quality') ? 'selected' : ''; ?>>Product Quality</option>
                                <option value="customer_service" <?php echo (isset($_POST['category']) && $_POST['category'] === 'customer_service') ? 'selected' : ''; ?>>Customer Service</option>
                                <option value="website" <?php echo (isset($_POST['category']) && $_POST['category'] === 'website') ? 'selected' : ''; ?>>Website Experience</option>
                                <option value="delivery" <?php echo (isset($_POST['category']) && $_POST['category'] === 'delivery') ? 'selected' : ''; ?>>Delivery Service</option>
                                <option value="pricing" <?php echo (isset($_POST['category']) && $_POST['category'] === 'pricing') ? 'selected' : ''; ?>>Pricing</option>
                                <option value="suggestion" <?php echo (isset($_POST['category']) && $_POST['category'] === 'suggestion') ? 'selected' : ''; ?>>Suggestion</option>
                                <option value="complaint" <?php echo (isset($_POST['category']) && $_POST['category'] === 'complaint') ? 'selected' : ''; ?>>Complaint</option>
                                <option value="other" <?php echo (isset($_POST['category']) && $_POST['category'] === 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>
                                Overall Rating <span class="required">*</span>
                            </label>
                            <div style="display: flex; align-items: center;">
                                <div class="rating-group">
                                    <input type="radio" id="star5" name="rating" value="5" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 5) ? 'checked' : ''; ?> required />
                                    <label for="star5">⭐</label>
                                    <input type="radio" id="star4" name="rating" value="4" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 4) ? 'checked' : ''; ?> />
                                    <label for="star4">⭐</label>
                                    <input type="radio" id="star3" name="rating" value="3" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 3) ? 'checked' : ''; ?> />
                                    <label for="star3">⭐</label>
                                    <input type="radio" id="star2" name="rating" value="2" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 2) ? 'checked' : ''; ?> />
                                    <label for="star2">⭐</label>
                                    <input type="radio" id="star1" name="rating" value="1" <?php echo (isset($_POST['rating']) && $_POST['rating'] == 1) ? 'checked' : ''; ?> />
                                    <label for="star1">⭐</label>
                                </div>
                                <div class="rating-display" id="ratingDisplay">
                                    <?php 
                                    if (isset($_POST['rating'])) {
                                        echo htmlspecialchars($_POST['rating']) . ' Stars';
                                    } else {
                                        echo 'Select rating';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="message">
                                Your Feedback <span class="required">*</span>
                            </label>
                            <textarea 
                                id="message" 
                                name="message" 
                                class="form-control"
                                placeholder="Please share your detailed feedback here..."
                                required
                                maxlength="1000"
                                oninput="updateCharCount()"
                            ><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                            <div class="char-count">
                                <span id="charCount">0</span> / 1000 characters
                            </div>
                        </div>

                        <button type="submit" name="submit_feedback" class="btn btn-primary">
                            📤 Submit Feedback
                        </button>
                    </form>
                </div>

                <!-- Sidebar -->
                <div class="sidebar">
                    <!-- Info Card -->
                    <div class="info-card">
                        <h3>💡 Feedback Guidelines</h3>
                        <ul>
                            <li>Be specific and detailed</li>
                            <li>Include relevant order numbers if applicable</li>
                            <li>Keep feedback constructive</li>
                            <li>We review all feedback within 48 hours</li>
                            <li>Your privacy is protected</li>
                        </ul>
                    </div>

                    <!-- Feedback History -->
                    <div class="feedback-history">
                        <h3>📋 Your Recent Feedback</h3>
                        <?php if ($feedback_history && count($feedback_history) > 0): ?>
                            <?php foreach ($feedback_history as $history): ?>
                                <div class="history-item" onclick="toggleFeedback(this)">
                                    <div class="history-header">
                                        <span class="history-subject"><?php echo htmlspecialchars($history['subject']); ?></span>
                                        <span class="status-badge status-<?php echo htmlspecialchars($history['status']); ?>">
                                            <?php echo htmlspecialchars($history['status']); ?>
                                        </span>
                                    </div>
                                    <div class="history-date">
                                        <?php echo date('M d, Y', strtotime($history['created_at'])); ?>
                                    </div>
                                    <span class="history-category">
                                        <?php echo htmlspecialchars(str_replace('_', ' ', ucwords($history['category'], '_'))); ?>
                                    </span>
                                    <span class="history-rating">
                                        <?php echo htmlspecialchars($history['rating']); ?> ⭐
                                    </span>
                                    <div class="history-message">
                                        <?php echo htmlspecialchars($history['message']); ?>
                                    </div>
                                    <?php if (!empty($history['admin_response'])): ?>
                                        <div class="admin-response">
                                            <div class="admin-response-header">
                                                <span>Admin Response</span>
                                            </div>
                                            <div class="admin-response-text">
                                                <?php echo htmlspecialchars($history['admin_response']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-history">
                                <p>No feedback submitted yet</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Update character count
        function updateCharCount() {
            const textarea = document.getElementById('message');
            const charCount = document.getElementById('charCount');
            charCount.textContent = textarea.value.length;
        }

        // Initialize character count on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCharCount();
        });

        // Toggle feedback details
        function toggleFeedback(item) {
            item.classList.toggle('expanded');
        }

        // Update rating display
        document.querySelectorAll('.rating-group input[type="radio"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const ratingDisplay = document.getElementById('ratingDisplay');
                if (this.checked) {
                    ratingDisplay.textContent = this.value + ' Stars';
                    ratingDisplay.classList.add('active');
                } else {
                    ratingDisplay.textContent = 'Select rating';
                    ratingDisplay.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>

<?php include 'partials/footer.php'; ?>
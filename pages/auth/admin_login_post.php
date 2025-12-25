<?php
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./admin_login.php');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Invalid CSRF token');
}

// current failed attempts & whether captcha is required
$requireCaptcha = is_recaptcha_required('admin_failed_login_count', 3);

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$_SESSION['admin_old'] = ['username' => $username];
$errors = [];

/**
 * Handle Remember Me cookie for admin:
 * - If checked: always remember this username (even if login fails)
 * - If unchecked: always delete the cookie (even if login fails)
 */
if (!empty($_POST['remember'])) {
    // Set / update cookie for 30 days
    setcookie('remember_admin', $username, time() + 60 * 60 * 24 * 30, '/', '', false, true);
} else {
    // Remove cookie if exists
    if (isset($_COOKIE['remember_admin'])) {
        setcookie('remember_admin', '', time() - 3600, '/', '', false, true);
    }
}

// -------- reCAPTCHA SERVER VALIDATION --------
if ($requireCaptcha) {
    if (!verify_recaptcha($_POST['g-recaptcha-response'] ?? '')) {
        $errors[] = 'CAPTCHA verification failed. Please try again.';
    }
}

// Minimal validation with generic error messages
if (!$username) {
    $errors[] = 'Invalid admin username or password.';
}
if (strlen($password) < 6) {
    $errors[] = 'Invalid admin username or password.';
}

if (!$errors) {
    // Look up by Admin.username (your username column)
    $stmt = $pdo->prepare("SELECT admin_id, username, email, password, role FROM Admin WHERE BINARY username = ?");
    $stmt->execute([$username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    $valid = false;
    if ($admin && password_verify($password, $admin['password'])) {
        $valid = true;
    }

    if ($valid) {
        // successful login → reset failed counter
        unset($_SESSION['admin_failed_login_count']);

        // Store admin session separately from member
        $_SESSION['admin'] = [
            'id'       => $admin['admin_id'],
            'username' => $admin['username'],
            'email'    => $admin['email'],
            'role'     => $admin['role'] ?? 'admin',
        ];

        unset($_SESSION['admin_old']);
        header('Location: /admin/admin_dashboard.php');
        exit;
    } else {
        // Generic message to avoid user enumeration
        $errors[] = 'Invalid admin username or password.';
    }
}

// If we reached here, something failed (captcha and/or credentials)

// bump failed attempts counter
$_SESSION['admin_failed_login_count'] = $failedAttempts + 1;

$_SESSION['admin_errors'] = $errors;
header('Location: ./admin_login.php');
exit;

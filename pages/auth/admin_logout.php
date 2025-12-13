<?php
session_start();

require_once __DIR__ . '/../../helper/auth.php';

// Validate CSRF token
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !verify_csrf($_POST['csrf_token'] ?? '')
) {
    http_response_code(403);
    die('Invalid logout request.');
}

// Remove admin session ONLY (keep user session separate if any)
unset($_SESSION['admin']);
unset($_SESSION['admin_logged_in']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_email']);

// Optionally destroy entire session (recommended if admin sessions separate)
session_regenerate_id(true);

// Redirect to admin login page
header("Location: /pages/auth/admin_login.php");
exit;

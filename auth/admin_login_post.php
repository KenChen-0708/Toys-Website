<?php
require_once '../helper/connection.php';
require_once '../helper/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
  header('Location: ./admin_login.php'); 
  exit; 
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) { 
  http_response_code(403); 
  die('Invalid CSRF token'); 
}

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
    setcookie('remember_admin', $username, time() + 60*60*24*30, '/', '', false, true);
} else {
    // Remove cookie if exists
    if (isset($_COOKIE['remember_admin'])) {
        setcookie('remember_admin', '', time() - 3600, '/', '', false, true);
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
  // Look up by Admin.name (your username column)
  $stmt = $conn->prepare("SELECT admin_id, name, email, password FROM Admin WHERE BINARY name = ?");
  $stmt->bind_param('s', $username);
  $stmt->execute();
  $admin = $stmt->get_result()->fetch_assoc();

  $valid = false;
  if ($admin && password_verify($password, $admin['password'])) {
    $valid = true;
  }

  if ($valid) {
    // Store admin session separately from member
    $_SESSION['admin'] = [
      'id'    => $admin['admin_id'],
      'name'  => $admin['name'],
      'email' => $admin['email'],
    ];

    unset($_SESSION['admin_old']);
    header('Location: ../admin/admin_dashboard.php');
    exit;
  } else {
    // Generic message to avoid user enumeration
    $errors[] = 'Invalid admin username or password.';
  }
}

$_SESSION['admin_errors'] = $errors;
header('Location: ./admin_login.php');
exit;

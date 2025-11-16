<?php
require_once '../helper/pdo_connection.php';
require_once '../helper/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ./login.php');
  exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
  http_response_code(403);
  die('Invalid CSRF token');
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$_SESSION['old'] = ['username' => $username];
$errors = [];

/**
 * Handle Remember Me cookie
 * - If checked: always store current username (even if login fails)
 * - If unchecked: always remove cookie (even if login fails)
 */
if (!empty($_POST['remember'])) {
    // Set / update cookie for 30 days
    setcookie('remember_username', $username, time() + 60*60*24*30, '/', '', false, true);
} else {
    // Remove cookie if it exists
    if (isset($_COOKIE['remember_username'])) {
        setcookie('remember_username', '', time() - 3600, '/', '', false, true);
    }
}

/* Minimal validation */
if (!$username) {
  $errors[] = 'Invalid username or password.';
}
if (strlen($password) < 6) {
  $errors[] = 'Invalid username or password.';
}

if (!$errors) {
  $stmt = $pdo->prepare("
    SELECT member_id, username, email, password
    FROM Member
    WHERE BINARY username = ?
  ");
  $stmt->execute([$username]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($user && password_verify($password, $user['password'])) {

    $_SESSION['member'] = [
      'id'    => $user['member_id'],
      'username'  => $user['username'],
      'email' => $user['email'],
    ];

    unset($_SESSION['old']);
    header('Location: ../index.php');
    exit;
  }

  // Generic error message (prevents user enumeration)
  $errors[] = 'Invalid username or password.';
}

$_SESSION['errors'] = $errors;
header('Location: ./login.php');
exit;

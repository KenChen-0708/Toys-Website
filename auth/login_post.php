<?php
require_once '../helper/connection.php';
require_once '../helper/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ./login.php'); exit; }
if (!verify_csrf($_POST['csrf_token'] ?? '')) { http_response_code(403); die('Invalid CSRF token'); }

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$_SESSION['old'] = ['username' => $username];
$errors = [];

/* Validation: username min 3 */
if (strlen($username) < 3) $errors[] = 'Username must be at least 3 characters.';
/* Validation: password min 6 (matches your UI + security) */
if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

if (!$errors) {
  // Look up by Member.name (your username column)
  $stmt = $conn->prepare("SELECT member_id, name, email, password FROM Member WHERE BINARY name = ?");
  $stmt->bind_param('s', $username);
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();

  $valid = false;
  if ($user) {
    $hash = $user['password'];
    $valid = password_verify($password, $hash);
  }

  if ($valid) {
    $_SESSION['member'] = [
      'id' => $user['member_id'],
      'name' => $user['name'],
      'email' => $user['email'], // kept for convenience elsewhere
    ];

    if (!empty($_POST['remember'])) {
      setcookie('remember_username', $username, time()+60*60*24*30, '/', '', false, true);
    }

    unset($_SESSION['old']);
    header('Location: ../index.php'); exit;
  } else {
    $errors[] = 'Invalid username or password.';
  }
}

$_SESSION['errors'] = $errors;
header('Location: ./login.php'); exit;

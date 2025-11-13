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

/* Validation: username min 3 */
if (strlen($username) < 3) $errors[] = 'Username must be at least 3 characters.';
/* Validation: password min 6 */
if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

if (!$errors) {
  // Look up by Admin.username
  $stmt = $conn->prepare("SELECT admin_id, name, email, password FROM Admin WHERE BINARY name = ?");
  $stmt->bind_param('s', $username);
  $stmt->execute();
  $admin = $stmt->get_result()->fetch_assoc();

  $valid = false;
  if ($admin) {
    $hash = $admin['password'];

    // If your admin passwords are hashed with password_hash:
    // if (password_get_info($hash)['algo'] !== 0) {
    //   $valid = password_verify($password, $hash);
    // } else {
      // Legacy plaintext fallback (if you have old seed data)
      $valid = hash_equals($hash, $password);
    // }
  }

  if ($valid) {
    // Store admin session separately from member
    $_SESSION['admin'] = [
      'id'    => $admin['admin_id'],
      'name'  => $admin['name'],
      'email' => $admin['email'],
    ];

    // Optional: admin "remember me" (simple username cookie, or implement token like we discussed for members)
    if (!empty($_POST['remember'])) {
      setcookie('remember_admin', $username, time()+60*60*24*30, '/', '', false, true);
    }

    unset($_SESSION['admin_old']);
    header('Location: ../admin/admin_dashboard.php'); // change to your admin dashboard
    exit;
  } else {
    $errors[] = 'Invalid admin username or password.';
  }
}

$_SESSION['admin_errors'] = $errors;
header('Location: ./admin_login.php');
exit;

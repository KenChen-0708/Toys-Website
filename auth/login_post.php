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
  // Check if database connection is valid
  if (!isset($conn) || !$conn || ($conn->connect_error ?? false)) {
    $errors[] = 'Database connection error. Please try again later.';
  } else {
    // Look up by Member.username (your username column)
    $stmt = $conn->prepare("SELECT member_id, username, email, password FROM Member WHERE BINARY username = ?");
    
    // Check if prepare() succeeded
    if ($stmt === false) {
      $db_error = $conn->error ?? 'Unknown error';
      error_log("Login prepare error: " . $db_error);
      // For debugging - show actual error (remove in production)
      $errors[] = 'Database error: ' . htmlspecialchars($db_error) . '. Please check if the Member table exists.';
    } else {
      $stmt->bind_param('s', $username);
      if ($stmt->execute()) {
        $result = $stmt->get_result();
        $user = $result ? $result->fetch_assoc() : null;

        $valid = false;
        if ($user) {
          $hash = $user['password'];
          $valid = password_verify($password, $hash);
        }

        if ($valid) {
          $_SESSION['member'] = [
            'id' => $user['member_id'],
            'name' => $user['username'], // Using username as name for session
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
      } else {
        error_log("Login execute error: " . $stmt->error);
        $errors[] = 'Database error. Please try again later.';
      }
      
      $stmt->close();
    }
  }
}

$_SESSION['errors'] = $errors;
header('Location: ./login.php'); exit;

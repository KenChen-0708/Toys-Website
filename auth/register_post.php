<?php
require_once '../helper/connection.php';
require_once '../helper/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ./register.php'); exit; }
if (!verify_csrf($_POST['csrf_token'] ?? '')) { http_response_code(403); die('Invalid CSRF token'); }

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

$_SESSION['old'] = compact('username','email','phone','address');
$errors = [];

// Username 
if (strlen($username) < 3) {
    $errors[] = 'Username must be at least 3 characters.';
}else if (preg_match('/\s/', $username)) {
    $errors[] = 'Username cannot contain spaces. Use "_" instead.';
}else if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
    $errors[] = 'Username can only contain letters, numbers, and underscores.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
if ($password !== $password_confirm) $errors[] = 'Passwords do not match.';

if (!$errors) {
  // --- username (case-sensitive) --- 
  $checkName = $conn->prepare("SELECT 1 FROM Member WHERE BINARY username = ?");
  $checkName->bind_param('s', $username);
  $checkName->execute();
  if ($checkName->get_result()->fetch_row()) {
    $errors[] = 'This username is already taken.';
  }

  // --- email (case-insensitive by default collation, fine) ---
  $checkEmail = $conn->prepare("SELECT 1 FROM Member WHERE email = ?");
  $checkEmail->bind_param('s', $email);
  $checkEmail->execute();
  if ($checkEmail->get_result()->fetch_row()) {
    $errors[] = 'This email is already registered.';
  }

  if (!$errors) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("
      INSERT INTO Member (username, password, email, phone_number, address, register_date)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('ssssss', $username, $hash, $email, $phone, $address, $now);

    if ($stmt->execute()) {
      $member_id = $stmt->insert_id;
      $cart = $conn->prepare("INSERT INTO Cart (user_id) VALUES (?)");
      $cart->bind_param('i', $member_id);
      $cart->execute();

      unset($_SESSION['old']);
      $_SESSION['member'] = ['id'=>$member_id,'username'=>$username,'email'=>$email];
      header('Location: ../index.php'); exit;
    } else {
      if ($conn->errno === 1062) {
        // handles UNIQUE index collisions on username/email
        $errors[] = 'Username or email already exists.';
      } else {
        $errors[] = 'Something went wrong. Please try again.';
      }
    }
  }

}

$_SESSION['errors'] = $errors;
header('Location: ./register.php'); exit;

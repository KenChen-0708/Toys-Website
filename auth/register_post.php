<?php
require_once '../helper/pdo_connection.php';
require_once '../helper/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ./register.php'); exit; }
if (!verify_csrf($_POST['csrf_token'] ?? '')) { http_response_code(403); die('Invalid CSRF token'); }

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$phone = trim($_POST['phone'] ?? '');

// Address fields
$addressStreet = trim($_POST['address_street'] ?? '');
$addressState = trim($_POST['address_state'] ?? '');
$addressPostcode = trim($_POST['address_postcode'] ?? '');
$addressCountry = trim($_POST['address_country'] ?? 'Malaysia');
$addressCity = trim($_POST['address_city'] ?? '');

// Combine address fields into formatted string
$address = '';
$addressParts = [];
if ($addressStreet !== '') $addressParts[] = $addressStreet;
if ($addressCity !== '') $addressParts[] = $addressCity;
if ($addressState !== '' || $addressPostcode !== '') {
  $statePostcode = trim($addressState . ' ' . $addressPostcode);
  if ($statePostcode !== '') $addressParts[] = $statePostcode;
}
if ($addressCountry !== '') $addressParts[] = $addressCountry;

if (!empty($addressParts)) {
  $address = implode(', ', $addressParts);
}

$_SESSION['old'] = compact('username','email','phone','address_street','address_city','address_state','address_postcode','address_country');
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
  $checkName = $pdo->prepare("SELECT 1 FROM Member WHERE BINARY username = ?");
  $checkName->execute([$username]);
  if ($checkName->fetch()) {
    $errors[] = 'This username is already taken.';
  }

  // --- email (case-insensitive by default collation, fine) ---
  $checkEmail = $pdo->prepare("SELECT 1 FROM Member WHERE email = ?");
  $checkEmail->execute([$email]);
  if ($checkEmail->fetch()) {
    $errors[] = 'This email is already registered.';
  }

  if (!$errors) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = date('Y-m-d H:i:s');
    
    // Convert empty strings to NULL for optional fields
    $phoneValue = ($phone !== '') ? $phone : null;
    $addressValue = ($address !== '') ? $address : null;
    
    try {
      $stmt = $pdo->prepare("
        INSERT INTO Member (username, password, email, phone_number, address, register_date)
        VALUES (?, ?, ?, ?, ?, ?)
      ");
      $stmt->execute([$username, $hash, $email, $phoneValue, $addressValue, $now]);

      $member_id = $pdo->lastInsertId();
      $cart = $pdo->prepare("INSERT INTO Cart (member_id) VALUES (?)");
      $cart->execute([$member_id]);

      unset($_SESSION['old']);
      $_SESSION['member'] = ['id'=>$member_id,'username'=>$username,'email'=>$email];
      header('Location: ../index.php'); exit;
    } catch (PDOException $e) {
      if ($e->getCode() == 23000) {
        // handles UNIQUE index collisions on username/email
        $errors[] = 'Username or email already exists.';
      } else {
        // Log the actual error for debugging (remove in production or use proper logging)
        error_log('Registration error: ' . $e->getMessage() . ' | Code: ' . $e->getCode());
        $errors[] = 'Something went wrong. Please try again. Error: ' . $e->getMessage();
      }
    }
  }

}

$_SESSION['errors'] = $errors;
header('Location: ./register.php'); exit;

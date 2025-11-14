<?php
session_start();
require_once '../helper/connection.php';
require_once '../helper/auth.php';

// Ensure guest only
require_guest();

// Validate CSRF
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    $_SESSION['errors'] = ["Invalid CSRF token."];
    header("Location: ./reset_password.php");
    exit;
}

// Collect POST data
$email = trim($_POST['email'] ?? '');
$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirmation = $_POST['password_confirmation'] ?? '';

$errors = [];

// Basic validation
if (!$email || !$token) {
    $errors[] = "Invalid password reset link.";
}

if (strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters.";
}

if ($password !== $password_confirmation) {
    $errors[] = "Passwords do not match.";
}

// Stop if validation failed
if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    header("Location: ./reset_password.php?email=" . urlencode($email) . "&token=" . urlencode($token));
    exit;
}

// Step 1: Hash the raw token from URL before checking
$tokenHash = hash('sha256', $token);

$stmt = $conn->prepare("
    SELECT * FROM password_resets 
    WHERE email = ? AND token = ?
    LIMIT 1
");
$stmt->bind_param("ss", $email, $tokenHash);
$stmt->execute();
$result = $stmt->get_result();
$reset = $result->fetch_assoc();


if (!$reset) {
    $_SESSION['errors'] = ["Invalid or expired password reset token."];
    header("Location: ./reset_password.php?email=" . urlencode($email) . "&token=" . urlencode($token));
    exit;
}

// Step 2: Check if token expired
$current_time = date("Y-m-d H:i:s");
if ($current_time > $reset['expires_at']) {
    $_SESSION['errors'] = ["This password reset link has expired."];
    header("Location: ./reset_password.php?email=" . urlencode($email) . "&token=" . urlencode($token));
    exit;
}

// Step 3: Hash new password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Step 4: Update Member table
$updateMember = $conn->prepare("
    UPDATE Member SET password = ? WHERE email = ?
");
$updateMember->bind_param("ss", $hashed_password, $email);
$updateMember->execute();

// Step 5: Delete token after use
$deleteToken = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
$deleteToken->bind_param("s", $email);
$deleteToken->execute();

// Step 6: Redirect with success message
$_SESSION['success'] = "Your password has been successfully updated. Please log in.";

header("Location: ../auth/login.php");
exit;

?>

<?php
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';

// Ensure guest only
require_guest();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./reset_password.php');
    exit;
}

// Validate CSRF using the same helper as login_post.php
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Invalid CSRF token');
}

// Collect POST data
$email = trim($_POST['email'] ?? '');
$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirmation = $_POST['password_confirmation'] ?? '';

$errors = [];

// Password format pattern: at least 8 chars, one upper, one lower, one digit, one symbol
$passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$/';

// Basic validation
if (!$email || !$token) {
    $errors[] = "Invalid password reset link.";
}

if (!preg_match($passwordPattern, $password)) {
    $errors[] = "Password must be at least 8 characters and include uppercase, lowercase, number and symbol.";
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

// Step 1: Hash the raw token from URL before checking (must match stored hash)
$tokenHash = hash('sha256', $token);

$stmt = $pdo->prepare("
    SELECT * FROM password_resets 
    WHERE email = ? AND token = ?
    LIMIT 1
");
$stmt->execute([$email, $tokenHash]);
$reset = $stmt->fetch(PDO::FETCH_ASSOC);

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

// Step 4: Update Member table (by email)
$updateMember = $pdo->prepare("
    UPDATE Member SET password = ? WHERE email = ?
");
$updateMember->execute([$hashed_password, $email]);

// Step 5: Delete token after use
$deleteToken = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
$deleteToken->execute([$email]);

// Step 6: Redirect with success message to login page
$_SESSION['success'] = "Your password has been successfully updated. Please log in.";

header("Location: /pages/auth/login.php");
exit;

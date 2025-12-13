<?php
require_once __DIR__ . '/../../helper/pdo_connection.php';
require_once __DIR__ . '/../../helper/auth.php';
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /accounts.php');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

$memberId          = $_SESSION['member']['id'];
$currentPassword   = $_POST['current_password'] ?? '';
$newPassword       = $_POST['new_password'] ?? '';
$newPasswordConfirm = $_POST['new_password_confirm'] ?? '';

$errors = [];

$passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$/';

// Basic validations
if (strlen($currentPassword) < 6) {
    $errors[] = 'Current password is required.';
}

if (!preg_match($passwordPattern, $newPassword)) {
    $errors[] = 'New password must be at least 8 characters and include uppercase, lowercase, number and symbol.';
}

if ($newPassword !== $newPasswordConfirm) {
    $errors[] = 'New password and confirmation do not match.';
}

if ($errors) {
    $_SESSION['password_errors'] = $errors;
    header('Location: /accounts.php#change-password');
    exit;
}

// Fetch current password hash
$stmt = $pdo->prepare("SELECT password FROM Member WHERE member_id = ?");
$stmt->execute([$memberId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    $_SESSION['password_errors'] = ['Account not found.'];
    header('Location: /accounts.php#change-password');
    exit;
}

$dbPassword = $row['password'];

// Verify current password
if (!password_verify($currentPassword, $dbPassword)) {
    $_SESSION['password_errors'] = ['Current password is incorrect.'];
    header('Location: /accounts.php#change-password');
    exit;
}

$newHash = password_hash($newPassword, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE Member SET password = ? WHERE member_id = ?");
$stmt->execute([$newHash, $memberId]);

$_SESSION['password_success'] = 'Your password has been updated successfully.';

header('Location: /accounts.php#change-password');
exit;

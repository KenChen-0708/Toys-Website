<?php
require_once __DIR__ . '/../../helper/pdo_connection.php';
require_once __DIR__ . '/../../helper/auth.php';
require_once __DIR__ . '/../../helper/mailer.php';
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
$otpCode           = trim($_POST['otp_code'] ?? '');

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

// Require OTP for password changes
$otpSession = $_SESSION['otp_password'] ?? null;
$now = time();
$validOtp = ($otpSession && isset($otpSession['code'], $otpSession['expires']) && $otpCode !== '' && $otpCode === $otpSession['code'] && $now <= (int)$otpSession['expires']);

if (!$validOtp) {
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $_SESSION['otp_password'] = [
        'code' => $code,
        'expires' => $now + 600,
        'new_password' => $newPassword,
    ];

    $sendToEmail = $_SESSION['member']['email'] ?? '';
    $sendToName  = $_SESSION['member']['username'] ?? '';
    $subject = 'Verify Your Password Change';
    $htmlBody = '<p>Your verification code is: <strong>' . htmlspecialchars($code) . '</strong></p>' .
                '<p>This code expires in 10 minutes. Enter it on the Change Password page to confirm.</p>';
    $sent = @send_app_mail($sendToEmail, $sendToName, $subject, $htmlBody);

    if (!$sent) {
        $_SESSION['password_errors'] = ['Unable to send verification email. Please confirm your email address or try again later.'];
        header('Location: /accounts.php#change-password');
        exit;
    }

    $_SESSION['password_errors'] = ['We sent a verification code to your email. Enter the OTP to confirm your password change.'];
    $_SESSION['otp_required_password'] = true;
    header('Location: /accounts.php#change-password');
    exit;
}

$newHash = password_hash($newPassword, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE Member SET password = ? WHERE member_id = ?");
$stmt->execute([$newHash, $memberId]);

unset($_SESSION['otp_password'], $_SESSION['otp_required_password']);

$_SESSION['password_success'] = 'Your password has been updated successfully.';

header('Location: /accounts.php#change-password');
exit;

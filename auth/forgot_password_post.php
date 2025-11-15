<?php
session_start();

require_once '../helper/connection.php';
require_once '../helper/auth.php';

// PHPMailer (manual include)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';

// CSRF check
if (!isset($_POST['csrf_token']) || !hash_equals(csrf_token(), $_POST['csrf_token'])) {
    $_SESSION['errors'] = ['Invalid request. Please try again.'];
    header('Location: ./forgot_password.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$errors = [];

// Basic validation
if (!$email) {
    $errors[] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

if ($errors) {
    $_SESSION['errors'] = $errors;
    header('Location: ./forgot_password.php');
    exit;
}

// Find user by email
$sql = "SELECT member_id AS id, username AS username, email FROM member WHERE email = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    die("SQL error: " . $conn->error); // dev debug
}
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    $_SESSION['success'] = 'If the email <strong>' . htmlspecialchars($email) . '</strong> is registered, a reset link has been sent.';
    header('Location: ./forgot_password.php');
    exit;
}

// Generate reset token
$rawToken  = bin2hex(random_bytes(32)); // 64 chars
$tokenHash = hash('sha256', $rawToken);
$expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

// Delete old tokens for this email
$del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
$del->bind_param('s', $user['email']);
$del->execute();
$del->close();

// Insert new reset token
$stmt = $conn->prepare("
    INSERT INTO password_resets (email, token, expires_at)
    VALUES (?, ?, ?)
");
$stmt->bind_param('sss', $user['email'], $tokenHash, $expiresAt);
$stmt->execute();

// Build reset link
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host   = $_SERVER['HTTP_HOST']; // includes port automatically

$baseUrl = $scheme . '://' . $host . '/auth';
$resetLink = $baseUrl . '/reset_password.php?token=' . urlencode($rawToken) .
             '&email=' . urlencode($user['email']);

// Send email
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'kaijian1003@gmail.com';
    $mail->Password   = 'joxs kmae bila jdnh'; // app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('kaijian1003@gmail.com', 'Online Shore');
    $mail->addAddress($user['email'], $user['username']);

    $mail->isHTML(true);
    $mail->Subject = 'Reset your password';
    $mail->Body    = '
      <p>Hi ' . htmlspecialchars($user['username']) . ',</p>
      <p>You requested a password reset for your account. Click the button below to create a new password:</p>
      <p>
        <a href="' . htmlspecialchars($resetLink) . '" 
           style="display:inline-block;padding:10px 18px;background:#111827;color:#ffffff;text-decoration:none;border-radius:999px;">
          Reset Password
        </a>
      </p>
      <p>Or copy and paste this link into your browser:</p>
      <p><a href="' . htmlspecialchars($resetLink) . '">' . htmlspecialchars($resetLink) . '</a></p>
      <p><small>This link will expire in 1 hour. If you did not request this, you can safely ignore this email.</small></p>
    ';

    $mail->AltBody = "Hi {$user['username']},\n\n"
        . "To reset your password, open this link:\n{$resetLink}\n\n"
        . "This link will expire in 1 hour. If you did not request this, you can ignore this email.";

    $mail->send();

    $_SESSION['success'] = 'If the email <strong>' . htmlspecialchars($email) . '</strong> is registered, a reset link has been sent.';
} catch (Exception $e) {
    $_SESSION['errors'] = ['We could not send the reset email. Please try again later.'];
}

header('Location: ./forgot_password.php');
exit;

<?php
session_start();

require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';
require_once '../../helper/mailer.php';

// CSRF check
if (!isset($_POST['csrf_token']) || !hash_equals(csrf_token(), $_POST['csrf_token'])) {
    $_SESSION['errors'] = ['Invalid request. Please try again.'];
    header('Location: ./forgot_password.php');
    exit;
}

// reCAPTCHA verification
$recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
if (!verify_recaptcha($recaptcha_response)) {
    $_SESSION['errors'] = ['Please complete the CAPTCHA verification.'];
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
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("SQL error: " . $e->getMessage()); // dev debug
}

// If user not found, still show generic success (no user enumeration)
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
$del = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
$del->execute([$user['email']]);

// Insert new reset token
$stmt = $pdo->prepare("
    INSERT INTO password_resets (email, token, expires_at)
    VALUES (?, ?, ?)
");
$stmt->execute([$user['email'], $tokenHash, $expiresAt]);

// Build reset link
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host   = $_SERVER['HTTP_HOST']; // includes port automatically

$baseUrl   = $scheme . '://' . $host . '/pages/auth';
$resetLink = $baseUrl . '/reset_password.php?token=' . urlencode($rawToken) .
    '&email=' . urlencode($user['email']);

// Build email content
$usernameEsc = htmlspecialchars($user['username']);
$resetLinkEsc = htmlspecialchars($resetLink);

$htmlBody = '
  <p>Hi ' . $usernameEsc . ',</p>
  <p>You requested a password reset for your account. Click the button below to create a new password:</p>
  <p>
    <a href="' . $resetLinkEsc . '" 
       style="display:inline-block;padding:10px 18px;background:#111827;color:#ffffff;text-decoration:none;border-radius:999px;">
      Reset Password
    </a>
  </p>
  <p>Or copy and paste this link into your browser:</p>
  <p><a href="' . $resetLinkEsc . '">' . $resetLinkEsc . '</a></p>
  <p><small>This link will expire in 1 hour. If you did not request this, you can safely ignore this email.</small></p>
';

$altBody = "Hi {$user['username']},\n\n"
    . "You requested a password reset for your account.\n\n"
    . "To reset your password, open this link:\n{$resetLink}\n\n"
    . "This link will expire in 1 hour. If you did not request this, you can ignore this email.";

// Send email via reusable function
$sent = send_app_mail(
    $user['email'],
    $user['username'],
    'Reset your password',
    $htmlBody,
    $altBody
);

if ($sent) {
    $_SESSION['success'] = 'If the email <strong>' . htmlspecialchars($email) . '</strong> is registered, a reset link has been sent.';
} else {
    $_SESSION['errors'] = ['We could not send the reset email. Please try again later.'];
}

header('Location: ./forgot_password.php');
exit;

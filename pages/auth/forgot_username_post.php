<?php
session_start();

require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';
require_once '../../helper/mailer.php';

// CSRF check
if (!isset($_POST['csrf_token']) || !hash_equals(csrf_token(), $_POST['csrf_token'])) {
    $_SESSION['errors'] = ['Invalid request. Please try again.'];
    header('Location: ./forgot_username.php');
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
    header('Location: ./forgot_username.php');
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

// If not found, still show generic success (no user enumeration)
if (!$user) {
    $_SESSION['success'] = 'If the email <strong>' . htmlspecialchars($email) . '</strong> is registered, the username has been emailed.';
    header('Location: ./forgot_username.php');
    exit;
}

// Build email content
$usernameEsc = htmlspecialchars($user['username']);

$htmlBody = '
  <p>Hi ' . $usernameEsc . ',</p>
  <p>You requested a reminder of your username for your Toy World account.</p>
  <p>Your username is: <strong>' . $usernameEsc . '</strong></p>
  <p>If you did not request this, you can safely ignore this email.</p>
';

$altBody = "Hi {$user['username']},\n\n"
    . "You requested a reminder of your Toy World username.\n\n"
    . "Your username is: {$user['username']}\n\n"
    . "If you did not request this, you can ignore this email.";

// Send email via reusable function
$sent = send_app_mail(
    $user['email'],
    $user['username'],
    'Your Toy World username',
    $htmlBody,
    $altBody
);

if ($sent) {
    $_SESSION['success'] = 'If the email <strong>' . htmlspecialchars($email) . '</strong> is registered, the username has been emailed.';
} else {
    $_SESSION['errors'] = ['We could not send the username email. Please try again later.'];
}

header('Location: ./forgot_username.php');
exit;

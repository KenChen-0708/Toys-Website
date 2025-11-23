<?php
session_start();

require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';

// PHPMailer (manual include)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../../phpmailer/src/Exception.php';
require_once __DIR__ . '/../../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../../phpmailer/src/SMTP.php';

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

// Send username email
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
    $mail->Subject = 'Your Online Shore username';
    $mail->Body    = '
      <p>Hi ' . htmlspecialchars($user['username']) . ',</p>
      <p>You requested a reminder of your username for your Online Shore account.</p>
      <p>Your username is: <strong>' . htmlspecialchars($user['username']) . '</strong></p>
      <p>If you did not request this, you can safely ignore this email.</p>
    ';

    $mail->AltBody = "Hi {$user['username']},\n\n"
        . "You requested a reminder of your Online Shore username.\n\n"
        . "Your username is: {$user['username']}\n\n"
        . "If you did not request this, you can ignore this email.";

    $mail->send();

    $_SESSION['success'] = 'If the email <strong>' . htmlspecialchars($email) . '</strong> is registered, the username has been emailed.';
} catch (Exception $e) {
    $_SESSION['errors'] = ['We could not send the username email. Please try again later.'];
}

header('Location: ./forgot_username.php');
exit;

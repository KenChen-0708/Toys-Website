<?php
/**
 * Profile Update POST Handler
 * Handles profile information updates (name, email, phone, address)
 * Uses PDO as per assignment requirements
 */

require_once '../helper/pdo_connection.php';
require_once '../helper/auth.php';

// Require authentication
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../accounts.php');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Invalid CSRF token');
}

$member_id = $_SESSION['member']['id'] ?? null;
if (!$member_id) {
    header('Location: ../auth/login.php');
    exit;
}

// Get form data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone_number = trim($_POST['phone_number'] ?? '');
$address = trim($_POST['address'] ?? '');

// Store old values for form repopulation
$_SESSION['old_profile'] = compact('name', 'email', 'phone_number', 'address');
$errors = [];

// Validation: Username
if (strlen($name) < 3) {
    $errors[] = 'Username must be at least 3 characters.';
} elseif (preg_match('/\s/', $name)) {
    $errors[] = 'Username cannot contain spaces. Use "_" instead.';
} elseif (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
    $errors[] = 'Username can only contain letters, numbers, and underscores.';
}

// Validation: Email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address.';
}

// If no errors, proceed with update
if (empty($errors) && $db_ok && $pdo) {
    try {
        $pdo->beginTransaction();

        // Check if username is already taken by another user
        $stmt_check_name = $pdo->prepare("SELECT member_id FROM Member WHERE BINARY username = ? AND member_id != ?");
        $stmt_check_name->execute([$name, $member_id]);
        if ($stmt_check_name->fetch()) {
            $errors[] = 'This username is already taken.';
        }

        // Check if email is already taken by another user
        $stmt_check_email = $pdo->prepare("SELECT member_id FROM Member WHERE email = ? AND member_id != ?");
        $stmt_check_email->execute([$email, $member_id]);
        if ($stmt_check_email->fetch()) {
            $errors[] = 'This email is already registered.';
        }

        if (empty($errors)) {
            // Update profile
            $stmt_update = $pdo->prepare("
                UPDATE Member 
                SET username = ?, email = ?, phone_number = ?, address = ?
                WHERE member_id = ?
            ");
            $stmt_update->execute([$name, $email, $phone_number, $address, $member_id]);

            // Update session
            $_SESSION['member']['name'] = $name;
            $_SESSION['member']['email'] = $email;

            $pdo->commit();
            unset($_SESSION['old_profile']);
            $_SESSION['success_message'] = 'Profile updated successfully!';
            header('Location: ../accounts.php?tab=update-profile');
            exit;
        } else {
            $pdo->rollBack();
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = 'Something went wrong. Please try again.';
        // Log error for debugging (not shown to user)
        error_log("Profile update error: " . $e->getMessage());
    }
} elseif (!$db_ok) {
    $errors[] = 'Database connection failed. Please try again later.';
}

// If there are errors, redirect back with errors
$_SESSION['profile_errors'] = $errors;
header('Location: ../accounts.php?tab=update-profile');
exit;


<?php
/**
 * Password Update POST Handler
 * Handles password changes
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
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

$errors = [];

// Validation
if (empty($current_password)) {
    $errors[] = 'Current password is required.';
}

if (strlen($new_password) < 6) {
    $errors[] = 'New password must be at least 6 characters.';
}

if ($new_password !== $confirm_password) {
    $errors[] = 'New password and confirm password do not match.';
}

// If no errors, proceed with password update
if (empty($errors) && $db_ok && $pdo) {
    try {
        // Get current password hash
        $stmt_get = $pdo->prepare("SELECT password FROM Member WHERE member_id = ?");
        $stmt_get->execute([$member_id]);
        $user = $stmt_get->fetch();

        if (!$user) {
            $errors[] = 'User not found.';
        } else {
            // Verify current password
            if (!password_verify($current_password, $user['password'])) {
                $errors[] = 'Current password is incorrect.';
            } else {
                // Update password
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update = $pdo->prepare("UPDATE Member SET password = ? WHERE member_id = ?");
                $stmt_update->execute([$new_hash, $member_id]);

                $_SESSION['success_message'] = 'Password updated successfully!';
                header('Location: ../accounts.php?tab=change-password');
                exit;
            }
        }
    } catch (PDOException $e) {
        $errors[] = 'Something went wrong. Please try again.';
        // Log error for debugging (not shown to user)
        error_log("Password update error: " . $e->getMessage());
    }
} elseif (!$db_ok) {
    $errors[] = 'Database connection failed. Please try again later.';
}

// If there are errors, redirect back with errors
$_SESSION['password_errors'] = $errors;
header('Location: ../accounts.php?tab=change-password');
exit;


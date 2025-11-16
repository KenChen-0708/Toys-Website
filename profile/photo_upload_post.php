<?php
/**
 * Profile Photo Upload POST Handler
 * Handles profile photo uploads
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

$errors = [];

// Check if file was uploaded
if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Please select a photo to upload.';
} else {
    $file = $_FILES['profile_photo'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        $errors[] = 'Invalid file type. Please upload a JPEG, PNG, or GIF image.';
    }
    
    // Validate file size (max 5MB)
    $max_size = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $max_size) {
        $errors[] = 'File size exceeds 5MB. Please upload a smaller image.';
    }
    
    // If no errors, process upload
    if (empty($errors) && $db_ok && $pdo) {
        try {
            // Create profiles directory if it doesn't exist
            $upload_dir = __DIR__ . '/../assets/img/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = 'profile_' . $member_id . '_' . uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // Get old photo path to delete it later
                $stmt_get = $pdo->prepare("SELECT profile_photo_path FROM Member WHERE member_id = ?");
                $stmt_get->execute([$member_id]);
                $old_photo = $stmt_get->fetchColumn();
                
                // Update database with relative path
                $relative_path = 'assets/img/profiles/' . $new_filename;
                $stmt_update = $pdo->prepare("UPDATE Member SET profile_photo_path = ? WHERE member_id = ?");
                $stmt_update->execute([$relative_path, $member_id]);
                
                // Delete old photo if it exists
                if ($old_photo && file_exists(__DIR__ . '/../' . $old_photo)) {
                    @unlink(__DIR__ . '/../' . $old_photo);
                }
                
                $_SESSION['success_message'] = 'Profile photo updated successfully!';
                header('Location: ../accounts.php?tab=update-profile');
                exit;
            } else {
                $errors[] = 'Failed to upload file. Please try again.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Something went wrong. Please try again.';
            // Log error for debugging (not shown to user)
            error_log("Photo upload error: " . $e->getMessage());
        }
    } elseif (!$db_ok) {
        $errors[] = 'Database connection failed. Please try again later.';
    }
}

// If there are errors, redirect back with errors
$_SESSION['photo_errors'] = $errors;
header('Location: ../accounts.php?tab=update-profile');
exit;


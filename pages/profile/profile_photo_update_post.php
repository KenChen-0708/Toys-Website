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

$memberId = $_SESSION['member']['id'];
$captureData = $_POST['capture_data'] ?? '';
$errors = [];

// Fetch current member to know existing photo path
$stmt = $pdo->prepare("SELECT profile_photo_path FROM Member WHERE member_id = ?");
$stmt->execute([$memberId]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$current) {
    $errors[] = 'Account not found.';
}

$newPhotoPath = $current['profile_photo_path'] ?? null;

// Handle webcam capture (base64) or normal file upload (one of them)
if (!$errors) {
    $uploadDirFs = realpath(__DIR__ . '/../../assets/img');
    if ($uploadDirFs === false) {
        $uploadDirFs = __DIR__ . '/../../assets/img';
    }
    $profileDirFs = $uploadDirFs . '/profile_photo';

    if (!is_dir($profileDirFs)) {
        mkdir($profileDirFs, 0755, true);
    }

    // 1) Webcam capture data or drag-and-drop converted to data URL (if present)
    if ($captureData !== '') {
        // Match data URL format: data:image/[format];base64,[data]
        if (preg_match('/^data:image\/(png|jpeg|jpg|webp);base64,(.+)$/i', $captureData, $matches)) {
            $ext = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];
            $raw = base64_decode($matches[2]);
            if ($raw === false) {
                $errors[] = 'Invalid captured image data.';
            } else {
                if (strlen($raw) > 2 * 1024 * 1024) { // 2MB
                    $errors[] = 'Captured photo is too large. Maximum size is 2MB.';
                } else {
                    $fileName = 'profile_' . $memberId . '_' . time() . '.' . $ext;
                    $targetFs = $profileDirFs . '/' . $fileName;
                    if (file_put_contents($targetFs, $raw) === false) {
                        $errors[] = 'Failed to save captured photo.';
                    } else {
                        $newPhotoPath = '/assets/img/profile_photo/' . $fileName;
                    }
                }
            }
        } else {
            $errors[] = 'Invalid captured image format.';
        }
    }
    // 2) Fallback to normal file upload if no capture
    elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error: ' . $file['error'];
        } else {
            $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed)) {
                $errors[] = 'Invalid file type. Only JPG, PNG, and WEBP are allowed.';
            } elseif ($file['size'] > 2 * 1024 * 1024) {
                $errors[] = 'File is too large. Maximum size is 2MB.';
            } else {
                $ext = match ($mime) {
                    'image/jpeg', 'image/jpg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    default => 'jpg',
                };
                $fileName = 'profile_' . $memberId . '_' . time() . '.' . $ext;
                $targetFs = $profileDirFs . '/' . $fileName;

                if (!move_uploaded_file($file['tmp_name'], $targetFs)) {
                    $errors[] = 'Failed to save uploaded photo.';
                } else {
                    $newPhotoPath = '/assets/img/profile_photo/' . $fileName;
                }
            }
        }
    }
    // 3) If neither capture nor file upload, keep existing photo (no change)
}

// Delete old photo if a new one was saved
if (!$errors && $newPhotoPath !== ($current['profile_photo_path'] ?? null) && !empty($current['profile_photo_path'])) {
    $oldPath = $current['profile_photo_path'];
    if (strpos($oldPath, '/assets/img/profile_photo/') === 0) {
        $oldFs = realpath(__DIR__ . '/../..' . $oldPath);
        if ($oldFs && is_file($oldFs)) {
            @unlink($oldFs);
        }
    }
}

if ($errors) {
    $_SESSION['profile_errors'] = $errors;
    header('Location: /accounts.php#update-profile');
    exit;
}

// Perform update - only update profile photo
$stmt = $pdo->prepare("
    UPDATE Member
       SET profile_photo_path = ?
     WHERE member_id = ?
");
$stmt->execute([
    $newPhotoPath,
    $memberId,
]);

// Update session so header shows fresh info
if ($newPhotoPath) {
    $_SESSION['member']['profile_photo_path'] = $newPhotoPath;
} elseif ($newPhotoPath === null && isset($current['profile_photo_path'])) {
    // If photo was removed, clear it from session
    unset($_SESSION['member']['profile_photo_path']);
}

$_SESSION['profile_success'] = 'Profile photo updated successfully.';
$_SESSION['profile_photo_updated'] = true; // Flag to trigger photo update

header('Location: /accounts.php#update-profile');
exit;


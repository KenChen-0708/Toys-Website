<?php
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./login.php');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

$_SESSION['old'] = ['username' => $username];
$errors = [];

// Failed attempts (before this try)
$failedBefore = $_SESSION['failed_login_count'] ?? 0;
// CAPTCHA required after 3 failed attempts
$requireCaptcha = is_recaptcha_required('failed_login_count', 3);

// Remember Me
if (!empty($_POST['remember'])) {
    setcookie('remember_username', $username, time() + 86400 * 30, '/', '', false, true);
} else {
    setcookie('remember_username', '', time() - 3600, '/', '', false, true);
}

// Basic validation
if (!$username || strlen($password) < 6) {
    $errors[] = "Invalid username or password.";
}

// reCAPTCHA server verification
if ($requireCaptcha) {
    $response = $_POST['g-recaptcha-response'] ?? '';

    if (!verify_recaptcha($response)) {
        $errors[] = "Invalid username or password.";
    }
}

if (!$errors) {
    $stmt = $pdo->prepare("SELECT member_id, username, email, password, is_blocked FROM Member WHERE BINARY username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Check if account is blocked
        if (isset($user['is_blocked']) && $user['is_blocked'] == 1) {
            $errors[] = "Your account has been blocked. Please contact support for assistance.";
        } else {
            $_SESSION['member'] = [
                'id' => $user['member_id'],
                'username' => $user['username'],
                'email' => $user['email'],
            ];

            try {
                $stmtSettings = $pdo->prepare("SELECT theme_preference FROM Member_Settings WHERE member_id = ?");
                $stmtSettings->execute([$user['member_id']]);
                $settings = $stmtSettings->fetch(PDO::FETCH_ASSOC);

                // Save to session (Default to 'light' if not found)
                $_SESSION['member']['theme'] = $settings['theme_preference'] ?? 'light';
            } catch (Exception $e) {
                $_SESSION['member']['theme'] = 'light';
            }

            unset($_SESSION['failed_login_count']);
            unset($_SESSION['old']);

            header('Location: /index.php');
            exit;
        }
    } else {
        $errors[] = "Invalid username or password.";
    }
}

// Login failed → increment fail count
if ($errors) {
    $_SESSION['failed_login_count'] = $failedBefore + 1;
}

$_SESSION['errors'] = $errors;
header('Location: ./login.php');
exit;

<?php
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';
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

$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$captureData = $_POST['capture_data'] ?? '';

// Address fields
$addressStreet = trim($_POST['address_street'] ?? '');
$addressState = trim($_POST['address_state'] ?? '');
$addressPostcode = trim($_POST['address_postcode'] ?? '');
$addressCountry = trim($_POST['address_country'] ?? 'Malaysia');
$addressCity = trim($_POST['address_city'] ?? '');

$errors = [];

$passwordPattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$/';
$phonePattern    = '/^0\d{1,2}-?\d{6,8}$/';

// Username validation (same style as register/manage_member)
if ($username === '') {
    $errors[] = 'Username is required.';
} elseif (strlen($username) < 3) {
    $errors[] = 'Username must be at least 3 characters.';
} elseif (preg_match('/\s/', $username)) {
    $errors[] = 'Username cannot contain spaces. Use "_" instead.';
} elseif (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
    $errors[] = 'Username can only contain letters, numbers, and underscores.';
}

// Email validation
if ($email === '') {
    $errors[] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email.';
}

// Phone (optional)
if ($phone !== '') {
    // Remove spaces and dashes for validation
    $phoneClean = preg_replace('/[\s\-]/', '', $phone);
    if (!preg_match('/^0\d{9,10}$/', $phoneClean)) {
        $errors[] = 'Please enter a valid Malaysian phone number. Format: 0XX-XXXXXXX or 0XXXXXXXXX (10-11 digits).';
    }
}

// Street address validation (optional, but if provided, validate)
if ($addressStreet !== '') {
    if (strlen($addressStreet) < 5) {
        $errors[] = 'Street address is too short. Please provide a complete address (minimum 5 characters).';
    } elseif (strlen($addressStreet) > 200) {
        $errors[] = 'Street address is too long. Please keep it under 200 characters.';
    } elseif (!preg_match('/[a-zA-Z]/', $addressStreet)) {
        $errors[] = 'Street address must contain at least one letter.';
    }
}

// Address validation (optional, but if provided, validate components)
if ($addressPostcode !== '' && !preg_match('/^\d{5}$/', $addressPostcode)) {
    $errors[] = 'Postcode must be exactly 5 digits.';
}

// Combine address fields into formatted string
$address = '';
$addressParts = [];
if ($addressStreet !== '') $addressParts[] = $addressStreet;
if ($addressCity !== '') $addressParts[] = $addressCity;
if ($addressState !== '' || $addressPostcode !== '') {
    $statePostcode = trim($addressState . ' ' . $addressPostcode);
    if ($statePostcode !== '') $addressParts[] = $statePostcode;
}
if ($addressCountry !== '') $addressParts[] = $addressCountry;

if (!empty($addressParts)) {
    $address = implode(', ', $addressParts);
}

// Verify account exists
$checkStmt = $pdo->prepare("SELECT 1 FROM Member WHERE member_id = ?");
$checkStmt->execute([$memberId]);
if (!$checkStmt->fetch()) {
    $errors[] = 'Account not found.';
}

// Uniqueness checks
if (!$errors) {
    // Username unique except current (case sensitive)
    $check = $pdo->prepare("SELECT 1 FROM Member WHERE BINARY username = ? AND member_id <> ?");
    $check->execute([$username, $memberId]);
    if ($check->fetch()) {
        $errors[] = 'This username is already taken.';
    }

    // Email unique except current
    $check = $pdo->prepare("SELECT 1 FROM Member WHERE email = ? AND member_id <> ?");
    $check->execute([$email, $memberId]);
    if ($check->fetch()) {
        $errors[] = 'This email is already registered.';
    }

    // Phone unique except current (if provided)
    if ($phone !== '') {
        $check = $pdo->prepare("SELECT 1 FROM Member WHERE phone_number = ? AND member_id <> ?");
        $check->execute([$phone, $memberId]);
        if ($check->fetch()) {
            $errors[] = 'This phone number is already in use.';
        }
    }
}

if ($errors) {
    $_SESSION['profile_errors'] = $errors;
    header('Location: /accounts.php#update-profile');
    exit;
}

// Perform update (only update non-photo fields)
$stmt = $pdo->prepare("
    UPDATE Member
       SET username = ?,
           email = ?,
           phone_number = ?,
           address = ?
     WHERE member_id = ?
");
$stmt->execute([
    $username,
    $email,
    $phone !== '' ? $phone : null,
    $address !== '' ? $address : null,
    $memberId,
]);

// Update session so header shows fresh info
$_SESSION['member']['username'] = $username;
$_SESSION['member']['email']    = $email;

$_SESSION['profile_success'] = 'Profile updated successfully.';

header('Location: /accounts.php#update-profile');
exit;


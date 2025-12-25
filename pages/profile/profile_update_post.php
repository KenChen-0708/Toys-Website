<?php
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';
require_once '../../helper/mailer.php';
require_auth();

// Generate a fingerprint of sensitive profile fields to detect stale updates.
function profile_fingerprint(array $row): string
{
    return hash('sha256', implode('|', [
        (string)($row['username'] ?? ''),
        (string)($row['email'] ?? ''),
        (string)($row['phone_number'] ?? ''),
    ]));
}

// Check for conflicts against other members for username/email/phone.
function detect_profile_conflicts(PDO $pdo, int $memberId, string $username, string $email, ?string $phone): array
{
    $errors = [];

    $checks = [
        [
            'sql' => 'SELECT 1 FROM Member WHERE BINARY username = ? AND member_id <> ? LIMIT 1',
            'params' => [$username, $memberId],
            'message' => 'This username is already taken.',
        ],
        [
            'sql' => 'SELECT 1 FROM Member WHERE email = ? AND member_id <> ? LIMIT 1',
            'params' => [$email, $memberId],
            'message' => 'This email is already registered.',
        ],
    ];

    if ($phone !== '') {
        $checks[] = [
            'sql' => 'SELECT 1 FROM Member WHERE phone_number = ? AND member_id <> ? LIMIT 1',
            'params' => [$phone, $memberId],
            'message' => 'This phone number is already in use.',
        ];
    }

    foreach ($checks as $check) {
        $stmt = $pdo->prepare($check['sql']);
        $stmt->execute($check['params']);
        if ($stmt->fetch()) {
            $errors[] = $check['message'];
        }
    }

    return $errors;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /accounts.php');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

$memberId = $_SESSION['member']['id'];
$incomingFingerprint = $_POST['profile_fingerprint'] ?? '';

$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$captureData = $_POST['capture_data'] ?? '';
$redirectTo = $_POST['redirect_to'] ?? 'update-profile';
$otpCode   = trim($_POST['otp_code'] ?? '');

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

// Run conflict detection and updates in a transaction to avoid stale writes.
$inTransaction = false;
try {
    $pdo->beginTransaction();
    $inTransaction = true;

    $currentStmt = $pdo->prepare('SELECT username, email, phone_number, password FROM Member WHERE member_id = ? FOR UPDATE');
    $currentStmt->execute([$memberId]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        $errors[] = 'Account not found.';
    }

    // Detect if profile data changed since the form was loaded.
    if (!$errors && $incomingFingerprint !== '' && $incomingFingerprint !== profile_fingerprint($current)) {
        $errors[] = 'Your profile was updated in another session. Please reload and try again.';
    }

    // Uniqueness checks
    if (!$errors) {
        $errors = array_merge($errors, detect_profile_conflicts($pdo, $memberId, $username, $email, $phone));
    }

    if ($errors) {
        $pdo->rollBack();
        $_SESSION['profile_errors'] = $errors;
        header('Location: /accounts.php#' . $redirectTo);
        exit;
    }

    // If sensitive fields are changing, require OTP validation first
    $sensitiveChanged = false;
    if ($current) {
        if ($email !== ($current['email'] ?? '')) $sensitiveChanged = true;
        if ($phone !== ($current['phone_number'] ?? '')) $sensitiveChanged = true;
    }

    if ($sensitiveChanged) {
        $currentPassword = $_POST['current_password'] ?? '';
        // Check OTP in session
        $otpSession = $_SESSION['otp_profile'] ?? null;
        $now = time();
        $validOtp = ($otpSession && isset($otpSession['code'], $otpSession['expires']) && $otpCode !== '' && $otpCode === $otpSession['code'] && $now <= (int)$otpSession['expires']);

        if (!$validOtp) {
            // Require current password before sending OTP
            if ($currentPassword === '') {
                $pdo->rollBack();
                $_SESSION['profile_errors'] = ['Please enter your current password to confirm these changes.'];
                header('Location: /accounts.php#' . $redirectTo);
                exit;
            }

            if (empty($current['password']) || !password_verify($currentPassword, $current['password'])) {
                $pdo->rollBack();
                $_SESSION['profile_errors'] = ['The current password you entered is incorrect.'];
                header('Location: /accounts.php#' . $redirectTo);
                exit;
            }
            // Generate and send OTP, store pending changes and ask user to verify
            $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $_SESSION['otp_profile'] = [
                'code' => $code,
                'expires' => $now + 600, // 10 minutes
            ];
            $_SESSION['pending_profile'] = [
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'address_street' => $addressStreet,
                'address_city' => $addressCity,
                'address_state' => $addressState,
                'address_postcode' => $addressPostcode,
                'address_country' => $addressCountry,
            ];

            // Always send OTP to current email on file
            $sendToEmail = (string)($current['email'] ?? '');
            $sendToName = (string)($current['username'] ?? '');
            if ($sendToEmail === '' || !filter_var($sendToEmail, FILTER_VALIDATE_EMAIL)) {
                $pdo->rollBack();
                $_SESSION['profile_errors'] = ['Cannot send verification email because your current email on file is missing or invalid.'];
                header('Location: /accounts.php#' . $redirectTo);
                exit;
            }
            $subject = 'Verify Your Profile Changes';
            $htmlBody = '<p>Your verification code is: <strong>' . htmlspecialchars($code) . '</strong></p>' .
                        '<p>This code expires in 10 minutes. Enter it on the Update Profile page to confirm changes.</p>';
            $sent = @send_app_mail($sendToEmail, $sendToName, $subject, $htmlBody);
            if (!$sent) {
                $pdo->rollBack();
                $_SESSION['profile_errors'] = ['Unable to send verification email. Please confirm your email address or try again later.'];
                header('Location: /accounts.php#' . $redirectTo);
                exit;
            }

            $pdo->rollBack();
            $_SESSION['profile_errors'] = ['We sent a verification code to your current email. Enter the OTP to confirm changes.'];
            $_SESSION['otp_required_profile'] = true;
            header('Location: /accounts.php#' . $redirectTo);
            exit;
        } else {
            // OTP was provided but is invalid
            $pdo->rollBack();
            $_SESSION['profile_errors'] = ['Invalid or expired verification code. Please try again.'];
            $_SESSION['otp_invalid'] = true;
            $_SESSION['otp_required_profile'] = true;
            header('Location: /accounts.php#' . $redirectTo);
            exit;
        }
    }

    // Perform update (only update non-photo fields - NO address field)
    $stmt = $pdo->prepare("
        UPDATE Member
           SET username = ?,
               email = ?,
               phone_number = ?
         WHERE member_id = ?
    ");
    $stmt->execute([
        $username,
        $email,
        $phone !== '' ? $phone : null,
        $memberId,
    ]);

    // Update or create default shipping address in Shipping_Address table
    if ($addressStreet || $addressCity) {
        // Check if user has a default shipping address (most recent one)
        $checkStmt = $pdo->prepare("
            SELECT shipping_address_id 
            FROM Shipping_Address 
            WHERE member_id = ? 
            ORDER BY created_date DESC 
            LIMIT 1
        ");
        $checkStmt->execute([$memberId]);
        $existingAddress = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        $shippingAddressLine1 = $addressStreet ?: '';
        $shippingAddressLine2 = '';
        $shippingCity = $addressCity ?: '';
        $shippingState = $addressState ?: '';
        $shippingPostcode = $addressPostcode ?: '';
        $shippingCountry = $addressCountry ?: 'Malaysia';
        
        if ($existingAddress) {
            // Update existing default address
            $updateStmt = $pdo->prepare("
                UPDATE Shipping_Address 
                SET address_line1 = ?,
                    address_line2 = ?,
                    city = ?,
                    state = ?,
                    postal_code = ?,
                    country = ?
                WHERE shipping_address_id = ?
            ");
            $updateStmt->execute([
                $shippingAddressLine1,
                $shippingAddressLine2,
                $shippingCity,
                $shippingState,
                $shippingPostcode,
                $shippingCountry,
                $existingAddress['shipping_address_id']
            ]);
        } else {
            // Create new default shipping address
            $insertStmt = $pdo->prepare("
                INSERT INTO Shipping_Address 
                (member_id, address_line1, address_line2, city, state, postal_code, country, created_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $insertStmt->execute([
                $memberId,
                $shippingAddressLine1,
                $shippingAddressLine2,
                $shippingCity,
                $shippingState,
                $shippingPostcode,
                $shippingCountry
            ]);
        }
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($inTransaction && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['profile_errors'] = ['Unable to update profile right now. Please try again.'];
    header('Location: /accounts.php#' . $redirectTo);
    exit;
}

// Update session so header shows fresh info
$_SESSION['member']['username'] = $username;
$_SESSION['member']['email']    = $email;

// Clear OTP flow if it was used
unset($_SESSION['otp_profile'], $_SESSION['pending_profile'], $_SESSION['otp_required_profile']);

$_SESSION['profile_success'] = 'Profile updated successfully.';

header('Location: /accounts.php#' . $redirectTo);
exit;


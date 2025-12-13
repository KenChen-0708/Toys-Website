<?php
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./register.php');
    exit;
}
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Invalid CSRF token');
}

// reCAPTCHA verification
$recaptcha_secret = '6LdtlBAsAAAAAKEkbvhhoY94jGMkg6BHMiWVSKV0';
$recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

$verify_url = 'https://www.google.com/recaptcha/api/siteverify';
$verify_data = [
    'secret' => $recaptcha_secret,
    'response' => $recaptcha_response,
    'remoteip' => $_SERVER['REMOTE_ADDR']
];

$options = [
    'http' => [
        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
        'method' => 'POST',
        'content' => http_build_query($verify_data)
    ]
];

$context = stream_context_create($options);
$verify_response = file_get_contents($verify_url, false, $context);
$response_data = json_decode($verify_response);

if (!$response_data->success) {
    $_SESSION['errors'] = ['Please complete the CAPTCHA verification.'];
    header('Location: ./register.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$phone = trim($_POST['phone'] ?? '');

$_SESSION['old'] = compact('username', 'email', 'phone');
$errors = [];

// Username checks
if (strlen($username) < 3) $errors[] = 'Username must be at least 3 characters.';
else if (preg_match('/\s/', $username)) $errors[] = 'Username cannot contain spaces.';
else if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) $errors[] = 'Username contains invalid characters.';

// Email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';

// Password
if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
if ($password !== $password_confirm) $errors[] = 'Passwords do not match.';

// Phone (optional)
if ($phone && !preg_match('/^0\d{1,2}-?\d{6,8}$/', $phone)) {
    $errors[] = 'Invalid phone number.';
}

if (!$errors) {

    // Username unique
    $checkName = $pdo->prepare("SELECT 1 FROM Member WHERE BINARY username = ?");
    $checkName->execute([$username]);
    if ($checkName->fetch()) $errors[] = 'This username is already taken.';

    // Email unique
    $checkEmail = $pdo->prepare("SELECT 1 FROM Member WHERE email = ?");
    $checkEmail->execute([$email]);
    if ($checkEmail->fetch()) $errors[] = 'This email is already registered.';

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $now = date('Y-m-d H:i:s');

        try {
            $stmt = $pdo->prepare("
                INSERT INTO Member (username, password, email, phone_number, register_date)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $hash, $email, $phone, $now]);

            $member_id = $pdo->lastInsertId();

            // Create cart
            $cart = $pdo->prepare("INSERT INTO Cart (member_id) VALUES (?)");
            $cart->execute([$member_id]);

            unset($_SESSION['old']);
            $_SESSION['member'] = [
                'id' => $member_id,
                'username' => $username,
                'email' => $email
            ];

            header('Location: /index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = ($e->getCode() == 23000)
                ? 'Username or email already exists.'
                : 'Something went wrong.';
        }
    }
}

$_SESSION['errors'] = $errors;
header('Location: ./register.php');
exit;

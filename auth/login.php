<?php
$title = "Login";
require_once '../helper/pdo_connection.php';
require_once '../helper/auth.php';
require_guest();

// reCAPTCHA SITE KEY
$recaptcha_site_key = '6LdtlBAsAAAAALXJnaRjT_-VL98wF1bYuzuwKbVj';

// Failed attempts
$failedAttempts = $_SESSION['failed_login_count'] ?? 0;
// Require CAPTCHA after 3 failed attempts
$requireCaptcha = $failedAttempts >= 3;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="/assets/css/styles.css">

    <!-- reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

    <title><?= htmlspecialchars($title) ?></title>

    <style>
        .field-hint { opacity: 0; max-height: 0; transition: .2s; font-size:.8rem; color:#6b7280; }
        .field-hint--visible { opacity:1; max-height:80px; }
        .hint--error { color:#b91c1c; }
        .input--error { border-color:#b91c1c; }

        .captcha-row { margin-top: 0.5rem; }
        .captcha-row .g-recaptcha { margin-top: 0.25rem; }
    </style>
</head>
<body>

<?php
include '../partials/header.php';

$errors = $_SESSION['errors'] ?? [];
$old = $_SESSION['old'] ?? [];
$rememberedUsername = $_COOKIE['remember_username'] ?? '';

unset($_SESSION['errors'], $_SESSION['old']);
?>

<main class="main">

<section class="auth-hero">
    <div class="auth-card">
        <h1 class="auth-title">Welcome Back</h1>
        <p class="auth-sub">Sign in to continue shopping</p>

        <?php if ($errors): ?>
            <div class="alert alert--danger">
                <?php foreach ($errors as $e): ?>
                    <div><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form id="loginForm" class="form grid" method="POST" action="./login_post.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

            <!-- Username -->
            <div class="form__row">
                <label>Username</label>
                <input id="username" name="username" type="text"
                       class="form__input"
                       required
                       value="<?= htmlspecialchars($old['username'] ?? $rememberedUsername) ?>"
                       data-hint="usernameHint">
                <p id="usernameHint" class="field-hint">Enter your username.</p>
            </div>

            <!-- Password -->
            <div class="form__row">
                <label>Password</label>
                <div class="input-affix">
                    <input id="password" name="password" type="password"
                           class="form__input"
                           required minlength="6"
                           data-hint="passwordHint">
                    <button type="button" id="togglePwd">👁</button>
                </div>
                <p id="passwordHint" class="field-hint">Enter your password.</p>
            </div>

            <?php if ($requireCaptcha): ?>
            <!-- reCAPTCHA -->
            <div class="form__row captcha-row">
                <label>Verification</label>
                <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($recaptcha_site_key) ?>"></div>
                <p id="captchaHint" class="field-hint field-hint--visible">Please complete the CAPTCHA.</p>
            </div>
            <?php endif; ?>

            <div class="auth-meta">
                <label class="checkbox">
                    <input type="checkbox" name="remember" value="1" <?= $rememberedUsername ? 'checked' : '' ?>>
                    <span>Remember me</span>
                </label>
                <a href="./forgot_password.php" class="link">Forgot password?</a>
            </div>

            <button class="btn" type="submit">Login</button>

            <p class="auth-sub" style="margin-top:1rem;">
                New here? <a href="./register.php" class="link">Create an account</a>
            </p>

            <div class="auth-sub" style="font-size:0.9rem; margin-top:-1rem;">
                <a href="./admin_login.php" class="link">Admin Login</a>
            </div>
        </form>

    </div>
</section>

</main>

<script>
document.addEventListener('DOMContentLoaded', () => {

    const form = document.getElementById('loginForm');
    const username = document.getElementById('username');
    const password = document.getElementById('password');
    const captchaHint = document.getElementById('captchaHint');

    const toggleBtn = document.getElementById('togglePwd');
    toggleBtn.addEventListener('click', () => {
        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        toggleBtn.textContent = show ? '🙈' : '👁';
    });

    // Hints
    document.querySelectorAll('[data-hint]').forEach(field => {
        const hint = document.getElementById(field.dataset.hint);
        field.addEventListener('focus', () => hint.classList.add('field-hint--visible'));
        field.addEventListener('blur', () => {
            if (!hint.classList.contains('hint--error')) hint.classList.remove('field-hint--visible');
        });
    });

    // Injected from PHP
    const requireCaptcha = <?= $requireCaptcha ? 'true' : 'false' ?>;

    form.addEventListener('submit', (e) => {
        let valid = true;

        // Basic validation
        if (!username.value.trim()) {
            username.classList.add('input--error');
            valid = false;
        } else username.classList.remove('input--error');

        if (!password.value.trim() || password.value.length < 6) {
            password.classList.add('input--error');
            valid = false;
        } else password.classList.remove('input--error');

        if (!valid) {
            e.preventDefault();
            return;
        }

        // Client-side CAPTCHA check
        if (requireCaptcha) {
            const response = grecaptcha.getResponse();
            if (!response) {
                captchaHint.classList.add('hint--error');
                e.preventDefault();
            }
        }
    });
});
</script>

<?php include '../partials/footer.php'; ?>
</body>
</html>

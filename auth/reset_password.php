<?php
$title = "Reset Password";
require_once '../helper/pdo_connection.php';
require_once '../helper/auth.php';
require_guest();
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <!--=============== FLATICON ===============-->
    <link
      rel="stylesheet"
      href="https://cdn-uicons.flaticon.com/2.0.0/uicons-regular-straight/css/uicons-regular-straight.css"
    />

    <!--=============== CSS ===============-->
    <link rel="stylesheet" href="/assets/css/styles.css" />

    <title><?= htmlspecialchars($title) ?></title>
  </head>
  <body>
<?php 
include '../partials/header.php';

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';
?>

<style>
  .input-with-icon {
    position: relative;
    display: flex;
    align-items: center;
  }

  .password-toggle {
    position: absolute;
    right: 0.75rem;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    padding: 0;
  }

  /* Hints */
  .field-hint {
    font-size: .8rem;
    color: #6b7280;
    margin-top: .25rem;
    opacity: 0;
    max-height: 0;
    transition: .18s;
  }
  .field-hint--visible {
    opacity: 1;
    max-height: 80px;
  }
  .hint--error {
    color: #b91c1c;
  }

  .form__input.input--error {
    border-color: #b91c1c;
  }
</style>

<main class="main">
  <section class="breadcrumb">
    <ul class="breadcrumb__list flex container">
      <li><a href="../index.php" class="breadcrumb__link">Home</a></li>
      <li><span class="breadcrumb__link">></span></li>
      <li><span class="breadcrumb__link">Reset Password</span></li>
    </ul>
  </section>

  <section class="auth-hero">
    <div class="auth-card">
      <h1 class="auth-title">Set a new password</h1>
      <p class="auth-sub">Choose a strong password to secure your account.</p>

      <?php if ($errors): ?>
        <div class="alert alert--danger">
          <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form class="form grid" id="resetForm" action="./reset_password_post.php" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <!-- New password -->
        <div class="form__row">
          <label class="form__label" for="password">New password</label>
          <div class="input-with-icon">
            <input
              id="password"
              name="password"
              type="password"
              class="form__input"
              required
              minlength="8"
              autocomplete="new-password"
              pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$"
              title="At least 8 characters, including uppercase, lowercase, number and symbol."
              data-hint="passwordHint"
            />
            <button
              type="button"
              id="togglePwd"
              class="password-toggle"
              aria-label="Toggle password visibility"
            >👁</button>
          </div>
          <p id="passwordHint" class="field-hint">
            At least 8 characters, including uppercase, lowercase, number and symbol.
          </p>
        </div>

        <!-- Confirm password -->
        <div class="form__row">
          <label class="form__label" for="password_confirmation">Confirm password</label>
          <div class="input-with-icon">
            <input
              id="password_confirmation"
              name="password_confirmation"
              type="password"
              class="form__input"
              required
              minlength="8"
              autocomplete="new-password"
              data-hint="passwordConfirmHint"
            />
            <button
              type="button"
              id="togglePwd2"
              class="password-toggle"
              aria-label="Toggle confirm password visibility"
            >👁</button>
          </div>
          <p id="passwordConfirmHint" class="field-hint">
            Re-enter your new password.
          </p>
        </div>

        <button type="submit" class="btn">Update password</button>
      </form>
    </div>
  </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('resetForm');
  const passwordInput = document.getElementById('password');
  const confirmInput = document.getElementById('password_confirmation');
  const togglePwdBtn = document.getElementById('togglePwd');
  const togglePwd2Btn = document.getElementById('togglePwd2');

  const passwordHint = document.getElementById('passwordHint');
  const confirmHint = document.getElementById('passwordConfirmHint');

  // Same pattern as on the server / register page
  const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$/;

  function setError(field, hintEl, message) {
    field.classList.add('input--error');
    hintEl.textContent = message;
    hintEl.classList.add('field-hint--visible', 'hint--error');
  }

  function clearError(field, hintEl, defaultMessage) {
    field.classList.remove('input--error');
    hintEl.textContent = defaultMessage;
    hintEl.classList.remove('hint--error');
  }

  // Eye toggle for new password
  togglePwdBtn?.addEventListener('click', () => {
    const show = passwordInput.type === 'password';
    passwordInput.type = show ? 'text' : 'password';
    togglePwdBtn.textContent = show ? '🙈' : '👁';
  });

  // Eye toggle for confirm password
  togglePwd2Btn?.addEventListener('click', () => {
    const show = confirmInput.type === 'password';
    confirmInput.type = show ? 'text' : 'password';
    togglePwd2Btn.textContent = show ? '🙈' : '👁';
  });

  // Show hints on focus like login page
  document.querySelectorAll('[data-hint]').forEach(field => {
    const hint = document.getElementById(field.dataset.hint);
    field.addEventListener('focus', () => hint.classList.add('field-hint--visible'));
    field.addEventListener('blur', () => {
      if (!hint.classList.contains('hint--error')) {
        hint.classList.remove('field-hint--visible');
      }
    });
  });

  form?.addEventListener('submit', function (e) {
    const pwd = passwordInput.value.trim();
    const pwd2 = confirmInput.value.trim();
    let valid = true;

    // Reset to default messages
    clearError(
      passwordInput,
      passwordHint,
      "At least 8 characters, including uppercase, lowercase, number and symbol."
    );
    clearError(
      confirmInput,
      confirmHint,
      "Re-enter your new password."
    );

    // New password validation
    if (!pwd) {
      setError(passwordInput, passwordHint, "Password is required.");
      valid = false;
    } else if (!passwordPattern.test(pwd)) {
      setError(
        passwordInput,
        passwordHint,
        "Password must be at least 8 characters and include uppercase, lowercase, number and symbol."
      );
      valid = false;
    }

    // Confirm password validation
    if (!pwd2) {
      setError(confirmInput, confirmHint, "Please confirm your new password.");
      valid = false;
    } else if (pwd !== pwd2) {
      setError(confirmInput, confirmHint, "Passwords do not match.");
      valid = false;
    }

    if (!valid) {
      e.preventDefault();
    }
  });
});
</script>

<?php include '../partials/footer.php'; ?>

<?php
$title = "Forgot Password";
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
$success = $_SESSION['success'] ?? null;
unset($_SESSION['errors'], $_SESSION['success']);
?>
<style>
  /* Field hints & error styling (same pattern as login/register) */
  .field-hint {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 0.25rem;
    line-height: 1.3;
    opacity: 0;
    max-height: 0;
    transform: translateY(-2px);
    transition: opacity 0.18s ease, max-height 0.18s ease, transform 0.18s ease;
  }

  .field-hint--visible {
    opacity: 1;
    max-height: 80px;
    transform: translateY(0);
  }

  .hint--error {
    color: #b91c1c;
  }

  .form__input.input--error {
    border-color: #b91c1c;
    box-shadow: 0 0 0 1px rgba(185, 28, 28, 0.15);
  }
</style>

<main class="main">
  <section class="breadcrumb">
    <ul class="breadcrumb__list flex container">
      <li><a href="../index.php" class="breadcrumb__link">Home</a></li>
      <li><span class="breadcrumb__link">></span></li>
      <li><span class="breadcrumb__link">Forgot Password</span></li>
    </ul>
  </section>

  <section class="auth-hero">
    <div class="auth-card">
      <h1 class="auth-title">Forgot your password?</h1>
      <p class="auth-sub">
        Enter your email and we’ll send you a link to reset your password.
      </p>

      <?php if ($errors): ?>
        <div class="alert alert--danger">
          <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert--success">
          <?= $success ?>
        </div>
      <?php endif; ?>

      <form
        id="forgotForm"
        class="form grid"
        action="./forgot_password_post.php"
        method="POST"
        novalidate
      >
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="form__row">
          <label class="form__label" for="email">Email address</label>
          <input
            id="email"
            name="email"
            type="email"
            class="form__input"
            required
            autocomplete="email"
            data-hint="emailHint"
          />
          <p id="emailHint" class="field-hint">
            Enter the email associated with your account.
          </p>
        </div>

        <button type="submit" class="btn">Send reset link</button>

        <p class="auth-sub" style="margin-top:1rem">
          Remembered it? <a href="./login.php" class="link">Back to login</a>
        </p>

        <p class="auth-sub" style="margin-top:-1rem; margin-bottom: -4px">
          <a href="./forgot_username.php" class="link">Forgot your username?</a>
        </p>
      </form>
    </div>
  </section>
</main>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('forgotForm');
    if (!form) return;

    const emailField = document.getElementById('email');
    const emailHint  = document.getElementById('emailHint');

    // Store default hint text
    const defaultEmailHintText = emailHint.textContent;

    function setError(field, hintEl, message) {
      field.classList.add('input--error');
      hintEl.textContent = message;
      hintEl.classList.add('field-hint--visible', 'hint--error');
    }

    function clearError(field, hintEl) {
      field.classList.remove('input--error');
      hintEl.textContent = defaultEmailHintText;
      hintEl.classList.remove('hint--error');
    }

    // Focus-based hint behavior
    emailField.addEventListener('focus', function () {
      emailHint.classList.add('field-hint--visible');
    });

    emailField.addEventListener('blur', function () {
      if (!emailHint.classList.contains('hint--error')) {
        emailHint.classList.remove('field-hint--visible');
      }
    });

    function validateForm() {
      let isValid = true;
      clearError(emailField, emailHint);

      const value = emailField.value.trim();

      if (!value) {
        setError(emailField, emailHint, 'Email is required.');
        isValid = false;
      } else if (!emailField.checkValidity()) {
        // Uses native email pattern
        setError(emailField, emailHint, 'Please enter a valid email address.');
        isValid = false;
      }

      return isValid;
    }

    form.addEventListener('submit', function (e) {
      if (!validateForm()) {
        e.preventDefault();
      }
    });
  });
</script>

<?php include '../partials/footer.php'; ?>

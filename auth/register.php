<?php
$title = "Register";
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
$old = $_SESSION['old'] ?? [];
unset($_SESSION['errors'], $_SESSION['old']);
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
  }
  .field-hint {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 0.25rem;
    opacity: 0;
    max-height: 0;
    transition: opacity .18s ease, max-height .18s ease;
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

  <section class="auth-hero">
    <div class="auth-card">
      <h1 class="auth-title">Create your account</h1>

      <?php if ($errors): ?>
        <div class="alert alert--danger" role="alert">
          <?php foreach($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form id="registerForm" class="form grid" method="POST" action="./register_post.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

        <label class="form__label">
          <span>User Name *</span>
          <input
            name="username"
            type="text"
            class="form__input"
            required
            minlength="3"
            autocomplete="username"
            data-hint="nameHint"
            value="<?= htmlspecialchars($old['username'] ?? '') ?>"
          />
          <p id="nameHint" class="field-hint">
            3+ characters. Letters, numbers, and "_" only.
          </p>
        </label>

        <label class="form__label">
          <span>Email *</span>
          <input
            name="email"
            type="email"
            class="form__input"
            required
            autocomplete="email"
            data-hint="emailHint"
            value="<?= htmlspecialchars($old['email'] ?? '') ?>"
          />
          <p id="emailHint" class="field-hint">
            Enter a valid email like name@example.com.
          </p>
        </label>

        <div class="grid two">
          <label class="form__label">
            <span>Password *</span>
            <div class="input-with-icon">
              <input
                id="pwd"
                name="password"
                type="password"
                class="form__input"
                required
                minlength="8"
                autocomplete="new-password"
                pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$"
                data-hint="pwdHint"
              />
              <button type="button" id="togglePwd" class="password-toggle">👁</button>
            </div>
            <p id="pwdHint" class="field-hint">
              8+ characters incl. uppercase, lowercase, number, and symbol.
            </p>
          </label>

          <label class="form__label">
            <span>Confirm Password *</span>
            <div class="input-with-icon">
              <input
                id="pwd2"
                name="password_confirm"
                type="password"
                class="form__input"
                required
                minlength="8"
                autocomplete="new-password"
                data-hint="pwd2Hint"
              />
              <button type="button" id="togglePwd2" class="password-toggle">👁</button>
            </div>
            <p id="pwd2Hint" class="field-hint">
              Re-enter the same password.
            </p>
          </label>
        </div>

        <label class="form__label">
          <span>Phone</span>
          <input
            name="phone"
            type="tel"
            class="form__input"
            autocomplete="tel"
            data-hint="phoneHint"
            value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
          />
          <p id="phoneHint" class="field-hint">
            Example: 012-3456789
          </p>
        </label>

        <button class="btn btn--primary btn--block" type="submit">Create account</button>

        <p class="auth-sub">
          Already have an account? <a href="./login.php" class="link">Login</a>
        </p>
      </form>
    </div>
  </section>
</main>

<script>
  const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$/;

  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registerForm');
    if (!form) return;

    const pwd = document.getElementById('pwd');
    const pwd2 = document.getElementById('pwd2');
    const pwdHint = document.getElementById('pwdHint');
    const pwd2Hint = document.getElementById('pwd2Hint');

    // Save default hint texts so we can restore them after errors
    const hintDefaults = {};
    document.querySelectorAll('.field-hint').forEach(function (hint) {
      hintDefaults[hint.id] = hint.textContent;
    });

    function resetHint(hintEl) {
      if (!hintEl) return;
      if (hintDefaults[hintEl.id]) {
        hintEl.textContent = hintDefaults[hintEl.id];
      }
      hintEl.classList.remove('hint--error');
    }

    function setError(field, hintEl, message) {
      if (field) {
        field.classList.add('input--error');
      }
      if (hintEl) {
        hintEl.textContent = message;
        hintEl.classList.add('field-hint--visible', 'hint--error');
      }
    }

    function clearError(field, hintEl) {
      if (field) {
        field.classList.remove('input--error');
      }
      if (hintEl) {
        resetHint(hintEl);
      }
    }

    // 👁 Password eye toggles for both fields
    const togglePwdBtn = document.getElementById('togglePwd');
    const togglePwd2Btn = document.getElementById('togglePwd2');

    togglePwdBtn?.addEventListener('click', () => {
      const show = pwd.type === 'password';
      pwd.type = show ? 'text' : 'password';
      togglePwdBtn.textContent = show ? '🙈' : '👁';
    });

    togglePwd2Btn?.addEventListener('click', () => {
      const show = pwd2.type === 'password';
      pwd2.type = show ? 'text' : 'password';
      togglePwd2Btn.textContent = show ? '🙈' : '👁';
    });

    // ✅ Generic focus-based hints for all fields with data-hint attribute
    document.querySelectorAll('[data-hint]').forEach(function (field) {
      const hintId = field.getAttribute('data-hint');
      const hintEl = document.getElementById(hintId);
      if (!hintEl) return;

      field.addEventListener('focus', function () {
        hintEl.classList.add('field-hint--visible');
      });

      field.addEventListener('blur', function () {
        // Keep visible if currently showing an error
        if (hintEl.classList.contains('hint--error')) return;
        hintEl.classList.remove('field-hint--visible');
      });
    });

    // Live password feedback while typing
    function checkMatch() {
      if (!pwdHint) return;

      clearError(pwd, pwdHint);
      clearError(pwd2, pwd2Hint);

      pwdHint.classList.add('field-hint--visible');
      if (pwd2Hint) pwd2Hint.classList.add('field-hint--visible');

      if (pwd.value && !passwordPattern.test(pwd.value)) {
        setError(pwd, pwdHint, 'Password must be at least 8 characters and include uppercase, lowercase, number and symbol.');
      }

      if (pwd.value && pwd2.value && pwd.value !== pwd2.value) {
        if (pwd2Hint) {
          setError(pwd2, pwd2Hint, 'Passwords do not match.');
        } else if (pwdHint) {
          setError(pwd2, pwdHint, 'Passwords do not match.');
        }
      }
    }

    pwd?.addEventListener('input', checkMatch);
    pwd2?.addEventListener('input', checkMatch);

    function validateForm() {
      let isValid = true;

      const usernameField = form.querySelector('input[name="username"]');
      const emailField = form.querySelector('input[name="email"]');
      const phoneField = form.querySelector('input[name="phone"]');
      const addressField = form.querySelector('textarea[name="address"]');

      const nameHint = document.getElementById('nameHint');
      const emailHint = document.getElementById('emailHint');
      const phoneHint = document.getElementById('phoneHint');
      const addressHint = document.getElementById('addressHint');

      // Username
      const username = usernameField.value.trim();
      clearError(usernameField, nameHint);

      if (!username) {
        setError(usernameField, nameHint, 'Username is required.');
        isValid = false;
      } else if (username.length < 3) {
        setError(usernameField, nameHint, 'Username must be at least 3 characters.');
        isValid = false;
      } else if (/\s/.test(username)) {
        setError(usernameField, nameHint, 'Username cannot contain spaces. Use "_" instead.');
        isValid = false;
      } else if (!/^[A-Za-z0-9_]+$/.test(username)) {
        setError(usernameField, nameHint, 'Username can only contain letters, numbers, and underscores.');
        isValid = false;
      }

      // Email
      const email = emailField.value.trim();
      clearError(emailField, emailHint);

      if (!email) {
        setError(emailField, emailHint, 'Email is required.');
        isValid = false;
      } else if (!emailField.checkValidity()) {
        setError(emailField, emailHint, 'Please enter a valid email address.');
        isValid = false;
      }

      // Password
      clearError(pwd, pwdHint);
      clearError(pwd2, pwd2Hint);

      const password = pwd.value.trim();
      const passwordConfirm = pwd2.value.trim();

      if (!password) {
        setError(pwd, pwdHint, 'Password is required.');
        isValid = false;
      } else if (!passwordPattern.test(password)) {
        setError(pwd, pwdHint, 'Password must be at least 8 characters and include uppercase, lowercase, number and symbol.');
        isValid = false;
      }

      if (!passwordConfirm) {
        if (pwd2Hint) {
          setError(pwd2, pwd2Hint, 'Confirm Password is required.');
        } else {
          setError(pwd2, pwdHint, 'Confirm Password is required.');
        }
        isValid = false;
      } else if (password && passwordConfirm && password !== passwordConfirm) {
        if (pwd2Hint) {
          setError(pwd2, pwd2Hint, 'Passwords do not match.');
        } else {
          setError(pwd2, pwdHint, 'Passwords do not match.');
        }
        isValid = false;
      }

      // Phone (optional) – simple pattern if user fills it
      const phone = phoneField.value.trim();
      clearError(phoneField, phoneHint);
      if (phone && !/^0\d{1,2}-?\d{6,8}$/.test(phone)) {
        setError(phoneField, phoneHint, 'Please enter a valid phone format, e.g. 012-3456789.');
        isValid = false;
      }

      // Address (optional) – no strict validation, just show hint if filled but extremely short
      const address = addressField.value.trim();
      clearError(addressField, addressHint);
      if (address && address.length < 5) {
        setError(addressField, addressHint, 'Address looks too short. Please add more details.');
        isValid = false;
      }

      return isValid;
    }

    // On submit, only allow if all client-side checks pass
    form.addEventListener('submit', function (e) {
      if (!validateForm()) {
        e.preventDefault();
      }
    });
  });
</script>

<?php include '../partials/footer.php'; ?>

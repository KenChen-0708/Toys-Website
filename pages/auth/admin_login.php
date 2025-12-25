<?php
$title = "Admin Login";
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';
redirect_if_admin();
// For now, reuse require_guest() so logged-in users can't hit admin login.
require_guest();

// reCAPTCHA configuration
$recaptcha_site_key = get_recaptcha_site_key();
$requireCaptcha = is_recaptcha_required('admin_failed_login_count', 3);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <!--=============== FLATICON ===============-->
  <link
    rel="stylesheet"
    href="https://cdn-uicons.flaticon.com/2.0.0/uicons-regular-straight/css/uicons-regular-straight.css" />

  <!--=============== CSS ===============-->
  <link rel="stylesheet" href="/assets/css/styles.css" />

  <!-- Google reCAPTCHA v2 -->
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>

  <title><?= htmlspecialchars($title) ?></title>
</head>

<?php
include '../../partials/header.php';

$errors = $_SESSION['admin_errors'] ?? [];
$old = $_SESSION['admin_old'] ?? [];
$rememberedAdmin = $_COOKIE['remember_admin'] ?? '';
unset($_SESSION['admin_errors'], $_SESSION['admin_old']);
?>

<style>
  /* Extra small visual tweaks for admin card */
  .auth-card--admin {
    border-top: 4px solid #167cf9ff;
  }

  .auth-badge-admin {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.15rem 0.6rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    background: #111827;
    color: #8cb8eeff;
    margin-bottom: 0.5rem;
  }

  /* Input affix and password toggle */
  .input-affix {
    position: relative;
    display: flex;
    align-items: center;
  }

  .input-affix button {
    position: absolute;
    right: 0.75rem;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    padding: 0;
  }

  /* Field hints & error state */
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

  /* CAPTCHA block */
  .captcha-row {
    margin-top: 0.75rem;
  }

  .captcha-row .g-recaptcha {
    margin-top: 0.25rem;
  }
</style>

<main class="main">
  <!--=============== AUTH HERO (CENTERED CONTAINER) ===============-->
  <section class="auth-hero">
    <div class="auth-card auth-card--admin">
      <div class="auth-badge-admin">
        <span>⚙ Admin Portal</span>
      </div>
      <h1 class="auth-title">Admin Sign In</h1>
      <p class="auth-sub">Restricted area. Authorized staff only.</p>

      <?php if ($errors): ?>
        <div class="alert alert--danger" role="alert">
          <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form id="adminLoginForm" class="form grid" method="POST" action="./admin_login_post.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

        <!-- Admin Username -->
        <div class="form__row">
          <label class="form__label" for="admin_username">Admin Username</label>
          <div class="input-affix">
            <input
              id="admin_username"
              name="username"
              type="text"
              class="form__input"
              required
              autocomplete="username"
              data-hint="adminUsernameHint"
              value="<?= htmlspecialchars($old['username'] ?? $rememberedAdmin) ?>" />
          </div>
          <p id="adminUsernameHint" class="field-hint">
            Enter your admin username.
          </p>
        </div>

        <!-- Admin Password -->
        <div class="form__row">
          <label class="form__label" for="admin_password">Password</label>
          <div class="input-affix">
            <input
              id="admin_password"
              name="password"
              type="password"
              class="form__input"
              required
              autocomplete="current-password"
              data-hint="adminPasswordHint" />
            <button type="button" id="toggleAdminPwd" aria-label="Show password">👁</button>
          </div>
          <p id="adminPasswordHint" class="field-hint">
            Enter your admin password.
          </p>
        </div>

        <!-- reCAPTCHA (shown only after 3 failed attempts) -->
        <?php if ($requireCaptcha): ?>
          <div class="form__row captcha-row">
            <label class="form__label">Verification</label>
            <div class="g-recaptcha"
              data-sitekey="<?= htmlspecialchars($recaptcha_site_key) ?>">
            </div>
            <p id="adminCaptchaHint" class="field-hint field-hint--visible">
              Please complete the CAPTCHA verification.
            </p>
          </div>
        <?php endif; ?>

        <!-- Remember / Back to member -->
        <div class="auth-meta">
          <label class="checkbox">
            <input
              type="checkbox"
              name="remember"
              value="1"
              <?= $rememberedAdmin ? 'checked' : '' ?> />
            <span>Remember me</span>
          </label>
        </div>

        <!-- Button -->
        <button class="btn" type="submit">Login as Admin</button>

        <p class="auth-sub" style="margin-top:1rem; font-size:0.85rem; opacity:0.8;">
          You are logging into the <strong>ADMIN</strong> portal, not a customer account.
        </p>
        <div class="auth-sub" style="font-size:0.9rem; margin-top: -1rem; margin-bottom: -4px">
          <a href="/pages/auth/login.php" class="link">Back to member login</a>
        </div>
      </form>
    </div>
  </section>
</main>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('adminLoginForm');
    if (!form) return;

    const usernameField = document.getElementById('admin_username');
    const passwordField = document.getElementById('admin_password');
    const usernameHint = document.getElementById('adminUsernameHint');
    const passwordHint = document.getElementById('adminPasswordHint');
    const captchaHint = document.getElementById('adminCaptchaHint');

    // Injected from PHP: whether CAPTCHA is currently required
    const adminRequireCaptcha = <?= $requireCaptcha ? 'true' : 'false' ?>;

    // Store default hint texts
    const hintDefaults = {};
    document.querySelectorAll('.field-hint').forEach(function(hint) {
      hintDefaults[hint.id] = hint.textContent;
    });

    function setError(field, hintEl, message) {
      if (field) field.classList.add('input--error');
      if (hintEl) {
        hintEl.textContent = message;
        hintEl.classList.add('field-hint--visible', 'hint--error');
      }
    }

    function clearError(field, hintEl, defaultMessage) {
      if (field) field.classList.remove('input--error');
      if (hintEl) {
        hintEl.textContent = defaultMessage;
        hintEl.classList.remove('hint--error');
      }
    }

    // Password toggle
    const adminPwdBtn = document.getElementById('toggleAdminPwd');
    adminPwdBtn?.addEventListener('click', () => {
      const show = passwordField.type === 'password';
      passwordField.type = show ? 'text' : 'password';
      adminPwdBtn.textContent = show ? '🙈' : '👁';
    });

    // Focus-based hints
    document.querySelectorAll('[data-hint]').forEach(function(field) {
      const hintId = field.getAttribute('data-hint');
      const hintEl = document.getElementById(hintId);
      if (!hintEl) return;

      field.addEventListener('focus', function() {
        hintEl.classList.add('field-hint--visible');
      });

      field.addEventListener('blur', function() {
        if (hintEl.classList.contains('hint--error')) return;
        hintEl.classList.remove('field-hint--visible');
      });
    });

    function validateForm() {
      let isValid = true;

      const username = usernameField.value.trim();
      const password = passwordField.value.trim();

      // Username – only required
      clearError(usernameField, usernameHint, 'Enter your admin username.');
      if (!username) {
        setError(usernameField, usernameHint, 'Admin username is required.');
        isValid = false;
      }

      // Password – required + min length
      clearError(passwordField, passwordHint, 'Enter your admin password.');
      if (!password) {
        setError(passwordField, passwordHint, 'Password is required.');
        isValid = false;
      }

      // If CAPTCHA is required on this page, do a quick client-side check
      if (adminRequireCaptcha && typeof grecaptcha !== 'undefined' && captchaHint) {
        const response = grecaptcha.getResponse();
        clearError(null, captchaHint, hintDefaults['adminCaptchaHint'] || 'Please complete the CAPTCHA verification.');
        if (!response) {
          setError(null, captchaHint, 'Please complete the CAPTCHA verification.');
          isValid = false;
        }
      }

      return isValid;
    }

    form.addEventListener('submit', function(e) {
      if (!validateForm()) {
        e.preventDefault();
      }
    });
  });
</script>

<?php include '../../partials/footer.php'; ?>
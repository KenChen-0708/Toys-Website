<?php
$title = "Login";
include '../partials/header.php';
require_once '../helper/connection.php';
require_once '../helper/auth.php';
require_guest();

$errors = $_SESSION['errors'] ?? [];
$old = $_SESSION['old'] ?? [];
$rememberedUsername = $_COOKIE['remember_username'] ?? '';
unset($_SESSION['errors'], $_SESSION['old']);
?>

<style>
  .alert--success {
    background: #dcfce7;
    color: #166534;
    border-left: 4px solid #16a34a;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
  }

  /* Input + toggle */
  .input-affix {
    position: relative;
    display: flex;
    align-items: center;
  }
  .input-affix button {
    position: absolute;
    right: .75rem;
    background: transparent;
    border: none;
    cursor: pointer;
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
      <li><span class="breadcrumb__link">Login</span></li>
    </ul>
  </section>

  <section class="auth-hero">
    <div class="auth-card">
      <h1 class="auth-title">Welcome Back</h1>
      <p class="auth-sub">Sign in to continue shopping</p>

      <?php if ($errors): ?>
        <div class="alert alert--danger" role="alert">
          <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form id="loginForm" class="form grid" method="POST" action="./login_post.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

        <!-- Username -->
        <div class="form__row">
          <label class="form__label">Username</label>
          <div class="input-affix">
            <input
              id="username"
              name="username"
              type="text"
              class="form__input"
              required
              autocomplete="username"
              data-hint="usernameHint"
              value="<?= htmlspecialchars($old['username'] ?? $rememberedUsername) ?>"
            />
          </div>
          <p id="usernameHint" class="field-hint">
            Enter your username.
          </p>
        </div>

        <!-- Password -->
        <div class="form__row">
          <label class="form__label">Password</label>
          <div class="input-affix">
            <input
              id="password"
              name="password"
              type="password"
              class="form__input"
              required
              minlength="6"
              autocomplete="current-password"
              data-hint="passwordHint"
            />
            <button type="button" id="togglePwd">👁</button>
          </div>
          <p id="passwordHint" class="field-hint">
            Enter your password.
          </p>
        </div>

        <div class="auth-meta">
          <label class="checkbox">
            <input type="checkbox" name="remember" value="1" <?= $rememberedUsername ? 'checked' : '' ?> />
            <span>Remember me</span>
          </label>
          <a href="./forgot_password.php" class="link">Forgot password?</a>
        </div>

        <button class="btn" type="submit">Login</button>

        <p class="auth-sub" style="margin-top: 1rem;">
          New here? <a href="./register.php" class="link">Create an account</a>
        </p>

        <div class="auth-sub" style="font-size:0.9rem; margin-top: -1rem; margin-bottom: -4px">
          <a href="./admin_login.php" class="link">Admin Login</a>
        </div>
      </form>
    </div>
  </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {

  const form = document.getElementById('loginForm');
  const username = document.getElementById('username');
  const password = document.getElementById('password');
  const usernameHint = document.getElementById('usernameHint');
  const passwordHint = document.getElementById('passwordHint');

  // Password toggle
  const btn = document.getElementById('togglePwd');
  btn?.addEventListener('click', () => {
    const show = password.type === 'password';
    password.type = show ? 'text' : 'password';
    btn.textContent = show ? '🙈' : '👁';
  });

  // Show hint on focus
  document.querySelectorAll('[data-hint]').forEach(field => {
    const hint = document.getElementById(field.dataset.hint);
    field.addEventListener('focus', () => hint.classList.add('field-hint--visible'));
    field.addEventListener('blur', () => {
      if (!hint.classList.contains('hint--error'))
        hint.classList.remove('field-hint--visible');
    });
  });

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

  form.addEventListener('submit', function (e) {
    let valid = true;

    // Username: only required
    clearError(username, usernameHint, "Enter your username.");
    if (!username.value.trim()) {
      setError(username, usernameHint, "Username is required.");
      valid = false;
    }

    // Password: required + minimum length (no more checks)
    clearError(password, passwordHint, "Enter your password.");
    if (!password.value.trim()) {
      setError(password, passwordHint, "Password is required.");
      valid = false;
    } else if (password.value.length < 6) {
      setError(password, passwordHint, "Password must be at least 6 characters.");
      valid = false;
    }

    if (!valid) e.preventDefault();
  });

});
</script>

<?php include '../partials/footer.php'; ?>

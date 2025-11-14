<?php
$title = "Reset Password";
include '../partials/header.php';
require_once '../helper/connection.php';
require_once '../helper/auth.php';
require_guest();

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

      <?php if ($errors): ?>
        <div class="alert alert--danger">
          <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form class="form grid" id="resetForm" action="./reset_password_post.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

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
            />
            <button
              type="button"
              id="togglePwd"
              class="password-toggle"
              aria-label="Toggle password visibility"
            >👁</button>
          </div>
        </div>

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
            />
            <button
              type="button"
              id="togglePwd2"
              class="password-toggle"
              aria-label="Toggle confirm password visibility"
            >👁</button>
          </div>
        </div>

        <button type="submit" class="btn">Update password</button>
      </form>
    </div>
  </section>
</main>

<script>
  const passwordInput = document.getElementById('password');
  const confirmInput = document.getElementById('password_confirmation');
  const togglePwdBtn = document.getElementById('togglePwd');
  const togglePwd2Btn = document.getElementById('togglePwd2');
  const form = document.getElementById('resetForm');

  // Same pattern as register page
  const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$/;

  // 👁 Eye toggle for New password
  togglePwdBtn?.addEventListener('click', () => {
    const show = passwordInput.type === 'password';
    passwordInput.type = show ? 'text' : 'password';
    togglePwdBtn.textContent = show ? '🙈' : '👁';
  });

  // 👁 Eye toggle for Confirm password
  togglePwd2Btn?.addEventListener('click', () => {
    const show = confirmInput.type === 'password';
    confirmInput.type = show ? 'text' : 'password';
    togglePwd2Btn.textContent = show ? '🙈' : '👁';
  });

  // Basic client-side validation for pattern + match
  form?.addEventListener('submit', function(e) {
    const pwd = passwordInput.value.trim();
    const pwd2 = confirmInput.value.trim();
    let errors = [];

    if (!passwordPattern.test(pwd)) {
      errors.push('Password must be at least 8 characters and include uppercase, lowercase, number and symbol.');
    }

    if (pwd !== pwd2) {
      errors.push('Passwords do not match.');
    }

    if (errors.length > 0) {
      e.preventDefault();
      alert(errors.join("\n"));
    }
  });
</script>

<?php include '../partials/footer.php'; ?>

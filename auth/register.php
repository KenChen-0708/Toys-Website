<?php
$title = "Register";
include '../partials/header.php';
require_once '../helper/connection.php';
require_once '../helper/auth.php';
require_guest();

$errors = $_SESSION['errors'] ?? [];
$old = $_SESSION['old'] ?? [];
unset($_SESSION['errors'], $_SESSION['old']);
?>

<style>
  /* Simple modal styling – you can move this to your main CSS file */

  .modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
  }

  .modal-overlay.is-visible {
    display: flex;
  }

  .modal {
    background: #fff;
    max-width: 420px;
    width: 90%;
    border-radius: 12px;
    padding: 1.5rem 1.75rem;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.25);
    animation: modal-pop 0.2s ease-out;
  }

  .modal__title {
    font-size: 1.15rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
  }

  .modal__body {
    font-size: 0.95rem;
    color: #444;
    max-height: 260px;
    overflow-y: auto;
  }

  .modal__body ul {
    padding-left: 1.1rem;
    margin: 0.25rem 0 0;
  }

  .modal__body li {
    margin-bottom: 0.25rem;
  }

  .modal__footer {
    margin-top: 1.2rem;
    text-align: right;
  }

  .modal__btn {
    border: none;
    padding: 0.45rem 1.1rem;
    border-radius: 999px;
    font-size: 0.9rem;
    cursor: pointer;
  }

  .modal__btn--primary {
    background: #111827;
    color: #fff;
  }

  .modal__btn--primary:hover {
    opacity: 0.9;
  }

  .modal__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: #fee2e2;
    color: #b91c1c;
    margin-right: 0.5rem;
    font-size: 1.1rem;
  }

  @keyframes modal-pop {
    from {
      opacity: 0;
      transform: translateY(8px) scale(0.97);
    }
    to {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
  }

  /* Password toggle button */
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
      <li><span class="breadcrumb__link">Register</span></li>
    </ul>
  </section>

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
            <span>User Name</span>
            <input name="name" type="text" class="form__input"
              required minlength="3" autocomplete="name"
              value="<?= htmlspecialchars($old['name'] ?? '') ?>"/>
          </label>

          <label class="form__label">
            <span>Email</span>
            <div class="input-with-icon">
              <input name="email" type="email" class="form__input"
                required autocomplete="email"
                value="<?= htmlspecialchars($old['email'] ?? '') ?>"/>
            </div>
          </label>

          <div class="grid two">
            <label class="form__label">
              <span>Password</span>
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
                  title="At least 8 characters, including uppercase, lowercase, number and symbol."
                />
                <button
                  type="button"
                  id="togglePwd"
                  class="password-toggle"
                  aria-label="Toggle password visibility"
                >👁</button>
              </div>
            </label>

            <label class="form__label">
              <span>Confirm Password</span>
              <div class="input-with-icon">
                <input
                  id="pwd2"
                  name="password_confirm"
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
            </label>
          </div>

          <label class="form__label">
            <span>Phone (optional)</span>
            <input name="phone" type="tel" class="form__input" placeholder="012-3456789" autocomplete="tel"
              value="<?= htmlspecialchars($old['phone'] ?? '') ?>"/>
          </label>

          <label class="form__label">
            <span>Address (optional)</span>
            <textarea name="address" class="form__input" rows="3"><?= htmlspecialchars($old['address'] ?? '') ?></textarea>
          </label>

          <button class="btn btn--primary btn--block" type="submit">Create account</button>
          <p class="auth-sub" style="margin-bottom: -5px;">Already have an account? <a href="./login.php" class="link">Login</a></p>
        </form>
      </div>
  </section>
</main>

<!-- ✅ Modal markup -->
<div id="errorModal" class="modal-overlay" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modal__header" style="display:flex;align-items:center;margin-bottom:0.5rem;">
      <div class="modal__icon">!</div>
      <h2 id="modalTitle" class="modal__title">Please fix these issues</h2>
    </div>
    <div class="modal__body">
      <p>We couldn't submit your form because of the following:</p>
      <ul id="modalErrorList"></ul>
    </div>
    <div class="modal__footer">
      <button type="button" class="modal__btn modal__btn--primary" id="modalCloseBtn">
        OK, got it
      </button>
    </div>
  </div>
</div>

<script>
  // Password match hint – optional, but leaving it here if you add #pwdHint later
  const pwd = document.getElementById('pwd');
  const pwd2 = document.getElementById('pwd2');
  const hint = document.getElementById('pwdHint'); // only used if element exists

  const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$/;

  function checkMatch() {
    if (!hint) return;
    if (pwd.value && pwd2.value && pwd.value !== pwd2.value) {
      hint.textContent = 'Passwords do not match.';
      hint.classList.add('hint--error');
    } else if (pwd.value && !passwordPattern.test(pwd.value)) {
      hint.textContent = 'Password must be at least 8 characters and include uppercase, lowercase, number and symbol.';
      hint.classList.add('hint--error');
    } else {
      hint.textContent = 'Use at least 8 characters with uppercase, lowercase, number and symbol.';
      hint.classList.remove('hint--error');
    }
  }
  pwd?.addEventListener('input', checkMatch);
  pwd2?.addEventListener('input', checkMatch);

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

  // ✅ Modal logic + client-side validation
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registerForm');
    const modal = document.getElementById('errorModal');
    const modalErrorList = document.getElementById('modalErrorList');
    const modalCloseBtn = document.getElementById('modalCloseBtn');

    if (!form) return;

    function showModal(errors) {
      // Clear old errors
      modalErrorList.innerHTML = '';
      errors.forEach(msg => {
        const li = document.createElement('li');
        li.textContent = msg;
        modalErrorList.appendChild(li);
      });

      modal.classList.add('is-visible');
      modal.setAttribute('aria-hidden', 'false');
    }

    function hideModal() {
      modal.classList.remove('is-visible');
      modal.setAttribute('aria-hidden', 'true');
    }

    modalCloseBtn?.addEventListener('click', hideModal);

    // Close when clicking outside the modal box
    modal?.addEventListener('click', function(e) {
      if (e.target === modal) hideModal();
    });

    form.addEventListener('submit', function(e) {
      const username = form.querySelector('input[name="name"]').value.trim();
      const email = form.querySelector('input[name="email"]').value.trim();
      const password = form.querySelector('input[name="password"]').value.trim();
      const passwordConfirm = form.querySelector('input[name="password_confirm"]').value.trim();

      let errors = [];

      if (!username) errors.push('Username is required.');
      if (!email) errors.push('Email is required.');
      if (!password) errors.push('Password is required.');
      if (!passwordConfirm) errors.push('Confirm Password is required.');

      if (password && !passwordPattern.test(password)) {
        errors.push('Password must be at least 8 characters and include uppercase, lowercase, number and symbol.');
      }

      if (password && passwordConfirm && password !== passwordConfirm) {
        errors.push('Passwords do not match.');
      }

      if (errors.length > 0) {
        e.preventDefault();
        showModal(errors);
      }
    });
  });
</script>

<?php include '../partials/footer.php'; ?>

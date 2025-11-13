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
  /* Modal styling – same style as register page */

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
</style>

<main class="main">
  <!--=============== BREADCRUMB ===============-->
  <section class="breadcrumb">
    <ul class="breadcrumb__list flex container">
      <li><a href="../index.php" class="breadcrumb__link">Home</a></li>
      <li><span class="breadcrumb__link">></span></li>
      <li><span class="breadcrumb__link">Login</span></li>
    </ul>
  </section>

  <!--=============== AUTH HERO (CENTERED CONTAINER) ===============-->
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
          <label class="form__label" for="username">Username</label>
          <div class="input-affix">
            <input
              id="username"
              name="username"
              type="text"
              class="form__input"
              required
              minlength="3"
              autocomplete="username"
              value="<?= htmlspecialchars($old['username'] ?? $rememberedUsername) ?>"
            />
          </div>
        </div>

        <!-- Password -->
        <div class="form__row">
          <label class="form__label" for="password">Password</label>
          <div class="input-affix">
            <input
              id="password"
              name="password"
              type="password"
              class="form__input"
              required
              autocomplete="current-password"
              minlength="6"
            />
            <button type="button" id="togglePwd" aria-label="Show password">👁</button>
          </div>
        </div>

        <!-- Remember / Forgot -->
        <div class="auth-meta">
          <label class="checkbox">
            <input
              type="checkbox"
              name="remember"
              value="1"
              <?= $rememberedUsername ? 'checked' : '' ?>
            />
            <span>Remember me</span>
          </label>
          <a href="./forgot_password.php" class="link">Forgot password?</a>
        </div>

        <!-- Button -->
        <button class="btn" type="submit">Login</button>

        <p class="auth-sub" style="margin-top:1rem">
          New here? <a href="./register.php" class="link">Create an account</a>
        </p>

        <div class="auth-sub" style="font-size:0.9rem; margin-top: -1rem; margin-bottom: -4px">
          <a href="./admin_login.php" class="link">Admin Login</a>
        </div>
      </form>
    </div>
  </section>
</main>

<!-- ✅ Login error modal -->
<div id="loginErrorModal" class="modal-overlay" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle">
    <div class="modal__header" style="display:flex;align-items:center;margin-bottom:0.5rem;">
      <div class="modal__icon">!</div>
      <h2 id="loginModalTitle" class="modal__title">We couldn’t log you in</h2>
    </div>
    <div class="modal__body">
      <p>Please fix the following before continuing:</p>
      <ul id="loginModalErrorList"></ul>
    </div>
    <div class="modal__footer">
      <button type="button" class="modal__btn modal__btn--primary" id="loginModalCloseBtn">
        OK, got it
      </button>
    </div>
  </div>
</div>

<script>
  // Password toggle (existing behavior)
  const btn = document.getElementById('togglePwd');
  const pwd = document.getElementById('password');
  btn?.addEventListener('click', () => {
    const show = pwd.type === 'password';
    pwd.type = show ? 'text' : 'password';
    btn.textContent = show ? '🙈' : '👁';
  });

  // Modal + client-side validation
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('loginForm');
    const modal = document.getElementById('loginErrorModal');
    const modalErrorList = document.getElementById('loginModalErrorList');
    const modalCloseBtn = document.getElementById('loginModalCloseBtn');

    if (!form) return;

    function showModal(errors) {
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
    modal?.addEventListener('click', function(e) {
      if (e.target === modal) hideModal();
    });

    form.addEventListener('submit', function(e) {
      const username = form.querySelector('input[name="username"]').value.trim();
      const password = form.querySelector('input[name="password"]').value.trim();

      let errors = [];

      if (!username) errors.push('Username is required.');
      if (!password) errors.push('Password is required.');
      if (password && password.length < 6) {
        errors.push('Password must be at least 6 characters.');
      }

      if (errors.length > 0) {
        e.preventDefault();
        showModal(errors);
      }
    });
  });
</script>

<?php include '../partials/footer.php'; ?>

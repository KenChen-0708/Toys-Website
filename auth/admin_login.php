<?php
$title = "Admin Login";
include '../partials/header.php';
require_once '../helper/connection.php';
require_once '../helper/auth.php';
// For now, reuse require_guest() so logged-in users can't hit admin login.
require_guest();

$errors = $_SESSION['admin_errors'] ?? [];
$old = $_SESSION['admin_old'] ?? [];
unset($_SESSION['admin_errors'], $_SESSION['admin_old']);
?>

<style>
  /* Admin modal styling – same base, slightly different accent */

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
    background: white;
    color: black;
    max-width: 420px;
    width: 90%;
    border-radius: 12px;
    padding: 1.5rem 1.75rem;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.65);
    animation: modal-pop 0.2s ease-out;
    border: 1px solid #1f2937;
  }

  .modal__title {
    font-size: 1.15rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
  }

  .modal__body {
    font-size: 0.95rem;
    color: darkgrey;
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
    background: #f97316;
    color: #111827;
    font-weight: 500;
  }

  .modal__btn--primary:hover {
    opacity: 0.95;
  }

  .modal__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 999px;
    background: #f97316;
    color: #111827;
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

  /* Extra small visual tweaks for admin card */
  .auth-card--admin {
    border-top: 4px solid #f97316;
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
    color: #f97316;
    margin-bottom: 0.5rem;
  }
</style>

<main class="main">
  <!--=============== BREADCRUMB ===============-->
  <section class="breadcrumb">
    <ul class="breadcrumb__list flex container">
      <li><a href="../index.php" class="breadcrumb__link">Home</a></li>
      <li><span class="breadcrumb__link">></span></li>
      <li><span class="breadcrumb__link">Admin Login</span></li>
    </ul>
  </section>

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
              minlength="3"
              autocomplete="username"
              value="<?= htmlspecialchars($old['username'] ?? '') ?>"
            />
          </div>
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
              minlength="6"
            />
            <button type="button" id="toggleAdminPwd" aria-label="Show password">👁</button>
          </div>
        </div>

        <!-- Remember / Back to member -->
        <div class="auth-meta">
          <label class="checkbox">
            <input type="checkbox" name="remember" value="1" />
            <span>Remember me</span>
          </label>
        </div>

        <!-- Button -->
        <button class="btn" type="submit">Login as Admin</button>

        <p class="auth-sub" style="margin-top:1rem; font-size:0.85rem; opacity:0.8;">
          You are logging into the <strong>ADMIN</strong> portal, not a customer account.
        </p>
        <div class="auth-sub" style="font-size:0.9rem; margin-top: -1rem; margin-bottom: -4px">
          <a href="./login.php" class="link">Back to member login</a>
        </div>
      </form>
    </div>
  </section>
</main>

<!-- ✅ Admin login error modal -->
<div id="adminLoginErrorModal" class="modal-overlay" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="adminLoginModalTitle">
    <div class="modal__header" style="display:flex;align-items:center;margin-bottom:0.5rem;">
      <div class="modal__icon">!</div>
      <h2 id="adminLoginModalTitle" class="modal__title">Unable to sign you in</h2>
    </div>
    <div class="modal__body">
      <p>Please fix the following before continuing:</p>
      <ul id="adminLoginModalErrorList"></ul>
    </div>
    <div class="modal__footer">
      <button type="button" class="modal__btn modal__btn--primary" id="adminLoginModalCloseBtn">
        OK, got it
      </button>
    </div>
  </div>
</div>

<script>
  // Password toggle (admin)
  const adminPwdBtn = document.getElementById('toggleAdminPwd');
  const adminPwd = document.getElementById('admin_password');
  adminPwdBtn?.addEventListener('click', () => {
    const show = adminPwd.type === 'password';
    adminPwd.type = show ? 'text' : 'password';
    adminPwdBtn.textContent = show ? '🙈' : '👁';
  });

  // Modal + client-side validation for admin login
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('adminLoginForm');
    const modal = document.getElementById('adminLoginErrorModal');
    const modalErrorList = document.getElementById('adminLoginModalErrorList');
    const modalCloseBtn = document.getElementById('adminLoginModalCloseBtn');

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

      if (!username) errors.push('Admin username is required.');
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

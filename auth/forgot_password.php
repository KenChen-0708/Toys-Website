<?php
$title = "Forgot Password";
include '../partials/header.php';
require_once '../helper/connection.php';
require_once '../helper/auth.php';
require_guest();

$errors = $_SESSION['errors'] ?? [];
$success = $_SESSION['success'] ?? null;
unset($_SESSION['errors'], $_SESSION['success']);
?>
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

      <form class="form grid" action="./forgot_password_post.php" method="POST">
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
          />
        </div>

        <button type="submit" class="btn">Send reset link</button>

        <p class="auth-sub" style="margin-top:1rem">
          Remembered it? <a href="./login.php" class="link">Back to login</a>
        </p>
      </form>
    </div>
  </section>
</main>

<?php include '../partials/footer.php'; ?>

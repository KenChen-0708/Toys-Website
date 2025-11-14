<?php
$title = "Reset Password";
include '../partials/header.php';
require_once '../helper/connection.php';
require_once '../helper/auth.php';
require_guest();

session_start();

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

$email = $_GET['email'] ?? '';
$token = $_GET['token'] ?? '';

?>
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

      <form class="form grid" action="./reset_password_post.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="form__row">
          <label class="form__label" for="password">New password</label>
          <input
            id="password"
            name="password"
            type="password"
            class="form__input"
            required
            minlength="6"
          />
        </div>

        <div class="form__row">
          <label class="form__label" for="password_confirmation">Confirm password</label>
          <input
            id="password_confirmation"
            name="password_confirmation"
            type="password"
            class="form__input"
            required
            minlength="6"
          />
        </div>

        <button type="submit" class="btn">Update password</button>
      </form>
    </div>
  </section>
</main>

<?php include '../partials/footer.php'; ?>

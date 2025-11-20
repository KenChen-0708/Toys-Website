<?php
$title = "Forgot Username";
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
<main class="main">
  <section class="auth-hero">
    <div class="auth-card">
      <h1 class="auth-title">Forgot your username?</h1>
      <p class="auth-sub">
        Enter your email and we’ll send you the username associated with that account.
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

      <form class="form grid" action="./forgot_username_post.php" method="POST">
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

        <button type="submit" class="btn">Send username</button>

        <p class="auth-sub" style="margin-top:1rem">
          Remember it now? <a href="./login.php" class="link">Back to login</a>
        </p>
      </form>
    </div>
  </section>
</main>

<?php include '../partials/footer.php'; ?>

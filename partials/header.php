<?php
require_once  __DIR__ . '/../helper/auth.php';
require_once  __DIR__ . '/../helper/pdo_connection.php';

if (!function_exists('user_initials')) {
  function user_initials($username)
  {
    $parts = preg_split('/\s+/', trim((string)$username));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $second = mb_substr($parts[1] ?? '', 0, 1);
    return mb_strtoupper($first . $second ?: $first);
  }
}

// Get current page for active link
$current_page = basename($_SERVER['PHP_SELF']);

// Fetch profile photo for logged-in users
$profilePhotoPath = null;
$defaultInitials = '';
if (is_logged_in() && isset($_SESSION['member']['id'])) {
  $memberId = $_SESSION['member']['id'];
  $profileStmt = $pdo->prepare("SELECT profile_photo_path, username FROM Member WHERE member_id = ?");
  $profileStmt->execute([$memberId]);
  $profileData = $profileStmt->fetch(PDO::FETCH_ASSOC);
  if ($profileData) {
    $profilePhotoPath = $profileData['profile_photo_path'];
    $defaultInitials = user_initials($profileData['username']);
  }
}
?>

<body class="<?php echo (isset($_SESSION['member']['theme']) && $_SESSION['member']['theme'] === 'dark') ? 'dark-theme' : ''; ?>">
  <!--=============== HEADER ===============-->
  <header class="header">
    <nav class="nav container">
      <a href="/index.php" class="nav__logo">
        <img class="nav__logo-img" src="<?php echo (isset($_SESSION['member']['theme']) && $_SESSION['member']['theme'] === 'dark') ? '/assets/img/logo_dark_theme.png' : '/assets/img/logo.png'; ?>" alt="website logo" />
      </a>
      <div class="nav__menu" id="nav-menu">
        <ul class="nav__list">
          <li><a href="/index.php" class="nav__link <?= $current_page === 'index.php' ? 'active-link' : '' ?>">Home</a></li>
          <li><a href="/about.php" class="nav__link <?= $current_page === 'about.php' ? 'active-link' : '' ?>">About us</a></li>
          <li><a href="/pages/product/product_list.php" class="nav__link <?= $current_page === 'product_list.php' ? 'active-link' : '' ?>">Product</a></li>
          <li><a href="/feedback.php" class="nav__link <?= $current_page === 'feedback.php' ? 'active-link' : '' ?>">Feedback</a></li>
        </ul>
      </div>

      <div class="header__user-actions">
        <a href="/pages/wishlist/wishlist.php" class="header__action-btn" title="Wishlist">
          <img src="/assets/img/icon-heart.svg" alt="" />
          <span class="count">3</span>
        </a>
        <a href="/pages/cart/cart.php" class="header__action-btn" title="Cart">
          <img src="/assets/img/icon-cart.svg" alt="" />
          <span class="count">3</span>
        </a>
      </div>

      <div class="header__auth">
        <?php if (is_logged_in()): ?>
          <div class="header__user-profile">
            <button class="avatar-btn" type="button">
              <span class="avatar-circle">
                <?php if (!empty($profilePhotoPath) && file_exists(__DIR__ . '/..' . $profilePhotoPath)): ?>
                  <img src="<?= htmlspecialchars($profilePhotoPath) ?>" alt="Profile photo">
                <?php else: ?>
                  <?= htmlspecialchars($defaultInitials) ?>
                <?php endif; ?>
              </span>
            </button>
            <div class="profile-menu">
              <div class="profile-menu__header">
                <strong><?= htmlspecialchars($_SESSION['member']['username']) ?></strong>
                <small><?= htmlspecialchars($_SESSION['member']['email']) ?></small>
              </div>
              <a href="/accounts.php">My Account</a>
              <a href="/orders.php">My Orders</a>
              <a href="/setting.php">Setting</a>
              <form action="/pages/auth/logout.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <button type="submit" class="logout-btn">Log Out</button>
              </form>
            </div>
          </div>
        <?php else: ?>
          <button class="login-btn" onclick="window.location.href='/pages/auth/login.php'">Log In</button>
          <button class="register-btn" onclick="window.location.href='/pages/auth/register.php'">Register</button>
        <?php endif; ?>
      </div>
    </nav>
  </header>

  <script>
    document.addEventListener('click', function(e) {
      const prof = e.target.closest('.header__user-profile');
      document.querySelectorAll('.header__user-profile .profile-menu').forEach(m => m.style.display = 'none');
      if (prof && e.target.closest('.avatar-btn')) {
        const menu = prof.querySelector('.profile-menu');
        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
      }
    });
  </script>
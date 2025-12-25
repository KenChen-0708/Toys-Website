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
          <?php if (is_logged_in()): ?>
            <span class="count wishlist-count"></span>
          <?php endif; ?>
        </a>
        <a href="/pages/cart/cart.php" class="header__action-btn" title="Cart">
          <img src="/assets/img/icon-cart.svg" alt="" />
          <?php if (is_logged_in()): ?>
            <span class="count cart-count"></span>
          <?php endif; ?>
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
              <svg class="dropdown-icon" width="12" height="8" viewBox="0 0 12 8" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path d="M6 8C5.72 8 5.47 7.89 5.29 7.71L0.88 3.3C0.31 2.73 0.69 1.75 1.47 1.75H10.53C11.31 1.75 11.69 2.73 11.12 3.3L6.71 7.71C6.53 7.89 6.28 8 6 8Z" fill="currentColor"/>
              </svg>
            </button>
            <div class="profile-menu">
              <div class="profile-menu__header">
                <strong><?= htmlspecialchars($_SESSION['member']['username']) ?></strong>
                <small><?= htmlspecialchars($_SESSION['member']['email']) ?></small>
              </div>
              <a href="/accounts.php" class="profile-menu-item">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>My Account</span>
              </a>
              <a href="/orders.php" class="profile-menu-item">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                  <line x1="3" y1="6" x2="21" y2="6"></line>
                  <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                <span>My Orders</span>
              </a>
              <a href="/setting.php" class="profile-menu-item">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <circle cx="12" cy="12" r="3"></circle>
                  <path d="M12 1v6m0 6v6m-7.071-7.071l4.243 4.243m4.242-4.243l4.243 4.243m-12.728 0l4.243-4.243m4.242 4.243l4.243-4.243"></path>
                </svg>
                <span>Settings</span>
              </a>
              <form action="/pages/auth/logout.php" method="post" style="margin: 0;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <button type="submit" class="logout-btn profile-menu-item">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                  </svg>
                  <span>Log Out</span>
                </button>
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

  <!-- Header counts management script (requires jQuery) -->
  <script src="/assets/js/header-counts.js"></script>
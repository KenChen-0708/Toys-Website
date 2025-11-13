<?php
  $title = $title ?? 'Online Store';
  require_once  __DIR__ . '/../helper/auth.php';

  function user_initials($name) {
    $parts = preg_split('/\s+/', trim((string)$name));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $second = mb_substr($parts[1] ?? '', 0, 1);
    return mb_strtoupper($first . $second ?: $first);
  }
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
    <link rel="stylesheet" href="/online_store/assets/css/styles.css" />

    <title><?= htmlspecialchars($title) ?></title>
  </head>
  <body>
    <!--=============== HEADER ===============-->
    <header class="header">
      <div class="header__top">
        <div class="header__container container">
          <div class="header__contact">
            <span>(+01) - 2345 - 6789</span>
            <span>Our location</span>
          </div>
          <p class="header__alert-news">
            Super Values Deals - Save more coupons
          </p>
          <div>
            <?php if (is_logged_in()): ?>
              <div class="header__user-profile">
                <button class="avatar-btn" aria-haspopup="true" aria-expanded="false">
                  <span class="avatar-circle"><?= htmlspecialchars(user_initials($_SESSION['member']['name'])) ?></span>
                </button>
                <div class="profile-menu">
                  <div class="profile-menu__header">
                    <strong><?= htmlspecialchars($_SESSION['member']['name']) ?></strong><br>
                    <small><?= htmlspecialchars($_SESSION['member']['email']) ?></small>
                  </div>
                  <a href="/online_store/accounts.php">My Account</a>
                  <a href="/online_store/orders.php">My Orders</a>
                  <form action="/online_store/auth/logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <button type="submit" class="logout-btn">Log Out</button>
                  </form>
                </div>
              </div>
            <?php else: ?>
              <a href="/online_store/auth/login.php" class="header__top-action">Log In</a>
              <a href="/online_store/auth/register.php" class="header__top-action">Register</a>
            <?php endif; ?>
          </div>


          
        </div>
      </div>

      <nav class="nav container">
        <a href="/online_store/index.php" class="nav__logo">
          <img class="nav__logo-img" src="/online_store/assets/img/logo.jpg" alt="website logo" />
        </a>
        <div class="nav__menu" id="nav-menu">
          <ul class="nav__list">
            <li><a href="/online_store/index.php" class="nav__link">Home</a></li>
            <li><a href="/online_store/shop.php" class="nav__link">Shop</a></li>
            <li><a href="/online_store/accounts.php" class="nav__link active-link">My Account</a></li>
            <li><a href="/online_store/compare.php" class="nav__link">Compare</a></li>
          </ul>
          <div class="header__search">
            <input type="text" placeholder="Search For Items..." class="form__input" />
            <button class="search__btn">
              <img src="/online_store/assets/img/search.png" alt="search icon" />
            </button>
          </div>
        </div>

        <div class="header__user-actions">
          <a href="/online_store/wishlist.php" class="header__action-btn" title="Wishlist">
            <img src="/online_store/assets/img/icon-heart.svg" alt="" />
            <span class="count">3</span>
          </a>
          <a href="/online_store/cart.php" class="header__action-btn" title="Cart">
            <img src="/online_store/assets/img/icon-cart.svg" alt="" />
            <span class="count">3</span>
          </a>
        </div>
      </nav>
    </header>

<script>
  document.addEventListener('click', function(e){
    const prof = e.target.closest('.header__user-profile');
    document.querySelectorAll('.header__user-profile .profile-menu').forEach(m => m.style.display = 'none');
    if (prof && e.target.closest('.avatar-btn')) {
      const menu = prof.querySelector('.profile-menu');
      menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }
  });
</script>




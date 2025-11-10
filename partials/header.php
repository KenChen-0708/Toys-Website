<?php
  $title = $title ?? 'Ecommerce Website';
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

    <!--=============== SWIPER CSS ===============-->
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"
    />

    <!--=============== CSS ===============-->
    <link rel="stylesheet" href="./assets/css/styles.css" />

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
          <a href="login-register.php" class="header__top-action">
            Log In / Sign Up
          </a>
        </div>
      </div>

      <nav class="nav container">
        <a href="index.php" class="nav__logo">
          <img class="nav__logo-img" src="./assets/img/logo.svg" alt="website logo" />
        </a>
        <div class="nav__menu" id="nav-menu">
          <ul class="nav__list">
            <li><a href="index.php" class="nav__link">Home</a></li>
            <li><a href="shop.php" class="nav__link">Shop</a></li>
            <li><a href="accounts.php" class="nav__link active-link">My Account</a></li>
            <li><a href="compare.php" class="nav__link">Compare</a></li>
            <li><a href="login-register.php" class="nav__link">Login</a></li>
          </ul>
          <div class="header__search">
            <input type="text" placeholder="Search For Items..." class="form__input" />
            <button class="search__btn">
              <img src="./assets/img/search.png" alt="search icon" />
            </button>
          </div>
        </div>

        <div class="header__user-actions">
          <a href="wishlist.php" class="header__action-btn" title="Wishlist">
            <img src="./assets/img/icon-heart.svg" alt="" />
            <span class="count">3</span>
          </a>
          <a href="cart.php" class="header__action-btn" title="Cart">
            <img src="./assets/img/icon-cart.svg" alt="" />
            <span class="count">3</span>
          </a>
        </div>
      </nav>
    </header>

<?php 
session_start();
$title = "Products";
require_once 'helper/pdo_connection.php';
require_once __DIR__ . '/helper/auth.php';

function user_initials($name) {
  $parts = preg_split('/\s+/', trim((string)$name));
  $first = mb_substr($parts[0] ?? '', 0, 1);
  $second = mb_substr($parts[1] ?? '', 0, 1);
  return mb_strtoupper($first . $second ?: $first);
}

// Get categories for filter
$cat_query = "SELECT DISTINCT category FROM Product WHERE category IS NOT NULL AND category != '' ORDER BY category";
$categories = $pdo->query($cat_query)->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.0.0/uicons-regular-straight/css/uicons-regular-straight.css" />
  <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/2.0.0/uicons-solid-straight/css/uicons-solid-straight.css" />
  <title><?= htmlspecialchars($title) ?></title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --primary-color: #2563eb;
      --primary-dark: #1e40af;
      --text-dark: #1f2937;
      --text-light: #6b7280;
      --border-color: #e5e7eb;
      --bg-light: #f9fafb;
      --white: #ffffff;
      --header-bg: #ffffff;
      --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
      --radius-sm: 0.375rem;
      --radius-md: 0.5rem;
      --radius-lg: 0.75rem;
      --radius-xl: 1rem;
      --transition: all 0.3s ease;
    }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      color: var(--text-dark);
      background: var(--bg-light);
      line-height: 1.6;
    }

    /* ========== HEADER ========== */
    .header {
      background: var(--header-bg);
      box-shadow: var(--shadow-sm);
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .header__top {
      background: var(--text-dark);
      color: var(--white);
      padding: 0.5rem 0;
      font-size: 0.875rem;
    }

    .header__container {
      max-width: 1320px;
      margin: 0 auto;
      padding: 0 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      flex-wrap: wrap;
    }

    .header__contact {
      display: flex;
      gap: 1.5rem;
    }

    .header__alert-news {
      font-size: 0.875rem;
    }

    .header__top-action {
      color: var(--white);
      text-decoration: none;
      margin-left: 1rem;
      transition: var(--transition);
    }

    .header__top-action:hover {
      color: var(--primary-color);
    }

    .header__user-profile {
      position: relative;
      display: inline-block;
    }

    .avatar-btn {
      background: none;
      border: none;
      cursor: pointer;
      padding: 0;
    }

    .avatar-circle {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--primary-color);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--white);
    }

    .profile-menu {
      display: none;
      position: absolute;
      top: calc(100% + 0.5rem);
      right: 0;
      background: var(--white);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-lg);
      min-width: 200px;
      overflow: hidden;
      color: var(--text-dark);
    }

    .profile-menu__header {
      padding: 1rem;
      border-bottom: 1px solid var(--border-color);
    }

    .profile-menu a,
    .profile-menu button {
      display: block;
      width: 100%;
      padding: 0.75rem 1rem;
      text-decoration: none;
      color: var(--text-dark);
      border: none;
      background: none;
      text-align: left;
      cursor: pointer;
      transition: var(--transition);
      font-size: 0.875rem;
    }

    .profile-menu a:hover,
    .profile-menu button:hover {
      background: var(--bg-light);
    }

    .nav {
      max-width: 1320px;
      margin: 0 auto;
      padding: 1rem 1.5rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 2rem;
    }

    .nav__logo-img {
      height: 40px;
    }

    .nav__menu {
      display: flex;
      align-items: center;
      gap: 2rem;
      flex: 1;
    }

    .nav__list {
      display: flex;
      list-style: none;
      gap: 2rem;
    }

    .nav__link {
      text-decoration: none;
      color: var(--text-dark);
      font-weight: 500;
      transition: var(--transition);
    }

    .nav__link:hover,
    .nav__link.active-link {
      color: var(--primary-color);
    }

    .header__search {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      flex: 1;
      max-width: 400px;
    }

    .form__input {
      flex: 1;
      padding: 0.625rem 1rem;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      font-size: 0.875rem;
    }

    .search__btn {
      background: var(--primary-color);
      border: none;
      padding: 0.625rem 1rem;
      border-radius: var(--radius-md);
      cursor: pointer;
      transition: var(--transition);
    }

    .search__btn:hover {
      background: var(--primary-dark);
    }

    .search__btn img {
      width: 20px;
      height: 20px;
      filter: brightness(0) invert(1);
    }

    .header__user-actions {
      display: flex;
      gap: 1rem;
    }

    .header__action-btn {
      position: relative;
      text-decoration: none;
    }

    .header__action-btn img {
      width: 24px;
      height: 24px;
    }

    .header__action-btn .count {
      position: absolute;
      top: -8px;
      right: -8px;
      background: var(--primary-color);
      color: var(--white);
      font-size: 0.75rem;
      font-weight: 600;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* ========== MAIN CONTENT ========== */
    .products-page {
      padding: 3rem 0;
      min-height: calc(100vh - 200px);
    }

    .container {
      max-width: 1320px;
      margin: 0 auto;
      padding: 0 1.5rem;
    }

    .page-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2.5rem;
      gap: 2rem;
      flex-wrap: wrap;
    }

    .page-title {
      font-size: 2.25rem;
      font-weight: 700;
      color: var(--text-dark);
      margin: 0;
      line-height: 1.2;
    }

    .page-subtitle {
      font-size: 1rem;
      color: var(--text-light);
      margin-top: 0.5rem;
    }

    .product-count {
      font-weight: 600;
      color: var(--primary-color);
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .mobile-filter-btn {
      display: none;
      align-items: center;
      gap: 0.5rem;
      padding: 0.625rem 1rem;
      background: var(--white);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--text-dark);
      cursor: pointer;
      transition: var(--transition);
    }

    .mobile-filter-btn:hover {
      background: var(--bg-light);
    }

    .sort-wrapper {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .sort-label {
      font-size: 0.875rem;
      font-weight: 500;
      color: var(--text-dark);
    }

    .sort-select {
      padding: 0.625rem 2.5rem 0.625rem 1rem;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      font-size: 0.875rem;
      color: var(--text-dark);
      background: var(--white);
      cursor: pointer;
      transition: var(--transition);
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236b7280' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
    }

    .sort-select:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .products-layout {
      display: grid;
      grid-template-columns: 280px 1fr;
      gap: 2rem;
      position: relative;
    }

    /* ========== FILTERS SIDEBAR ========== */
    .filters-sidebar {
      background: var(--white);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-xl);
      padding: 1.5rem;
      height: fit-content;
      position: sticky;
      top: 120px;
    }

    .sidebar-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      padding-bottom: 1rem;
      border-bottom: 1px solid var(--border-color);
    }

    .sidebar-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--text-dark);
      margin: 0;
    }

    .close-sidebar {
      display: none;
      background: none;
      border: none;
      font-size: 1.25rem;
      color: var(--text-light);
      cursor: pointer;
      padding: 0.25rem;
    }

    .filter-group {
      margin-bottom: 1.5rem;
    }

    .filter-label {
      display: block;
      font-size: 0.875rem;
      font-weight: 600;
      color: var(--text-dark);
      margin-bottom: 0.5rem;
    }

    .search-wrapper {
      position: relative;
    }

    .search-icon {
      position: absolute;
      left: 1rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
      font-size: 1rem;
      pointer-events: none;
    }

    .search-input {
      width: 100%;
      padding: 0.625rem 1rem 0.625rem 2.75rem;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      font-size: 0.875rem;
      transition: var(--transition);
    }

    .search-input:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .filter-select {
      width: 100%;
      padding: 0.625rem 1rem;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      font-size: 0.875rem;
      color: var(--text-dark);
      background: var(--white);
      cursor: pointer;
      transition: var(--transition);
    }

    .filter-select:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .price-inputs {
      display: grid;
      grid-template-columns: 1fr auto 1fr;
      align-items: center;
      gap: 0.5rem;
    }

    .price-input {
      width: 100%;
      padding: 0.625rem 0.75rem;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      font-size: 0.875rem;
      transition: var(--transition);
      min-width: 0;
    }

    .price-input:focus {
      outline: none;
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .price-separator {
      color: var(--text-light);
      font-weight: 500;
      font-size: 0.875rem;
    }

    .filter-actions {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      margin-top: 2rem;
      padding-top: 1.5rem;
      border-top: 1px solid var(--border-color);
    }

    .btn {
      padding: 0.75rem 1.5rem;
      border-radius: var(--radius-md);
      font-size: 0.875rem;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      border: none;
      text-align: center;
    }

    .btn-primary {
      background: var(--primary-color);
      color: var(--white);
    }

    .btn-primary:hover {
      background: var(--primary-dark);
      transform: translateY(-1px);
      box-shadow: var(--shadow-md);
    }

    .btn-secondary {
      background: var(--bg-light);
      color: var(--text-dark);
      border: 1px solid var(--border-color);
    }

    .btn-secondary:hover {
      background: var(--white);
      border-color: var(--text-dark);
    }

    .btn-block {
      width: 100%;
    }

    /* ========== PRODUCTS GRID ========== */
    .products-content {
      min-height: 500px;
      position: relative;
    }

    .loading-overlay {
      display: none;
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(255, 255, 255, 0.8);
      z-index: 10;
      align-items: center;
      justify-content: center;
    }

    .loading-overlay.active {
      display: flex;
    }

    .spinner {
      width: 48px;
      height: 48px;
      border: 4px solid var(--border-color);
      border-top-color: var(--primary-color);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .products-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
      gap: 1.5rem;
      margin-bottom: 3rem;
    }

    .product-card {
      background: var(--white);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-xl);
      overflow: hidden;
      transition: var(--transition);
      position: relative;
      z-index: 1;
    }

    .product-card:hover {
      box-shadow: var(--shadow-lg);
      transform: translateY(-4px);
      border-color: transparent;
    }

    .product-card.out-of-stock {
      opacity: 0.7;
    }

    .product-image-wrapper {
      position: relative;
      overflow: hidden;
      background: var(--bg-light);
      aspect-ratio: 1 / 1;
    }

    .product-link {
      display: block;
      height: 100%;
    }

    .product-image {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: var(--transition);
    }

    .product-image.secondary {
      position: absolute;
      top: 0;
      left: 0;
      opacity: 0;
    }

    .product-card:hover .product-image.primary {
      opacity: 0;
    }

    .product-card:hover .product-image.secondary {
      opacity: 1;
    }

    .product-card:hover .product-image {
      transform: scale(1.05);
    }

    .product-badge {
      position: absolute;
      top: 1rem;
      left: 1rem;
      padding: 0.375rem 0.75rem;
      border-radius: var(--radius-md);
      font-size: 0.75rem;
      font-weight: 600;
      z-index: 1;
    }

    .badge-sold {
      background: #ef4444;
      color: var(--white);
    }

    .product-actions {
      position: absolute;
      top: 1rem;
      right: 1rem;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      opacity: 0;
      transform: translateX(10px);
      transition: var(--transition);
      z-index: 10;
    }

    .product-card:hover .product-actions {
      opacity: 1;
      transform: translateX(0);
    }

    .action-btn {
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--white);
      border: 1px solid var(--border-color);
      border-radius: 50%;
      color: var(--text-dark);
      font-size: 1rem;
      cursor: pointer;
      transition: var(--transition);
      text-decoration: none;
      position: relative;
      z-index: 10;
    }

    .action-btn:hover {
      background: var(--primary-color);
      color: var(--white);
      border-color: var(--primary-color);
      transform: scale(1.1);
    }

    .product-info {
      padding: 1.25rem;
    }

    .product-category {
      display: inline-block;
      font-size: 0.75rem;
      font-weight: 500;
      color: var(--text-light);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 0.5rem;
    }

    .product-name {
      margin: 0 0 0.75rem;
      font-size: 1rem;
      font-weight: 600;
      line-height: 1.4;
    }

    .product-name a {
      color: var(--text-dark);
      text-decoration: none;
      transition: var(--transition);
    }

    .product-name a:hover {
      color: var(--primary-color);
    }

    .product-rating {
      display: flex;
      gap: 0.25rem;
      margin-bottom: 1rem;
      color: #fbbf24;
      font-size: 0.875rem;
    }

    .product-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .product-price {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text-dark);
    }

    .add-to-cart-btn {
      width: 44px;
      height: 44px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--primary-color);
      border: none;
      border-radius: 50%;
      color: var(--white);
      font-size: 1.125rem;
      cursor: pointer;
      transition: var(--transition);
      position: relative;
      z-index: 10;
    }

    .add-to-cart-btn:hover:not(:disabled) {
      background: var(--primary-dark);
      transform: scale(1.1);
    }

    .add-to-cart-btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* ========== PAGINATION ========== */
    .pagination {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 0.5rem;
      margin-top: 3rem;
    }

    .pagination-btn,
    .pagination-number {
      min-width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0 0.75rem;
      background: var(--white);
      border: 1px solid var(--border-color);
      border-radius: var(--radius-md);
      color: var(--text-dark);
      font-size: 0.875rem;
      font-weight: 500;
      text-decoration: none;
      transition: var(--transition);
      cursor: pointer;
    }

    .pagination-btn:hover,
    .pagination-number:hover {
      background: var(--bg-light);
      border-color: var(--text-dark);
    }

    .pagination-number.active {
      background: var(--primary-color);
      color: var(--white);
      border-color: var(--primary-color);
    }

    .pagination-ellipsis {
      padding: 0 0.5rem;
      color: var(--text-light);
    }

    .pagination-numbers {
      display: flex;
      gap: 0.5rem;
    }

    /* ========== EMPTY STATE ========== */
    .empty-state {
      text-align: center;
      padding: 4rem 2rem;
    }

    .empty-icon {
      font-size: 4rem;
      color: var(--text-light);
      margin-bottom: 1.5rem;
    }

    .empty-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text-dark);
      margin: 0 0 0.75rem;
    }

    .empty-text {
      font-size: 1rem;
      color: var(--text-light);
      margin: 0 0 2rem;
    }

    /* ========== SIDEBAR OVERLAY ========== */
    .sidebar-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 999;
      opacity: 0;
      transition: opacity 0.3s ease;
      pointer-events: none;
    }

    .sidebar-overlay.active {
      opacity: 1;
      pointer-events: auto;
    }

    /* ========== TOAST NOTIFICATIONS ========== */
    .toast {
      position: fixed;
      top: 20px;
      right: 20px;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 1rem 1.25rem;
      background: white;
      border-radius: 0.75rem;
      box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      z-index: 10000;
      opacity: 0;
      transform: translateX(100px);
      transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
      min-width: 280px;
      max-width: 400px;
    }

    .toast.show {
      opacity: 1;
      transform: translateX(0);
    }

    .toast-icon {
      font-size: 1.25rem;
      flex-shrink: 0;
    }

    .toast-message {
      font-size: 0.875rem;
      font-weight: 500;
      color: #1f2937;
    }

    .toast--success {
      border-left: 4px solid #10b981;
    }

    .toast--success .toast-icon {
      color: #10b981;
    }

    .toast--error {
      border-left: 4px solid #ef4444;
    }

    .toast--error .toast-icon {
      color: #ef4444;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); }
      50% { transform: scale(1.2); }
    }

    .pulse {
      animation: pulse 0.5s ease-in-out;
    }

    /* ========== RESPONSIVE ========== */
    @media screen and (max-width: 1200px) {
      .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      }
    }

    @media screen and (max-width: 992px) {
      .nav__list {
        display: none;
      }

      .header__search {
        max-width: 300px;
      }

      .products-layout {
        grid-template-columns: 1fr;
      }

      .mobile-filter-btn {
        display: flex;
      }

      .filters-sidebar {
        position: fixed;
        top: 0;
        left: -100%;
        width: 320px;
        max-width: 90%;
        height: 100vh;
        overflow-y: auto;
        z-index: 1000;
        border-radius: 0;
        transition: left 0.3s ease;
      }

      .filters-sidebar.active {
        left: 0;
      }

      .close-sidebar {
        display: block;
      }

      .sidebar-overlay {
        display: block;
        pointer-events: none;
      }
      
      .sidebar-overlay.active {
        pointer-events: auto;
      }

      .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      }
    }

    @media screen and (max-width: 768px) {
      .header__contact,
      .header__alert-news {
        display: none;
      }

      .products-page {
        padding: 2rem 0;
      }

      .page-header {
        flex-direction: column;
        align-items: flex-start;
      }

      .page-title {
        font-size: 1.875rem;
      }

      .header-actions {
        width: 100%;
        justify-content: space-between;
      }

      .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 1rem;
      }

      .product-info {
        padding: 1rem;
      }

      .product-name {
        font-size: 0.875rem;
      }

      .product-price {
        font-size: 1.25rem;
      }

      .add-to-cart-btn {
        width: 38px;
        height: 38px;
        font-size: 1rem;
      }
    }

    @media screen and (max-width: 576px) {
      .container {
        padding: 0 1rem;
      }

      .page-title {
        font-size: 1.5rem;
      }

      .products-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .product-actions {
        opacity: 1;
        transform: translateX(0);
      }

      .action-btn {
        width: 36px;
        height: 36px;
        font-size: 0.875rem;
        z-index: 10;
      }
      
      .add-to-cart-btn {
        z-index: 10;
      }

      .toast {
        right: 10px;
        left: 10px;
        min-width: auto;
      }
    }
  </style>
</head>
<body>
  <!-- ========== HEADER ========== -->
  <header class="header">
    <div class="header__top">
      <div class="header__container">
        <div class="header__contact">
          <span>(+01) - 2345 - 6789</span>
          <span>Our location</span>
        </div>
        <p class="header__alert-news">Super Values Deals - Save more coupons</p>
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
                <a href="/accounts.php">My Account</a>
                <a href="/orders.php">My Orders</a>
                <form action="/auth/logout.php" method="post">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <button type="submit" class="logout-btn">Log Out</button>
                </form>
              </div>
            </div>
          <?php else: ?>
            <a href="/auth/login.php" class="header__top-action">Log In</a>
            <a href="/auth/register.php" class="header__top-action">Register</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <nav class="nav container">
      <a href="/index.php" class="nav__logo">
        <img class="nav__logo-img" src="/assets/img/logo.jpg" alt="website logo" />
      </a>
      <div class="nav__menu" id="nav-menu">
        <ul class="nav__list">
          <li><a href="/index.php" class="nav__link">Home</a></li>
          <li><a href="/product_list.php" class="nav__link active-link">Product</a></li>
          <li><a href="/accounts.php" class="nav__link">My Account</a></li>
          <li><a href="/compare.php" class="nav__link">Compare</a></li>
        </ul>
        <div class="header__search">
          <input type="text" placeholder="Search For Items..." class="form__input" />
          <button class="search__btn">
            <img src="/assets/img/search.png" alt="search icon" />
          </button>
        </div>
      </div>

      <div class="header__user-actions">
        <a href="/wishlist.php" class="header__action-btn" title="Wishlist">
          <img src="/assets/img/icon-heart.svg" alt="Wishlist" />
          <span class="count wishlist-count">0</span>
        </a>
        <a href="/cart.php" class="header__action-btn" title="Cart">
          <img src="/assets/img/icon-cart.svg" alt="Cart" />
          <span class="count cart-count">0</span>
        </a>
      </div>
    </nav>
  </header>

  <!-- ========== MAIN CONTENT ========== -->
  <main class="products-page">
    <div class="container">
      <!-- Page Header -->
      <div class="page-header">
        <div class="header-content">
          <h1 class="page-title">Discover Products</h1>
          <p class="page-subtitle">
            <span class="product-count" id="productCount">0</span> products available
          </p>
        </div>
        
        <div class="header-actions">
          <button class="mobile-filter-btn" id="mobileFilterBtn">
            <i class="fi fi-rs-settings-sliders"></i>
            <span>Filters</span>
          </button>
          
          <div class="sort-wrapper">
            <label for="sortSelect" class="sort-label">Sort by</label>
            <select id="sortSelect" class="sort-select">
              <option value="newest">Latest</option>
              <option value="price_low">Price: Low to High</option>
              <option value="price_high">Price: High to Low</option>
              <option value="name_asc">Name: A-Z</option>
              <option value="name_desc">Name: Z-A</option>
            </select>
          </div>
        </div>
      </div>

      <div class="products-layout">
        <!-- Filters Sidebar -->
        <aside class="filters-sidebar" id="filterSidebar">
          <div class="sidebar-header">
            <h2 class="sidebar-title">Filters</h2>
            <button class="close-sidebar" id="closeSidebar">
              <i class="fi fi-rs-cross"></i>
            </button>
          </div>

          <div class="filter-group">
            <label class="filter-label">Search</label>
            <div class="search-wrapper">
              <i class="fi fi-rs-search search-icon"></i>
              <input type="text" id="searchInput" class="search-input" placeholder="Search products...">
            </div>
          </div>

          <div class="filter-group">
            <label class="filter-label">Category</label>
            <select id="categorySelect" class="filter-select">
              <option value="">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>">
                  <?php echo htmlspecialchars($cat); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="filter-group">
            <label class="filter-label">Price Range</label>
            <div class="price-inputs">
              <input type="number" id="minPrice" class="price-input" placeholder="Min" min="0" step="0.01">
              <span class="price-separator">—</span>
              <input type="number" id="maxPrice" class="price-input" placeholder="Max" min="0" step="0.01">
            </div>
          </div>

          <div class="filter-actions">
            <button class="btn btn-primary btn-block" id="applyFilters">Apply Filters</button>
            <button class="btn btn-secondary btn-block" id="clearFilters">Clear All</button>
          </div>
        </aside>

        <!-- Products Content -->
        <div class="products-content">
          <div class="loading-overlay" id="loadingOverlay">
            <div class="spinner"></div>
          </div>

          <div class="products-grid" id="productsGrid">
            <!-- Products will be loaded here via AJAX -->
          </div>

          <!-- Pagination -->
          <nav class="pagination" id="pagination" style="display: none;">
            <!-- Pagination will be generated via AJAX -->
          </nav>
        </div>
      </div>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>
  </main>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    $(document).ready(function() {
      let currentPage = 1;
      let isLoading = false;

      // Initialize - load products on page load
      loadProducts();

      // Mobile filter toggle
      $('#mobileFilterBtn').on('click', function() {
        $('#filterSidebar').addClass('active');
        $('#sidebarOverlay').addClass('active');
        $('body').css('overflow', 'hidden');
      });

      // Close sidebar
      $('#closeSidebar, #sidebarOverlay').on('click', function() {
        closeMobileSidebar();
      });

      // Apply filters
      $('#applyFilters').on('click', function() {
        currentPage = 1;
        loadProducts();
        closeMobileSidebar();
      });

      // Clear filters
      $('#clearFilters').on('click', function() {
        $('#searchInput').val('');
        $('#categorySelect').val('');
        $('#minPrice').val('');
        $('#maxPrice').val('');
        $('#sortSelect').val('newest');
        currentPage = 1;
        loadProducts();
        closeMobileSidebar();
      });

      // Sort change
      $('#sortSelect').on('change', function() {
        currentPage = 1;
        loadProducts();
      });

      // Search on Enter key
      $('#searchInput').on('keypress', function(e) {
        if (e.which === 13) {
          currentPage = 1;
          loadProducts();
          closeMobileSidebar();
        }
      });

      // Pagination clicks (delegated)
      $(document).on('click', '.pagination-btn, .pagination-number', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page && !$(this).hasClass('active') && !isLoading) {
          currentPage = page;
          loadProducts();
          smoothScrollToTop();
        }
      });

      // Add to cart (delegated)
      $(document).on('click', '.add-to-cart-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        if ($btn.is(':disabled')) return;
        
        const productId = $(this).data('product-id');
        addToCart(productId, $btn);
      });

      // Add to wishlist (delegated)
      $(document).on('click', '.wishlist-btn', function(e) {
        e.preventDefault();
        const productId = $(this).data('product-id');
        const $btn = $(this);
        addToWishlist(productId, $btn);
      });

      // Add to compare (delegated)
      $(document).on('click', '.compare-btn', function(e) {
        e.preventDefault();
        const productId = $(this).data('product-id');
        const $btn = $(this);
        addToCompare(productId, $btn);
      });

      // Reset filters button (delegated - for dynamically created button in empty state)
      $(document).on('click', '#resetFilters', function(e) {
        e.preventDefault();
        $('#searchInput').val('');
        $('#categorySelect').val('');
        $('#minPrice').val('');
        $('#maxPrice').val('');
        $('#sortSelect').val('newest');
        currentPage = 1;
        loadProducts();
        closeMobileSidebar();
      });

      /**
       * Close mobile sidebar
       */
      function closeMobileSidebar() {
        $('#filterSidebar').removeClass('active');
        $('#sidebarOverlay').removeClass('active');
        $('body').css('overflow', '');
      }

      /**
       * Load products via AJAX
       */
      function loadProducts() {
        if (isLoading) return;
        isLoading = true;

        const params = {
          page: currentPage,
          search: $('#searchInput').val(),
          category: $('#categorySelect').val(),
          min_price: $('#minPrice').val(),
          max_price: $('#maxPrice').val(),
          sort: $('#sortSelect').val(),
          ajax: 1
        };

        // Show loading overlay
        $('#loadingOverlay').addClass('active');

        $.ajax({
          url: 'ajax/load-products.php',
          method: 'GET',
          data: params,
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              renderProducts(response.products);
              renderPagination(response.pagination);
              $('#productCount').text(response.total);
              
              // Update URL without reload
              updateURL(params);
            } else {
              showToast(response.message || 'Failed to load products', 'error');
            }
          },
          error: function() {
            showToast('An error occurred while loading products', 'error');
          },
          complete: function() {
            $('#loadingOverlay').removeClass('active');
            isLoading = false;
          }
        });
      }

      /**
       * Render products
       */
      function renderProducts(products) {
        const $grid = $('#productsGrid');
        
        if (!products || products.length === 0) {
          $grid.html(`
            <div class="empty-state" style="grid-column: 1 / -1;">
              <div class="empty-icon">
                <i class="fi fi-rs-box-open"></i>
              </div>
              <h3 class="empty-title">No Products Found</h3>
              <p class="empty-text">We couldn't find any products matching your criteria. Try adjusting your filters.</p>
              <button class="btn btn-primary" id="resetFilters">Reset Filters</button>
            </div>
          `);
          return;
        }

        let html = '';
        products.forEach(product => {
          const hoverImage = product.first_sub_image || product.main_image_path;
          const isOutOfStock = product.status === 'Out of Stock';
          
          html += `
            <article class="product-card ${isOutOfStock ? 'out-of-stock' : ''}">
              <div class="product-image-wrapper">
                <a href="product_details.php?id=${product.product_id}" class="product-link">
                  <img src="${escapeHtml(product.main_image_path)}" 
                       alt="${escapeHtml(product.name)}" 
                       class="product-image primary" 
                       loading="lazy" />
                  <img src="${escapeHtml(hoverImage)}" 
                       alt="${escapeHtml(product.name)}" 
                       class="product-image secondary" 
                       loading="lazy" />
                </a>
                
                ${isOutOfStock ? '<span class="product-badge badge-sold">Sold Out</span>' : ''}
                
                <div class="product-actions">
                  <button class="action-btn wishlist-btn" data-product-id="${product.product_id}" title="Add to Wishlist">
                    <i class="fi fi-rs-heart"></i>
                  </button>
                  <button class="action-btn compare-btn" data-product-id="${product.product_id}" title="Compare">
                    <i class="fi fi-rs-shuffle"></i>
                  </button>
                  <a href="product_details.php?id=${product.product_id}" class="action-btn view-btn" title="Quick View">
                    <i class="fi fi-rs-eye"></i>
                  </a>
                </div>
              </div>
              
              <div class="product-info">
                <span class="product-category">${escapeHtml(product.category)}</span>
                <h3 class="product-name">
                  <a href="product_details.php?id=${product.product_id}">
                    ${escapeHtml(product.name)}
                  </a>
                </h3>
                
                <div class="product-rating">
                  <i class="fi fi-ss-star"></i>
                  <i class="fi fi-ss-star"></i>
                  <i class="fi fi-ss-star"></i>
                  <i class="fi fi-ss-star"></i>
                  <i class="fi fi-rs-star"></i>
                </div>
                
                <div class="product-footer">
                  <span class="product-price">${parseFloat(product.price).toFixed(2)}</span>
                  <button class="add-to-cart-btn" data-product-id="${product.product_id}" ${isOutOfStock ? 'disabled' : ''}>
                    <i class="fi fi-rs-shopping-cart-add"></i>
                  </button>
                </div>
              </div>
            </article>
          `;
        });
        
        $grid.html(html);
      }

      /**
       * Render pagination
       */
      function renderPagination(pagination) {
        const $pagination = $('#pagination');
        
        if (!pagination || pagination.total_pages <= 1) {
          $pagination.hide();
          return;
        }

        let html = '';
        
        // Previous button
        if (pagination.current_page > 1) {
          html += `
            <a href="#" class="pagination-btn" data-page="${pagination.current_page - 1}">
              <i class="fi fi-rs-angle-left"></i>
            </a>
          `;
        }

        html += '<div class="pagination-numbers">';

        // First page
        if (pagination.start_page > 1) {
          html += `<a href="#" class="pagination-number" data-page="1">1</a>`;
          if (pagination.start_page > 2) {
            html += '<span class="pagination-ellipsis">...</span>';
          }
        }

        // Page numbers
        for (let i = pagination.start_page; i <= pagination.end_page; i++) {
          const activeClass = i === pagination.current_page ? 'active' : '';
          html += `<a href="#" class="pagination-number ${activeClass}" data-page="${i}">${i}</a>`;
        }

        // Last page
        if (pagination.end_page < pagination.total_pages) {
          if (pagination.end_page < pagination.total_pages - 1) {
            html += '<span class="pagination-ellipsis">...</span>';
          }
          html += `<a href="#" class="pagination-number" data-page="${pagination.total_pages}">${pagination.total_pages}</a>`;
        }

        html += '</div>';

        // Next button
        if (pagination.current_page < pagination.total_pages) {
          html += `
            <a href="#" class="pagination-btn" data-page="${pagination.current_page + 1}">
              <i class="fi fi-rs-angle-right"></i>
            </a>
          `;
        }

        $pagination.html(html).show();
      }

      /**
       * Update URL without reload
       */
      function updateURL(params) {
        const cleanParams = {};
        Object.keys(params).forEach(key => {
          if (params[key] && key !== 'ajax') {
            cleanParams[key] = params[key];
          }
        });

        const queryString = $.param(cleanParams);
        const newUrl = window.location.pathname + (queryString ? '?' + queryString : '');
        window.history.pushState({ path: newUrl }, '', newUrl);
      }

      /**
       * Add product to cart
       */
      function addToCart(productId, $btn) {
        $btn.prop('disabled', true);
        const originalHtml = $btn.html();
        $btn.html('<i class="fi fi-rs-spinner"></i>');

        $.ajax({
          url: 'cart_actions.php',
          method: 'POST',
          data: {
            action: 'add',
            product_id: productId,
            quantity: 1
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              showToast('Product added to cart!', 'success');
              if (response.cart_count !== undefined) {
                updateCartCount(response.cart_count);
              }
              
              $btn.html('<i class="fi fi-rs-check"></i>');
              setTimeout(() => {
                $btn.html(originalHtml);
                $btn.prop('disabled', false);
              }, 1000);
            } else {
              showToast(response.message || 'Failed to add to cart', 'error');
              $btn.html(originalHtml);
              $btn.prop('disabled', false);
            }
          },
          error: function() {
            showToast('An error occurred. Please try again.', 'error');
            $btn.html(originalHtml);
            $btn.prop('disabled', false);
          }
        });
      }

      /**
       * Add product to wishlist
       */
      function addToWishlist(productId, $btn) {
        const isActive = $btn.hasClass('active');
        
        $.ajax({
          url: 'wishlist_actions.php',
          method: 'POST',
          data: {
            action: isActive ? 'remove' : 'add',
            product_id: productId
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              $btn.toggleClass('active');
              const message = isActive ? 'Removed from wishlist' : 'Added to wishlist!';
              showToast(message, 'success');
              if (response.wishlist_count !== undefined) {
                updateWishlistCount(response.wishlist_count);
              }
            } else {
              showToast(response.message || 'Failed to update wishlist', 'error');
            }
          },
          error: function() {
            showToast('An error occurred. Please try again.', 'error');
          }
        });
      }

      /**
       * Add product to compare
       */
      function addToCompare(productId, $btn) {
        $.ajax({
          url: 'compare_actions.php',
          method: 'POST',
          data: {
            action: 'add',
            product_id: productId
          },
          dataType: 'json',
          success: function(response) {
            if (response.success) {
              $btn.addClass('active');
              showToast('Product added to compare!', 'success');
            } else {
              showToast(response.message || 'Failed to add to compare', 'error');
            }
          },
          error: function() {
            showToast('An error occurred. Please try again.', 'error');
          }
        });
      }

      /**
       * Update cart count
       */
      function updateCartCount(count) {
        $('.cart-count').text(count).addClass('pulse');
        setTimeout(() => $('.cart-count').removeClass('pulse'), 500);
      }

      /**
       * Update wishlist count
       */
      function updateWishlistCount(count) {
        $('.wishlist-count').text(count).addClass('pulse');
        setTimeout(() => $('.wishlist-count').removeClass('pulse'), 500);
      }

      /**
       * Smooth scroll to top
       */
      function smoothScrollToTop() {
        $('html, body').animate({ scrollTop: 0 }, 400, 'swing');
      }

      /**
       * Show toast notification
       */
      function showToast(message, type = 'success') {
        $('.toast').remove();

        const iconMap = {
          success: 'fi-rs-check-circle',
          error: 'fi-rs-cross-circle',
          info: 'fi-rs-info'
        };

        const $toast = $(`
          <div class="toast toast--${type}">
            <i class="fi ${iconMap[type]} toast-icon"></i>
            <span class="toast-message">${escapeHtml(message)}</span>
          </div>
        `);

        $('body').append($toast);
        setTimeout(() => $toast.addClass('show'), 10);

        setTimeout(() => {
          $toast.removeClass('show');
          setTimeout(() => $toast.remove(), 300);
        }, 3000);
      }

      /**
       * Escape HTML to prevent XSS
       */
      function escapeHtml(text) {
        const map = {
          '&': '&amp;',
          '<': '&lt;',
          '>': '&gt;',
          '"': '&quot;',
          "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
      }

      // Handle browser back/forward
      window.addEventListener('popstate', function() {
        location.reload();
      });

      // Profile menu toggle
      document.addEventListener('click', function(e){
        const prof = e.target.closest('.header__user-profile');
        document.querySelectorAll('.header__user-profile .profile-menu').forEach(m => m.style.display = 'none');
        if (prof && e.target.closest('.avatar-btn')) {
          const menu = prof.querySelector('.profile-menu');
          menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }
      });
    });
  </script>
</body>
</html>
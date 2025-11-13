<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function csrf_token() {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}
function verify_csrf($token) {
  return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

function is_logged_in() { return !empty($_SESSION['member']); }
function require_guest() { if (is_logged_in()) { header('Location: ../index.php'); exit; } }
function require_auth() { if (!is_logged_in()) { header('Location: ./login.php'); exit; } }

function require_admin() {
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (empty($_SESSION['admin'])) {
    header('Location: ../auth/admin_login.php');
    exit;
  }
}

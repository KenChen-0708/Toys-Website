<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

function csrf_token()
{
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}
function verify_csrf($token)
{
  return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

function is_logged_in()
{
  return !empty($_SESSION['member']);
}
function require_guest()
{
  if (is_logged_in()) {
    header('Location: /index.php');
    exit;
  }
}
function require_auth()
{
  if (!is_logged_in()) {
    header('Location: /pages/auth/login.php');
    exit;
  }
}


function redirect_if_admin()
{
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  if (!empty($_SESSION['admin'])) {
    header('Location: /admin/admin_dashboard.php');
    exit;
  }
}

function require_admin()
{
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  if (empty($_SESSION['admin'])) {
    header('Location: /pages/auth/admin_login.php');
    exit;
  }
}

/**
 * Get current admin role from session
 */
function get_admin_role()
{
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  return $_SESSION['admin']['role'] ?? 'admin';
}

/**
 * Check custom permissions for an admin (requires PDO connection)
 * @param PDO $pdo - Database connection
 * @param int $adminId - Admin ID
 * @param string $resource - Resource name
 * @param string $action - Action name
 * @return bool
 */
function check_custom_permission_with_pdo($pdo, $adminId, $resource, $action)
{
  try {
    if ($pdo instanceof PDO) {
      $stmt = $pdo->prepare("SELECT 1 FROM Admin_Permission WHERE admin_id = ? AND resource = ? AND action = ?");
      $stmt->execute([$adminId, $resource, $action]);
      return (bool)$stmt->fetch();
    }
  } catch (Exception $e) {
    // Table might not exist, return false
  }
  return false;
}

/**
 * Check if current admin has a specific permission
 * @param string $resource - The resource to check (e.g., 'members', 'admins', 'products', 'orders', 'feedback', 'analytics')
 * @param string $action - The action to check (e.g., 'view', 'create', 'update', 'delete')
 * @return bool
 */
function has_permission($resource, $action = 'view', $pdo = null)
{
  // Get PDO from global if not provided
  if ($pdo === null && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
  }

  $role = get_admin_role();

  // Super Admin has all permissions
  if ($role === 'super admin') {
    return true;
  }

  // Dashboard view permission is always granted (to prevent redirect loops)
  if ($resource === 'dashboard' && $action === 'view') {
    return true;
  }

  // Check if permission is explicitly denied for this admin
  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }
  $adminId = $_SESSION['admin']['id'] ?? 0;

  if ($adminId > 0 && $pdo instanceof PDO) {
    try {
      // First check if permission is explicitly denied
      $stmt = $pdo->prepare("SELECT 1 FROM Admin_Permission_Denied WHERE admin_id = ? AND resource = ? AND action = ?");
      $stmt->execute([$adminId, $resource, $action]);
      if ($stmt->fetch()) {
        return false; // Permission is explicitly denied
      }

      // Check for explicit admin permissions (set by Super Admin)
      if (check_custom_permission_with_pdo($pdo, $adminId, $resource, $action)) {
        return true; // Admin has explicit permission
      }
    } catch (Exception $e) {
      // If tables don't exist yet, continue to check role permissions
    }
  }

  // Check role-based permissions from database (base permissions)
  if ($pdo instanceof PDO) {
    try {
      $stmt = $pdo->prepare("SELECT 1 FROM Role_Permission WHERE role = ? AND resource = ? AND action = ?");
      $stmt->execute([$role, $resource, $action]);
      if ($stmt->fetch()) {
        return true; // Role has permission (and it's not denied)
      }
    } catch (Exception $e) {
      // If table doesn't exist yet, return false
    }
  }

  return false;
}

/**
 * Require a specific permission, redirect if not allowed
 * @param string $resource - The resource to check
 * @param string $action - The action to check
 */
function require_permission($resource, $action = 'view', $pdo = null)
{
  require_admin(); // First ensure admin is logged in

  // Try to get PDO from global scope if not provided
  if ($pdo === null && isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
    $pdo = $GLOBALS['pdo'];
  }

  if (!has_permission($resource, $action, $pdo)) {
    header('Location: ../admin/admin_dashboard.php?error=access_denied');
    exit;
  }
}

/**
 * Check if admin can access a specific page
 * @param string $page - The page name (e.g., 'manage_member.php', 'manage_admin.php')
 * @return bool
 */
function can_access_page($page)
{
  $role = get_admin_role();

  // Super Admin can access everything
  if ($role === 'super admin') {
    return true;
  }

  // Map pages to resources
  $page_permissions = [
    'admin_dashboard.php' => ['resource' => 'dashboard', 'action' => 'view'],
    'manage_member.php' => ['resource' => 'members', 'action' => 'view'],
    'manage_admin.php' => ['resource' => 'admins', 'action' => 'view'],
    'manage_orders.php' => ['resource' => 'orders', 'action' => 'view'],
    'order_details.php' => ['resource' => 'orders', 'action' => 'view'],
    'manage_product.php' => ['resource' => 'products', 'action' => 'view'],
    'feedback_management.php' => ['resource' => 'feedback', 'action' => 'view'],
    'analytics.php' => ['resource' => 'analytics', 'action' => 'view'],
  ];

  if (isset($page_permissions[$page])) {
    return has_permission($page_permissions[$page]['resource'], $page_permissions[$page]['action']);
  }

  // Default: deny access if page not in list
  return false;
}

/**
 * Verify Google reCAPTCHA
 * @param string $response - The g-recaptcha-response from the form
 * @return bool
 */
function verify_recaptcha($response)
{
  $secret = '6LdtlBAsAAAAAKEkbvhhoY94jGMkg6BHMiWVSKV0';

  if (empty($response)) {
    return false;
  }

  $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
  $data = [
    'secret' => $secret,
    'response' => $response,
    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
  ];

  $options = [
    'http' => [
      'header' => "Content-type: application/x-www-form-urlencoded\r\n",
      'method' => 'POST',
      'content' => http_build_query($data)
    ]
  ];

  $context = stream_context_create($options);
  $verify_response = file_get_contents($verify_url, false, $context);
  $response_data = json_decode($verify_response);

  return !empty($response_data->success);
}

<?php
  $title = 'My Account - Profile';
  require_once __DIR__ . '/helper/auth.php';
  require_once __DIR__ . '/helper/pdo_connection.php';
  
  // Require authentication
  require_auth();
  
  $member_id = $_SESSION['member']['id'] ?? null;
  if (!$member_id) {
    header('Location: ./auth/login.php');
    exit;
  }
  
  // Get user data from database
  $user_data = null;
  if ($db_ok && $pdo) {
    try {
      $stmt = $pdo->prepare("SELECT member_id, username, email, phone_number, address, profile_photo_path, register_date FROM Member WHERE member_id = ?");
      $stmt->execute([$member_id]);
      $user_data = $stmt->fetch();
    } catch (PDOException $e) {
      error_log("Error fetching user data: " . $e->getMessage());
    }
  }
  
  // Get order statistics
  $order_stats = ['total' => 0, 'pending' => 0, 'completed' => 0];
  if ($db_ok && $pdo) {
    try {
      $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM `Order` WHERE member_id = ?");
      $stmt->execute([$member_id]);
      $order_stats['total'] = $stmt->fetchColumn();
      
      $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM `Order` WHERE member_id = ? AND status = 'Pending'");
      $stmt->execute([$member_id]);
      $order_stats['pending'] = $stmt->fetchColumn();
      
      $stmt = $pdo->prepare("SELECT COUNT(*) as completed FROM `Order` WHERE member_id = ? AND status = 'Delivered'");
      $stmt->execute([$member_id]);
      $order_stats['completed'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
      error_log("Error fetching order stats: " . $e->getMessage());
    }
  }
  
  // Get messages and errors
  $success_message = $_SESSION['success_message'] ?? null;
  $profile_errors = $_SESSION['profile_errors'] ?? [];
  $password_errors = $_SESSION['password_errors'] ?? [];
  $photo_errors = $_SESSION['photo_errors'] ?? [];
  $old_profile = $_SESSION['old_profile'] ?? [];
  
  // Clear messages after displaying
  unset($_SESSION['success_message'], $_SESSION['profile_errors'], $_SESSION['password_errors'], $_SESSION['photo_errors'], $_SESSION['old_profile']);
  
  // Get active tab from query string
  $active_tab = $_GET['tab'] ?? 'dashboard';
  
  include_once __DIR__ . '/partials/header.php';
?>

<link rel="stylesheet" href="/assets/css/profile.css">

<main class="main">
  <!--=============== BREADCRUMB ===============-->
  <section class="breadcrumb">
    <ul class="breadcrumb__list flex container">
      <li><a href="/index.php" class="breadcrumb__link">Home</a></li>
      <li><span class="breadcrumb__link">></span></li>
      <li><span class="breadcrumb__link">My Account</span></li>
    </ul>
  </section>

  <!--=============== PROFILE PAGE ===============-->
  <section class="profile-page">
    <div class="profile-container">
      
      <!-- Profile Header -->
      <div class="profile-header">
        <div class="profile-header-content">
          <div class="profile-avatar-wrapper">
            <div class="profile-avatar">
              <?php if ($user_data && $user_data['profile_photo_path']): ?>
                <img src="/<?= htmlspecialchars($user_data['profile_photo_path']) ?>" alt="Profile Photo">
              <?php else: ?>
                <div class="profile-avatar-placeholder">
                  <i class="fi fi-rs-user"></i>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="profile-info">
            <h1><?= htmlspecialchars($user_data['username'] ?? 'User') ?></h1>
            <p><i class="fi fi-rs-envelope"></i> <?= htmlspecialchars($user_data['email'] ?? '') ?></p>
            <?php if ($user_data && $user_data['phone_number']): ?>
              <p><i class="fi fi-rs-phone-call"></i> <?= htmlspecialchars($user_data['phone_number']) ?></p>
            <?php endif; ?>
            <?php if ($user_data && $user_data['register_date']): ?>
              <p><i class="fi fi-rs-calendar"></i> Member since <?= date('F Y', strtotime($user_data['register_date'])) ?></p>
            <?php endif; ?>
            <div class="profile-stats">
              <div class="profile-stat">
                <span class="profile-stat-value"><?= $order_stats['total'] ?></span>
                <span class="profile-stat-label">Total Orders</span>
              </div>
              <div class="profile-stat">
                <span class="profile-stat-value"><?= $order_stats['pending'] ?></span>
                <span class="profile-stat-label">Pending</span>
              </div>
              <div class="profile-stat">
                <span class="profile-stat-value"><?= $order_stats['completed'] ?></span>
                <span class="profile-stat-label">Completed</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content Grid -->
      <div class="profile-main-grid">
        
        <!-- Sidebar Navigation -->
        <aside class="profile-sidebar">
          <nav>
            <a href="#" class="profile-nav-item <?= $active_tab === 'dashboard' ? 'active' : '' ?>" data-section="dashboard">
              <i class="fi fi-rs-dashboard"></i>
              <span>Dashboard</span>
            </a>
            <a href="#" class="profile-nav-item <?= $active_tab === 'orders' ? 'active' : '' ?>" data-section="orders">
              <i class="fi fi-rs-shopping-bag"></i>
              <span>My Orders</span>
            </a>
            <a href="#" class="profile-nav-item <?= $active_tab === 'update-profile' ? 'active' : '' ?>" data-section="update-profile">
              <i class="fi fi-rs-user"></i>
              <span>Edit Profile</span>
            </a>
            <a href="#" class="profile-nav-item <?= $active_tab === 'address' ? 'active' : '' ?>" data-section="address">
              <i class="fi fi-rs-marker"></i>
              <span>My Address</span>
            </a>
            <a href="#" class="profile-nav-item <?= $active_tab === 'change-password' ? 'active' : '' ?>" data-section="change-password">
              <i class="fi fi-rs-lock"></i>
              <span>Change Password</span>
            </a>
            <form action="./auth/logout.php" method="post" style="margin: 0;">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
              <button type="submit" class="profile-nav-item logout" style="width: 100%; text-align: left; background: none; border: none; cursor: pointer;">
                <i class="fi fi-rs-exit"></i>
                <span>Logout</span>
              </button>
            </form>
          </nav>
        </aside>

        <!-- Content Area -->
        <div class="profile-content">
          
          <!-- Dashboard Section -->
          <div class="profile-content-section <?= $active_tab === 'dashboard' ? 'active' : '' ?>" id="dashboard">
            <h2 class="section-title">
              <i class="fi fi-rs-dashboard"></i>
              Quick Actions & Recent Activity
            </h2>
            
            <!-- Quick Actions -->
            <div class="dashboard-grid" style="margin-bottom: 2rem;">
              <a href="#orders" class="dashboard-card" onclick="switchSection('orders'); return false;" style="text-decoration: none; color: inherit; display: block;">
                <div class="dashboard-card-icon"><i class="fi fi-rs-shopping-bag"></i></div>
                <div class="dashboard-card-value"><?= $order_stats['total'] ?></div>
                <div class="dashboard-card-label">View All Orders</div>
              </a>
              <a href="#update-profile" class="dashboard-card" onclick="switchSection('update-profile'); return false;" style="text-decoration: none; color: inherit; display: block;">
                <div class="dashboard-card-icon"><i class="fi fi-rs-user"></i></div>
                <div class="dashboard-card-value">Edit</div>
                <div class="dashboard-card-label">Update Profile</div>
              </a>
              <a href="#change-password" class="dashboard-card" onclick="switchSection('change-password'); return false;" style="text-decoration: none; color: inherit; display: block;">
                <div class="dashboard-card-icon"><i class="fi fi-rs-lock"></i></div>
                <div class="dashboard-card-value">Secure</div>
                <div class="dashboard-card-label">Change Password</div>
              </a>
              <a href="/shop.php" class="dashboard-card" style="text-decoration: none; color: inherit; display: block;">
                <div class="dashboard-card-icon"><i class="fi fi-rs-shopping-cart"></i></div>
                <div class="dashboard-card-value">Shop</div>
                <div class="dashboard-card-label">Continue Shopping</div>
              </a>
            </div>

            <!-- Welcome Message -->
            <div style="background: linear-gradient(135deg, var(--first-color-alt) 0%, rgba(26, 122, 94, 0.1) 100%); border-radius: 12px; padding: 2rem; border-left: 4px solid var(--first-color);">
              <h3 style="margin-bottom: 1rem; color: var(--title-color); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fi fi-rs-smile" style="color: var(--first-color);"></i>
                Welcome Back, <?= htmlspecialchars($user_data['username'] ?? 'User') ?>!
              </h3>
              <p style="color: var(--text-color); line-height: 1.8; margin-bottom: 1rem;">
                Manage your account settings, track your orders, and update your profile information all in one place.
              </p>
              <?php if ($order_stats['total'] === 0): ?>
                <a href="/shop.php" class="btn-modern" style="display: inline-flex;">
                  <i class="fi fi-rs-shopping-cart"></i>
                  Start Shopping
                </a>
              <?php else: ?>
                <a href="#orders" class="btn-modern" onclick="switchSection('orders'); return false;" style="display: inline-flex;">
                  <i class="fi fi-rs-shopping-bag"></i>
                  View My Orders
                </a>
              <?php endif; ?>
            </div>
          </div>

          <!-- Orders Section -->
          <div class="profile-content-section <?= $active_tab === 'orders' ? 'active' : '' ?>" id="orders">
            <h2 class="section-title">
              <i class="fi fi-rs-shopping-bag"></i>
              My Orders
            </h2>
            
            <?php
            // Fetch orders from database
            $orders = [];
            if ($db_ok && $pdo) {
              try {
                $stmt = $pdo->prepare("
                  SELECT order_id, order_date, total_amount, status 
                  FROM `Order` 
                  WHERE member_id = ? 
                  ORDER BY order_date DESC
                ");
                $stmt->execute([$member_id]);
                $orders = $stmt->fetchAll();
              } catch (PDOException $e) {
                error_log("Error fetching orders: " . $e->getMessage());
              }
            }
            ?>
            
            <?php if (empty($orders)): ?>
              <div class="empty-state">
                <i class="fi fi-rs-shopping-bag"></i>
                <p>You haven't placed any orders yet.</p>
                <a href="/shop.php" class="btn-modern">Start Shopping</a>
              </div>
            <?php else: ?>
              <div class="orders-table-wrapper">
                <table class="orders-table">
                  <thead>
                    <tr>
                      <th>Order #</th>
                      <th>Date</th>
                      <th>Status</th>
                      <th>Total</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($orders as $order): ?>
                      <tr>
                        <td><strong>#<?= htmlspecialchars($order['order_id']) ?></strong></td>
                        <td><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                        <td>
                          <span class="status-badge <?= strtolower($order['status']) ?>">
                            <?= htmlspecialchars($order['status']) ?>
                          </span>
                        </td>
                        <td><strong>$<?= number_format($order['total_amount'], 2) ?></strong></td>
                        <td><a href="#" class="view-order-link">View Details</a></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <!-- Update Profile Section -->
          <div class="profile-content-section <?= $active_tab === 'update-profile' ? 'active' : '' ?>" id="update-profile">
            <h2 class="section-title">
              <i class="fi fi-rs-user"></i>
              Edit Profile
            </h2>
            
            <?php if ($success_message): ?>
              <div class="alert-modern success">
                <i class="fi fi-rs-check-circle"></i>
                <span><?= htmlspecialchars($success_message) ?></span>
              </div>
            <?php endif; ?>
            
            <?php if (!empty($profile_errors)): ?>
              <div class="alert-modern error">
                <i class="fi fi-rs-exclamation-circle"></i>
                <div>
                  <?php foreach($profile_errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
            
            <?php if (!empty($photo_errors)): ?>
              <div class="alert-modern error">
                <i class="fi fi-rs-exclamation-circle"></i>
                <div>
                  <?php foreach($photo_errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
            
            <!-- Profile Photo Upload -->
            <div class="photo-upload-section">
              <div class="photo-preview-wrapper">
                <?php if ($user_data && $user_data['profile_photo_path']): ?>
                  <img src="/<?= htmlspecialchars($user_data['profile_photo_path']) ?>" 
                       alt="Profile Photo" 
                       class="photo-preview"
                       id="photoPreview">
                <?php else: ?>
                  <div class="photo-placeholder" id="photoPlaceholder">
                    <i class="fi fi-rs-user"></i>
                  </div>
                <?php endif; ?>
              </div>
              <form method="POST" action="./profile/photo_upload_post.php" enctype="multipart/form-data" id="photoUploadForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <div class="file-upload-wrapper">
                  <input type="file" 
                         name="profile_photo" 
                         id="profilePhotoInput"
                         accept="image/jpeg,image/jpg,image/png,image/gif" 
                         class="file-upload-input"
                         onchange="previewPhoto(this)">
                  <label for="profilePhotoInput" class="file-upload-label">
                    <i class="fi fi-rs-camera"></i>
                    <span>Choose Photo</span>
                  </label>
                </div>
                <button type="submit" class="btn-modern" style="margin-top: 1rem;">
                  <i class="fi fi-rs-upload"></i>
                  Upload Photo
                </button>
              </form>
            </div>
            
            <!-- Profile Update Form -->
            <form method="POST" action="./profile/profile_update_post.php" class="modern-form">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
              
              <div class="form-group-modern">
                <label class="form-label-modern" for="name">Username</label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       class="form-input-modern" 
                       required 
                       minlength="3"
                       pattern="[A-Za-z0-9_]+"
                       title="Username can only contain letters, numbers, and underscores"
                       value="<?= htmlspecialchars($old_profile['name'] ?? $user_data['username'] ?? '') ?>" 
                       placeholder="Enter your username" />
              </div>
              
              <div class="form-group-modern">
                <label class="form-label-modern" for="email">Email Address</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       class="form-input-modern" 
                       required
                       value="<?= htmlspecialchars($old_profile['email'] ?? $user_data['email'] ?? '') ?>" 
                       placeholder="Enter your email" />
              </div>
              
              <div class="form-group-modern">
                <label class="form-label-modern" for="phone_number">Phone Number</label>
                <input type="text" 
                       id="phone_number" 
                       name="phone_number" 
                       class="form-input-modern"
                       value="<?= htmlspecialchars($old_profile['phone_number'] ?? $user_data['phone_number'] ?? '') ?>" 
                       placeholder="Enter your phone number" />
              </div>
              
              <div class="form-group-modern">
                <label class="form-label-modern" for="address">Address</label>
                <textarea id="address" 
                          name="address" 
                          class="form-input-modern form-textarea-modern"
                          rows="4"
                          placeholder="Enter your address"><?= htmlspecialchars($old_profile['address'] ?? $user_data['address'] ?? '') ?></textarea>
              </div>
              
              <button type="submit" class="btn-modern">
                <i class="fi fi-rs-check"></i>
                Save Changes
              </button>
            </form>
          </div>

          <!-- Address Section -->
          <div class="profile-content-section <?= $active_tab === 'address' ? 'active' : '' ?>" id="address">
            <h2 class="section-title">
              <i class="fi fi-rs-marker"></i>
              My Addresses
            </h2>
            
            <?php if ($user_data && !empty($user_data['address'])): ?>
              <!-- Address Information Grid -->
              <div class="address-info-grid">
                <div class="address-info-item">
                  <i class="fi fi-rs-marker"></i>
                  <div class="address-info-item-content">
                    <div class="address-info-label">Address Type</div>
                    <div class="address-info-value">Shipping Address</div>
                  </div>
                </div>
                <?php if ($user_data['phone_number']): ?>
                <div class="address-info-item">
                  <i class="fi fi-rs-phone-call"></i>
                  <div class="address-info-item-content">
                    <div class="address-info-label">Contact Number</div>
                    <div class="address-info-value"><?= htmlspecialchars($user_data['phone_number']) ?></div>
                  </div>
                </div>
                <?php endif; ?>
                <div class="address-info-item">
                  <i class="fi fi-rs-envelope"></i>
                  <div class="address-info-item-content">
                    <div class="address-info-label">Email</div>
                    <div class="address-info-value"><?= htmlspecialchars($user_data['email']) ?></div>
                  </div>
                </div>
              </div>

              <!-- Address Card -->
              <div class="address-card">
                <div class="address-card-header">
                  <div class="address-card-title">
                    <i class="fi fi-rs-home"></i>
                    <span>Default Shipping Address</span>
                  </div>
                  <span class="address-badge">Active</span>
                </div>
                <div class="address-card-content">
                  <?= nl2br(htmlspecialchars($user_data['address'])) ?>
                </div>
                <div class="address-card-actions">
                  <a href="#update-profile" class="btn-modern btn-modern-secondary" onclick="switchSection('update-profile'); return false;">
                    <i class="fi fi-rs-edit"></i>
                    Edit Address
                  </a>
                  <button type="button" class="btn-modern btn-modern-secondary" onclick="copyAddress()" style="background: white; color: var(--first-color); border: 2px solid var(--first-color);">
                    <i class="fi fi-rs-copy"></i>
                    Copy Address
                  </button>
                </div>
              </div>

              <!-- Address Tips -->
              <div style="background: linear-gradient(135deg, var(--first-color-alt) 0%, rgba(26, 122, 94, 0.05) 100%); border-radius: 12px; padding: 1.5rem; border-left: 4px solid var(--first-color); margin-top: 1.5rem;">
                <h3 style="margin-bottom: 1rem; color: var(--title-color); display: flex; align-items: center; gap: 0.5rem; font-size: 1rem;">
                  <i class="fi fi-rs-info" style="color: var(--first-color);"></i>
                  Address Management Tips
                </h3>
                <ul style="color: var(--text-color); line-height: 2; padding-left: 1.5rem; margin: 0;">
                  <li>Keep your address up to date to ensure timely delivery</li>
                  <li>Include apartment/unit numbers for accurate delivery</li>
                  <li>Update your address if you move to a new location</li>
                  <li>Your address will be used for all future orders</li>
                </ul>
              </div>
            <?php else: ?>
              <div class="empty-state">
                <i class="fi fi-rs-marker"></i>
                <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No address on file</p>
                <p style="font-size: 0.95rem; color: var(--text-color-light); margin-bottom: 1.5rem;">
                  Add a shipping address to make checkout faster and easier.
                </p>
                <a href="#update-profile" class="btn-modern" onclick="switchSection('update-profile'); return false;">
                  <i class="fi fi-rs-plus"></i>
                  Add Your First Address
                </a>
              </div>

              <!-- Why Add Address Section -->
              <div style="background: linear-gradient(135deg, var(--first-color-alt) 0%, rgba(26, 122, 94, 0.05) 100%); border-radius: 12px; padding: 2rem; margin-top: 2rem; text-align: center;">
                <h3 style="margin-bottom: 1rem; color: var(--title-color); display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                  <i class="fi fi-rs-star" style="color: var(--first-color);"></i>
                  Why Add Your Address?
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
                  <div>
                    <i class="fi fi-rs-rocket" style="font-size: 2rem; color: var(--first-color); margin-bottom: 0.5rem; display: block;"></i>
                    <strong style="display: block; margin-bottom: 0.5rem;">Faster Checkout</strong>
                    <p style="font-size: 0.9rem; color: var(--text-color-light); margin: 0;">Save time during checkout</p>
                  </div>
                  <div>
                    <i class="fi fi-rs-shield-check" style="font-size: 2rem; color: var(--first-color); margin-bottom: 0.5rem; display: block;"></i>
                    <strong style="display: block; margin-bottom: 0.5rem;">Secure Storage</strong>
                    <p style="font-size: 0.9rem; color: var(--text-color-light); margin: 0;">Your data is safe with us</p>
                  </div>
                  <div>
                    <i class="fi fi-rs-truck" style="font-size: 2rem; color: var(--first-color); margin-bottom: 0.5rem; display: block;"></i>
                    <strong style="display: block; margin-bottom: 0.5rem;">Accurate Delivery</strong>
                    <p style="font-size: 0.9rem; color: var(--text-color-light); margin: 0;">Ensure correct delivery</p>
                  </div>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- Change Password Section -->
          <div class="profile-content-section <?= $active_tab === 'change-password' ? 'active' : '' ?>" id="change-password">
            <h2 class="section-title">
              <i class="fi fi-rs-lock"></i>
              Change Password
            </h2>
            
            <?php if ($success_message && $active_tab === 'change-password'): ?>
              <div class="alert-modern success">
                <i class="fi fi-rs-check-circle"></i>
                <span><?= htmlspecialchars($success_message) ?></span>
              </div>
            <?php endif; ?>
            
            <?php if (!empty($password_errors)): ?>
              <div class="alert-modern error">
                <i class="fi fi-rs-exclamation-circle"></i>
                <div>
                  <?php foreach($password_errors as $error): ?>
                    <div><?= htmlspecialchars($error) ?></div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
            
            <form method="POST" action="./profile/password_update_post.php" class="modern-form">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
              
              <div class="form-group-modern">
                <label class="form-label-modern" for="current_password">Current Password</label>
                <input type="password" 
                       id="current_password" 
                       name="current_password" 
                       class="form-input-modern" 
                       required 
                       autocomplete="current-password"
                       placeholder="Enter your current password" />
              </div>
              
              <div class="form-group-modern">
                <label class="form-label-modern" for="new_password">New Password</label>
                <input type="password" 
                       id="new_password" 
                       name="new_password" 
                       class="form-input-modern" 
                       required 
                       minlength="6"
                       autocomplete="new-password"
                       placeholder="Enter your new password"
                       oninput="checkPasswordStrength(this.value)" />
                <div class="password-strength" id="passwordStrength" style="display: none;">
                  <div class="password-strength-bar" id="passwordStrengthBar"></div>
                </div>
                <small style="color: var(--text-color-light); font-size: 0.875rem; margin-top: 0.5rem; display: block;">
                  Must be at least 6 characters
                </small>
              </div>
              
              <div class="form-group-modern">
                <label class="form-label-modern" for="confirm_password">Confirm New Password</label>
                <input type="password" 
                       id="confirm_password" 
                       name="confirm_password" 
                       class="form-input-modern" 
                       required 
                       minlength="6"
                       autocomplete="new-password"
                       placeholder="Confirm your new password" />
              </div>
              
              <button type="submit" class="btn-modern">
                <i class="fi fi-rs-key"></i>
                Change Password
              </button>
            </form>
          </div>

        </div>
      </div>
    </div>
  </section>
</main>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
(function($) {
  $(document).ready(function() {
    // Section switching functionality
    $('.profile-nav-item[data-section]').on('click', function(e) {
      e.preventDefault();
      const section = $(this).data('section');
      switchSection(section);
    });
    
    // Auto-switch to section from URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam) {
      switchSection(tabParam);
    }
    
    // Password confirmation validation
    $('#new_password, #confirm_password').on('input', function() {
      const newPwd = $('#new_password').val();
      const confirmPwd = $('#confirm_password').val();
      const confirmInput = $('#confirm_password')[0];
      
      if (confirmPwd && newPwd !== confirmPwd) {
        confirmInput.setCustomValidity('Passwords do not match');
      } else {
        confirmInput.setCustomValidity('');
      }
    });
    
    // Auto-hide success messages after 5 seconds
    setTimeout(function() {
      $('.alert-modern.success').fadeOut('slow');
    }, 5000);
  });
  
  // Expose switchSection function globally
  window.switchSection = function(section) {
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', section);
    window.history.pushState({}, '', url);
    
    // Remove active classes
    $('.profile-nav-item').removeClass('active');
    $('.profile-content-section').removeClass('active');
    
    // Add active classes
    $('.profile-nav-item[data-section="' + section + '"]').addClass('active');
    const targetSection = $('#' + section);
    if (targetSection.length) {
      targetSection.addClass('active');
    }
    
    // Scroll to top of content
    if ($('.profile-content').length) {
      $('.profile-content').get(0).scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };
  
  // Photo preview function
  window.previewPhoto = function(input) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function(e) {
        const preview = $('#photoPreview');
        const placeholder = $('#photoPlaceholder');
        
        if (preview.length) {
          preview.attr('src', e.target.result);
        } else {
          placeholder.html('<img src="' + e.target.result + '" class="photo-preview" style="width: 100%; height: 100%; object-fit: cover;">');
        }
      };
      reader.readAsDataURL(input.files[0]);
    }
  };
  
  // Copy address function
  window.copyAddress = function() {
    const addressText = $('.address-card-content').text().trim();
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(addressText).then(function() {
        alert('Address copied to clipboard!');
      });
    } else {
      // Fallback for older browsers
      const textArea = document.createElement('textarea');
      textArea.value = addressText;
      document.body.appendChild(textArea);
      textArea.select();
      document.execCommand('copy');
      document.body.removeChild(textArea);
      alert('Address copied to clipboard!');
    }
  };
  
  // Password strength checker
  window.checkPasswordStrength = function(password) {
    const strengthBar = $('#passwordStrengthBar');
    const strengthContainer = $('#passwordStrength');
    
    if (password.length === 0) {
      strengthContainer.hide();
      return;
    }
    
    strengthContainer.show();
    
    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[^a-zA-Z\d]/.test(password)) strength++;
    
    strengthBar.removeClass('weak medium strong');
    if (strength <= 2) {
      strengthBar.addClass('weak');
    } else if (strength <= 3) {
      strengthBar.addClass('medium');
    } else {
      strengthBar.addClass('strong');
    }
  };
})(jQuery);
</script>

<?php include_once __DIR__ . '/partials/footer.php'; ?>

<?php
$title = 'Ecommerce Website — Account';
require_once __DIR__ . '/helper/pdo_connection.php';
require_once __DIR__ . '/helper/auth.php';
require_auth();

// Ensure user_initials function is available
if (!function_exists('user_initials')) {
  function user_initials($username)
  {
    $parts = preg_split('/\s+/', trim((string)$username));
    $first = mb_substr($parts[0] ?? '', 0, 1);
    $second = mb_substr($parts[1] ?? '', 0, 1);
    return mb_strtoupper($first . $second ?: $first);
  }
}

$memberId = $_SESSION['member']['id'];

// Fetch fresh member data from DB (NO address field - using Shipping_Address table instead)
$stmt = $pdo->prepare("SELECT member_id, username, email, phone_number, profile_photo_path, register_date FROM Member WHERE member_id = ?");
$stmt->execute([$memberId]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch default shipping address from Shipping_Address table (most recent one)
$addressParts = [
  'street' => '',
  'city' => '',
  'state' => '',
  'postcode' => '',
  'country' => 'Malaysia'
];

$defaultAddress = null;
$addressStmt = $pdo->prepare("
    SELECT * FROM Shipping_Address 
    WHERE member_id = ? 
    ORDER BY created_date DESC 
    LIMIT 1
");
$addressStmt->execute([$memberId]);
$defaultAddress = $addressStmt->fetch(PDO::FETCH_ASSOC);

if ($defaultAddress) {
    $addressParts['street'] = $defaultAddress['address_line1'] ?? '';
    $addressParts['city'] = $defaultAddress['city'] ?? '';
    $addressParts['state'] = $defaultAddress['state'] ?? '';
    $addressParts['postcode'] = $defaultAddress['postal_code'] ?? '';
    $addressParts['country'] = $defaultAddress['country'] ?? 'Malaysia';
}

// Malaysian states list
$malaysianStates = [
  'Johor',
  'Kedah',
  'Kelantan',
  'Kuala Lumpur',
  'Labuan',
  'Malacca',
  'Negeri Sembilan',
  'Pahang',
  'Penang',
  'Perak',
  'Perlis',
  'Putrajaya',
  'Sabah',
  'Sarawak',
  'Selangor',
  'Terengganu'
];

// Cities by state mapping
$citiesByState = [
  'Johor' => ['Johor Bahru', 'Iskandar Puteri', 'Batu Pahat', 'Kluang', 'Muar', 'Pasir Gudang', 'Segamat', 'Kulai'],
  'Kedah' => ['Alor Setar', 'Sungai Petani', 'Kulim', 'Langkawi', 'Jitra', 'Kuala Kedah'],
  'Kelantan' => ['Kota Bharu', 'Pasir Mas', 'Tumpat', 'Tanah Merah', 'Gua Musang'],
  'Kuala Lumpur' => ['Kuala Lumpur', 'Ampang', 'Cheras', 'Kepong', 'Setapak'],
  'Labuan' => ['Victoria'],
  'Malacca' => ['Malacca City', 'Alor Gajah', 'Jasin', 'Masjid Tanah'],
  'Negeri Sembilan' => ['Seremban', 'Port Dickson', 'Nilai', 'Kuala Pilah', 'Rembau'],
  'Pahang' => ['Kuantan', 'Temerloh', 'Bentong', 'Raub', 'Mentakab', 'Cameron Highlands'],
  'Penang' => ['George Town', 'Butterworth', 'Bukit Mertajam', 'Bayan Lepas', 'Seberang Perai'],
  'Perak' => ['Ipoh', 'Taiping', 'Teluk Intan', 'Sitiawan', 'Kampar', 'Batu Gajah'],
  'Perlis' => ['Kangar', 'Arau', 'Padang Besar'],
  'Putrajaya' => ['Putrajaya'],
  'Sabah' => ['Kota Kinabalu', 'Sandakan', 'Tawau', 'Lahad Datu', 'Keningau', 'Semporna'],
  'Sarawak' => ['Kuching', 'Miri', 'Sibu', 'Bintulu', 'Limbang', 'Sarikei'],
  'Selangor' => ['Shah Alam', 'Petaling Jaya', 'Subang Jaya', 'Klang', 'Kajang', 'Selayang', 'Rawang', 'Puchong'],
  'Terengganu' => ['Kuala Terengganu', 'Kemaman', 'Dungun', 'Besut', 'Marang']
];

// Get all cities for initial display or fallback
$allCities = [];
foreach ($citiesByState as $cities) {
  $allCities = array_merge($allCities, $cities);
}
$allCities = array_unique($allCities);
sort($allCities);

if (!$member) {
  // If somehow missing, force logout for safety
  header('Location: /pages/auth/logout.php');
  exit;
}

// Simple dashboard stats for this member
$stats = [
  'total_orders'   => 0,
  'total_spent'    => 0,
  'active_orders'  => 0,
  'wishlist_items' => 0,
  'last_order_at'  => null,
];

// Order stats
$orderStmt = $pdo->prepare("
      SELECT 
        COUNT(*)                                                  AS total_orders,
        COALESCE(SUM(CASE WHEN status <> 'Cancelled' THEN total_amount END), 0) AS total_spent,
        COUNT(CASE WHEN status IN ('Pending','Shipped') THEN 1 END)            AS active_orders,
        MAX(order_date)                                           AS last_order_at
      FROM `Order`
      WHERE member_id = ?
  ");
$orderStmt->execute([$memberId]);
$orderRow = $orderStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$stats['total_orders']  = (int)($orderRow['total_orders'] ?? 0);
$stats['total_spent']   = (float)($orderRow['total_spent'] ?? 0);
$stats['active_orders'] = (int)($orderRow['active_orders'] ?? 0);
$stats['last_order_at'] = $orderRow['last_order_at'] ?? null;

// Wishlist count
$wishStmt = $pdo->prepare("
      SELECT COUNT(*) 
      FROM Wishlist_Item wi
      INNER JOIN Wishlist w ON wi.wishlist_id = w.wishlist_id
      WHERE w.member_id = ?
  ");
$wishStmt->execute([$memberId]);
$stats['wishlist_items'] = (int)$wishStmt->fetchColumn();

// Simple reward points: 1 point per RM10 spent (rounded down)
$rewardPoints = (int)floor(($stats['total_spent'] ?? 0) / 10);

// Recent orders list
$recentStmt = $pdo->prepare("
      SELECT order_id, order_date, status, total_amount
      FROM `Order`
      WHERE member_id = ?
      ORDER BY order_date DESC
      LIMIT 5
  ");
$recentStmt->execute([$memberId]);
$recentOrders = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

// Profile completeness (simple progress based on key fields)
$profileFieldsTotal = 4; // email, phone, address, profile photo
$profileFieldsDone  = 0;

if (!empty($member['email'])) {
  $profileFieldsDone++;
}
if (!empty($member['phone_number'])) {
  $profileFieldsDone++;
}
// Check if user has a shipping address
if ($defaultAddress) {
  $profileFieldsDone++;
}
if (!empty($member['profile_photo_path'])) {
  $profileFieldsDone++;
}

$profileProgress = (int)round(($profileFieldsDone / $profileFieldsTotal) * 100);

// Flash messages
$profileErrors   = $_SESSION['profile_errors']   ?? [];
$profileSuccess  = $_SESSION['profile_success']  ?? '';
$passwordErrors  = $_SESSION['password_errors']  ?? [];
$passwordSuccess = $_SESSION['password_success'] ?? '';
$profilePhotoUpdated = $_SESSION['profile_photo_updated'] ?? false;

unset($_SESSION['profile_errors'], $_SESSION['profile_success'], $_SESSION['password_errors'], $_SESSION['password_success'], $_SESSION['profile_photo_updated']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <!--=============== FLATICON ===============-->
  <link
    rel="stylesheet"
    href="https://cdn-uicons.flaticon.com/2.0.0/uicons-regular-straight/css/uicons-regular-straight.css" />

  <!--=============== CSS ===============-->
  <link rel="stylesheet" href="/assets/css/styles.css" />
  <link rel="stylesheet" href="/assets/css/account.css" />
  <link rel="stylesheet" href="/assets/css/dark_mode_style.css">
  <title><?= htmlspecialchars($title) ?></title>
</head>

<?php include_once __DIR__ . '/partials/header.php'; ?>

<main class="main">
  <!--=============== ACCOUNTS ===============-->
  <section class="accounts section--lg">
    <div class="accounts__container">
      <!-- Left Column: Profile Sidebar (25%) -->
      <aside class="profile-sidebar">
        <div class="profile-sidebar__header">
          <div class="profile-sidebar__photo" id="profilePhotoTrigger" style="cursor: pointer; position: relative;" title="Click to change profile photo">
            <?php
            $hasPhoto = !empty($member['profile_photo_path']) && file_exists(__DIR__ . $member['profile_photo_path']);
            if ($hasPhoto):
            ?>
              <img src="<?= htmlspecialchars($member['profile_photo_path']) ?><?= $profilePhotoUpdated ? '?v=' . time() : '' ?>" alt="Profile photo" id="sidebarProfileImg">
            <?php else: ?>
              <div class="profile-sidebar__photo-placeholder" id="sidebarProfileIcon">
                <?php
                $initials = user_initials($member['username'] ?? '');
                echo htmlspecialchars($initials ?: 'U');
                ?>
              </div>
            <?php endif; ?>
            <!-- Camera icon badge - always visible -->
            <div class="profile-photo-badge">
              <i class="fi fi-rs-camera"></i>
            </div>
            <!-- Overlay text on hover -->
            <div class="profile-photo-overlay">
              <i class="fi fi-rs-camera"></i>
              <span>Change Photo</span>
            </div>
          </div>
          <h2 class="profile-sidebar__name"><?= htmlspecialchars($member['username']) ?></h2>
          <p class="profile-sidebar__email"><?= htmlspecialchars($member['email']) ?></p>
        </div>

        <nav class="profile-sidebar__menu">
          <button class="profile-menu__item active" data-content="dashboard">
            <i class="fi fi-rs-user"></i>
            <span>Profile Info</span>
          </button>
          <button class="profile-menu__item" data-content="update-profile">
            <i class="fi fi-rs-edit"></i>
            <span>Update Profile</span>
          </button>
          <button class="profile-menu__item" data-content="change-password">
            <i class="fi fi-rs-key"></i>
            <span>Change Password</span>
          </button>
          <button class="profile-menu__item" data-content="address">
            <i class="fi fi-rs-marker"></i>
            <span>Addresses</span>
          </button>
        </nav>
      </aside>

      <!-- Right Column: Dynamic Content Panel (75%) -->
      <div class="profile-content">
        <div class="content-panel active" id="dashboard">

          <div class="content-panel__header">
            <h3 class="content-panel__title">Welcome back, <?= htmlspecialchars($member['username']) ?> 👋</h3>
            <p class="content-panel__description">
              Quickly see your recent activity, track your orders, and jump to common actions.
            </p>
          </div>

          <div class="content-panel__meta">
            <span>Member since <?= htmlspecialchars(date('d M Y', strtotime($member['register_date']))) ?></span>
            <span class="content-panel__points">Reward points: <?= $rewardPoints ?></span>
          </div>

          <div class="content-panel__body">
            <div class="account-stats grid">
            <div class="account-stat-card stat-card--orders" data-stat-action="orders" data-stat-url="orders.php">
              <div class="stat-icon stat-icon--orders">
                <i class="fi fi-rs-shopping-bag"></i>
              </div>
              <div class="stat-body">
                <p class="stat-label">Total Orders</p>
                <p class="stat-value"><?= $stats['total_orders'] ?></p>
              </div>
            </div>

            <div class="account-stat-card stat-card--spent" data-stat-action="orders" data-stat-url="orders.php">
              <div class="stat-icon stat-icon--spent">
                <i class="fi fi-rs-credit-card"></i>
              </div>
              <div class="stat-body">
                <p class="stat-label">Total Spent</p>
                <p class="stat-value">RM <?= number_format($stats['total_spent'], 2) ?></p>
              </div>
            </div>

            <div class="account-stat-card stat-card--active" data-stat-action="orders" data-stat-url="orders.php?status=active">
              <div class="stat-icon stat-icon--active">
                <i class="fi fi-rs-truck-side"></i>
              </div>
              <div class="stat-body">
                <p class="stat-label">Active Orders</p>
                <p class="stat-value"><?= $stats['active_orders'] ?></p>
              </div>
            </div>

            <div class="account-stat-card stat-card--wishlist" data-stat-action="wishlist" data-stat-url="/pages/wishlist/wishlist.php">
              <div class="stat-icon stat-icon--wishlist">
                <i class="fi fi-rs-heart"></i>
              </div>
              <div class="stat-body">
                <p class="stat-label">Wishlist Items</p>
                <p class="stat-value"><?= $stats['wishlist_items'] ?></p>
              </div>
            </div>
            </div>

          <h5 class="dashboard-panel__subtitle">Profile checklist</h5>
          <ul class="profile-checklist">
            <li class="profile-check-item" data-checklist-action="update-profile" data-checklist-field="email">
              <span>
                <strong>Email</strong>
                <small>Your login and contact email.</small>
              </span>
              <span class="profile-check-badge profile-check-badge--done">Done</span>
            </li>
            <li class="profile-check-item" data-checklist-action="update-profile" data-checklist-field="phone">
              <span>
                <strong>Phone number</strong>
                <small><?= $member['phone_number'] ? 'Used for contact and delivery.' : 'Add a phone number in "Update Profile".' ?></small>
              </span>
              <span class="profile-check-badge <?= $member['phone_number'] ? 'profile-check-badge--done' : 'profile-check-badge--missing' ?>">
                <?= $member['phone_number'] ? 'Done' : 'Missing' ?>
              </span>
            </li>
            <li class="profile-check-item" data-checklist-action="update-profile" data-checklist-field="address">
              <span>
                <strong>Address</strong>
                <small><?= $defaultAddress ? 'We will use this as your default shipping address.' : 'Add your shipping address in "Update Profile".' ?></small>
              </span>
              <span class="profile-check-badge <?= $defaultAddress ? 'profile-check-badge--done' : 'profile-check-badge--missing' ?>">
                <?= $defaultAddress ? 'Done' : 'Missing' ?>
              </span>
            </li>
            <li class="profile-check-item" data-checklist-action="photo" data-checklist-field="photo">
              <span>
                <strong>Profile photo</strong>
                <small><?= $member['profile_photo_path'] ? 'Helps personalize your account.' : 'Upload a profile photo in "Update Profile".' ?></small>
              </span>
              <span class="profile-check-badge <?= $member['profile_photo_path'] ? 'profile-check-badge--done' : 'profile-check-badge--missing' ?>">
                <?= $member['profile_photo_path'] ? 'Done' : 'Missing' ?>
              </span>
            </li>
          </ul>
          
          <div class="profile-completeness">
            <h4 class="dashboard-panel__title">Profile completeness</h4>
            <div class="profile-progress">
              <div class="profile-progress-bar">
                <div class="profile-progress-fill" style="width: <?= $profileProgress ?>%;"></div>
              </div>
              <div class="profile-progress-meta">
                <span class="profile-progress-percent"><?= $profileProgress ?>%</span>
                <span class="profile-progress-hint">
                  <?= $profileProgress === 100 ? 'Your profile is complete.' : 'Complete your profile to make checkout faster.' ?>
                </span>
              </div>
            </div>
          </div>
          </div>
        </div>

        <div class="content-panel" id="address">
          <div class="content-panel__header">
            <h3 class="content-panel__title">Shipping Address</h3>
            <p class="content-panel__description">
              Manage your shipping addresses for faster checkout.
            </p>
          </div>
          <div class="content-panel__body">
            <?php if ($defaultAddress): ?>
              <?php
              // Count how many orders used this address
              $orderCountStmt = $pdo->prepare("SELECT COUNT(*) FROM `Order` WHERE member_id = ?");
              $orderCountStmt->execute([$memberId]);
              $orderCount = (int)$orderCountStmt->fetchColumn();
              ?>

              <div class="address-card">
                <div class="address-card__header">
                  <div class="address-card__title">
                    <i class="fi fi-rs-marker"></i>
                    <span>Default Shipping Address</span>
                    <span class="address-badge address-badge--default">Default</span>
                  </div>
                  <div class="address-card__actions">
                    <button type="button" class="btn btn--sm btn--outline" id="copyAddressBtn" title="Copy address">
                      <i class="fi fi-rs-copy"></i> Copy
                    </button>
                    <button type="button" class="btn btn--sm btn--primary" id="editAddressBtn">
                      <i class="fi fi-rs-edit"></i> Edit
                    </button>
                  </div>
                </div>

                <div class="address-card__body">
                  <div class="address-display">
                    <?php if ($defaultAddress): ?>
                      <?php if (!empty($defaultAddress['address_line1'])): ?>
                        <div class="address-line"><?= htmlspecialchars($defaultAddress['address_line1']) ?></div>
                      <?php endif; ?>
                      <?php if (!empty($defaultAddress['address_line2'])): ?>
                        <div class="address-line"><?= htmlspecialchars($defaultAddress['address_line2']) ?></div>
                      <?php endif; ?>
                      <?php if (!empty($defaultAddress['city']) || !empty($defaultAddress['state']) || !empty($defaultAddress['postal_code'])): ?>
                        <div class="address-line">
                          <?php if (!empty($defaultAddress['city'])): ?>
                            <?= htmlspecialchars($defaultAddress['city']) ?>
                          <?php endif; ?>
                          <?php if (!empty($defaultAddress['state'])): ?>
                            <?= !empty($defaultAddress['city']) ? ', ' : '' ?><?= htmlspecialchars($defaultAddress['state']) ?>
                          <?php endif; ?>
                          <?php if (!empty($defaultAddress['postal_code'])): ?>
                            <?= htmlspecialchars($defaultAddress['postal_code']) ?>
                          <?php endif; ?>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($defaultAddress['country'])): ?>
                        <div class="address-line"><?= htmlspecialchars($defaultAddress['country']) ?></div>
                      <?php endif; ?>
                    <?php else: ?>
                      <div class="address-line">No address provided</div>
                    <?php endif; ?>
                  </div>

                  <div class="address-info">
                    <div class="address-info__item">
                      <i class="fi fi-rs-shopping-bag"></i>
                      <span>Used in <strong><?= $orderCount ?></strong> order<?= $orderCount !== 1 ? 's' : '' ?></span>
                    </div>
                    <?php if (!empty($member['phone_number'])): ?>
                      <div class="address-info__item">
                        <i class="fi fi-rs-phone-call"></i>
                        <span>Contact: <strong><?= htmlspecialchars($member['phone_number']) ?></strong></span>
                      </div>
                    <?php endif; ?>
                  </div>

                    <div class="address-actions">
                      <?php if ($defaultAddress): 
                        $mapAddress = trim(implode(', ', array_filter([
                          $defaultAddress['address_line1'],
                          $defaultAddress['city'],
                          $defaultAddress['state'],
                          $defaultAddress['postal_code'],
                          $defaultAddress['country']
                        ])));
                      ?>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($mapAddress) ?>"
                          target="_blank"
                          class="btn btn--sm btn--outline"
                          title="View on Google Maps">
                          <i class="fi fi-rs-map-marker"></i> View on Map
                        </a>
                      <?php endif; ?>
                    </div>
                </div>
              </div>

              <div class="address-hint">
                <p>
                  <i class="fi fi-rs-info"></i>
                  This address will be used as the default shipping address for all your orders. You can update it anytime in the "Update Profile" section.
                </p>
              </div>
            <?php else: ?>
              <div class="address-empty">
                <div class="address-empty__icon">
                  <i class="fi fi-rs-marker"></i>
                </div>
                <h4 class="address-empty__title">No Address Added</h4>
                <p class="address-empty__description">
                  Add a shipping address to make checkout faster and ensure your orders are delivered to the right place.
                </p>
                <a href="#update-profile" class="btn btn--md btn--primary" onclick="document.querySelector('[data-target=\'#update-profile\']').click(); return false;">
                  <i class="fi fi-rs-plus"></i> Add Address
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="content-panel" id="change-password">
          <div class="content-panel__header">
            <h3 class="content-panel__title">Change Password</h3>
            <p class="content-panel__description">
              Update your password to keep your account secure.
            </p>
          </div>
          <div class="content-panel__body">
            <?php if ($passwordErrors): ?>
              <div class="alert alert--danger" role="alert" style="margin-bottom:1rem;">
                <?php foreach ($passwordErrors as $e): ?>
                  <div><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
              </div>
            <?php elseif ($passwordSuccess): ?>
              <div class="alert alert--success" role="alert" style="margin-bottom:1rem;">
                <?= htmlspecialchars($passwordSuccess) ?>
              </div>
            <?php endif; ?>

            <div class="password-change-card">
              <div class="password-change-info">
                <i class="fi fi-rs-shield-check"></i>
                <div>
                  <h4>Secure Your Account</h4>
                  <p>Use a strong password to protect your account. Make sure it's unique and not used elsewhere.</p>
                </div>
              </div>

              <form class="password-change-form" method="POST" action="/pages/auth/password_update_post.php" novalidate id="passwordChangeForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

                <div class="password-field-group">
                  <label class="password-label">
                    <span>Current Password <span class="required">*</span></span>
                    <div class="password-input-wrapper">
                      <input
                        type="password"
                        name="current_password"
                        id="current_password"
                        placeholder="Enter your current password"
                        class="form__input password-input"
                        required
                        autocomplete="current-password" />
                      <button type="button" class="password-toggle" data-target="current_password" title="Show/Hide password">
                        <i class="fi fi-rs-eye"></i>
                      </button>
                    </div>
                  </label>
                </div>

                <div class="password-field-group">
                  <label class="password-label">
                    <span>New Password <span class="required">*</span></span>
                    <div class="password-input-wrapper">
                      <input
                        type="password"
                        name="new_password"
                        id="new_password"
                        placeholder="Enter your new password"
                        class="form__input password-input"
                        required
                        autocomplete="new-password"
                        minlength="8" />
                      <button type="button" class="password-toggle" data-target="new_password" title="Show/Hide password">
                        <i class="fi fi-rs-eye"></i>
                      </button>
                    </div>
                    <div class="password-strength" id="passwordStrength">
                      <div class="password-strength-bar">
                        <div class="password-strength-fill" id="passwordStrengthFill"></div>
                      </div>
                      <div class="password-strength-text" id="passwordStrengthText">Password strength</div>
                    </div>
                    <div class="password-requirements" id="passwordRequirements">
                      <div class="password-req-item" data-requirement="length">
                        <i class="fi fi-rs-cross-small req-icon"></i>
                        <span>At least 8 characters</span>
                      </div>
                      <div class="password-req-item" data-requirement="uppercase">
                        <i class="fi fi-rs-cross-small req-icon"></i>
                        <span>One uppercase letter</span>
                      </div>
                      <div class="password-req-item" data-requirement="lowercase">
                        <i class="fi fi-rs-cross-small req-icon"></i>
                        <span>One lowercase letter</span>
                      </div>
                      <div class="password-req-item" data-requirement="number">
                        <i class="fi fi-rs-cross-small req-icon"></i>
                        <span>One number</span>
                      </div>
                      <div class="password-req-item" data-requirement="special">
                        <i class="fi fi-rs-cross-small req-icon"></i>
                        <span>One special character (@$!%*#?&^_-)</span>
                      </div>
                    </div>
                  </label>
                </div>

                <div class="password-field-group">
                  <label class="password-label">
                    <span>Confirm New Password <span class="required">*</span></span>
                    <div class="password-input-wrapper">
                      <input
                        type="password"
                        name="new_password_confirm"
                        id="new_password_confirm"
                        placeholder="Re-enter your new password"
                        class="form__input password-input"
                        required
                        autocomplete="new-password" />
                      <button type="button" class="password-toggle" data-target="new_password_confirm" title="Show/Hide password">
                        <i class="fi fi-rs-eye"></i>
                      </button>
                    </div>
                    <div class="password-match" id="passwordMatch">
                      <i class="fi fi-rs-check"></i>
                      <span>Passwords match</span>
                    </div>
                  </label>
                </div>

                <div class="password-change-actions">
                  <button type="button" class="btn btn--md btn--outline" id="clearPasswordForm">
                    <i class="fi fi-rs-refresh"></i> Clear
                  </button>
                  <button class="btn btn--md btn--primary" type="submit">
                    <i class="fi fi-rs-check"></i> Update Password
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="content-panel" id="wishlist">
          <div class="content-panel__header">
            <h3 class="content-panel__title">My Wishlist</h3>
            <p class="content-panel__description">
              View and manage your saved wishlist items.
            </p>
          </div>
          <div class="content-panel__body">
            <div style="text-align: center; padding: 3rem 2rem;">
              <div style="margin-bottom: 1.5rem;">
                <i class="fi fi-rs-heart" style="font-size: 4rem; color: var(--first-color); opacity: 0.6;"></i>
              </div>
              <h4 style="margin-bottom: 1rem; color: var(--title-color);">View Your Wishlist</h4>
              <p style="margin-bottom: 2rem; color: var(--text-color); max-width: 500px; margin-left: auto; margin-right: auto;">
                You have <?= $stats['wishlist_items'] ?> item<?= $stats['wishlist_items'] !== 1 ? 's' : '' ?> in your wishlist.
              </p>
              <a href="/pages/wishlist/wishlist.php" class="btn btn--md btn--primary" style="display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none;">
                <i class="fi fi-rs-heart"></i>
                Go to Wishlist Page
              </a>
            </div>
          </div>
        </div>

        <!-- Update Profile Content -->
        <div class="content-panel" id="update-profile">
          <div class="content-panel__header">
            <h3 class="content-panel__title">Update Profile</h3>
            <p class="content-panel__description">
              Update your personal information and contact details.
            </p>
          </div>
          <div class="content-panel__body">
            <?php if ($profileErrors): ?>
              <div class="alert alert--danger" role="alert" style="margin-bottom:1rem;">
                <?php foreach ($profileErrors as $e): ?>
                  <div><?= htmlspecialchars($e) ?></div>
                <?php endforeach; ?>
              </div>
            <?php elseif ($profileSuccess): ?>
              <div class="alert alert--success" role="alert" style="margin-bottom:1rem;">
                <?= htmlspecialchars($profileSuccess) ?>
              </div>
            <?php endif; ?>

            <!-- Profile Information Section -->
            <div class="profile-edit-card">
              <form class="profile-edit-form" method="POST" action="/pages/profile/profile_update_post.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

                <!-- Personal Information Section -->
                <div class="profile-section">
                  <h4 class="profile-section__title">Personal Information</h4>
                  <div class="profile-edit-fields">
                    <label class="form__label">
                      <span>Username <span class="required">*</span></span>
                      <input
                        type="text"
                        name="username"
                        class="form__input"
                        required
                        minlength="3"
                        value="<?= htmlspecialchars($member['username']) ?>"
                        placeholder="Enter your username" />
                    </label>

                    <label class="form__label">
                      <span>Email <span class="required">*</span></span>
                      <input
                        type="email"
                        name="email"
                        class="form__input"
                        required
                        value="<?= htmlspecialchars($member['email']) ?>"
                        placeholder="Enter your email address" />
                    </label>
                  </div>
                </div>

                <!-- Contact Information Section -->
                <div class="profile-section">
                  <h4 class="profile-section__title">Contact Information</h4>
                  <div class="profile-edit-fields">
                    <label class="form__label">
                      <span>Phone Number</span>
                      <input
                        type="tel"
                        name="phone"
                        class="form__input"
                        value="<?= htmlspecialchars($member['phone_number'] ?? '') ?>"
                        placeholder="e.g., 012-3456789 or 0123456789"
                        pattern="^0\d{1,2}-?\d{6,8}$"
                        maxlength="12"
                        title="Malaysian phone format: 0XX-XXXXXXX or 0XXXXXXXXX (10-11 digits)" />
                    </label>
                  </div>
                </div>

                <!-- Action Buttons -->
                <div class="profile-edit-actions">
                  <button class="btn btn--md btn--primary" type="submit">
                    <i class="fi fi-rs-check"></i> Save Changes
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<!-- Profile Photo Change Modal -->
<div class="profile-photo-modal" id="profilePhotoModal">
  <div class="profile-photo-modal__content">
    <button class="profile-photo-modal__close" id="closePhotoModal">
      <i class="fi fi-rs-cross"></i>
    </button>
    <h3 class="profile-photo-modal__title">Change Profile Photo</h3>

    <form class="profile-photo-form" method="POST" action="/pages/profile/profile_photo_update_post.php" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="profile-section">
        <div class="profile-edit-photo-row">
          <div class="profile-photo-wrapper">
            <!-- Current/Preview Photo Display -->
            <div class="profile-photo-current" id="modalProfilePhotoCurrent" style="position: relative; width: 140px; height: 140px; margin: 0 auto 2rem;">
              <?php
              $hasPhoto = !empty($member['profile_photo_path']) && file_exists(__DIR__ . $member['profile_photo_path']);
              if ($hasPhoto):
              ?>
                <img src="<?= htmlspecialchars($member['profile_photo_path']) ?>" alt="Profile photo" id="modalCurrentProfileImg" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
              <?php else: ?>
                <div class="profile-edit-avatar" id="modalDefaultProfileAvatar" style="width: 100%; height: 100%; border-radius: 50%;">
                  <span style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; font-size: 2.25rem; font-weight: 700; color: #fff; line-height: 1;">
                    <?php
                    $initials = user_initials($member['username'] ?? '');
                    echo htmlspecialchars($initials ?: 'U');
                    ?>
                  </span>
                </div>
              <?php endif; ?>
              <img id="modalPhotoPreview" class="photo-preview" alt="" style="display:none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 50%; object-fit: cover; z-index: 2;">
              <video id="modalWebcamVideo" autoplay playsinline style="display:none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 50%; object-fit: cover; z-index: 1;"></video>
              <button type="button" id="modalRemovePreviewBtn" class="photo-preview-remove" title="Remove image" style="display:none; position: absolute; top: -8px; right: -8px; z-index: 10;">
                <i class="fi fi-rs-cross"></i>
              </button>
              <div class="webcam-controls" id="modalWebcamControls" style="display:none; position: absolute; bottom: -60px; left: 50%; transform: translateX(-50%); z-index: 10;">
                <button type="button" id="modalCaptureWebcamBtn" class="webcam-capture-btn" title="Capture photo">
                  <i class="fi fi-rs-camera"></i>
                  <span>Capture</span>
                </button>
              </div>
            </div>

            <!-- Upload Options - Image Processing above Dropzone -->
            <div class="photo-upload-options">
              <div id="modalPhotoEditControls" class="photo-edit-controls" style="display:none;">
                <div class="photo-edit-controls__buttons">
                  <button type="button" id="modalRotateLeftBtn" class="photo-edit-btn" title="Rotate Left">
                    <i class="fi fi-rs-rotate-left"></i>
                    <span>Rotate Left</span>
                  </button>
                  <button type="button" id="modalRotateRightBtn" class="photo-edit-btn" title="Rotate Right">
                    <i class="fi fi-rs-rotate-right"></i>
                    <span>Rotate Right</span>
                  </button>
                  <button type="button" id="modalFlipHorizontalBtn" class="photo-edit-btn" title="Flip Horizontal">
                    <i class="fi fi-rs-arrows-horizontal"></i>
                    <span>Flip H</span>
                  </button>
                  <button type="button" id="modalFlipVerticalBtn" class="photo-edit-btn" title="Flip Vertical">
                    <i class="fi fi-rs-arrows-vertical"></i>
                    <span>Flip V</span>
                  </button>
                  <button type="button" id="modalResetImageBtn" class="photo-edit-btn photo-edit-btn--reset" title="Reset">
                    <i class="fi fi-rs-refresh"></i>
                    <span>Reset</span>
                  </button>
                </div>
              </div>

              <div id="modalPhotoDropZone" class="photo-dropzone">
                <p class="photo-dropzone__text">
                  <strong>Drag & drop</strong> an image here<br>
                  or <span class="photo-dropzone__browse">click to browse</span>
                </p>
                <input
                  type="file"
                  name="profile_photo"
                  id="modalProfilePhotoInput"
                  accept="image/*"
                  style="display:none" />
              </div>
            </div>
          </div>
          <input type="hidden" name="capture_data" id="modalCaptureData">
          <p class="profile-photo-hint">
            <i class="fi fi-rs-info"></i> JPG, PNG or WEBP. Max 2MB. Use webcam or upload a file.
          </p>
        </div>
      </div>
      <!-- Action Button for Photo -->
      <div class="profile-edit-actions">
        <button class="btn btn--md btn--primary" type="submit">
          <i class="fi fi-rs-check"></i> Save Photo
        </button>
        <button type="button" class="btn btn--md btn--outline" id="cancelPhotoModal">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Address Edit Modal -->
<div class="address-edit-modal" id="addressEditModal">
  <div class="address-edit-modal__content">
    <button class="address-edit-modal__close" id="closeAddressModal">
      <i class="fi fi-rs-cross"></i>
    </button>
    <h3 class="address-edit-modal__title">Edit Shipping Address</h3>
    
    <form class="address-edit-form" method="POST" action="/pages/profile/profile_update_post.php" id="addressEditForm">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <input type="hidden" name="username" value="<?= htmlspecialchars($member['username']) ?>">
      <input type="hidden" name="email" value="<?= htmlspecialchars($member['email']) ?>">
      <input type="hidden" name="phone" value="<?= htmlspecialchars($member['phone_number'] ?? '') ?>">
      <input type="hidden" name="redirect_to" value="address">
      
      <div class="address-edit-fields">
        <label class="form__label">
          <span>Street Address</span>
          <input
            type="text"
            name="address_street"
            class="form__input"
            value="<?= htmlspecialchars($addressParts['street']) ?>"
            placeholder="Enter street address" />
        </label>

        <div class="form-row">
          <label class="form__label">
            <span>City</span>
            <input
              type="text"
              name="address_city"
              class="form__input"
              value="<?= htmlspecialchars($addressParts['city']) ?>"
              placeholder="Enter city" />
          </label>

          <label class="form__label">
            <span>State</span>
            <select name="address_state" class="form__input" id="addressEditState">
              <option value="">Select State</option>
              <?php foreach ($malaysianStates as $state): ?>
                <option value="<?= htmlspecialchars($state) ?>" <?= $addressParts['state'] === $state ? 'selected' : '' ?>>
                  <?= htmlspecialchars($state) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>

        <div class="form-row">
          <label class="form__label">
            <span>Postcode</span>
            <input
              type="text"
              name="address_postcode"
              class="form__input"
              value="<?= htmlspecialchars($addressParts['postcode']) ?>"
              placeholder="e.g., 50000"
              pattern="\d{5}"
              maxlength="5" />
          </label>

          <label class="form__label">
            <span>Country</span>
            <input
              type="text"
              name="address_country"
              class="form__input"
              value="<?= htmlspecialchars($addressParts['country']) ?>"
              placeholder="Country"
              readonly />
          </label>
        </div>
      </div>

      <div class="address-edit-modal__actions">
        <button type="button" class="btn btn--md btn--outline" id="cancelAddressModalBtn">
          <i class="fi fi-rs-cross"></i> Cancel
        </button>
        <button type="submit" class="btn btn--md btn--primary">
          <i class="fi fi-rs-check"></i> Save Address
        </button>
      </div>
    </form>
  </div>
</div>

<?php include_once __DIR__ . '/partials/footer.php'; ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  // Cities by state mapping for JavaScript
  const citiesByState = <?= json_encode($citiesByState, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/account.js"></script>
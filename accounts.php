<?php
  $title = 'Ecommerce Website — Account';
  require_once __DIR__ . '/helper/pdo_connection.php';
  require_once __DIR__ . '/helper/auth.php';
  require_auth();

  $memberId = $_SESSION['member']['id'];

  // Fetch fresh member data from DB
  $stmt = $pdo->prepare("SELECT member_id, username, email, phone_number, address, profile_photo_path, register_date FROM Member WHERE member_id = ?");
  $stmt->execute([$memberId]);
  $member = $stmt->fetch(PDO::FETCH_ASSOC);
  
  // Parse address into components if it exists
  $addressParts = [
    'street' => '',
    'city' => '',
    'state' => '',
    'postcode' => '',
    'country' => 'Malaysia'
  ];
  
  if (!empty($member['address'])) {
    // Try to parse existing address (format: "Street, City, State Postcode, Country")
    $addr = $member['address'];
    // Simple parsing - split by commas and newlines
    $lines = preg_split('/[,\n]/', $addr);
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines);
    
    if (count($lines) >= 1) $addressParts['street'] = $lines[0];
    if (count($lines) >= 2) $addressParts['city'] = $lines[1];
    if (count($lines) >= 3) {
      // Check if this line has postcode (numbers)
      if (preg_match('/^(.+?)\s+(\d{5})$/', $lines[2], $matches)) {
        $addressParts['state'] = $matches[1];
        $addressParts['postcode'] = $matches[2];
      } else {
        $addressParts['state'] = $lines[2];
      }
    }
    if (count($lines) >= 4) {
      if (preg_match('/\d{5}/', $lines[3])) {
        $addressParts['postcode'] = $lines[3];
      } else {
        $addressParts['country'] = $lines[3];
      }
    }
    if (count($lines) >= 5) $addressParts['country'] = end($lines);
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
    header('Location: /auth/logout.php');
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
  if (!empty($member['address'])) {
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
      href="https://cdn-uicons.flaticon.com/2.0.0/uicons-regular-straight/css/uicons-regular-straight.css"
    />

    <!--=============== CSS ===============-->
    <link rel="stylesheet" href="/assets/css/styles.css" />
    <link rel="stylesheet" href="/assets/css/account.css" />

    <title><?= htmlspecialchars($title) ?></title>
  </head>
  <body>
<?php include_once __DIR__ . '/partials/header.php'; ?>

<main class="main">
  <!--=============== ACCOUNTS ===============-->
  <section class="accounts section--lg">
    <div class="accounts__container container grid">
      <div class="account__tabs">
        <p class="account__tab active-tab" data-target="#dashboard">
          <i class="fi fi-rs-settings-sliders"></i> Dashboard
        </p>
        <p class="account__tab" data-target="#orders">
          <i class="fi fi-rs-shopping-bag"></i> Orders
        </p>
        <p class="account__tab" data-target="#update-profile">
          <i class="fi fi-rs-user"></i> Update Profile
        </p>
        <p class="account__tab" data-target="#address">
          <i class="fi fi-rs-marker"></i> My Address
        </p>
        <p class="account__tab" data-target="#change-password">
          <i class="fi fi-rs-settings-sliders"></i> Change Password
        </p>
        <p class="account__tab">
          <i class="fi fi-rs-exit"></i>
          <a href="/auth/logout.php" style="color: inherit;">Logout</a>
        </p>
      </div>

      <div class="tabs__content">
        <div class="tab__content active-tab" id="dashboard" content>
          <div class="dashboard-profile-card">
            <div class="account-summary-avatar">
              <?php if (!empty($member['profile_photo_path'])): ?>
                <img src="<?= htmlspecialchars($member['profile_photo_path']) ?><?= $profilePhotoUpdated ? '?v=' . time() : '' ?>" alt="Profile photo" id="dashboardProfileImg">
              <?php else: ?>
                <i class="fi fi-rs-tree" id="dashboardProfileIcon"></i>
              <?php endif; ?>
            </div>
            <div class="account-summary-info">
              <p class="account-summary-name"><?= htmlspecialchars($member['username']) ?></p>
              <p class="account-summary-email"><?= htmlspecialchars($member['email']) ?></p>
              <p class="account-summary-meta">
                Member since <?= htmlspecialchars(date('M Y', strtotime($member['register_date']))) ?>
              </p>
            </div>
          </div>

          <div class="dashboard-header">
            <div class="dashboard-greeting">
              <h3 class="tab__header">Welcome back, <?= htmlspecialchars($member['username']) ?> 👋</h3>
              <p class="tab__description">
                Quickly see your recent activity, track your orders, and jump to common actions.
              </p>
            </div>
            <div class="dashboard-meta">
              <span>Member since <?= htmlspecialchars(date('d M Y', strtotime($member['register_date']))) ?></span>
              <span class="dashboard-meta__points">Reward points: <?= $rewardPoints ?></span>
            </div>
          </div>

          <div class="account-stats grid">
            <div class="account-stat-card stat-card--orders">
              <div class="stat-icon stat-icon--orders">
                <i class="fi fi-rs-shopping-bag"></i>
              </div>
              <div class="stat-body">
                <p class="stat-label">Total Orders</p>
                <p class="stat-value"><?= $stats['total_orders'] ?></p>
              </div>
            </div>

            <div class="account-stat-card stat-card--spent">
              <div class="stat-icon stat-icon--spent">
                <i class="fi fi-rs-credit-card"></i>
              </div>
              <div class="stat-body">
                <p class="stat-label">Total Spent</p>
                <p class="stat-value">RM <?= number_format($stats['total_spent'], 2) ?></p>
              </div>
            </div>

            <div class="account-stat-card stat-card--active">
              <div class="stat-icon stat-icon--active">
                <i class="fi fi-rs-truck-side"></i>
              </div>
              <div class="stat-body">
                <p class="stat-label">Active Orders</p>
                <p class="stat-value"><?= $stats['active_orders'] ?></p>
              </div>
            </div>

            <div class="account-stat-card stat-card--wishlist">
              <div class="stat-icon stat-icon--wishlist">
                <i class="fi fi-rs-heart"></i>
              </div>
              <div class="stat-body">
                <p class="stat-label">Wishlist Items</p>
                <p class="stat-value"><?= $stats['wishlist_items'] ?></p>
              </div>
            </div>
          </div>

          <div class="dashboard-panel profile-panel">
            <div class="profile-panel-left">
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
            <div class="profile-panel-right">
              <h5 class="dashboard-panel__subtitle">Profile checklist</h5>
              <ul class="profile-checklist">
                <li class="profile-check-item">
                  <span>
                    <strong>Email</strong>
                    <small>Your login and contact email.</small>
                  </span>
                  <span class="profile-check-badge profile-check-badge--done">Done</span>
                </li>
                <li class="profile-check-item">
                  <span>
                    <strong>Phone number</strong>
                    <small><?= $member['phone_number'] ? 'Used for contact and delivery.' : 'Add a phone number in "Update Profile".' ?></small>
                  </span>
                  <span class="profile-check-badge <?= $member['phone_number'] ? 'profile-check-badge--done' : 'profile-check-badge--missing' ?>">
                    <?= $member['phone_number'] ? 'Done' : 'Missing' ?>
                  </span>
                </li>
                <li class="profile-check-item">
                  <span>
                    <strong>Address</strong>
                    <small><?= $member['address'] ? 'We will use this as your default shipping address.' : 'Add your shipping address in "Update Profile".' ?></small>
                  </span>
                  <span class="profile-check-badge <?= $member['address'] ? 'profile-check-badge--done' : 'profile-check-badge--missing' ?>">
                    <?= $member['address'] ? 'Done' : 'Missing' ?>
                  </span>
                </li>
                <li class="profile-check-item">
                  <span>
                    <strong>Profile photo</strong>
                    <small><?= $member['profile_photo_path'] ? 'Helps personalize your account.' : 'Upload a profile photo in "Update Profile".' ?></small>
                  </span>
                  <span class="profile-check-badge <?= $member['profile_photo_path'] ? 'profile-check-badge--done' : 'profile-check-badge--missing' ?>">
                    <?= $member['profile_photo_path'] ? 'Done' : 'Missing' ?>
                  </span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <div class="tab__content" id="orders" content>
          <h3 class="tab__header">Your Orders</h3>
          <div class="tab__body">
            <p class="tab__description" style="margin-bottom:0.75rem;">
              This page is coming soon.
            </p>
          </div>
        </div>

        <div class="tab__content" id="update-profile" content>
          <div class="tab__body">
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

            <div class="profile-edit-card">
              <form class="profile-edit-form" method="POST" action="/auth/profile_update_post.php" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

                <!-- Profile Photo Section -->
                <div class="profile-section">
                  <h4 class="profile-section__title">Profile Photo</h4>
                  <div class="profile-edit-photo-row">
                    <div class="profile-photo-wrapper">
                      <div class="profile-photo-current" id="profilePhotoCurrent">
                        <?php if (!empty($member['profile_photo_path'])): ?>
                          <img src="<?= htmlspecialchars($member['profile_photo_path']) ?>" alt="Profile photo" id="currentProfileImg">
                        <?php else: ?>
                          <div class="profile-edit-avatar" id="defaultProfileAvatar">
                            <i class="fi fi-rs-tree"></i>
                          </div>
                        <?php endif; ?>
                        <img id="photoPreview" class="photo-preview" alt="Preview" style="display:none;">
                        <video id="webcamVideo" autoplay playsinline style="display:none;"></video>
                        <button type="button" id="removePreviewBtn" class="photo-preview-remove" title="Remove image" style="display:none;">
                          <i class="fi fi-rs-cross"></i>
                        </button>
                        <div class="webcam-controls" id="webcamControls" style="display:none;">
                          <button type="button" id="captureWebcamBtn" class="webcam-capture-btn" title="Capture photo">
                            <i class="fi fi-rs-camera"></i>
                          </button>
                          <button type="button" id="closeWebcamBtn" class="webcam-close-btn" title="Close webcam">
                            <i class="fi fi-rs-cross"></i>
                          </button>
                        </div>
                      </div>

                      <div class="photo-upload-options">
                        <div id="photoDropZone" class="photo-dropzone">
                          <p class="photo-dropzone__text">
                            Drag &amp; drop an image here, or <span class="photo-dropzone__browse">browse</span>
                          </p>
                          <input
                            type="file"
                            name="profile_photo"
                            id="profilePhotoInput"
                            accept="image/*"
                            style="display:none"
                          />
                        </div>

                        <div id="photoEditControls" class="photo-edit-controls" style="display:none;">
                          <div class="photo-edit-controls__title">Image Processing</div>
                          <div class="photo-edit-controls__buttons">
                            <button type="button" id="rotateLeftBtn" class="photo-edit-btn" title="Rotate Left">
                              <i class="fi fi-rs-rotate-left"></i>
                              <span>Rotate Left</span>
                            </button>
                            <button type="button" id="rotateRightBtn" class="photo-edit-btn" title="Rotate Right">
                              <i class="fi fi-rs-rotate-right"></i>
                              <span>Rotate Right</span>
                            </button>
                            <button type="button" id="flipHorizontalBtn" class="photo-edit-btn" title="Flip Horizontal">
                              <i class="fi fi-rs-arrows-horizontal"></i>
                              <span>Flip H</span>
                            </button>
                            <button type="button" id="flipVerticalBtn" class="photo-edit-btn" title="Flip Vertical">
                              <i class="fi fi-rs-arrows-vertical"></i>
                              <span>Flip V</span>
                            </button>
                            <button type="button" id="resetImageBtn" class="photo-edit-btn photo-edit-btn--reset" title="Reset">
                              <i class="fi fi-rs-refresh"></i>
                              <span>Reset</span>
                            </button>
                          </div>
                        </div>

                        <div class="photo-or">or</div>

                        <div class="photo-webcam">
                          <button type="button" id="openWebcamBtn" class="btn btn--sm">Use webcam</button>
                          <canvas id="webcamCanvas" style="display:none;"></canvas>
                        </div>
                      </div>
                    </div>
                    <input type="hidden" name="capture_data" id="captureData">
                    <p class="profile-photo-hint">
                      Optional. JPG, PNG or WEBP. Max 2MB. You can upload a file or capture with your webcam.
                    </p>
                  </div>
                </div>

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
                        placeholder="Enter your username"
                      />
                    </label>

                    <label class="form__label">
                      <span>Email <span class="required">*</span></span>
                      <input
                        type="email"
                        name="email"
                        class="form__input"
                        required
                        value="<?= htmlspecialchars($member['email']) ?>"
                        placeholder="Enter your email address"
                      />
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
                        title="Malaysian phone format: 0XX-XXXXXXX or 0XXXXXXXXX (10-11 digits)"
                      />
                    </label>

                    <label class="form__label form__label--full">
                      <span>Street Address</span>
                      <input
                        type="text"
                        name="address_street"
                        class="form__input"
                        value="<?= htmlspecialchars($addressParts['street']) ?>"
                        placeholder="e.g., No. 123, Jalan Bukit Bintang, Taman Desa"
                        minlength="5"
                        maxlength="200"
                        title="Enter your complete street address including house/building number and street name"
                      />
                    </label>

                    <label class="form__label">
                      <span>State</span>
                      <select
                        name="address_state"
                        class="form__input"
                        id="address_state"
                      >
                        <option value="">Select State</option>
                        <?php foreach ($malaysianStates as $state): ?>
                          <option value="<?= htmlspecialchars($state) ?>" <?= $addressParts['state'] === $state ? 'selected' : '' ?>>
                            <?= htmlspecialchars($state) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </label>

                    <label class="form__label">
                      <span>City</span>
                      <select
                        name="address_city"
                        class="form__input"
                        id="address_city"
                      >
                        <option value="">Select City</option>
                        <?php 
                          // Show cities based on selected state, or all cities if no state selected
                          $citiesToShow = [];
                          if (!empty($addressParts['state']) && isset($citiesByState[$addressParts['state']])) {
                            $citiesToShow = $citiesByState[$addressParts['state']];
                          } else {
                            $citiesToShow = $allCities;
                          }
                          foreach ($citiesToShow as $city): 
                        ?>
                          <option value="<?= htmlspecialchars($city) ?>" <?= $addressParts['city'] === $city ? 'selected' : '' ?>>
                            <?= htmlspecialchars($city) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </label>

                    <label class="form__label">
                      <span>Postcode</span>
                      <input
                        type="text"
                        name="address_postcode"
                        class="form__input"
                        value="<?= htmlspecialchars($addressParts['postcode']) ?>"
                        placeholder="e.g., 50000"
                        pattern="[0-9]{5}"
                        maxlength="5"
                      />
                    </label>

                    <label class="form__label">
                      <span>Country</span>
                      <input
                        type="text"
                        name="address_country"
                        class="form__input"
                        value="<?= htmlspecialchars($addressParts['country']) ?>"
                        placeholder="e.g., Malaysia"
                      />
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

        <div class="tab__content" id="address" content>
          <h3 class="tab__header">Shipping Address</h3>
          <div class="tab__body">
            <?php if (!empty($member['address'])): ?>
              <?php
                // Count how many orders used this address
                $orderCountStmt = $pdo->prepare("SELECT COUNT(*) FROM `Order` WHERE member_id = ? AND shipping_address = ?");
                $orderCountStmt->execute([$memberId, $member['address']]);
                $orderCount = (int)$orderCountStmt->fetchColumn();
                
                // Parse address for better display
                $addrLines = preg_split('/[,\n]/', $member['address']);
                $addrLines = array_map('trim', $addrLines);
                $addrLines = array_filter($addrLines);
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
                    <a href="#update-profile" class="btn btn--sm btn--primary" onclick="document.querySelector('[data-target=\'#update-profile\']').click(); return false;">
                      <i class="fi fi-rs-edit"></i> Edit
                    </a>
                  </div>
                </div>
                
                <div class="address-card__body">
                  <div class="address-display">
                    <?php foreach ($addrLines as $line): ?>
                      <div class="address-line"><?= htmlspecialchars($line) ?></div>
                    <?php endforeach; ?>
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
                    <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($member['address']) ?>" 
                       target="_blank" 
                       class="btn btn--sm btn--outline"
                       title="View on Google Maps">
                      <i class="fi fi-rs-map-marker"></i> View on Map
                    </a>
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

        <div class="tab__content" id="change-password" content>
          <h3 class="tab__header">Change Password</h3>
          <div class="tab__body">
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

              <form class="password-change-form" method="POST" action="/auth/password_update_post.php" novalidate id="passwordChangeForm">
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
                        autocomplete="current-password"
                      />
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
                        minlength="8"
                      />
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
                        autocomplete="new-password"
                      />
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
                  <button class="btn btn--md btn--primary" type="submit">
                    <i class="fi fi-rs-check"></i> Update Password
                  </button>
                  <button type="button" class="btn btn--md btn--outline" id="clearPasswordForm">
                    <i class="fi fi-rs-refresh"></i> Clear
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
<?php include_once __DIR__ . '/partials/footer.php'; ?>
<script>
// Cities by state mapping for JavaScript
const citiesByState = <?= json_encode($citiesByState, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/account.js"></script>

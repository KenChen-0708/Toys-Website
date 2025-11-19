<?php
$title = "Register";
require_once '../helper/pdo_connection.php';
require_once '../helper/auth.php';
require_guest();

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

    <title><?= htmlspecialchars($title) ?></title>
  </head>
  <body>
<?php 
include '../partials/header.php';

$errors = $_SESSION['errors'] ?? [];
$old = $_SESSION['old'] ?? [];
unset($_SESSION['errors'], $_SESSION['old']);

// Parse old address data if exists
$addressParts = [
  'street' => $old['address_street'] ?? '',
  'city' => $old['address_city'] ?? '',
  'state' => $old['address_state'] ?? '',
  'postcode' => $old['address_postcode'] ?? '',
  'country' => $old['address_country'] ?? 'Malaysia'
];
?>

<style>
  /* Password toggle button */
  .input-with-icon {
    position: relative;
    display: flex;
    align-items: center;
  }

  .password-toggle {
    position: absolute;
    right: 0.75rem;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    padding: 0;
  }

  /* Field hints – only visible on focus / error */
  .field-hint {
    font-size: 0.8rem;
    color: #6b7280;
    margin-top: 0.25rem;
    line-height: 1.3;
    opacity: 0;
    max-height: 0;
    transform: translateY(-2px);
    transition: opacity 0.18s ease, max-height 0.18s ease, transform 0.18s ease;
  }

  .field-hint--visible {
    opacity: 1;
    max-height: 80px;
    transform: translateY(0);
  }

  .hint--error {
    color: #b91c1c;
  }

  .form__input.input--error {
    border-color: #b91c1c;
    box-shadow: 0 0 0 1px rgba(185, 28, 28, 0.15);
  }
</style>

<main class="main">
  <section class="breadcrumb">
    <ul class="breadcrumb__list flex container">
      <li><a href="../index.php" class="breadcrumb__link">Home</a></li>
      <li><span class="breadcrumb__link">></span></li>
      <li><span class="breadcrumb__link">Register</span></li>
    </ul>
  </section>

  <section class="auth-hero">
      <div class="auth-card">
        <h1 class="auth-title">Create your account</h1>

        <?php if ($errors): ?>
          <div class="alert alert--danger" role="alert">
            <?php foreach($errors as $e): ?>
              <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form id="registerForm" class="form grid" method="POST" action="./register_post.php" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

          <!-- Account Information -->
          <div class="grid two">
            <label class="form__label">
              <span>User Name *</span>
              <input
                name="username"
                type="text"
                class="form__input"
                required
                minlength="3"
                autocomplete="username"
                data-hint="nameHint"
                value="<?= htmlspecialchars($old['username'] ?? '') ?>"
              />
              <p id="nameHint" class="field-hint">
                3+ characters. Letters, numbers, and "_" only. No spaces.
              </p>
            </label>

            <label class="form__label">
              <span>Email *</span>
              <div class="input-with-icon">
                <input
                  name="email"
                  type="email"
                  class="form__input"
                  required
                  autocomplete="email"
                  data-hint="emailHint"
                  value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                />
              </div>
              <p id="emailHint" class="field-hint">
                Use a valid email like <em>name@example.com</em>.
              </p>
            </label>
          </div>

          <div class="grid two">
            <label class="form__label">
              <span>Password *</span>
              <div class="input-with-icon">
                <input
                  id="pwd"
                  name="password"
                  type="password"
                  class="form__input"
                  required
                  minlength="8"
                  autocomplete="new-password"
                  pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$"
                  title="At least 8 characters, including uppercase, lowercase, number and symbol."
                  data-hint="pwdHint"
                />
                <button
                  type="button"
                  id="togglePwd"
                  class="password-toggle"
                  aria-label="Toggle password visibility"
                >👁</button>
              </div>
              <p id="pwdHint" class="field-hint">
                Use at least 8 characters with uppercase, lowercase, number and symbol.
              </p>
            </label>

            <label class="form__label">
              <span>Confirm Password *</span>
              <div class="input-with-icon">
                <input
                  id="pwd2"
                  name="password_confirm"
                  type="password"
                  class="form__input"
                  required
                  minlength="8"
                  autocomplete="new-password"
                  data-hint="pwd2Hint"
                />
                <button
                  type="button"
                  id="togglePwd2"
                  class="password-toggle"
                  aria-label="Toggle confirm password visibility"
                >👁</button>
              </div>
              <p id="pwd2Hint" class="field-hint">
                Re-enter the same password as above.
              </p>
            </label>
          </div>

          <!-- Contact Information -->
          <label class="form__label">
            <span>Phone</span>
            <input
              name="phone"
              type="tel"
              class="form__input"
              autocomplete="tel"
              data-hint="phoneHint"
              value="<?= htmlspecialchars($old['phone'] ?? '') ?>"
            />
            <p id="phoneHint" class="field-hint">
              Optional. Use 012-3456789 or 0123456789 format.
            </p>
          </label>

          <!-- Address Information -->
          <label class="form__label form__label--full">
            <span>Street Address</span>
            <input
              type="text"
              name="address_street"
              class="form__input"
              data-hint="addressStreetHint"
              value="<?= htmlspecialchars($addressParts['street']) ?>"
              placeholder="e.g., No. 123, Jalan Bukit Bintang, Taman Desa"
              minlength="5"
              maxlength="200"
            />
            <p id="addressStreetHint" class="field-hint">
              Optional. Enter your complete street address including house/building number and street name.
            </p>
          </label>

          <div class="grid two">
            <label class="form__label">
              <span>State</span>
              <select
                name="address_state"
                class="form__input"
                id="address_state"
                data-hint="addressStateHint"
              >
                <option value="">Select State</option>
                <?php foreach ($malaysianStates as $state): ?>
                  <option value="<?= htmlspecialchars($state) ?>" <?= $addressParts['state'] === $state ? 'selected' : '' ?>>
                    <?= htmlspecialchars($state) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <p id="addressStateHint" class="field-hint">
                Optional. Select your state.
              </p>
            </label>

            <label class="form__label">
              <span>City</span>
              <select
                name="address_city"
                class="form__input"
                id="address_city"
                data-hint="addressCityHint"
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
              <p id="addressCityHint" class="field-hint">
                Optional. Select your city.
              </p>
            </label>
          </div>

          <div class="grid two">
            <label class="form__label">
              <span>Postcode</span>
              <input
                type="text"
                name="address_postcode"
                class="form__input"
                data-hint="addressPostcodeHint"
                value="<?= htmlspecialchars($addressParts['postcode']) ?>"
                placeholder="e.g., 50000"
                pattern="[0-9]{5}"
                maxlength="5"
              />
              <p id="addressPostcodeHint" class="field-hint">
                Optional. Enter 5-digit postcode.
              </p>
            </label>

            <label class="form__label">
              <span>Country</span>
              <input
                type="text"
                name="address_country"
                class="form__input"
                data-hint="addressCountryHint"
                value="<?= htmlspecialchars($addressParts['country']) ?>"
                placeholder="e.g., Malaysia"
              />
              <p id="addressCountryHint" class="field-hint">
                Optional. Enter your country.
              </p>
            </label>
          </div>

          <button class="btn btn--primary btn--block" type="submit">Create account</button>
          <p class="auth-sub" style="margin-bottom: -5px;">
            Already have an account?
            <a href="./login.php" class="link">Login</a>
          </p>
        </form>
      </div>
  </section>
</main>

<script>
  const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&^_\-])[A-Za-z\d@$!%*#?&^_\-]{8,}$/;
  
  // Pass citiesByState data to JavaScript
  const citiesByState = <?= json_encode($citiesByState, JSON_UNESCAPED_UNICODE) ?>;

  document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registerForm');
    if (!form) return;

    const pwd = document.getElementById('pwd');
    const pwd2 = document.getElementById('pwd2');
    const pwdHint = document.getElementById('pwdHint');
    const pwd2Hint = document.getElementById('pwd2Hint');

    // Save default hint texts so we can restore them after errors
    const hintDefaults = {};
    document.querySelectorAll('.field-hint').forEach(function (hint) {
      hintDefaults[hint.id] = hint.textContent;
    });

    function resetHint(hintEl) {
      if (!hintEl) return;
      if (hintDefaults[hintEl.id]) {
        hintEl.textContent = hintDefaults[hintEl.id];
      }
      hintEl.classList.remove('hint--error');
    }

    function setError(field, hintEl, message) {
      if (field) {
        field.classList.add('input--error');
      }
      if (hintEl) {
        hintEl.textContent = message;
        hintEl.classList.add('field-hint--visible', 'hint--error');
      }
    }

    function clearError(field, hintEl) {
      if (field) {
        field.classList.remove('input--error');
      }
      if (hintEl) {
        resetHint(hintEl);
      }
    }

    // 👁 Password eye toggles for both fields
    const togglePwdBtn = document.getElementById('togglePwd');
    const togglePwd2Btn = document.getElementById('togglePwd2');

    togglePwdBtn?.addEventListener('click', () => {
      const show = pwd.type === 'password';
      pwd.type = show ? 'text' : 'password';
      togglePwdBtn.textContent = show ? '🙈' : '👁';
    });

    togglePwd2Btn?.addEventListener('click', () => {
      const show = pwd2.type === 'password';
      pwd2.type = show ? 'text' : 'password';
      togglePwd2Btn.textContent = show ? '🙈' : '👁';
    });

    // ✅ Generic focus-based hints for all fields with data-hint attribute
    document.querySelectorAll('[data-hint]').forEach(function (field) {
      const hintId = field.getAttribute('data-hint');
      const hintEl = document.getElementById(hintId);
      if (!hintEl) return;

      field.addEventListener('focus', function () {
        hintEl.classList.add('field-hint--visible');
      });

      field.addEventListener('blur', function () {
        // Keep visible if currently showing an error
        if (hintEl.classList.contains('hint--error')) return;
        hintEl.classList.remove('field-hint--visible');
      });
    });

    // Live password feedback while typing
    function checkMatch() {
      if (!pwdHint) return;

      clearError(pwd, pwdHint);
      clearError(pwd2, pwd2Hint);

      pwdHint.classList.add('field-hint--visible');
      if (pwd2Hint) pwd2Hint.classList.add('field-hint--visible');

      if (pwd.value && !passwordPattern.test(pwd.value)) {
        setError(pwd, pwdHint, 'Password must be at least 8 characters and include uppercase, lowercase, number and symbol.');
      }

      if (pwd.value && pwd2.value && pwd.value !== pwd2.value) {
        if (pwd2Hint) {
          setError(pwd2, pwd2Hint, 'Passwords do not match.');
        } else if (pwdHint) {
          setError(pwd2, pwdHint, 'Passwords do not match.');
        }
      }
    }

    pwd?.addEventListener('input', checkMatch);
    pwd2?.addEventListener('input', checkMatch);

    function validateForm() {
      let isValid = true;

      const usernameField = form.querySelector('input[name="username"]');
      const emailField = form.querySelector('input[name="email"]');
      const phoneField = form.querySelector('input[name="phone"]');

      const nameHint = document.getElementById('nameHint');
      const emailHint = document.getElementById('emailHint');
      const phoneHint = document.getElementById('phoneHint');

      // Username
      const username = usernameField.value.trim();
      clearError(usernameField, nameHint);

      if (!username) {
        setError(usernameField, nameHint, 'Username is required.');
        isValid = false;
      } else if (username.length < 3) {
        setError(usernameField, nameHint, 'Username must be at least 3 characters.');
        isValid = false;
      } else if (/\s/.test(username)) {
        setError(usernameField, nameHint, 'Username cannot contain spaces. Use "_" instead.');
        isValid = false;
      } else if (!/^[A-Za-z0-9_]+$/.test(username)) {
        setError(usernameField, nameHint, 'Username can only contain letters, numbers, and underscores.');
        isValid = false;
      }

      // Email
      const email = emailField.value.trim();
      clearError(emailField, emailHint);

      if (!email) {
        setError(emailField, emailHint, 'Email is required.');
        isValid = false;
      } else if (!emailField.checkValidity()) {
        setError(emailField, emailHint, 'Please enter a valid email address.');
        isValid = false;
      }

      // Password
      clearError(pwd, pwdHint);
      clearError(pwd2, pwd2Hint);

      const password = pwd.value.trim();
      const passwordConfirm = pwd2.value.trim();

      if (!password) {
        setError(pwd, pwdHint, 'Password is required.');
        isValid = false;
      } else if (!passwordPattern.test(password)) {
        setError(pwd, pwdHint, 'Password must be at least 8 characters and include uppercase, lowercase, number and symbol.');
        isValid = false;
      }

      if (!passwordConfirm) {
        if (pwd2Hint) {
          setError(pwd2, pwd2Hint, 'Confirm Password is required.');
        } else {
          setError(pwd2, pwdHint, 'Confirm Password is required.');
        }
        isValid = false;
      } else if (password && passwordConfirm && password !== passwordConfirm) {
        if (pwd2Hint) {
          setError(pwd2, pwd2Hint, 'Passwords do not match.');
        } else {
          setError(pwd2, pwdHint, 'Passwords do not match.');
        }
        isValid = false;
      }

      // Phone (optional) – simple pattern if user fills it
      const phone = phoneField.value.trim();
      clearError(phoneField, phoneHint);
      if (phone && !/^0\d{1,2}-?\d{6,8}$/.test(phone)) {
        setError(phoneField, phoneHint, 'Please enter a valid phone format, e.g. 012-3456789.');
        isValid = false;
      }

      // Address fields (optional) – validate if provided
      const addressStreet = form.querySelector('input[name="address_street"]').value.trim();
      const addressPostcode = form.querySelector('input[name="address_postcode"]').value.trim();
      const addressStreetHint = document.getElementById('addressStreetHint');
      const addressPostcodeHint = document.getElementById('addressPostcodeHint');
      
      clearError(form.querySelector('input[name="address_street"]'), addressStreetHint);
      clearError(form.querySelector('input[name="address_postcode"]'), addressPostcodeHint);
      
      if (addressStreet && addressStreet.length < 5) {
        setError(form.querySelector('input[name="address_street"]'), addressStreetHint, 'Street address is too short. Please provide a complete address (minimum 5 characters).');
        isValid = false;
      }
      
      if (addressPostcode && !/^\d{5}$/.test(addressPostcode)) {
        setError(form.querySelector('input[name="address_postcode"]'), addressPostcodeHint, 'Postcode must be exactly 5 digits.');
        isValid = false;
      }

      return isValid;
    }

    // State/City dropdown interaction
    const stateSelect = document.getElementById('address_state');
    const citySelect = document.getElementById('address_city');
    
    if (stateSelect && citySelect && typeof citiesByState !== 'undefined') {
      // Update city options when state changes
      stateSelect.addEventListener('change', function() {
        const selectedState = this.value;
        
        // Clear current city selection
        citySelect.innerHTML = '<option value="">Select City</option>';
        
        // Get cities for selected state
        if (selectedState && citiesByState[selectedState]) {
          const cities = citiesByState[selectedState];
          cities.forEach(function(city) {
            const option = document.createElement('option');
            option.value = city;
            option.textContent = city;
            citySelect.appendChild(option);
          });
        } else {
          // If no state selected, show all cities
          Object.values(citiesByState).flat().sort().forEach(function(city) {
            const option = document.createElement('option');
            option.value = city;
            option.textContent = city;
            citySelect.appendChild(option);
          });
        }
      });
    }

    // On submit, only allow if all client-side checks pass
    form.addEventListener('submit', function (e) {
      if (!validateForm()) {
        e.preventDefault();
      }
    });
  });
</script>

<?php include '../partials/footer.php'; ?>

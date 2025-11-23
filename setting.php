<?php
session_start();
require_once 'helper/pdo_connection.php'; 
require_once 'helper/auth.php'; 

// Ensure user is logged in
if (!isset($_SESSION['member'])) {
    header('Location: /pages/auth/login.php');
    exit;
}

$member_id = $_SESSION['member']['id'];
$message = '';
$message_type = '';

// --- 1. Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $theme = isset($_POST['theme_preference']) ? $_POST['theme_preference'] : 'light';
    $notifications = isset($_POST['email_notifications']) ? 1 : 0;

    // Validation
    if (!in_array($theme, ['light', 'dark'])) {
        $theme = 'light';
    }

    try {
        $sql = "INSERT INTO Member_Settings (member_id, theme_preference, email_notifications) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                theme_preference = VALUES(theme_preference), 
                email_notifications = VALUES(email_notifications)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$member_id, $theme, $notifications]);

        $_SESSION['member']['theme'] = $theme;

        $message = "Settings saved successfully!";
        $message_type = "success";
        
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

// --- 2. Fetch Current Settings ---
try {
    $stmt = $pdo->prepare("SELECT * FROM Member_Settings WHERE member_id = ?");
    $stmt->execute([$member_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    $current_theme = $settings['theme_preference'] ?? 'light';
    $current_notif = $settings['email_notifications'] ?? 1;

} catch (PDOException $e) {
    $current_theme = 'light';
    $current_notif = 1;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Evara</title>
    
    <link rel="stylesheet" href="assets/css/styles.css">

    <style>
        /* =========================================
           1. CORE VARIABLES & DARK MODE OVERRIDES
           ========================================= */
        :root {
            --theme-transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        body {
            transition: var(--theme-transition);
        }

        /* DARK MODE VARIABLES */
        body.dark-theme {
            /* Backgrounds */
            --body-color: #0f172a;       /* Very dark slate */
            --container-color: #1e293b;  /* Lighter slate for cards */
            --nav-bg: #1e293b;           /* Nav background */
            
            /* CHANGE: Set footer-bg to #1e293b (same as nav-bg) instead of #0f172a */
            --footer-bg: #1e293b;        
            
            /* Text Colors */
            --title-color: #f1f5f9;      /* Bright White-ish */
            --text-color: #cbd5e1;       /* Light Gray */
            --text-color-light: #94a3b8; /* Dim Gray */
            
            /* Borders */
            --border-color: #334155;
            
            /* Accents */
            --first-color: #34d399;      /* Bright Emerald */
            
            /* Inputs */
            --input-bg: #0f172a;
        }

        /* =========================================
           2. FIX: HEADER & FOOTER TEXT VISIBILITY
           ========================================= */
        
        /* Force Header/Nav links to be light in dark mode */
        body.dark-theme .nav__link {
            color: var(--title-color);
        }

        /* Fix Header Icons (Wishlist, Cart) - turn black icons white */
        body.dark-theme .header__action-btn img {
            filter: brightness(0) invert(1);
        }
        
        body.dark-theme .nav__link:hover,
        body.dark-theme .active-link {
            color: var(--first-color);
        }
        
        body.dark-theme .header__top {
            background-color: #020617; /* Extra dark for top bar */
            color: #e2e8f0;
        }
        
        /* Force Footer text to be light (Overriding the hardcoded #0f3c2f) */
        body.dark-theme .footer__title,
        body.dark-theme .footer__link,
        body.dark-theme .footer__description,
        body.dark-theme .footer__subtitle,
        body.dark-theme .copyright, 
        body.dark-theme .designer,
        body.dark-theme .footer__contact {
            color: var(--text-color);
        }
        
        body.dark-theme .footer__link:hover {
            color: var(--first-color);
            margin-left: 0.25rem; /* Keep the hover animation */
        }
        
        /* Logo Fix (If your logo is dark text) */
        /* Optional: Add a filter to invert logo color if it's an image with transparent bg */
        body.dark-theme .nav__logo-img,
        body.dark-theme .footer__logo-img {
            filter: brightness(0) invert(1);
        }

        /* =========================================
           3. SETTINGS PAGE LAYOUT
           ========================================= */
        .settings-container {
            max-width: 700px;
            margin: 3rem auto;
            padding: 0 1.5rem;
        }

        .settings-card {
            background-color: var(--container-color);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 2.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
            transition: var(--theme-transition);
        }

        /* Dark mode input fix */
        body.dark-theme .form__input,
        body.dark-theme input[type="text"],
        body.dark-theme input[type="email"] {
            background-color: var(--body-color);
            border-color: var(--border-color);
            color: var(--title-color);
        }

        .settings-header {
            margin-bottom: 2.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1.5rem;
        }

        .settings-header h2 {
            font-size: 1.75rem;
            color: var(--title-color);
        }

        .settings-header p {
            color: var(--text-color-light);
        }

        .settings-group { margin-bottom: 2.5rem; }
        
        .settings-group h3 {
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--first-color);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .setting-info h4 {
            font-size: 1rem;
            color: var(--title-color);
            font-weight: 600;
        }
        .setting-info p {
            font-size: 0.875rem;
            color: var(--text-color-light);
        }

        /* =========================================
           4. SWITCH TOGGLE UI
           ========================================= */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
            cursor: pointer;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #cbd5e1;
            transition: .4s;
            border-radius: 34px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 6px;
        }
        
        .icon-sun { color: #f59e0b; font-size: 14px; }
        .icon-moon { color: #f1f5f9; font-size: 14px; opacity: 0.5; }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px; width: 22px;
            left: 4px; bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            z-index: 2;
        }

        input:checked + .slider { background-color: #334155; }
        input:checked + .slider:before { transform: translateX(30px); }
        input:checked + .slider .icon-sun { opacity: 0.5; }
        input:checked + .slider .icon-moon { opacity: 1; }
        
        .form-actions { margin-top: 2rem; text-align: right; }
        .btn { padding: 12px 24px; border-radius: 8px; }
        
        /* Alert Box */
        .alert-box {
            padding: 1rem; border-radius: 8px; margin-bottom: 2rem;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    </style>
</head>

<body class="<?php echo ($current_theme === 'dark') ? 'dark-theme' : ''; ?>">
    
    <?php include 'partials/header.php'; ?>

    <main class="main">
        <div class="settings-container">
            
            <?php if ($message): ?>
                <div class="alert-box alert-<?php echo $message_type; ?>">
                    <?php echo ($message_type == 'success') ? '✅' : '⚠️'; ?>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="settings-card">
                <div class="settings-header">
                    <h2>Preferences</h2>
                    <p>Manage your interface experience and notifications.</p>
                </div>

                <div class="settings-group">
                    <h3>Appearance</h3>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Dark Mode</h4>
                            <p>Adjust the screen brightness for low-light environments.</p>
                        </div>
                        
                        <input type="hidden" name="theme_preference" id="themeInput" value="<?php echo htmlspecialchars($current_theme); ?>">
                        
                        <label class="switch" aria-label="Toggle Dark Mode">
                            <input type="checkbox" id="themeToggle" <?php echo ($current_theme === 'dark') ? 'checked' : ''; ?>>
                            <span class="slider">
                                <span class="icon-sun">☀️</span>
                                <span class="icon-moon">🌙</span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="settings-group">
                    <h3>Notifications</h3>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Email Updates</h4>
                            <p>Receive weekly newsletters and promotion alerts.</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="email_notifications" value="1" 
                                <?php echo ($current_notif == 1) ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">Save Changes</button>
                </div>
            </form>
        </div>
    </main>

    <?php include 'partials/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('themeToggle');
            const themeInput = document.getElementById('themeInput');
            const body = document.body;

            if (themeToggle && themeInput) {
                themeToggle.addEventListener('change', function() {
                    if (this.checked) {
                        // Switch to Dark
                        body.classList.add('dark-theme');
                        themeInput.value = 'dark';
                    } else {
                        // Switch to Light
                        body.classList.remove('dark-theme');
                        themeInput.value = 'light';
                    }
                });
            }
        });
    </script>
</body>
</html>
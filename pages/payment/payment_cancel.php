<?php
session_start();
require '../../helper/pdo_connection.php';

// Check if user is logged in
if (!isset($_SESSION['member'])) {
    header('Location: /pages/auth/login.php');
    exit();
}

$member_id = $_SESSION['member']['id'];

// Check if checkout data exists in session
if (!isset($_SESSION['checkout_data'])) {
    header('Location: ../checkout/checkout.php?error=session_expired');
    exit();
}

$checkout_data = $_SESSION['checkout_data'];

// Validate checkout data belongs to current user
if ($checkout_data['member_id'] !== $member_id) {
    unset($_SESSION['checkout_data']);
    header('Location: ../checkout/checkout.php?error=invalid_session');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled</title>
    <link rel="stylesheet" href="/assets/css/payment_cancel.css">
</head>

<body class="<?php echo (isset($_SESSION['member']['theme']) && $_SESSION['member']['theme'] === 'dark') ? 'dark-theme' : ''; ?>">
    <div class="cancel-container">
        <div class="cancel-icon"></div>

        <h1>Payment Cancelled</h1>
        <p class="message">
            Your payment was cancelled. Don't worry, your order is still saved and you can complete the payment anytime.
        </p>

        <div class="order-info">
            <strong>Your checkout is still saved</strong>
            <p>Total Amount: RM<?php echo number_format($checkout_data['final_amount'], 2); ?></p>
        </div>

        <ul class="info-list">
            <li>✓ Your items are still in your cart</li>
            <li>✓ No order was created yet</li>
            <li>✓ You can complete payment anytime</li>
        </ul>

        <div class="action-buttons">
            <a href="payment.php" class="btn btn-primary">
                Try Payment Again
            </a>
            <a href="../checkout/checkout.php" class="btn btn-secondary">
                Back to Checkout
            </a>
        </div>
    </div>
    
    <script src="/assets/js/payment_cancel.js"></script>
</body>

</html>
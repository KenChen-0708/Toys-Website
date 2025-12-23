<?php
session_start();
require '../../helper/pdo_connection.php';

// Check if user is logged in
if (!isset($_SESSION['member'])) {
    header('Location: /pages/auth/login.php');
    exit();
}

$member_id = $_SESSION['member']['id'];
$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    header('Location: ../checkout/checkout.php');
    exit();
}

try {
    // Get order details and validate it's still payable
    $stmt = $pdo->prepare("
        SELECT o.*, COUNT(oi.order_item_id) as item_count
        FROM `Order` o
        LEFT JOIN Order_Item oi ON o.order_id = oi.order_id
        WHERE o.order_id = ? AND o.member_id = ? AND o.status = 'Pending'
        GROUP BY o.order_id
    ");
    $stmt->execute([$order_id, $member_id]);
    $order = $stmt->fetch();

    if (!$order) {
        // Order not found, completed, or not belonging to user
        header('Location: ../cart/cart.php?error=order_not_found');
        exit();
    }
    
    if ($order['item_count'] == 0) {
        // Order has no items
        header('Location: ../cart/cart.php?error=invalid_order');
        exit();
    }
} catch (PDOException $e) {
    header('Location: ../cart/cart.php?error=database_error');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .cancel-container {
            background: white;
            border-radius: 20px;
            padding: 50px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .cancel-icon {
            width: 100px;
            height: 100px;
            background: #ff9800;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            animation: scaleIn 0.5s ease;
        }

        .cancel-icon::after {
            content: '!';
            color: white;
            font-size: 60px;
            font-weight: bold;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }

            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        h1 {
            font-size: 32px;
            color: #333;
            margin-bottom: 15px;
        }

        .message {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .order-info {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 12px;
            padding: 20px;
            margin: 30px 0;
            color: #856404;
        }

        .order-info strong {
            display: block;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .btn {
            display: inline-block;
            padding: 15px 40px;
            margin: 10px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #ff9800;
            color: white;
        }

        .btn-primary:hover {
            background: #f57c00;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
                text-align: center;
            }
        }

        .info-list {
            text-align: left;
            margin: 20px 0;
            padding-left: 20px;
        }

        .info-list li {
            margin: 10px 0;
            color: #555;
            line-height: 1.6;
        }
    </style>
</head>

<body class="<?php echo (isset($_SESSION['member']['theme']) && $_SESSION['member']['theme'] === 'dark') ? 'dark-theme' : ''; ?>">
    <div class="cancel-container">
        <div class="cancel-icon"></div>

        <h1>Payment Cancelled</h1>
        <p class="message">
            Your payment was cancelled. Don't worry, your order is still saved and you can complete the payment anytime.
        </p>

        <div class="order-info">
            <strong>Order #<?php echo $order['order_id']; ?> is still pending</strong>
            <p>Total Amount: RM<?php echo number_format($order['total_amount'], 2); ?></p>
        </div>

        <ul class="info-list">
            <li>✓ Your order has been saved</li>
            <li>✓ Items are reserved for you</li>
            <li>✓ You can complete payment later</li>
        </ul>

        <div class="action-buttons">
            <a href="payment.php?order_id=<?php echo $order_id; ?>" class="btn btn-primary">
                Try Payment Again
            </a>
            <a href="../../index.php" class="btn btn-secondary">
                Back to Home
            </a>
        </div>
    </div>
    
    <script>
        // Prevent browser back button after payment cancellation
        if (window.history && window.history.pushState) {
            window.history.replaceState(null, null, window.location.href);
            window.history.pushState(null, null, window.location.href);
            
            window.addEventListener('popstate', function(event) {
                // Redirect to home page instead of going back to payment
                window.location.href = '../../index.php';
            });
        }
        
        // Auto-redirect to home page after 60 seconds
        let countdown = 60;
        const countdownElement = document.createElement('div');
        countdownElement.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 14px;
            z-index: 1000;
        `;
        countdownElement.textContent = `Redirecting to home in ${countdown}s`;
        document.body.appendChild(countdownElement);
        
        const countdownTimer = setInterval(() => {
            countdown--;
            countdownElement.textContent = `Redirecting to home in ${countdown}s`;
            
            if (countdown <= 0) {
                clearInterval(countdownTimer);
                window.location.href = '../../index.php';
            }
        }, 1000);
        
        // Allow user to cancel auto-redirect by clicking the countdown
        countdownElement.addEventListener('click', () => {
            clearInterval(countdownTimer);
            countdownElement.textContent = 'Auto-redirect cancelled';
            setTimeout(() => {
                countdownElement.remove();
            }, 2000);
        });
        
        countdownElement.title = 'Click to cancel auto-redirect';
        countdownElement.style.cursor = 'pointer';
    </script>
</body>

</html>
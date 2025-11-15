<?php
// checkout.php

require 'stripe-php-master/init.php'; // Stripe PHP SDK
\Stripe\Stripe::setApiKey('sk_test_51SSdhpL3TTFqZeEiC8Zq2GlP6vX1oU0BVr1Il5xr0T5OXKXkewj27TGIxs0AvwzSfcHlY2znCsIlexDHk9gbJOXE00vbXRgf2n'); // Replace with your Secret Key

// Database connection
$host = "localhost";
$db = "assignment";
$user = "root";
$pass = "";
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Assume user is logged in
$member_id = 1; // Replace with session user_id

// 1️⃣ Retrieve cart items
$sql = "SELECT ci.quantity, p.price, p.product_id, p.name
        FROM Cart_Item ci
        JOIN Cart c ON ci.cart_id = c.cart_id
        JOIN Product p ON ci.product_id = p.product_id
        WHERE c.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
$total_amount = 0;
$product_summary = [];

while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
    $total_amount += $row['price'] * $row['quantity'];
    $product_summary[] = $row['name'] . " x" . $row['quantity'];
}

$summary_str = implode(", ", $product_summary);
$amount_in_cents = $total_amount * 100; // Stripe expects cents
$conn->close();

// 2️⃣ Create Stripe Checkout Session
$domain = 'http://localhost:8000'; // replace 'yourproject' with your folder path
try {
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'myr',
                'product_data' => [
                    'name' => $summary_str,
                ],
                'unit_amount' => $amount_in_cents,
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => $domain . '/?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => $domain . '/',
        'metadata' => [
            'member_id' => $member_id
        ],

        // 1. Force collection of the full Billing Address
        'billing_address_collection' => 'required', // or 'auto' (default)

        // 2. Enable collection of the Shipping Address
        'shipping_address_collection' => [
            // Specify which countries you ship to using 2-letter ISO codes
            'allowed_countries' => ['MY'],
        ],
    ]);

    // Redirect user to Stripe-hosted Checkout page
    header("Location: " . $session->url);
    exit();

} catch (Exception $e) {
    die("Error creating Stripe Checkout Session: " . $e->getMessage());
}

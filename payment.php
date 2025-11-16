<?php
// checkout.php

require 'stripe-php-master/init.php'; // Stripe PHP SDK
require 'helper/pdo_connection.php';   // PDO connection

\Stripe\Stripe::setApiKey('sk_test_51SSdhpL3TTFqZeEiC8Zq2GlP6vX1oU0BVr1Il5xr0T5OXKXkewj27TGIxs0AvwzSfcHlY2znCsIlexDHk9gbJOXE00vbXRgf2n'); // Replace with your Secret Key

// Assume user is logged in
$member_id = 1; // Replace with session user_id

try {
    // 1️⃣ Retrieve cart items
    $sql = "SELECT ci.quantity, p.price, p.product_id, p.name
            FROM Cart_Item ci
            JOIN Cart c ON ci.cart_id = c.cart_id
            JOIN Product p ON ci.product_id = p.product_id
            WHERE c.member_id = :member_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['member_id' => $member_id]);
    $cart_items = $stmt->fetchAll();

    if (!$cart_items) {
        die("Your cart is empty.");
    }

    $total_amount = 0;
    $product_summary = [];
    foreach ($cart_items as $row) {
        $total_amount += $row['price'] * $row['quantity'];
        $product_summary[] = $row['name'] . " x" . $row['quantity'];
    }

    $summary_str = implode(", ", $product_summary);
    $amount_in_cents = $total_amount * 100; // Stripe expects amount in cents

    // 2️⃣ Create Stripe Checkout Session
    $domain = 'http://localhost:8000'; // Replace with your project folder or domain

    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card', 'fpx', 'grabpay'],
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
        'billing_address_collection' => 'required',
        'shipping_address_collection' => [
            'allowed_countries' => ['MY'],
        ],
    ]);

    // Redirect to Stripe Checkout
    header("Location: " . $session->url);
    exit();

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

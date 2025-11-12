<?php

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'online_store'; 

// Create connection
// Disable mysqli exceptions locally and catch constructor failures to keep UI rendering
mysqli_report(MYSQLI_REPORT_OFF);
$conn = null;
$db_ok = true;
try {
    $conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);
} catch (mysqli_sql_exception $e) {
    $db_ok = false;
}

// Check connection
if (!$conn || $conn->connect_error) {
    $db_ok = false;
}

if (!$db_ok) {
    echo "<p style='color:red;'>Database connection failed. Please check your configuration or try again later.</p>";

    // Optionally, show detailed error info for debugging (not in production!)
    // echo "<p>Error: " . $conn->connect_error . "</p>";
    exit; // Stop further execution
}
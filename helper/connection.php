<?php

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'online_store'; 

// Create connection
// Disable mysqli exceptions locally and catch constructor failures to keep UI rendering
mysqli_report(MYSQLI_REPORT_OFF);

/** @var mysqli|null $conn */
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


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
    echo '<div style="background:#f8d7da; color:#721c24; padding:10px; text-align:center; position:fixed; top:0; left:0; width:100%; z-index:999;">
        ⚠️ Database connection failed. Some data may not be available.
    </div>';
}

<?php
/**
 * PDO Database Connection
 * Establishes a PDO connection to the database as per assignment requirements
 */

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'online_store';

$pdo = null;
$db_ok = true;

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_ok = false;
    error_log("PDO Connection Error: " . $e->getMessage());
    // Optionally, show a user-friendly message without exposing raw error
    // echo "<p style='color:red;'>Database connection failed. Please try again later.</p>";
    // exit;
}


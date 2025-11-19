<?php
// PDO Database Connection
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'online_store';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    // Make PDO available globally for permission checking
    $GLOBALS['pdo'] = $pdo;
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

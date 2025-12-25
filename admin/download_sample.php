<?php
// Simple download handler for sample CSV file
session_start();
require_once '../helper/auth.php';

// Check if user is admin
if (!isset($_SESSION['admin'])) {
    header('HTTP/1.0 403 Forbidden');
    exit('Access denied');
}

$file = 'sample_csv/sample_products.csv';

// Check if file exists
if (!file_exists($file)) {
    // Log the error for debugging
    error_log("Sample products file not found: " . realpath('.') . '/' . $file);
    header('HTTP/1.0 404 Not Found');
    exit('File not found: sample_products.csv');
}

// Verify file is readable
if (!is_readable($file)) {
    error_log("Sample products file not readable: " . realpath('.') . '/' . $file);
    header('HTTP/1.0 403 Forbidden');
    exit('File not accessible');
}

// Set headers for file download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="sample_products.csv"');
header('Content-Length: ' . filesize($file));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');
header('Pragma: public');

// Clear any output buffers
if (ob_get_level()) {
    ob_end_clean();
}

// Output file content
readfile($file);
exit();
?>
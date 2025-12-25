<?php
session_start();

// Clear OTP-related session data
unset($_SESSION['otp_required_profile']);
unset($_SESSION['otp_invalid']);
unset($_SESSION['pending_profile']);
unset($_SESSION['otp_profile']);

// Return success
header('Content-Type: application/json');
echo json_encode(['success' => true]);

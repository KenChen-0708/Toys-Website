<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * reCAPTCHA CONFIGURATION
 * These keys are used across the entire application.
 */
define('RECAPTCHA_SITE_KEY', '6LdtlBAsAAAAALXJnaRjT_-VL98wF1bYuzuwKbVj');
define('RECAPTCHA_SECRET_KEY', '6LdtlBAsAAAAAKEkbvhhoY94jGMkg6BHMiWVSKV0');

/**
 * Verify Google reCAPTCHA
 * @param string $response - The g-recaptcha-response from the form
 * @return bool
 */
if (!function_exists('verify_recaptcha')) {
    function verify_recaptcha($response)
    {
        if (empty($response)) {
            return false;
        }

        $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => RECAPTCHA_SECRET_KEY,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 10,
            ]
        ];

        $context = stream_context_create($options);
        $verify_response = @file_get_contents($verify_url, false, $context);

        if ($verify_response === false) {
            return false;
        }

        $response_data = json_decode($verify_response);
        return !empty($response_data->success);
    }
}

/**
 * Check if reCAPTCHA should be displayed based on failed attempts
 * @param string $session_key - The session key storing failed attempts count
 * @param int $threshold - Number of failed attempts before requiring CAPTCHA
 * @return bool
 */
function is_recaptcha_required($session_key = 'failed_login_count', $threshold = 3)
{
    $failed_attempts = $_SESSION[$session_key] ?? 0;
    return $failed_attempts >= $threshold;
}

/**
 * Get the reCAPTCHA site key
 * @return string
 */
function get_recaptcha_site_key()
{
    return RECAPTCHA_SITE_KEY;
}

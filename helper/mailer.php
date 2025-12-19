<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';

/**
 * Send an email using PHPMailer with common SMTP settings.
 *
 * @param string $toEmail
 * @param string $toName
 * @param string $subject
 * @param string $htmlBody
 * @param string $altBody
 * @return bool true on success, false on failure
 */
function send_app_mail(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = ''): bool
{
    $mail = new PHPMailer(true);

    try {
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'kaijian1003@gmail.com';
        $mail->Password   = 'joxs kmae bila jdnh'; // app password (consider moving to env variable)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender & recipient
        $mail->setFrom('kaijian1003@gmail.com', 'Toy World');
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody ?: strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

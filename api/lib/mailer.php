<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/env.php';

function send_mail(string $toEmail, string $toName, string $subject, string $html, string $text=''): bool {
  $mail = new PHPMailer(true);
  try {
    $mail->isSMTP();
    $mail->Host       = env('SMTP_HOST');
    $mail->Port       = (int)env('SMTP_PORT','587');
    $mail->SMTPSecure = env('SMTP_SECURE','tls');
    $mail->SMTPAuth   = true;
    $mail->Username   = env('SMTP_USER');
    $mail->Password   = env('SMTP_PASS');

    $mail->setFrom(env('SMTP_FROM'), env('SMTP_FROM_NAME','CollagenDirect'));
    $mail->addAddress($toEmail, $toName ?: $toEmail);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $html;
    $mail->AltBody = $text ?: strip_tags($html);

    $mail->send();
    return true;
  } catch (Exception $e) {
    error_log('Mailer error: '.$e->getMessage());
    return false;
  }
}

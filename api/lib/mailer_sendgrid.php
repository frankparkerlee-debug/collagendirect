<?php
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/env.php';

use SendGrid\Mail\Mail;

function sendgrid_send_template(string $toEmail, string $toName, string $templateId, array $dynamicData, ?string $fromEmail=null, ?string $fromName=null): bool {
  $sg = new \SendGrid(env('SENDGRID_API_KEY'));
  $email = new Mail();

  $fromEmail = $fromEmail ?: env('SMTP_FROM', 'clinical@collagendirect.com');
  $fromName  = $fromName  ?: env('SMTP_FROM_NAME', 'CollagenDirect');

  $email->setFrom($fromEmail, $fromName);
  $email->addTo($toEmail, $toName ?: $toEmail);
  $email->setTemplateId($templateId);
  $email->addDynamicTemplateDatas($dynamicData);

  try {
    $resp = $sg->send($email);
    return $resp->statusCode() >= 200 && $resp->statusCode() < 300;
  } catch (\Throwable $e) {
    error_log('SendGrid API error: '.$e->getMessage());
    return false;
  }
}

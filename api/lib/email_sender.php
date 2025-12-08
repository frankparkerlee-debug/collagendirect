<?php
declare(strict_types=1);

/**
 * Unified Email Sender
 *
 * Supports two methods:
 * 1. SMTP (Namecheap cPanel, Gmail, etc.) - Primary, simpler setup
 * 2. SendGrid API - Fallback if SMTP not configured
 *
 * For Namecheap Private Email, use these settings:
 * SMTP_HOST=mail.privateemail.com
 * SMTP_PORT=587
 * SMTP_SECURE=tls
 * SMTP_USER=no-reply@collagendirect.health
 * SMTP_PASS=your_email_password
 * SMTP_FROM=no-reply@collagendirect.health
 * SMTP_FROM_NAME=CollagenDirect
 */

require_once __DIR__ . '/env.php';

/**
 * Send email via SMTP or SendGrid (auto-detects based on config)
 *
 * @param string $toEmail Recipient email
 * @param string $toName Recipient name
 * @param string $subject Email subject
 * @param string $html HTML body
 * @param string $text Plain text body (optional, auto-generated from HTML if not provided)
 * @return bool Success status
 */
function send_email(string $toEmail, string $toName, string $subject, string $html, string $text = ''): bool {
    // Try SMTP first (Namecheap, Gmail, etc.)
    $smtpHost = env('SMTP_HOST');
    if ($smtpHost) {
        error_log("[email] Attempting SMTP send to $toEmail via $smtpHost");
        $result = send_email_smtp($toEmail, $toName, $subject, $html, $text);
        if ($result) {
            error_log("[email] SMTP send successful to $toEmail");
            return true;
        }
        error_log("[email] SMTP send failed, trying SendGrid fallback");
    }

    // Fallback to SendGrid
    $sendgridKey = env('SENDGRID_API_KEY');
    if ($sendgridKey) {
        error_log("[email] Attempting SendGrid send to $toEmail");
        return send_email_sendgrid($toEmail, $toName, $subject, $html, $text);
    }

    error_log("[email] ERROR: No email provider configured (SMTP_HOST or SENDGRID_API_KEY required)");
    return false;
}

/**
 * Send email via SMTP (PHPMailer)
 */
function send_email_smtp(string $toEmail, string $toName, string $subject, string $html, string $text = ''): bool {
    // Check if PHPMailer is available
    $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($autoloadPath)) {
        error_log("[email] PHPMailer not installed - run: composer require phpmailer/phpmailer");
        return false;
    }

    require_once $autoloadPath;

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("[email] PHPMailer class not found");
        return false;
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = env('SMTP_HOST');
        $mail->Port       = (int)env('SMTP_PORT', '587');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('SMTP_USER');
        $mail->Password   = env('SMTP_PASS');

        // TLS/SSL
        $secure = strtolower(env('SMTP_SECURE', 'tls'));
        if ($secure === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($secure === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }

        // Sender
        $mail->setFrom(
            env('SMTP_FROM', 'no-reply@collagendirect.health'),
            env('SMTP_FROM_NAME', 'CollagenDirect')
        );

        // Recipient
        $mail->addAddress($toEmail, $toName ?: $toEmail);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text ?: strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html));
        $mail->CharSet = 'UTF-8';

        $mail->send();
        return true;

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log("[email] SMTP Error: " . $e->getMessage());
        return false;
    } catch (\Throwable $e) {
        error_log("[email] SMTP Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email via SendGrid API
 */
function send_email_sendgrid(string $toEmail, string $toName, string $subject, string $html, string $text = ''): bool {
    $apiKey = env('SENDGRID_API_KEY');
    if (!$apiKey) {
        error_log("[email] SendGrid API key not configured");
        return false;
    }

    $fromEmail = env('SMTP_FROM', 'no-reply@collagendirect.health');
    $fromName = env('SMTP_FROM_NAME', 'CollagenDirect');

    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $toEmail, 'name' => $toName ?: $toEmail]],
                'subject' => $subject
            ]
        ],
        'from' => ['email' => $fromEmail, 'name' => $fromName],
        'content' => [
            ['type' => 'text/plain', 'value' => $text ?: strip_tags($html)],
            ['type' => 'text/html', 'value' => $html]
        ]
    ];

    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("[email] SendGrid cURL error: $error");
        return false;
    }

    if ($status === 202) {
        error_log("[email] SendGrid send successful to $toEmail");
        return true;
    }

    error_log("[email] SendGrid error (HTTP $status): $response");
    return false;
}

/**
 * Generate a nicely formatted HTML email template
 */
function email_template(string $title, string $bodyContent): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>$title</title>
</head>
<body style="margin:0; padding:0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f5f5f5;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f5f5f5; padding: 20px 0;">
    <tr>
      <td align="center">
        <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
          <!-- Header -->
          <tr>
            <td style="background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%); padding: 30px; text-align: center;">
              <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 600;">CollagenDirect</h1>
              <p style="color: rgba(255,255,255,0.9); margin: 5px 0 0 0; font-size: 14px;">Advanced Wound Care Solutions</p>
            </td>
          </tr>
          <!-- Body -->
          <tr>
            <td style="padding: 40px 30px;">
              $bodyContent
            </td>
          </tr>
          <!-- Footer -->
          <tr>
            <td style="background-color: #f8fafc; padding: 20px 30px; border-top: 1px solid #e2e8f0;">
              <p style="margin: 0 0 10px 0; font-size: 13px; color: #64748b;">
                <strong>Need Help?</strong><br>
                Email: <a href="mailto:support@collagendirect.health" style="color: #0d9488;">support@collagendirect.health</a><br>
                Phone: (888) 415-6880
              </p>
              <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                &copy; 2024 CollagenDirect. All rights reserved.<br>
                <a href="https://collagendirect.health" style="color: #0d9488;">collagendirect.health</a>
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}

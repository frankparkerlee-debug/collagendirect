<?php
declare(strict_types=1);

/**
 * Registration Welcome Email
 * Sends welcome email to self-registered users (no temp password needed)
 * Uses SMTP/Gmail via email_sender.php
 */

require_once __DIR__ . '/email_sender.php';

function send_registration_welcome_email(array $userData): bool {
  $email = $userData['email'];
  $name = trim(($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? ''));
  $userType = $userData['userType'];
  $practiceName = $userData['practiceName'] ?? '';

  // Build role-specific content
  $roleTitle = '';
  $portalAccess = '';
  $nextSteps = '';

  switch ($userType) {
    case 'practice_admin':
      $roleTitle = 'Practice Manager';
      $portalAccess = '
        <p><strong>Your Portal Access:</strong></p>
        <p>You now have full access to the CollagenDirect Physician Portal where you can:</p>
        <ul style="margin: 10px 0; padding-left: 20px;">
          <li>Create and manage patient orders</li>
          <li>Track order status and shipments</li>
          <li>Upload patient documentation</li>
          <li>Manage your practice team and physicians</li>
          <li>Configure billing and insurance settings</li>
        </ul>';
      $nextSteps = '
        <p><strong>Next Steps:</strong></p>
        <ol style="margin: 10px 0; padding-left: 20px;">
          <li>Log in to your portal</li>
          <li>Complete your practice profile</li>
          <li>Add physicians to your practice (if applicable)</li>
          <li>Review the training materials below</li>
          <li>Start creating orders for your patients</li>
        </ol>';
      break;

    case 'physician':
      $roleTitle = 'Physician';
      $pmEmail = $userData['practiceManagerEmail'] ?? '';
      $portalAccess = '
        <p><strong>Your Portal Access:</strong></p>
        <p>You now have access to the CollagenDirect Physician Portal where you can:</p>
        <ul style="margin: 10px 0; padding-left: 20px;">
          <li>Create and manage patient orders</li>
          <li>Review wound photos and assessments</li>
          <li>Track patient progress</li>
          <li>Generate billable E/M codes for telehealth reviews</li>
          <li>Access patient documentation</li>
        </ul>';
      $nextSteps = '
        <p><strong>Next Steps:</strong></p>
        <ol style="margin: 10px 0; padding-left: 20px;">
          <li>Log in to your portal</li>
          <li>Complete your physician profile</li>
          <li>Review the training materials below</li>
          <li>Start creating orders for your patients</li>
        </ol>';
      if ($pmEmail) {
        $nextSteps .= "<p style='margin-top: 10px;'>Your practice manager ($pmEmail) will manage your practice settings.</p>";
      }
      break;

    case 'dme_wholesale':
      $roleTitle = 'DME Wholesale Provider';
      $portalAccess = '
        <p><strong>Your Portal Access:</strong></p>
        <p>You now have access to wholesale ordering through CollagenDirect:</p>
        <ul style="margin: 10px 0; padding-left: 20px;">
          <li>Order products at wholesale pricing</li>
          <li>Track your account balance</li>
          <li>Manage direct billing to your patients/insurers</li>
          <li>Access all practice management features</li>
        </ul>';
      $nextSteps = '
        <p><strong>Next Steps:</strong></p>
        <ol style="margin: 10px 0; padding-left: 20px;">
          <li>Log in to your portal</li>
          <li>Complete your DME license verification</li>
          <li>Review wholesale pricing and payment terms</li>
          <li>Start placing wholesale orders</li>
        </ol>';
      break;

    default:
      $roleTitle = 'User';
      $portalAccess = '<p>You now have access to the CollagenDirect portal.</p>';
      $nextSteps = '';
  }

  $subject = "Welcome to CollagenDirect - Registration Complete";

  // Build HTML body content
  $practiceInfo = $practiceName ? "<p style='margin: 5px 0;'><strong>Practice:</strong> " . htmlspecialchars($practiceName) . "</p>" : '';

  $bodyContent = '
    <p style="font-size: 16px; color: #1e293b; margin-bottom: 20px;">Hello ' . htmlspecialchars($name) . ',</p>

    <p style="font-size: 16px; color: #1e293b; margin-bottom: 20px;">
      Welcome to CollagenDirect! Your registration as a ' . $roleTitle . ' has been successfully completed.
    </p>

    <div style="background-color: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 20px; margin: 20px 0;">
      <p style="margin: 0 0 10px 0; font-weight: 600; color: #0f766e;">Account Information:</p>
      <p style="margin: 5px 0; color: #1e293b;"><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
      ' . $practiceInfo . '
      <p style="margin: 15px 0 0 0;">
        <a href="https://collagendirect.health/portal" style="display: inline-block; background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%); color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600;">Log In to Portal</a>
      </p>
    </div>

    ' . $portalAccess . '
    ' . $nextSteps . '

    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
      <p style="font-weight: 600; color: #0f766e; margin-bottom: 10px;">Training & Support Resources:</p>
      <ul style="margin: 10px 0; padding-left: 20px; color: #1e293b;">
        <li><a href="https://collagendirect.health/portal-guide/" style="color: #0d9488;">Portal Training Guide</a></li>
        <li><a href="https://collagendirect.health/portal-guide/#videos" style="color: #0d9488;">Video Tutorials</a></li>
        <li><a href="https://collagendirect.health/portal-guide/#orders" style="color: #0d9488;">Order Creation Walkthrough</a></li>
        <li><a href="https://collagendirect.health/portal-guide/#faq" style="color: #0d9488;">FAQs</a></li>
      </ul>
    </div>

    <p style="font-size: 16px; color: #1e293b; margin-top: 25px;">
      Thank you for choosing CollagenDirect. We look forward to partnering with you!
    </p>

    <p style="font-size: 16px; color: #1e293b; margin-top: 20px;">
      Best regards,<br>
      <strong>The CollagenDirect Team</strong>
    </p>
  ';

  // Plain text version
  $plainText = "Hello $name,

Welcome to CollagenDirect! Your registration as a $roleTitle has been successfully completed.

ACCOUNT INFORMATION:
Email: $email" . ($practiceName ? "\nPractice: $practiceName" : "") . "

Log in at: https://collagendirect.health/portal

TRAINING & SUPPORT RESOURCES:
- Portal Training Guide: https://collagendirect.health/portal-guide/
- Video Tutorials: https://collagendirect.health/portal-guide/#videos
- Order Creation Walkthrough: https://collagendirect.health/portal-guide/#orders
- FAQs: https://collagendirect.health/portal-guide/#faq

Need Help?
- Email: support@collagendirect.health
- Phone: (888) 415-6880

Thank you for choosing CollagenDirect!

Best regards,
The CollagenDirect Team
";

  // Build HTML email using the email template
  $htmlBody = email_template($subject, $bodyContent);

  // Send via SMTP (Gmail/PHPMailer)
  try {
    $result = send_email($email, $name, $subject, $htmlBody, $plainText);
    if ($result) {
      error_log("[registration_welcome] Email sent to $email (user type: $userType)");
      return true;
    } else {
      error_log("[registration_welcome] Failed to send email to $email");
      return false;
    }
  } catch (\Throwable $e) {
    error_log("[registration_welcome] Exception: " . $e->getMessage());
    return false;
  }
}

/**
 * Send notification email to admin about new registration
 */
function send_admin_new_registration_notification(array $userData): bool {
  $adminEmail = getenv('ADMIN_EMAIL') ?: 'admin@collagendirect.health';

  $name = trim(($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? ''));
  $email = $userData['email'];
  $userType = $userData['userType'];
  $practiceName = $userData['practiceName'] ?? 'N/A';
  $npi = $userData['npi'] ?? 'N/A';
  $phone = $userData['phone'] ?? 'N/A';
  $city = $userData['city'] ?? '';
  $state = $userData['state'] ?? '';

  $subject = "New Registration: $name ($userType)";

  $bodyContent = '
    <h2 style="color: #1e293b; margin: 0 0 20px 0;">New User Registration</h2>
    <p style="color: #475569;">A new user has registered on CollagenDirect:</p>

    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0;">
      <p style="margin: 5px 0;"><strong>Name:</strong> ' . htmlspecialchars($name) . '</p>
      <p style="margin: 5px 0;"><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
      <p style="margin: 5px 0;"><strong>User Type:</strong> ' . htmlspecialchars($userType) . '</p>
      <p style="margin: 5px 0;"><strong>Practice:</strong> ' . htmlspecialchars($practiceName) . '</p>
      <p style="margin: 5px 0;"><strong>NPI:</strong> ' . htmlspecialchars($npi) . '</p>
      <p style="margin: 5px 0;"><strong>Phone:</strong> ' . htmlspecialchars($phone) . '</p>
      <p style="margin: 5px 0;"><strong>Location:</strong> ' . htmlspecialchars("$city, $state") . '</p>
      <p style="margin: 5px 0;"><strong>Time:</strong> ' . date('Y-m-d H:i:s T') . '</p>
    </div>

    <div style="text-align: center; margin: 30px 0;">
      <a href="https://collagendirect.health/admin" style="display: inline-block; background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%); color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600;">View in Admin Portal</a>
    </div>
  ';

  $plainText = "New User Registration

Name: $name
Email: $email
User Type: $userType
Practice: $practiceName
NPI: $npi
Phone: $phone
Location: $city, $state
Time: " . date('Y-m-d H:i:s T') . "

Admin Portal: https://collagendirect.health/admin
";

  $htmlBody = email_template($subject, $bodyContent);

  try {
    $result = send_email($adminEmail, 'Admin', $subject, $htmlBody, $plainText);
    if ($result) {
      error_log("[registration_welcome] Admin notification sent for: $email");
      return true;
    }
  } catch (\Throwable $e) {
    error_log("[registration_welcome] Admin notification failed: " . $e->getMessage());
  }

  return false;
}

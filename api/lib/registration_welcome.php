<?php
declare(strict_types=1);

/**
 * Registration Welcome Email
 * Sends welcome email to self-registered users (no temp password needed)
 */

require_once __DIR__ . '/sg_curl.php';

function send_registration_welcome_email(array $userData): bool {
  $apiKey = getenv('SENDGRID_API_KEY');
  if (!$apiKey) {
    error_log('SendGrid API key not configured');
    return false;
  }

  $fromEmail = getenv('SMTP_FROM') ?: 'no-reply@collagendirect.health';
  $fromName = getenv('SMTP_FROM_NAME') ?: 'CollagenDirect';

  $email = $userData['email'];
  $name = trim(($userData['firstName'] ?? '') . ' ' . ($userData['lastName'] ?? ''));
  $userType = $userData['userType'];
  $practiceName = $userData['practiceName'] ?? '';

  // Build role-specific message
  $roleTitle = '';
  $nextSteps = '';
  $portalAccess = '';

  switch ($userType) {
    case 'practice_admin':
      $roleTitle = 'Practice Manager';
      $portalAccess = "
**Your Portal Access:**
You now have full access to the CollagenDirect Physician Portal where you can:
- Create and manage patient orders
- Track order status and shipments
- Upload patient documentation
- Manage your practice team and physicians
- Configure billing and insurance settings";

      $nextSteps = "
**Next Steps:**
1. Log in to your portal at: https://collagendirect.health/portal
2. Complete your practice profile
3. Add physicians to your practice (if applicable)
4. Start creating orders for your patients";
      break;

    case 'physician':
      $roleTitle = 'Physician';
      $pmEmail = $userData['practiceManagerEmail'] ?? '';

      $portalAccess = "
**Your Portal Access:**
You now have access to the CollagenDirect Physician Portal where you can:
- Create and manage patient orders
- Review wound photos and assessments
- Track patient progress
- Generate billable E/M codes for telehealth reviews
- Access patient documentation";

      $nextSteps = "
**Next Steps:**
1. Log in to your portal at: https://collagendirect.health/portal
2. Complete your physician profile
3. Start creating orders for your patients";

      if ($pmEmail) {
        $nextSteps .= "\n4. Your practice manager ($pmEmail) will manage your practice settings";
      }
      break;

    case 'dme_hybrid':
      $roleTitle = 'DME Hybrid Provider';
      $portalAccess = "
**Your Portal Access:**
You now have full access to the CollagenDirect Physician Portal with hybrid billing features:
- Create orders billed through CollagenDirect OR your practice
- Configure insurance routing by payer
- Track wholesale purchases for direct-billed orders
- Manage both referral and direct billing workflows";

      $nextSteps = "
**Next Steps:**
1. Log in to your portal at: https://collagendirect.health/portal
2. Complete your practice and DME license information
3. Configure your billing routing preferences (Settings → Billing)
4. Start creating orders with flexible billing options";
      break;

    case 'dme_wholesale':
      $roleTitle = 'DME Wholesale Provider';
      $portalAccess = "
**Your Portal Access:**
You now have access to wholesale ordering through CollagenDirect:
- Order products at wholesale pricing
- Track your account balance
- Manage direct billing to your patients/insurers
- Access all practice management features";

      $nextSteps = "
**Next Steps:**
1. Log in to your portal at: https://collagendirect.health/portal
2. Complete your DME license verification
3. Review wholesale pricing and payment terms
4. Start placing wholesale orders";
      break;

    default:
      $roleTitle = 'User';
      $portalAccess = "You now have access to the CollagenDirect portal.";
      $nextSteps = "Log in at: https://collagendirect.health/portal";
  }

  $subject = "Welcome to CollagenDirect - Registration Complete";

  // Build email body
  $emailBody = "Hello $name,

Welcome to CollagenDirect! Your registration as a $roleTitle has been successfully completed.

**Account Information:**
Email: $email";

  if ($practiceName) {
    $emailBody .= "\nPractice: $practiceName";
  }

  $emailBody .= "\n\n$portalAccess

$nextSteps

**Need Help?**
- Portal Support: https://docs.collagendirect.health
- Email: support@collagendirect.health
- Phone: (888) 415-6880

**Important Reminders:**
- Keep your login credentials secure
- Complete your profile information for faster order processing
- Review our HIPAA Business Associate Agreement in your portal settings

Thank you for choosing CollagenDirect. We look forward to partnering with you in providing exceptional wound care to your patients.

Best regards,
The CollagenDirect Team

---
CollagenDirect
Advanced Wound Care Solutions
https://collagendirect.health
";

  // Send via SendGrid
  $data = [
    'personalizations' => [
      [
        'to' => [['email' => $email, 'name' => $name]],
        'subject' => $subject
      ]
    ],
    'from' => ['email' => $fromEmail, 'name' => $fromName],
    'content' => [
      ['type' => 'text/plain', 'value' => $emailBody]
    ],
    'reply_to' => ['email' => 'support@collagendirect.health', 'name' => 'CollagenDirect Support']
  ];

  try {
    $result = sg_curl_send($apiKey, $data);
    if ($result['success']) {
      error_log("Registration welcome email sent successfully to $email (user type: $userType)");
      return true;
    } else {
      error_log("Failed to send registration welcome email to $email: " . ($result['error'] ?? 'Unknown error'));
      return false;
    }
  } catch (\Throwable $e) {
    error_log("Exception sending registration welcome email to $email: " . $e->getMessage());
    return false;
  }
}

/**
 * Send notification email to admin about new registration
 */
function send_admin_new_registration_notification(array $userData): bool {
  $apiKey = getenv('SENDGRID_API_KEY');
  if (!$apiKey) {
    return false; // Silently fail admin notification
  }

  $fromEmail = getenv('SMTP_FROM') ?: 'no-reply@collagendirect.health';
  $fromName = getenv('SMTP_FROM_NAME') ?: 'CollagenDirect';
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

  $emailBody = "A new user has registered on CollagenDirect:

**User Details:**
Name: $name
Email: $email
User Type: $userType
Practice: $practiceName
NPI: $npi
Phone: $phone
Location: $city, $state

**Registration Time:** " . date('Y-m-d H:i:s T') . "

**Admin Portal:** https://collagendirect.health/admin

---
This is an automated notification.
";

  $data = [
    'personalizations' => [
      [
        'to' => [['email' => $adminEmail]],
        'subject' => $subject
      ]
    ],
    'from' => ['email' => $fromEmail, 'name' => $fromName],
    'content' => [
      ['type' => 'text/plain', 'value' => $emailBody]
    ]
  ];

  try {
    $result = sg_curl_send($apiKey, $data);
    if ($result['success']) {
      error_log("Admin notification sent for new registration: $email");
      return true;
    }
  } catch (\Throwable $e) {
    error_log("Failed to send admin notification for registration: " . $e->getMessage());
  }

  return false; // Don't fail registration if admin notification fails
}

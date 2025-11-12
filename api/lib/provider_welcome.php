<?php
declare(strict_types=1);

// Provider Welcome Email Function
// Sends welcome email to newly created providers (practice owners, physicians, employees, manufacturers)

require_once __DIR__ . '/sg_curl.php'; // Use the existing SendGrid curl wrapper

function send_provider_welcome_email(string $email, string $name, string $role, string $tempPassword): bool {
  $apiKey = getenv('SENDGRID_API_KEY');
  if (!$apiKey) {
    error_log('SendGrid API key not configured');
    return false;
  }

  $fromEmail = getenv('SMTP_FROM') ?: 'no-reply@collagendirect.health';
  $fromName = getenv('SMTP_FROM_NAME') ?: 'CollagenDirect';

  // Determine role-specific message
  $roleMessage = '';
  $loginUrl = 'https://collagendirect.health/portal';
  $setupInstructions = '';
  $trainingMaterials = "
**Training & Support Resources:**
- Getting Started Guide: https://docs.collagendirect.health/getting-started
- Video Tutorials: https://docs.collagendirect.health/videos
- Order Creation Walkthrough: https://docs.collagendirect.health/orders
- Billing & Documentation: https://docs.collagendirect.health/billing
- FAQs: https://docs.collagendirect.health/faq";

  switch (strtolower($role)) {
    case 'practice owner':
    case 'practice_admin':
      $roleMessage = 'as a Practice Owner';
      $setupInstructions = "
**Getting Started:**
Before you can submit orders, please complete your practice profile:
1. Practice information (name, address, contact)
2. Medical license verification
3. DEA/NPI information
4. DME license (if applicable)
5. Sign Business Associate Agreement (BAA)
6. Add physicians to your practice (if multi-physician practice)

**About Your Role:**
As a Practice Owner, you can:
- Create and manage orders for your patients
- Add and manage physicians within your practice
- Configure practice-wide billing settings
- Access all orders created by physicians in your practice
- Review wound photos and telehealth assessments";
      break;

    case 'physician':
      $roleMessage = 'as a Physician';
      $setupInstructions = "
**Getting Started:**
Before you can submit orders, please complete your profile:
1. Verify your medical license information
2. Review your DEA/NPI information
3. Sign Business Associate Agreement (BAA)
4. Contact your practice administrator if you have any questions

**About Your Role:**
As a Physician within a practice, you can:
- Create and manage orders for your own patients
- Review wound photos and telehealth assessments
- Generate billable E/M codes for telehealth reviews
- Access your patient documentation
- All orders you create are also visible to your practice administrator";
      break;

    case 'employee':
    case 'admin':
      $roleMessage = 'as a CollagenDirect Employee';
      $loginUrl = 'https://collagendirect.onrender.com/admin';
      $setupInstructions = "
You now have access to the CollagenDirect Admin Portal where you can:
- Manage your assigned physicians and practices
- Review and process orders
- Communicate with providers
- Access patient and order information";
      break;

    case 'manufacturer':
      $roleMessage = 'as a Manufacturer Representative';
      $loginUrl = 'https://collagendirect.onrender.com/admin';
      $setupInstructions = "
You now have access to the CollagenDirect Admin Portal where you can:
- View all patient and order information
- Update order statuses
- Reply to provider messages
- Download order reports";
      break;

    default:
      $roleMessage = '';
      $setupInstructions = "
Please log in and complete your profile setup.";
  }

  $emailBody = "
Hello $name,

Welcome to CollagenDirect! You have been added to our platform $roleMessage.

**Your Login Credentials:**
Email: $email
Temporary Password: $tempPassword

Login at: $loginUrl

IMPORTANT: For security, please change your password after your first login.

$setupInstructions

$trainingMaterials

**Need Help?**
- Support Email: support@collagendirect.health
- Phone: (888) 415-6880
- Live Chat: Available in your portal

We're excited to have you on board and look forward to supporting your wound care practice!

Best regards,
The CollagenDirect Team
";

  $subject = "Welcome to CollagenDirect - Account Created";

  // Use plain text email via SendGrid API
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
    'tracking_settings' => [
      'click_tracking' => ['enable' => false, 'enable_text' => false],
      'open_tracking' => ['enable' => false]
    ]
  ];

  try {
    $result = sg_curl_send($apiKey, $data);
    if ($result['success']) {
      error_log("Welcome email sent successfully to $email");
      return true;
    } else {
      error_log("Failed to send welcome email to $email: " . ($result['error'] ?? 'Unknown error'));
      return false;
    }
  } catch (\Throwable $e) {
    error_log("Exception sending welcome email to $email: " . $e->getMessage());
    return false;
  }
}

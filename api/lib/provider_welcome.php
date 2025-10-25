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

  $fromEmail = getenv('SMTP_FROM') ?: 'clinical@collagendirect.com';
  $fromName = getenv('SMTP_FROM_NAME') ?: 'CollagenDirect';

  // Determine role-specific message
  $roleMessage = '';
  $loginUrl = 'https://collagendirect.onrender.com/login';
  $setupInstructions = '';

  switch (strtolower($role)) {
    case 'practice owner':
    case 'practice_admin':
      $roleMessage = 'as a Practice Owner';
      $setupInstructions = "
Before you can submit orders, please complete your practice profile:
- Practice information (name, address, contact)
- Medical license verification
- DEA/NPI information
- DME license (if applicable)
- Sign Business Associate Agreement (BAA)
- Add physicians to your practice (if multi-physician practice)";
      break;

    case 'physician':
      $roleMessage = 'as a Physician';
      $setupInstructions = "
Before you can submit orders, please complete your profile:
- Verify your medical license information
- Review your DEA/NPI information
- Sign Business Associate Agreement (BAA)
- Contact your practice administrator if you have any questions";
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

You have been added to CollagenDirect $roleMessage.

Your temporary login credentials:
Email: $email
Password: $tempPassword

IMPORTANT: $setupInstructions

Login at: $loginUrl

For security, please change your password after your first login.

If you have any questions or need assistance, please contact our support team at support@collagendirect.health.

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

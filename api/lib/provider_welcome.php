<?php
declare(strict_types=1);

// Provider Welcome Email Function
// Sends welcome email to newly created providers (practice owners, physicians, employees, manufacturers)

require_once __DIR__ . '/email_sender.php'; // Use unified email sender (SMTP/Gmail)

function send_provider_welcome_email(string $email, string $name, string $role, string $tempPassword): bool {

  // Determine role-specific message
  $roleMessage = '';
  $loginUrl = 'https://collagendirect.health/portal';
  $setupInstructions = '';

  switch (strtolower($role)) {
    case 'practice owner':
    case 'practice_admin':
      $roleMessage = 'as a Practice Owner';
      $setupInstructions = '
        <p><strong>Getting Started:</strong><br>
        Before you can submit orders, please complete your practice profile:</p>
        <ol style="margin: 10px 0; padding-left: 20px;">
          <li>Practice information (name, address, contact)</li>
          <li>Medical license verification</li>
          <li>DEA/NPI information</li>
          <li>DME license (if applicable)</li>
          <li>Sign Business Associate Agreement (BAA)</li>
          <li>Add physicians to your practice (if multi-physician practice)</li>
        </ol>
        <p><strong>About Your Role:</strong><br>
        As a Practice Owner, you can:</p>
        <ul style="margin: 10px 0; padding-left: 20px;">
          <li>Create and manage orders for your patients</li>
          <li>Add and manage physicians within your practice</li>
          <li>Configure practice-wide billing settings</li>
          <li>Access all orders created by physicians in your practice</li>
          <li>Review wound photos and telehealth assessments</li>
        </ul>';
      break;

    case 'physician':
      $roleMessage = 'as a Physician';
      $setupInstructions = '
        <p><strong>Getting Started:</strong><br>
        Before you can submit orders, please complete your profile:</p>
        <ol style="margin: 10px 0; padding-left: 20px;">
          <li>Verify your medical license information</li>
          <li>Review your DEA/NPI information</li>
          <li>Sign Business Associate Agreement (BAA)</li>
          <li>Contact your practice administrator if you have any questions</li>
        </ol>
        <p><strong>About Your Role:</strong><br>
        As a Physician within a practice, you can:</p>
        <ul style="margin: 10px 0; padding-left: 20px;">
          <li>Create and manage orders for your own patients</li>
          <li>Review wound photos and telehealth assessments</li>
          <li>Generate billable E/M codes for telehealth reviews</li>
          <li>Access your patient documentation</li>
          <li>All orders you create are also visible to your practice administrator</li>
        </ul>';
      break;

    case 'employee':
    case 'admin':
      $roleMessage = 'as a CollagenDirect Employee';
      $loginUrl = 'https://collagendirect.onrender.com/admin';
      $setupInstructions = '
        <p>You now have access to the CollagenDirect Admin Portal where you can:</p>
        <ul style="margin: 10px 0; padding-left: 20px;">
          <li>Manage your assigned physicians and practices</li>
          <li>Review and process orders</li>
          <li>Communicate with providers</li>
          <li>Access patient and order information</li>
        </ul>';
      break;

    case 'manufacturer':
      $roleMessage = 'as a Manufacturer Representative';
      $loginUrl = 'https://collagendirect.onrender.com/admin';
      $setupInstructions = '
        <p>You now have access to the CollagenDirect Admin Portal where you can:</p>
        <ul style="margin: 10px 0; padding-left: 20px;">
          <li>View all patient and order information</li>
          <li>Update order statuses</li>
          <li>Reply to provider messages</li>
          <li>Download order reports</li>
        </ul>';
      break;

    default:
      $roleMessage = '';
      $setupInstructions = '<p>Please log in and complete your profile setup.</p>';
  }

  // Build HTML body content
  $bodyContent = '
    <p style="font-size: 16px; color: #1e293b; margin-bottom: 20px;">Hello ' . htmlspecialchars($name) . ',</p>

    <p style="font-size: 16px; color: #1e293b; margin-bottom: 20px;">
      Welcome to CollagenDirect! You have been added to our platform ' . $roleMessage . '.
    </p>

    <div style="background-color: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 20px; margin: 20px 0;">
      <p style="margin: 0 0 10px 0; font-weight: 600; color: #0f766e;">Your Login Credentials:</p>
      <p style="margin: 0 0 5px 0; color: #1e293b;"><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
      <p style="margin: 0 0 15px 0; color: #1e293b;"><strong>Temporary Password:</strong> ' . htmlspecialchars($tempPassword) . '</p>
      <p style="margin: 0;">
        <a href="' . $loginUrl . '" style="display: inline-block; background: linear-gradient(135deg, #0d9488 0%, #14b8a6 100%); color: #ffffff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600;">Log In Now</a>
      </p>
    </div>

    <p style="background-color: #fef3c7; border: 1px solid #fcd34d; border-radius: 6px; padding: 12px; color: #92400e; font-size: 14px;">
      <strong>IMPORTANT:</strong> For security, please change your password after your first login.
    </p>

    ' . $setupInstructions . '

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
      We\'re excited to have you on board and look forward to supporting your wound care practice!
    </p>

    <p style="font-size: 16px; color: #1e293b; margin-top: 20px;">
      Best regards,<br>
      <strong>The CollagenDirect Team</strong>
    </p>
  ';

  // Plain text version
  $plainText = "Hello $name,

Welcome to CollagenDirect! You have been added to our platform $roleMessage.

YOUR LOGIN CREDENTIALS:
Email: $email
Temporary Password: $tempPassword
Login at: $loginUrl

IMPORTANT: For security, please change your password after your first login.

TRAINING & SUPPORT RESOURCES:
- Portal Training Guide: https://collagendirect.health/portal-guide/
- Video Tutorials: https://collagendirect.health/portal-guide/#videos
- Order Creation Walkthrough: https://collagendirect.health/portal-guide/#orders
- FAQs: https://collagendirect.health/portal-guide/#faq

Need Help?
- Support Email: support@collagendirect.health
- Phone: (888) 415-6880
- Live Chat: Available in your portal

We're excited to have you on board and look forward to supporting your wound care practice!

Best regards,
The CollagenDirect Team
";

  $subject = "Welcome to CollagenDirect - Account Created";

  // Build HTML email using the email template
  $htmlBody = email_template($subject, $bodyContent);

  // Send via SMTP (Gmail/PHPMailer)
  try {
    $result = send_email($email, $name, $subject, $htmlBody, $plainText);
    if ($result) {
      error_log("[provider_welcome] Welcome email sent successfully to $email");
      return true;
    } else {
      error_log("[provider_welcome] Failed to send welcome email to $email");
      return false;
    }
  } catch (\Throwable $e) {
    error_log("[provider_welcome] Exception sending welcome email to $email: " . $e->getMessage());
    return false;
  }
}

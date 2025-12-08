<?php
declare(strict_types=1);

/**
 * Centralized Email Notification System
 * Supports both SMTP (Namecheap cPanel) and SendGrid
 *
 * Priority: SMTP first (if configured), then SendGrid templates
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/sg_curl.php';
require_once __DIR__ . '/email_sender.php';

/**
 * 1. Password Reset Email
 * Template: SG_TMPL_PASSWORD_RESET (optional, falls back to SMTP or plain text)
 * Audience: All users
 * Trigger: User clicks "Forgot Password"
 *
 * Supports: SMTP (Namecheap) or SendGrid
 */
function send_password_reset_email(string $email, string $firstName, string $resetUrl): bool {
  // Try SMTP first if configured (Namecheap cPanel, etc.)
  $smtpHost = env('SMTP_HOST');
  if ($smtpHost) {
    error_log("[email] Using SMTP for password reset email to $email");

    $subject = 'Reset Your CollagenDirect Password';
    $bodyContent = <<<HTML
      <h2 style="color: #1e293b; margin: 0 0 20px 0;">Password Reset Request</h2>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        Hello <strong>$firstName</strong>,
      </p>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        We received a request to reset your password for your CollagenDirect account.
      </p>

      <div style="text-align: center; margin: 30px 0;">
        <a href="$resetUrl" style="display: inline-block; background: linear-gradient(135deg, #0d9488, #14b8a6); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;">
          Reset Password
        </a>
      </div>

      <p style="color: #64748b; font-size: 14px; margin: 20px 0;">
        This link will expire in <strong>15 minutes</strong> for security reasons.
      </p>

      <div style="background-color: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 15px; margin: 20px 0;">
        <p style="margin: 0; color: #92400e; font-size: 14px;">
          <strong>Didn't request this?</strong><br>
          If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.
        </p>
      </div>
HTML;

    $html = email_template($subject, $bodyContent);
    $result = send_email($email, $firstName, $subject, $html);
    if ($result) return true;
    error_log("[email] SMTP failed, trying SendGrid fallback");
  }

  // Try SendGrid template
  $templateId = env('SG_TMPL_PASSWORD_RESET');
  if ($templateId) {
    error_log('[email] Using SendGrid template for password reset: ' . $templateId);
    return sg_send(
      ['email' => $email, 'name' => $firstName],
      null,
      null,
      [
        'template_id' => $templateId,
        'dynamic_data' => [
          'first_name' => $firstName,
          'reset_url' => $resetUrl,
          'expires_minutes' => '15',
          'year' => date('Y'),
          'brand_logo_url' => 'https://collagendirect.health/assets/collagendirect.png'
        ],
        'categories' => ['auth', 'password-reset']
      ]
    );
  }

  // Final fallback: plain SendGrid email
  error_log('[email] Using plain SendGrid email for password reset');
  $apiKey = env('SENDGRID_API_KEY');
  if (!$apiKey) {
    error_log('[email] ERROR: No email provider configured');
    return false;
  }

  $fromEmail = env('SMTP_FROM', 'no-reply@collagendirect.health');
  $fromName = env('SMTP_FROM_NAME', 'CollagenDirect');
  $subject = 'Reset Your CollagenDirect Password';

  $emailBody = "Hello $firstName,

We received a request to reset your password for your CollagenDirect account.

Reset Your Password:
$resetUrl

This link will expire in 15 minutes for security reasons.

Didn't request this?
If you didn't request a password reset, you can safely ignore this email.

Need Help?
Email: support@collagendirect.health
Phone: (888) 415-6880

Best regards,
The CollagenDirect Team
";

  $data = [
    'personalizations' => [
      [
        'to' => [['email' => $email, 'name' => $firstName]],
        'subject' => $subject
      ]
    ],
    'from' => ['email' => $fromEmail, 'name' => $fromName],
    'content' => [
      ['type' => 'text/plain', 'value' => $emailBody]
    ],
    'categories' => ['auth', 'password-reset'],
    'tracking_settings' => [
      'click_tracking' => ['enable' => false, 'enable_text' => false],
      'open_tracking' => ['enable' => false]
    ]
  ];

  try {
    $result = sg_curl_send($apiKey, $data);
    if ($result['success']) {
      error_log("Password reset email sent successfully to $email");
      return true;
    } else {
      error_log("Failed to send password reset email to $email: " . ($result['error'] ?? 'Unknown error'));
      return false;
    }
  } catch (\Throwable $e) {
    error_log("Exception sending password reset email to $email: " . $e->getMessage());
    return false;
  }
}

/**
 * 2. Account Confirmation (Self-Registration)
 * Template: SG_TMPL_ACCOUNT_CONFIRM
 * Audience: Physicians & Practice Managers (self-registration)
 * Trigger: They register their own account
 */
function send_account_confirmation_email(string $email, string $fullName, string $practiceName): bool {
  $templateId = env('SG_TMPL_ACCOUNT_CONFIRM');
  if (!$templateId) {
    error_log('[email] Account confirmation template ID not configured');
    return false;
  }

  return sg_send(
    ['email' => $email, 'name' => $fullName],
    null,
    null,
    [
      'template_id' => $templateId,
      'dynamic_data' => [
        'user_full_name' => $fullName,
        'user_email' => $email,
        'practice_name' => $practiceName,
        'portal_url' => 'https://collagendirect.health/portal',
        'year' => date('Y'),
        'brand_logo_url' => 'https://collagendirect.health/assets/collagendirect.png'
      ],
      'categories' => ['account', 'registration']
    ]
  );
}

/**
 * 3. Physician Account Confirmation (Admin-Created)
 * Template: SG_TMPL_PHYSACCOUNT_CONFIRM
 * Audience: Physicians & Practice Managers (admin-created)
 * Trigger: Admin creates their account
 *
 * Supports: SMTP (Namecheap) or SendGrid
 */
function send_physician_account_created_email(string $email, string $fullName, string $tempPassword): bool {
  // Try SMTP first if configured (Namecheap cPanel, etc.)
  $smtpHost = env('SMTP_HOST');
  if ($smtpHost) {
    error_log("[email] Using SMTP for physician account email to $email");

    $subject = 'Welcome to CollagenDirect - Your Account is Ready';
    $bodyContent = <<<HTML
      <h2 style="color: #1e293b; margin: 0 0 20px 0;">Welcome to CollagenDirect!</h2>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        Hello <strong>$fullName</strong>,
      </p>
      <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
        Your CollagenDirect account has been created. You can now access the portal to manage orders and patients.
      </p>

      <div style="background-color: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 20px; margin: 25px 0;">
        <p style="margin: 0 0 10px 0; font-weight: 600; color: #0f766e;">Your Login Credentials:</p>
        <p style="margin: 0 0 5px 0; color: #475569;"><strong>Email:</strong> $email</p>
        <p style="margin: 0; color: #475569;"><strong>Temporary Password:</strong> $tempPassword</p>
      </div>

      <p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
        For security, please change your password after your first login.
      </p>

      <div style="text-align: center; margin: 30px 0;">
        <a href="https://collagendirect.health/portal" style="display: inline-block; background: linear-gradient(135deg, #0d9488, #14b8a6); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;">
          Login to Portal
        </a>
      </div>
HTML;

    $html = email_template($subject, $bodyContent);
    $result = send_email($email, $fullName, $subject, $html);
    if ($result) return true;
    error_log("[email] SMTP failed, trying SendGrid fallback");
  }

  // Fallback to SendGrid template
  $templateId = env('SG_TMPL_PHYSACCOUNT_CONFIRM');
  if (!$templateId) {
    error_log('[email] Physician account confirmation template ID not configured');
    return false;
  }

  return sg_send(
    ['email' => $email, 'name' => $fullName],
    null,
    null,
    [
      'template_id' => $templateId,
      'dynamic_data' => [
        'user_full_name' => $fullName,
        'user_email' => $email,
        'temp_password' => $tempPassword,
        'portal_url' => 'https://collagendirect.health/portal',
        'year' => date('Y'),
        'brand_logo_url' => 'https://collagendirect.health/assets/collagendirect.png'
      ],
      'categories' => ['account', 'admin-created']
    ]
  );
}

/**
 * 4. Order Received Confirmation (Patient)
 * Template: SG_TMPL_ORDER_RECEIVED
 * Audience: Patients
 * Trigger: Patient submits new order
 */
function send_order_received_email(array $orderData): bool {
  $templateId = env('SG_TMPL_ORDER_RECEIVED');
  if (!$templateId) {
    error_log('[email] Order received template ID not configured');
    return false;
  }

  $patientEmail = $orderData['patient_email'] ?? '';
  $patientName = $orderData['patient_name'] ?? '';

  if (!$patientEmail) {
    error_log('[email] Cannot send order received: missing patient email');
    return false;
  }

  // Format products array for template
  $products = [];
  if (!empty($orderData['product_name'])) {
    $products[] = [
      'name' => $orderData['product_name'],
      'quantity' => $orderData['quantity'] ?? '1'
    ];
  }

  return sg_send(
    ['email' => $patientEmail, 'name' => $patientName],
    null,
    null,
    [
      'template_id' => $templateId,
      'dynamic_data' => [
        'patient_name' => $patientName,
        'order_id' => $orderData['order_id'] ?? '',
        'order_date' => $orderData['order_date'] ?? date('m/d/Y'),
        'physician_name' => $orderData['physician_name'] ?? '',
        'practice_name' => $orderData['practice_name'] ?? '',
        'products' => $products,
        'year' => date('Y'),
        'brand_logo_url' => 'https://collagendirect.health/assets/collagendirect.png'
      ],
      'categories' => ['order', 'patient-confirmation']
    ]
  );
}

/**
 * 5. Order Approved Notification (Physician)
 * Template: SG_TMPL_ORDER_APPROVED
 * Audience: Physicians
 * Trigger: Admin approves their patient's order
 */
function send_order_approved_email(array $orderData): bool {
  $templateId = env('SG_TMPL_ORDER_APPROVED');
  if (!$templateId) {
    error_log('[email] Order approved template ID not configured');
    return false;
  }

  $physicianEmail = $orderData['physician_email'] ?? '';
  $physicianName = $orderData['physician_name'] ?? '';

  if (!$physicianEmail) {
    error_log('[email] Cannot send order approved: missing physician email');
    return false;
  }

  // Format products array for template
  $products = [];
  if (!empty($orderData['product_name'])) {
    $products[] = [
      'name' => $orderData['product_name'],
      'quantity' => $orderData['quantity'] ?? '1',
      'frequency' => $orderData['frequency'] ?? '',
      'duration_days' => $orderData['duration_days'] ?? ''
    ];
  }

  return sg_send(
    ['email' => $physicianEmail, 'name' => $physicianName],
    null,
    null,
    [
      'template_id' => $templateId,
      'dynamic_data' => [
        'physician_name' => $physicianName,
        'patient_name' => $orderData['patient_name'] ?? '',
        'order_id' => $orderData['order_id'] ?? '',
        'approved_datetime' => $orderData['approved_datetime'] ?? date('m/d/Y g:i A T'),
        'products' => $products,
        'year' => date('Y'),
        'brand_logo_url' => 'https://collagendirect.health/assets/collagendirect.png'
      ],
      'categories' => ['order', 'physician-notification']
    ]
  );
}

/**
 * 6. Order Shipped Notification (Patient)
 * Template: SG_TMPL_ORDER_SHIPPED
 * Audience: Patients
 * Trigger: Admin adds tracking information
 */
function send_order_shipped_email(array $orderData): bool {
  $templateId = env('SG_TMPL_ORDER_SHIPPED');
  if (!$templateId) {
    error_log('[email] Order shipped template ID not configured');
    return false;
  }

  $patientEmail = $orderData['patient_email'] ?? '';
  $patientName = $orderData['patient_name'] ?? '';

  if (!$patientEmail) {
    error_log('[email] Cannot send order shipped: missing patient email');
    return false;
  }

  $trackingNumber = $orderData['tracking_number'] ?? '';
  $carrier = $orderData['carrier'] ?? '';

  // Generate tracking URL based on carrier
  $trackingUrl = '';
  if ($trackingNumber) {
    $trackingUrl = match(strtolower($carrier)) {
      'ups' => "https://wwwapps.ups.com/WebTracking/track?tracknum={$trackingNumber}",
      'fedex' => "https://www.fedex.com/fedextrack/?trknbr={$trackingNumber}",
      'usps' => "https://tools.usps.com/go/TrackConfirmAction?tLabels={$trackingNumber}",
      default => "https://www.google.com/search?q=track+{$trackingNumber}"
    };
  }

  // Format products array for template
  $products = [];
  if (!empty($orderData['product_name'])) {
    $products[] = [
      'name' => $orderData['product_name'],
      'quantity' => $orderData['quantity'] ?? '1'
    ];
  }

  return sg_send(
    ['email' => $patientEmail, 'name' => $patientName],
    null,
    null,
    [
      'template_id' => $templateId,
      'dynamic_data' => [
        'patient_name' => $patientName,
        'order_id' => $orderData['order_id'] ?? '',
        'shipped_date' => $orderData['shipped_date'] ?? date('m/d/Y'),
        'carrier' => $carrier,
        'tracking_number' => $trackingNumber,
        'tracking_url' => $trackingUrl,
        'products' => $products,
        'year' => date('Y'),
        'brand_logo_url' => 'https://collagendirect.health/assets/collagendirect.png'
      ],
      'categories' => ['order', 'shipping']
    ]
  );
}

/**
 * 7. Manufacturer New Order Notification
 * Audience: ALL manufacturer reps AND superadmins
 * Trigger: New order is submitted
 * Uses SMTP (Gmail/Google Workspace)
 */
function send_manufacturer_order_email(array $orderData): bool {
  global $pdo;

  // Get database connection if not available globally
  if (!isset($pdo) || !$pdo) {
    require_once __DIR__ . '/../db.php';
  }

  try {
    // Get ALL manufacturer reps from admin_users
    $mfgStmt = $pdo->query("SELECT email, name FROM admin_users WHERE role = 'manufacturer'");
    $manufacturers = $mfgStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get ALL superadmins from users table
    $adminStmt = $pdo->query("SELECT email, first_name, last_name FROM users WHERE role = 'superadmin'");
    $superadmins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

    // Combine recipients
    $recipients = [];
    foreach ($manufacturers as $mfg) {
      $recipients[] = ['email' => $mfg['email'], 'name' => $mfg['name']];
    }
    foreach ($superadmins as $admin) {
      $recipients[] = ['email' => $admin['email'], 'name' => trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))];
    }

    if (empty($recipients)) {
      error_log('[email] No manufacturer reps or superadmins found for order notification');
      return false;
    }

    error_log('[email] Found ' . count($recipients) . ' recipients for order notification');

  } catch (Throwable $e) {
    error_log('[email] Failed to get recipients: ' . $e->getMessage());
    return false;
  }

  // Build email content
  $orderId = $orderData['order_id'] ?? '';
  $orderDate = $orderData['order_date'] ?? date('m/d/Y');
  $patientName = $orderData['patient_name'] ?? '';
  $patientDob = $orderData['patient_dob'] ?? 'N/A';
  $patientAddress = $orderData['patient_address'] ?? 'N/A';
  $insuranceProvider = $orderData['insurance_provider'] ?? 'N/A';
  $physicianName = $orderData['physician_name'] ?? '';
  $physicianNpi = $orderData['physician_npi'] ?? 'N/A';
  $practiceName = $orderData['practice_name'] ?? '';
  $productName = $orderData['product_name'] ?? 'N/A';
  $frequency = $orderData['frequency'] ?? 'N/A';
  $durationDays = $orderData['duration_days'] ?? 'N/A';

  $adminPortalUrl = "https://collagendirect.health/admin/orders.php?id={$orderId}";
  $subject = "New Order Submitted - Order #$orderId";

  // HTML email body
  $htmlBody = email_template($subject, "
    <h2 style='color: #1e293b; margin: 0 0 20px 0;'>New Order Submitted</h2>
    <p style='color: #475569; line-height: 1.6;'>A new order has been submitted and is ready for processing.</p>

    <div style='background-color: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 20px; margin: 20px 0;'>
      <h3 style='margin: 0 0 15px 0; color: #0f766e;'>Order Details</h3>
      <p style='margin: 5px 0; color: #475569;'><strong>Order ID:</strong> #$orderId</p>
      <p style='margin: 5px 0; color: #475569;'><strong>Order Date:</strong> $orderDate</p>
      <p style='margin: 5px 0; color: #475569;'><strong>Patient:</strong> $patientName</p>
      <p style='margin: 5px 0; color: #475569;'><strong>DOB:</strong> $patientDob</p>
      <p style='margin: 5px 0; color: #475569;'><strong>Address:</strong> $patientAddress</p>
    </div>

    <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0;'>
      <h3 style='margin: 0 0 15px 0; color: #334155;'>Provider Information</h3>
      <p style='margin: 5px 0; color: #475569;'><strong>Physician:</strong> $physicianName</p>
      <p style='margin: 5px 0; color: #475569;'><strong>NPI:</strong> $physicianNpi</p>
      <p style='margin: 5px 0; color: #475569;'><strong>Practice:</strong> $practiceName</p>
    </div>

    <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0;'>
      <h3 style='margin: 0 0 15px 0; color: #334155;'>Product Information</h3>
      <p style='margin: 5px 0; color: #475569;'><strong>Product:</strong> $productName</p>
      <p style='margin: 5px 0; color: #475569;'><strong>Frequency:</strong> $frequency</p>
      <p style='margin: 5px 0; color: #475569;'><strong>Duration:</strong> $durationDays days</p>
    </div>

    <div style='background-color: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 20px; margin: 20px 0;'>
      <h3 style='margin: 0 0 15px 0; color: #92400e;'>Insurance Information</h3>
      <p style='margin: 5px 0; color: #78350f;'><strong>Provider:</strong> $insuranceProvider</p>
    </div>

    <div style='text-align: center; margin: 30px 0;'>
      <a href='$adminPortalUrl' style='display: inline-block; background: linear-gradient(135deg, #0d9488, #14b8a6); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;'>
        View Order in Admin Portal
      </a>
    </div>
  ");

  // Plain text version
  $textBody = "New Order Submitted - Order #$orderId\n\n";
  $textBody .= "ORDER DETAILS\n";
  $textBody .= "Order ID: #$orderId\n";
  $textBody .= "Order Date: $orderDate\n";
  $textBody .= "Patient: $patientName\n";
  $textBody .= "DOB: $patientDob\n";
  $textBody .= "Address: $patientAddress\n\n";
  $textBody .= "PROVIDER\n";
  $textBody .= "Physician: $physicianName\n";
  $textBody .= "NPI: $physicianNpi\n";
  $textBody .= "Practice: $practiceName\n\n";
  $textBody .= "PRODUCT\n";
  $textBody .= "Product: $productName\n";
  $textBody .= "Frequency: $frequency\n";
  $textBody .= "Duration: $durationDays days\n\n";
  $textBody .= "INSURANCE: $insuranceProvider\n\n";
  $textBody .= "View order: $adminPortalUrl\n";

  // Send to ALL recipients via SMTP
  $successCount = 0;
  foreach ($recipients as $recipient) {
    $result = send_email($recipient['email'], $recipient['name'], $subject, $htmlBody, $textBody);
    if ($result) {
      $successCount++;
      error_log("[email] Order notification sent to {$recipient['email']} for order #$orderId");
    } else {
      error_log("[email] Failed to send order notification to {$recipient['email']} for order #$orderId");
    }
  }

  error_log("[email] Order notification: sent $successCount/" . count($recipients) . " emails for order #$orderId");
  return $successCount > 0;
}

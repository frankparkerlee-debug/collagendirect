<?php
declare(strict_types=1);

/**
 * Centralized Email Notification System
 * Uses SMTP/Gmail via email_sender.php (PHPMailer)
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/email_sender.php';

/**
 * 1. Password Reset Email
 * Audience: All users
 * Trigger: User clicks "Forgot Password"
 */
function send_password_reset_email(string $email, string $firstName, string $resetUrl): bool {
  error_log("[email] Sending password reset email to $email");

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

  $plainText = "Hello $firstName,

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

  $html = email_template($subject, $bodyContent);
  $result = send_email($email, $firstName, $subject, $html, $plainText);

  if ($result) {
    error_log("[email] Password reset email sent to $email");
    return true;
  }

  error_log("[email] Failed to send password reset email to $email");
  return false;
}

/**
 * 2. Account Confirmation (Self-Registration)
 * Audience: Physicians & Practice Managers (self-registration)
 * Trigger: They register their own account
 */
function send_account_confirmation_email(string $email, string $fullName, string $practiceName): bool {
  $subject = 'Welcome to CollagenDirect - Account Confirmed';

  $bodyContent = '
    <h2 style="color: #1e293b; margin: 0 0 20px 0;">Welcome to CollagenDirect!</h2>
    <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
      Hello <strong>' . htmlspecialchars($fullName) . '</strong>,
    </p>
    <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
      Your account has been created successfully. You can now access the CollagenDirect portal.
    </p>

    <div style="background-color: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 20px; margin: 25px 0;">
      <p style="margin: 0 0 10px 0; font-weight: 600; color: #0f766e;">Account Details:</p>
      <p style="margin: 0 0 5px 0; color: #475569;"><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
      <p style="margin: 0; color: #475569;"><strong>Practice:</strong> ' . htmlspecialchars($practiceName) . '</p>
    </div>

    <div style="text-align: center; margin: 30px 0;">
      <a href="https://collagendirect.health/portal" style="display: inline-block; background: linear-gradient(135deg, #0d9488, #14b8a6); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;">
        Login to Portal
      </a>
    </div>
  ';

  $plainText = "Welcome to CollagenDirect!

Hello $fullName,

Your account has been created successfully.

Account Details:
Email: $email
Practice: $practiceName

Login at: https://collagendirect.health/portal

Best regards,
The CollagenDirect Team
";

  $html = email_template($subject, $bodyContent);
  return send_email($email, $fullName, $subject, $html, $plainText);
}

/**
 * 3. Physician Account Confirmation (Admin-Created)
 * Audience: Physicians & Practice Managers (admin-created)
 * Trigger: Admin creates their account
 */
function send_physician_account_created_email(string $email, string $fullName, string $tempPassword): bool {
  error_log("[email] Sending physician account email to $email");

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

  $plainText = "Welcome to CollagenDirect!

Hello $fullName,

Your CollagenDirect account has been created.

YOUR LOGIN CREDENTIALS:
Email: $email
Temporary Password: $tempPassword

For security, please change your password after your first login.

Login at: https://collagendirect.health/portal

Best regards,
The CollagenDirect Team
";

  $html = email_template($subject, $bodyContent);
  $result = send_email($email, $fullName, $subject, $html, $plainText);

  if ($result) {
    error_log("[email] Physician account email sent to $email");
    return true;
  }

  error_log("[email] Failed to send physician account email to $email");
  return false;
}

/**
 * 3b. Employee Sales Rep Welcome Email
 * Audience: New employee sales reps
 * Trigger: Admin creates new employee sales rep account
 */
function send_employee_rep_welcome_email(string $email, string $fullName, string $tempPassword): bool {
  error_log("[email] Sending employee rep welcome email to $email");

  $subject = 'Welcome to CollagenDirect - Your Sales Rep Account';
  $bodyContent = <<<HTML
    <h2 style="color: #1e293b; margin: 0 0 20px 0;">Welcome to the CollagenDirect Team!</h2>
    <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
      Hello <strong>$fullName</strong>,
    </p>
    <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
      Your CollagenDirect Employee Sales Rep account has been created. You now have access to the Sales Rep Portal
      where you can manage your clinics, track orders, and earn commissions.
    </p>

    <div style="background-color: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 20px; margin: 25px 0;">
      <p style="margin: 0 0 10px 0; font-weight: 600; color: #4338ca;">Your Login Credentials:</p>
      <p style="margin: 0 0 5px 0; color: #475569;"><strong>Email:</strong> $email</p>
      <p style="margin: 0; color: #475569;"><strong>Temporary Password:</strong> $tempPassword</p>
    </div>

    <p style="color: #475569; line-height: 1.6; margin: 0 0 20px 0;">
      For security, please change your password after your first login.
    </p>

    <div style="text-align: center; margin: 30px 0;">
      <a href="https://collagendirect.health/admin/employee-rep/" style="display: inline-block; background: linear-gradient(135deg, #4f46e5, #6366f1); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;">
        Login to Sales Portal
      </a>
    </div>

    <div style="background-color: #fefce8; border: 1px solid #fde047; border-radius: 8px; padding: 20px; margin: 25px 0;">
      <p style="margin: 0 0 15px 0; font-weight: 600; color: #854d0e;">Getting Started Resources:</p>
      <ul style="margin: 0; padding-left: 20px; color: #475569;">
        <li style="margin-bottom: 8px;"><a href="https://collagendirect.health/training/sales" style="color: #4f46e5;">Sales Training Course</a></li>
        <li style="margin-bottom: 8px;"><a href="https://collagendirect.health/docs/sales-manual" style="color: #4f46e5;">Sales Rep Manual</a></li>
        <li style="margin-bottom: 8px;"><a href="https://collagendirect.health/docs/commission-guide" style="color: #4f46e5;">Commission Guide</a></li>
      </ul>
    </div>
HTML;

  $plainText = "Welcome to the CollagenDirect Team!

Hello $fullName,

Your CollagenDirect Employee Sales Rep account has been created.

YOUR LOGIN CREDENTIALS:
Email: $email
Temporary Password: $tempPassword

For security, please change your password after your first login.

Login to Sales Portal: https://collagendirect.health/admin/employee-rep/

GETTING STARTED RESOURCES:
- Sales Training Course: https://collagendirect.health/training/sales
- Sales Rep Manual: https://collagendirect.health/docs/sales-manual
- Commission Guide: https://collagendirect.health/docs/commission-guide

Questions? Contact support@collagendirect.health
";

  $html = email_template($subject, $bodyContent);
  $result = send_email($email, $fullName, $subject, $html, $plainText);

  if ($result) {
    error_log("[email] Employee rep welcome email sent to $email");
    return true;
  }

  error_log("[email] Failed to send employee rep welcome email to $email");
  return false;
}

/**
 * 4. Order Received Confirmation (Patient)
 * Audience: Patients
 * Trigger: Patient submits new order
 */
function send_order_received_email(array $orderData): bool {
  $patientEmail = $orderData['patient_email'] ?? '';
  $patientName = $orderData['patient_name'] ?? '';

  if (!$patientEmail) {
    error_log('[email] Cannot send order received: missing patient email');
    return false;
  }

  $orderId = $orderData['order_id'] ?? '';
  $orderDate = $orderData['order_date'] ?? date('m/d/Y');
  $productName = $orderData['product_name'] ?? 'Wound Care Product';
  $physicianName = $orderData['physician_name'] ?? '';
  $practiceName = $orderData['practice_name'] ?? '';

  $subject = "Order Received - Order #$orderId";

  $bodyContent = '
    <h2 style="color: #1e293b; margin: 0 0 20px 0;">Your Order Has Been Received</h2>
    <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
      Hello <strong>' . htmlspecialchars($patientName) . '</strong>,
    </p>
    <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
      Your wound care order has been submitted and is being processed.
    </p>

    <div style="background-color: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 20px; margin: 25px 0;">
      <p style="margin: 0 0 10px 0; font-weight: 600; color: #0f766e;">Order Details:</p>
      <p style="margin: 5px 0; color: #475569;"><strong>Order #:</strong> ' . htmlspecialchars($orderId) . '</p>
      <p style="margin: 5px 0; color: #475569;"><strong>Date:</strong> ' . htmlspecialchars($orderDate) . '</p>
      <p style="margin: 5px 0; color: #475569;"><strong>Product:</strong> ' . htmlspecialchars($productName) . '</p>
      <p style="margin: 5px 0; color: #475569;"><strong>Physician:</strong> Dr. ' . htmlspecialchars($physicianName) . '</p>
      <p style="margin: 5px 0; color: #475569;"><strong>Practice:</strong> ' . htmlspecialchars($practiceName) . '</p>
    </div>

    <p style="color: #475569; line-height: 1.6;">
      We will notify you when your order ships. If you have questions, contact us at support@collagendirect.health.
    </p>
  ';

  $plainText = "Your Order Has Been Received

Hello $patientName,

Your wound care order has been submitted and is being processed.

ORDER DETAILS:
Order #: $orderId
Date: $orderDate
Product: $productName
Physician: Dr. $physicianName
Practice: $practiceName

We will notify you when your order ships.

Questions? Contact support@collagendirect.health

Best regards,
The CollagenDirect Team
";

  $html = email_template($subject, $bodyContent);
  return send_email($patientEmail, $patientName, $subject, $html, $plainText);
}

/**
 * 5. Order Approved Notification (Physician)
 * Audience: Physicians
 * Trigger: Admin approves their patient's order
 */
function send_order_approved_email(array $orderData): bool {
  $physicianEmail = $orderData['physician_email'] ?? '';
  $physicianName = $orderData['physician_name'] ?? '';

  if (!$physicianEmail) {
    error_log('[email] Cannot send order approved: missing physician email');
    return false;
  }

  $orderId = $orderData['order_id'] ?? '';
  $patientName = $orderData['patient_name'] ?? '';
  $productName = $orderData['product_name'] ?? '';
  $approvedTime = $orderData['approved_datetime'] ?? date('m/d/Y g:i A');

  $subject = "Order Approved - Order #$orderId";

  $bodyContent = '
    <h2 style="color: #1e293b; margin: 0 0 20px 0;">Order Approved</h2>
    <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
      Hello Dr. <strong>' . htmlspecialchars($physicianName) . '</strong>,
    </p>
    <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
      Your patient\'s order has been approved and is now being processed.
    </p>

    <div style="background-color: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 20px; margin: 25px 0;">
      <p style="margin: 5px 0; color: #475569;"><strong>Order #:</strong> ' . htmlspecialchars($orderId) . '</p>
      <p style="margin: 5px 0; color: #475569;"><strong>Patient:</strong> ' . htmlspecialchars($patientName) . '</p>
      <p style="margin: 5px 0; color: #475569;"><strong>Product:</strong> ' . htmlspecialchars($productName) . '</p>
      <p style="margin: 5px 0; color: #475569;"><strong>Approved:</strong> ' . htmlspecialchars($approvedTime) . '</p>
    </div>

    <div style="text-align: center; margin: 30px 0;">
      <a href="https://collagendirect.health/portal" style="display: inline-block; background: linear-gradient(135deg, #0d9488, #14b8a6); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;">
        View in Portal
      </a>
    </div>
  ';

  $plainText = "Order Approved

Hello Dr. $physicianName,

Your patient's order has been approved and is now being processed.

Order #: $orderId
Patient: $patientName
Product: $productName
Approved: $approvedTime

View in portal: https://collagendirect.health/portal

Best regards,
The CollagenDirect Team
";

  $html = email_template($subject, $bodyContent);
  return send_email($physicianEmail, $physicianName, $subject, $html, $plainText);
}

/**
 * 6. Order Shipped Notification (Patient)
 * Audience: Patients
 * Trigger: Admin adds tracking information
 */
function send_order_shipped_email(array $orderData): bool {
  $patientEmail = $orderData['patient_email'] ?? '';
  $patientName = $orderData['patient_name'] ?? '';

  if (!$patientEmail) {
    error_log('[email] Cannot send order shipped: missing patient email');
    return false;
  }

  $orderId = $orderData['order_id'] ?? '';
  $trackingNumber = $orderData['tracking_number'] ?? '';
  $carrier = $orderData['carrier'] ?? '';
  $shippedDate = $orderData['shipped_date'] ?? date('m/d/Y');
  $productName = $orderData['product_name'] ?? 'Wound Care Product';

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

  $subject = "Your Order Has Shipped - Order #$orderId";

  $trackingSection = '';
  if ($trackingNumber) {
    $trackingSection = '
      <div style="background-color: #eff6ff; border: 1px solid #93c5fd; border-radius: 8px; padding: 20px; margin: 25px 0;">
        <p style="margin: 0 0 10px 0; font-weight: 600; color: #1d4ed8;">Tracking Information:</p>
        <p style="margin: 5px 0; color: #475569;"><strong>Carrier:</strong> ' . htmlspecialchars($carrier) . '</p>
        <p style="margin: 5px 0; color: #475569;"><strong>Tracking #:</strong> ' . htmlspecialchars($trackingNumber) . '</p>
        <p style="margin: 15px 0 0 0;">
          <a href="' . htmlspecialchars($trackingUrl) . '" style="display: inline-block; background: #1d4ed8; color: #ffffff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600;">
            Track Package
          </a>
        </p>
      </div>
    ';
  }

  $bodyContent = '
    <h2 style="color: #1e293b; margin: 0 0 20px 0;">Your Order Has Shipped!</h2>
    <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
      Hello <strong>' . htmlspecialchars($patientName) . '</strong>,
    </p>
    <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
      Great news! Your wound care order is on its way.
    </p>

    <div style="background-color: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 20px; margin: 25px 0;">
      <p style="margin: 5px 0; color: #475569;"><strong>Order #:</strong> ' . htmlspecialchars($orderId) . '</p>
      <p style="margin: 5px 0; color: #475569;"><strong>Product:</strong> ' . htmlspecialchars($productName) . '</p>
      <p style="margin: 5px 0; color: #475569;"><strong>Shipped:</strong> ' . htmlspecialchars($shippedDate) . '</p>
    </div>

    ' . $trackingSection . '

    <p style="color: #475569; line-height: 1.6;">
      Questions? Contact us at support@collagendirect.health.
    </p>
  ';

  $plainText = "Your Order Has Shipped!

Hello $patientName,

Great news! Your wound care order is on its way.

Order #: $orderId
Product: $productName
Shipped: $shippedDate
Carrier: $carrier
Tracking #: $trackingNumber
Track: $trackingUrl

Questions? Contact support@collagendirect.health

Best regards,
The CollagenDirect Team
";

  $html = email_template($subject, $bodyContent);
  return send_email($patientEmail, $patientName, $subject, $html, $plainText);
}

/**
 * 7. New Sales Hire Onboarding Welcome Email
 * Audience: New sales team members (Employee Salesperson role)
 * Trigger: Admin creates new employee with sales role
 */
function send_new_hire_welcome_email(string $email, string $fullName, string $tempPassword): bool {
  error_log("[email] Sending new hire welcome email to $email");

  // Extract first name from full name
  $nameParts = explode(' ', trim($fullName));
  $firstName = $nameParts[0] ?? $fullName;

  $subject = "Welcome to CollagenDirect, {$firstName}! Your Onboarding Resources";

  $htmlContent = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to CollagenDirect</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Arial', 'Helvetica', sans-serif; background-color: #f3f4f6;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">

                    <!-- Header with gradient -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #47c6be 0%, #34d399 100%); padding: 50px 40px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0 0 10px 0; font-size: 32px; font-weight: 900;">Welcome to CollagenDirect!</h1>
                            <p style="color: #e0f7f5; margin: 0; font-size: 16px;">Hi {$firstName}, we're thrilled to have you on our team!</p>
                        </td>
                    </tr>

                    <!-- Main content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="color: #1f2937; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">
                                Congratulations on joining CollagenDirect as a <strong>Sales Representative</strong>! You're about to embark on an exciting journey helping physicians transform their wound care practices.
                            </p>

                            <!-- Login Credentials -->
                            <div style="background-color: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                                <h2 style="color: #4338ca; margin: 0 0 15px 0; font-size: 18px; font-weight: 700;">Your Login Credentials</h2>
                                <p style="margin: 5px 0; color: #475569;"><strong>Email:</strong> {$email}</p>
                                <p style="margin: 5px 0; color: #475569;"><strong>Temporary Password:</strong> {$tempPassword}</p>
                                <p style="margin: 15px 0 0 0; font-size: 13px; color: #6366f1;">Please change your password after your first login.</p>
                                <a href="https://collagendirect.health/admin/"
                                   style="display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 14px; margin-top: 15px;">
                                    Login to Admin Portal
                                </a>
                            </div>

                            <p style="color: #1f2937; font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">
                                We've prepared a comprehensive onboarding playbook to help you hit the ground running. Below you'll find all the resources you need to become a successful member of our sales team.
                            </p>

                            <!-- Key Resources Section -->
                            <div style="background-color: #f0fdfc; border-left: 4px solid #47c6be; padding: 20px; margin-bottom: 30px; border-radius: 8px;">
                                <h2 style="color: #0a2540; margin: 0 0 15px 0; font-size: 20px; font-weight: 700;">Your Training Hub</h2>
                                <p style="color: #374151; font-size: 14px; margin: 0 0 15px 0;">
                                    Access your personalized onboarding portal with interactive checklists and progress tracking:
                                </p>
                                <a href="https://collagendirect.health/sales-training/new-hire-welcome.php?email={$email}"
                                   style="display: inline-block; background-color: #47c6be; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 700; font-size: 16px; margin-top: 10px;">
                                    Start Your Onboarding
                                </a>
                            </div>

                            <!-- What to Expect -->
                            <h2 style="color: #0a2540; margin: 0 0 20px 0; font-size: 20px; font-weight: 700;">Your First Week Journey</h2>

                            <!-- Phase 1 -->
                            <div style="margin-bottom: 20px;">
                                <div style="background-color: #fef2f2; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
                                    <h3 style="color: #dc2626; margin: 0 0 8px 0; font-size: 16px; font-weight: 700;">
                                        Day 1: HR & Compliance (Critical)
                                    </h3>
                                    <ul style="color: #374151; font-size: 14px; margin: 0; padding-left: 20px; line-height: 1.6;">
                                        <li>Complete HIPAA training & sign confidentiality agreement</li>
                                        <li>Submit I-9, W-4, and direct deposit forms</li>
                                        <li>Review employee handbook & Business Associate Agreement</li>
                                        <li>Set up your @collagendirect.health email</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Phase 2 -->
                            <div style="margin-bottom: 20px;">
                                <div style="background-color: #eff6ff; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
                                    <h3 style="color: #2563eb; margin: 0 0 8px 0; font-size: 16px; font-weight: 700;">
                                        Days 2-3: Product & Industry Knowledge
                                    </h3>
                                    <ul style="color: #374151; font-size: 14px; margin: 0; padding-left: 20px; line-height: 1.6;">
                                        <li>Learn about collagen wound therapy and clinical evidence</li>
                                        <li>Memorize our 4 core products and HCPCS billing codes</li>
                                        <li>Understand insurance coverage (Medicare, Medicaid, commercial)</li>
                                        <li>Study common physician questions and objections</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Phase 3 -->
                            <div style="margin-bottom: 20px;">
                                <div style="background-color: #ecfdf5; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
                                    <h3 style="color: #059669; margin: 0 0 8px 0; font-size: 16px; font-weight: 700;">
                                        Days 3-5: Sales Methodology Training
                                    </h3>
                                    <ul style="color: #374151; font-size: 14px; margin: 0; padding-left: 20px; line-height: 1.6;">
                                        <li>Master our 4-Step Sales Process (Get Meeting → Conversation → Register → Nurture)</li>
                                        <li>Practice discovery questions and role-play scenarios</li>
                                        <li>Review cold call scripts and objection handling techniques</li>
                                        <li>Study competitive battle cards for positioning vs competitors</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Phase 4 -->
                            <div style="margin-bottom: 30px;">
                                <div style="background-color: #f5f3ff; border-radius: 8px; padding: 16px; margin-bottom: 12px;">
                                    <h3 style="color: #7c3aed; margin: 0 0 8px 0; font-size: 16px; font-weight: 700;">
                                        End of Week 1: Systems & Tools
                                    </h3>
                                    <ul style="color: #374151; font-size: 14px; margin: 0; padding-left: 20px; line-height: 1.6;">
                                        <li>Complete physician portal walkthrough</li>
                                        <li>Get CRM system access and training</li>
                                        <li>Receive your product sample kit</li>
                                        <li>Schedule shadow day with a senior sales rep</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Quick Reference Resources -->
                            <div style="background-color: #fef9c3; border-radius: 8px; padding: 20px; margin-bottom: 30px;">
                                <h2 style="color: #854d0e; margin: 0 0 15px 0; font-size: 18px; font-weight: 700;">Quick Access Resources</h2>
                                <table role="presentation" style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <a href="https://collagendirect.health/sales-training/" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px;">Training Hub Home</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <a href="https://collagendirect.health/sales-training/sales-process.php" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px;">4-Step Sales Process</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <a href="https://collagendirect.health/sales-training/product-training.php" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px;">Product Training</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <a href="https://collagendirect.health/sales-training/objections.php" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px;">Objection Handling</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0;">
                                            <a href="https://collagendirect.health/sales-training/hipaa-training.php" style="color: #2563eb; text-decoration: none; font-weight: 600; font-size: 14px;">HIPAA Training</a>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Support -->
                            <div style="background-color: #f9fafb; border-radius: 8px; padding: 20px; text-align: center;">
                                <p style="color: #6b7280; font-size: 14px; margin: 0 0 15px 0;">
                                    <strong>Questions or need help?</strong>
                                </p>
                                <p style="color: #2563eb; font-size: 16px; margin: 0; font-weight: 600;">
                                    Contact: <a href="mailto:parker@collagendirect.health" style="color: #2563eb; text-decoration: none;">parker@collagendirect.health</a>
                                </p>
                            </div>

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #0a2540; padding: 30px 40px; text-align: center;">
                            <p style="color: #9ca3af; font-size: 14px; margin: 0 0 10px 0;">
                                <strong style="color: #ffffff;">Welcome to the team!</strong><br>
                                We're excited to have you at CollagenDirect.
                            </p>
                            <p style="color: #6b7280; font-size: 12px; margin: 0;">
                                CollagenDirect | Streamlining Wound Care, One Patient at a Time<br>
                                <a href="https://collagendirect.health" style="color: #47c6be; text-decoration: none;">collagendirect.health</a>
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

  $textContent = <<<TEXT
Welcome to CollagenDirect, {$firstName}!

Hi {$firstName},

Congratulations on joining CollagenDirect as a Sales Representative! We're thrilled to have you on our team.

YOUR LOGIN CREDENTIALS
Email: {$email}
Temporary Password: {$tempPassword}
Login at: https://collagendirect.health/admin/

Please change your password after your first login.

YOUR TRAINING HUB
Access your personalized onboarding portal here:
https://collagendirect.health/sales-training/new-hire-welcome.php?email={$email}

YOUR FIRST WEEK JOURNEY

Day 1: HR & Compliance (Critical)
- Complete HIPAA training & sign confidentiality agreement
- Submit I-9, W-4, and direct deposit forms
- Review employee handbook & Business Associate Agreement
- Set up your @collagendirect.health email

Days 2-3: Product & Industry Knowledge
- Learn about collagen wound therapy and clinical evidence
- Memorize our 4 core products and HCPCS billing codes
- Understand insurance coverage
- Study common physician questions

Days 3-5: Sales Methodology Training
- Master our 4-Step Sales Process
- Practice discovery questions and role-play scenarios
- Review cold call scripts and objection handling
- Study competitive battle cards

End of Week 1: Systems & Tools
- Complete physician portal walkthrough
- Get CRM system access
- Receive your product sample kit
- Schedule shadow day with senior rep

QUICK ACCESS RESOURCES
- Training Hub: https://collagendirect.health/sales-training/
- 4-Step Sales Process: https://collagendirect.health/sales-training/sales-process.php
- Product Training: https://collagendirect.health/sales-training/product-training.php
- Objection Handling: https://collagendirect.health/sales-training/objections.php
- HIPAA Training: https://collagendirect.health/sales-training/hipaa-training.php

NEED HELP?
Contact Parker: parker@collagendirect.health

Welcome to the team!

CollagenDirect
Streamlining Wound Care, One Patient at a Time
https://collagendirect.health
TEXT;

  $result = send_email($email, $fullName, $subject, $htmlContent, $textContent);

  if ($result) {
    error_log("[email] New hire welcome email sent to $email");
    return true;
  }

  error_log("[email] Failed to send new hire welcome email to $email");
  return false;
}

/**
 * 8. Manufacturer New Order Notification
 * Audience: ALL manufacturer reps AND superadmins
 * Trigger: New order is submitted
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
  $patientCityState = $orderData['patient_city_state'] ?? 'N/A';
  $physicianName = $orderData['physician_name'] ?? '';
  $physicianNpi = $orderData['physician_npi'] ?? 'N/A';
  $practiceName = $orderData['practice_name'] ?? '';
  $productName = $orderData['product_name'] ?? 'N/A';
  $frequency = $orderData['frequency'] ?? 'N/A';
  $durationDays = $orderData['duration_days'] ?? 'N/A';

  $adminPortalUrl = "https://collagendirect.health/admin/orders.php?id={$orderId}";
  $subject = "New Order Submitted - Order #$orderId";

  $htmlBody = email_template($subject, "
    <h2 style='color: #1e293b; margin: 0 0 20px 0;'>New Order Submitted</h2>
    <p style='color: #475569; line-height: 1.6;'>A new order has been submitted and is ready for processing.</p>

    <div style='background-color: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 20px; margin: 20px 0;'>
      <h3 style='margin: 0 0 15px 0; color: #0f766e;'>Order Details</h3>
      <p style='margin: 5px 0; color: #475569;'><strong>Order ID:</strong> #$orderId</p>
      <p style='margin: 5px 0; color: #475569;'><strong>Order Date:</strong> $orderDate</p>
    </div>

    <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0;'>
      <h3 style='margin: 0 0 15px 0; color: #334155;'>Patient Information</h3>
      <p style='margin: 5px 0; color: #475569;'><strong>Patient:</strong> $patientName</p>
      <p style='margin: 5px 0; color: #475569;'><strong>DOB:</strong> $patientDob</p>
      <p style='margin: 5px 0; color: #475569;'><strong>Location:</strong> $patientCityState</p>
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

    <div style='text-align: center; margin: 30px 0;'>
      <a href='$adminPortalUrl' style='display: inline-block; background: linear-gradient(135deg, #0d9488, #14b8a6); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;'>
        View Order in Admin Portal
      </a>
    </div>
  ");

  $textBody = "New Order Submitted - Order #$orderId

ORDER DETAILS
Order ID: #$orderId
Order Date: $orderDate

PATIENT
Patient: $patientName
DOB: $patientDob
Location: $patientCityState

PROVIDER
Physician: $physicianName
NPI: $physicianNpi
Practice: $practiceName

PRODUCT
Product: $productName
Frequency: $frequency
Duration: $durationDays days

View order: $adminPortalUrl
";

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

/**
 * 9. Wholesale Order Submitted Email
 * Audience: All manufacturer reps and superadmins
 * Trigger: Wholesale order is submitted by a practice
 */
function send_wholesale_order_email(array $orderData): bool {
  global $pdo;

  // Get database connection if not available globally
  if (!isset($pdo) || !$pdo) {
    require_once __DIR__ . '/../../admin/db.php';
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
      error_log('[email] No manufacturer reps or superadmins found for wholesale order notification');
      return false;
    }

    error_log('[email] Found ' . count($recipients) . ' recipients for wholesale order notification');

  } catch (Throwable $e) {
    error_log('[email] Failed to get recipients for wholesale order: ' . $e->getMessage());
    return false;
  }

  // Build email content
  $orderNumber = $orderData['order_number'] ?? '';
  $orderDate = $orderData['order_date'] ?? date('m/d/Y');
  $physicianName = $orderData['physician_name'] ?? '';
  $physicianNpi = $orderData['physician_npi'] ?? 'N/A';
  $practiceName = $orderData['practice_name'] ?? '';
  $itemsCount = $orderData['items_count'] ?? 0;
  $productSummary = $orderData['product_summary'] ?? '';
  $notes = $orderData['notes'] ?? '';

  $adminPortalUrl = "https://collagendirect.health/admin/wholesale-orders.php";
  $subject = "New Wholesale Order - $orderNumber";

  $notesHtml = $notes ? "<p style='margin: 5px 0; color: #475569;'><strong>Notes:</strong> " . htmlspecialchars($notes) . "</p>" : '';
  $notesText = $notes ? "Notes: $notes\n" : '';

  $htmlBody = email_template($subject, "
    <h2 style='color: #1e293b; margin: 0 0 20px 0;'>New Wholesale Order Submitted</h2>
    <p style='color: #475569; line-height: 1.6;'>A new wholesale order has been submitted and is ready for processing.</p>

    <div style='background-color: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 20px; margin: 20px 0;'>
      <h3 style='margin: 0 0 15px 0; color: #92400e;'>Wholesale Order Details</h3>
      <p style='margin: 5px 0; color: #475569;'><strong>Order Number:</strong> $orderNumber</p>
      <p style='margin: 5px 0; color: #475569;'><strong>Order Date:</strong> $orderDate</p>
      <p style='margin: 5px 0; color: #475569;'><strong>Items:</strong> $itemsCount product(s)</p>
    </div>

    <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0;'>
      <h3 style='margin: 0 0 15px 0; color: #334155;'>Practice Information</h3>
      <p style='margin: 5px 0; color: #475569;'><strong>Practice:</strong> $practiceName</p>
      <p style='margin: 5px 0; color: #475569;'><strong>Physician:</strong> $physicianName</p>
      <p style='margin: 5px 0; color: #475569;'><strong>NPI:</strong> $physicianNpi</p>
    </div>

    <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0;'>
      <h3 style='margin: 0 0 15px 0; color: #334155;'>Products Ordered</h3>
      <p style='margin: 5px 0; color: #475569;'>$productSummary</p>
      $notesHtml
    </div>

    <div style='text-align: center; margin: 30px 0;'>
      <a href='$adminPortalUrl' style='display: inline-block; background: linear-gradient(135deg, #d97706, #f59e0b); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;'>
        View Wholesale Orders
      </a>
    </div>
  ");

  $textBody = "New Wholesale Order - $orderNumber

ORDER DETAILS
Order Number: $orderNumber
Order Date: $orderDate
Items: $itemsCount product(s)

PRACTICE
Practice: $practiceName
Physician: $physicianName
NPI: $physicianNpi

PRODUCTS ORDERED
$productSummary
$notesText
View orders: $adminPortalUrl
";

  // Send to ALL recipients via SMTP
  $successCount = 0;
  foreach ($recipients as $recipient) {
    $result = send_email($recipient['email'], $recipient['name'], $subject, $htmlBody, $textBody);
    if ($result) {
      $successCount++;
      error_log("[email] Wholesale order notification sent to {$recipient['email']} for order $orderNumber");
    } else {
      error_log("[email] Failed to send wholesale order notification to {$recipient['email']} for order $orderNumber");
    }
  }

  error_log("[email] Wholesale order notification: sent $successCount/" . count($recipients) . " emails for order $orderNumber");
  return $successCount > 0;
}

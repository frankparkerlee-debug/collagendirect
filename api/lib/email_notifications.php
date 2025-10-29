<?php
declare(strict_types=1);

/**
 * Centralized Email Notification System
 * Handles all 7 SendGrid template-based emails for CollagenDirect
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/sg_curl.php';

/**
 * 1. Password Reset Email
 * Template: SG_TMPL_PASSWORD_RESET
 * Audience: All users
 * Trigger: User clicks "Forgot Password"
 */
function send_password_reset_email(string $email, string $firstName, string $resetUrl): bool {
  $templateId = env('SG_TMPL_PASSWORD_RESET');
  if (!$templateId) {
    error_log('[email] Password reset template ID not configured');
    return false;
  }

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
 */
function send_physician_account_created_email(string $email, string $fullName, string $tempPassword): bool {
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
 * Template: SG_TMPL_MANUFACTURER_ORDER
 * Audience: Manufacturer
 * Trigger: New order is submitted
 */
function send_manufacturer_order_email(array $orderData): bool {
  $templateId = env('SG_TMPL_MANUFACTURER_ORDER');
  if (!$templateId) {
    error_log('[email] Manufacturer order template ID not configured');
    return false;
  }

  $manufacturerEmail = $orderData['manufacturer_email'] ?? 'orders@manufacturer.com';

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

  $orderId = $orderData['order_id'] ?? '';
  $adminPortalUrl = "https://collagendirect.health/admin/orders.php?id={$orderId}";

  return sg_send(
    ['email' => $manufacturerEmail, 'name' => 'Manufacturer'],
    null,
    null,
    [
      'template_id' => $templateId,
      'dynamic_data' => [
        'order_id' => $orderId,
        'order_date' => $orderData['order_date'] ?? date('m/d/Y'),
        'patient_name' => $orderData['patient_name'] ?? '',
        'patient_dob' => $orderData['patient_dob'] ?? '',
        'patient_address' => $orderData['patient_address'] ?? '',
        'insurance_provider' => $orderData['insurance_provider'] ?? '',
        'physician_name' => $orderData['physician_name'] ?? '',
        'physician_npi' => $orderData['physician_npi'] ?? '',
        'practice_name' => $orderData['practice_name'] ?? '',
        'products' => $products,
        'admin_portal_url' => $adminPortalUrl,
        'year' => date('Y'),
        'brand_logo_url' => 'https://collagendirect.health/assets/collagendirect.png'
      ],
      'categories' => ['order', 'manufacturer']
    ]
  );
}

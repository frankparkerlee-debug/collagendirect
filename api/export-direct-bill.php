<?php
/**
 * Export Direct Bill Orders - CSV Export for Practice DME Billing
 * Provides comprehensive data for HCFA 1500 claims and billing systems
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Verify authentication
$user = verifyAuth();
if (!$user) {
  http_response_code(401);
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

$userId = $user['id'];
$userRole = $user['role'];

// Only allow practice users to export their direct bill orders
if (!in_array($userRole, ['practice_admin', 'physician', 'superadmin'])) {
  http_response_code(403);
  header('Content-Type: application/json');
  echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
  exit;
}

try {
  // Get filter parameters
  $startDate = $_GET['start_date'] ?? date('Y-m-01'); // Default to first of month
  $endDate = $_GET['end_date'] ?? date('Y-m-d'); // Default to today
  $billingRoute = $_GET['billing_route'] ?? 'practice_dme'; // Default to practice_dme only

  // Build query based on user role
  if ($userRole === 'superadmin') {
    // Superadmin can export all orders
    $whereClause = "WHERE o.billed_by = ? AND o.created_at >= ? AND o.created_at <= ?";
    $params = [$billingRoute, $startDate . ' 00:00:00', $endDate . ' 23:59:59'];
  } else {
    // Regular users only see their own orders
    $whereClause = "WHERE o.user_id = ? AND o.billed_by = ? AND o.created_at >= ? AND o.created_at <= ?";
    $params = [$userId, $billingRoute, $startDate . ' 00:00:00', $endDate . ' 23:59:59'];
  }

  // Comprehensive query for billing data
  $query = "
    SELECT
      -- Order Identification
      o.id as order_id,
      o.created_at as order_date,
      o.status as order_status,
      o.start_date as service_start_date,
      o.last_eval_date as evaluation_date,

      -- Product/Service Information
      o.product as product_name,
      pr.sku as product_sku,
      o.cpt as hcpcs_code,
      pr.description as product_description,
      pr.size as product_size,
      o.shipments_remaining as quantity,
      o.product_price as unit_price,
      (o.shipments_remaining * o.product_price) as total_charge,
      o.frequency_per_week,
      o.qty_per_change,
      o.duration_days,
      o.refills_allowed,

      -- Patient Demographics
      p.last_name as patient_last_name,
      p.first_name as patient_first_name,
      p.dob as patient_dob,
      p.gender as patient_gender,
      p.mrn as patient_mrn,
      p.ssn_last4 as patient_ssn_last4,
      p.phone as patient_phone,
      p.email as patient_email,

      -- Patient Address
      p.address as patient_address,
      p.city as patient_city,
      p.state as patient_state,
      p.zip as patient_zip,

      -- Insurance/Payer Information
      p.insurance_company,
      p.member_id,
      p.group_id,
      p.payer_phone,
      p.prior_auth_number,

      -- Clinical/Diagnosis
      o.icd10_primary,
      o.icd10_secondary,
      o.wound_location,
      o.wound_laterality,
      o.wound_notes,
      o.wound_length_cm,
      o.wound_width_cm,
      o.wound_depth_cm,
      o.wound_type,
      o.wound_stage,

      -- Provider Information
      u.last_name as provider_last_name,
      u.first_name as provider_first_name,
      u.npi as provider_npi,
      u.credential as provider_credential,
      u.specialty as provider_specialty,
      u.phone as provider_phone,
      u.email as provider_email,

      -- Practice Information
      u.practice_name,
      u.practice_address,
      u.practice_city,
      u.practice_state,
      u.practice_zip,
      u.practice_phone,
      u.practice_fax,
      u.tax_id as practice_tax_id,

      -- Shipping/Delivery
      o.shipping_address,
      o.shipping_city,
      o.shipping_state,
      o.shipping_zip,
      o.shipping_name,
      o.shipping_phone,
      o.delivery_mode,

      -- Additional Order Details
      o.payment_type,
      o.additional_instructions,
      o.secondary_dressing,

      -- Documentation
      p.id_card_path,
      p.ins_card_path,
      p.aob_path,
      o.rx_note_path,

      -- Signature/Authorization
      o.sign_name,
      o.sign_title,
      o.signed_at,

      -- Billing tracking
      o.billed_by,
      o.updated_at as last_updated

    FROM orders o
    JOIN patients p ON p.id = o.patient_id
    JOIN users u ON u.id = o.user_id
    LEFT JOIN products pr ON pr.id = o.product_id
    $whereClause
    ORDER BY o.created_at DESC
  ";

  $stmt = $pdo->prepare($query);
  $stmt->execute($params);
  $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Set headers for CSV download
  $filename = sprintf(
    'direct-bill-export_%s_to_%s_%s.csv',
    date('Ymd', strtotime($startDate)),
    date('Ymd', strtotime($endDate)),
    date('YmdHis')
  );

  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  header('Pragma: no-cache');
  header('Expires: 0');

  // Open output stream
  $output = fopen('php://output', 'w');

  // Write UTF-8 BOM for Excel compatibility
  fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

  // Write header row
  fputcsv($output, [
    // Order Identification
    'Order ID',
    'Order Date',
    'Order Status',
    'Service Start Date',
    'Evaluation Date',

    // Product/Service Information
    'Product Name',
    'Product SKU',
    'HCPCS Code',
    'Product Description',
    'Size',
    'Quantity',
    'Unit Price',
    'Total Charge',
    'Frequency per Week',
    'Qty per Change',
    'Duration Days',
    'Refills Allowed',

    // Patient Demographics
    'Patient Last Name',
    'Patient First Name',
    'Patient DOB',
    'Patient Gender',
    'Patient MRN',
    'Patient SSN (Last 4)',
    'Patient Phone',
    'Patient Email',

    // Patient Address
    'Patient Address',
    'Patient City',
    'Patient State',
    'Patient ZIP',

    // Insurance/Payer Information
    'Insurance Company',
    'Member ID',
    'Group ID',
    'Payer Phone',
    'Prior Authorization Number',

    // Clinical/Diagnosis
    'ICD-10 Primary',
    'ICD-10 Secondary',
    'Wound Location',
    'Wound Laterality',
    'Wound Notes',
    'Wound Length (cm)',
    'Wound Width (cm)',
    'Wound Depth (cm)',
    'Wound Type',
    'Wound Stage',

    // Provider Information
    'Provider Last Name',
    'Provider First Name',
    'Provider NPI',
    'Provider Credential',
    'Provider Specialty',
    'Provider Phone',
    'Provider Email',

    // Practice Information
    'Practice Name',
    'Practice Address',
    'Practice City',
    'Practice State',
    'Practice ZIP',
    'Practice Phone',
    'Practice Fax',
    'Practice Tax ID',

    // Shipping/Delivery
    'Shipping Address',
    'Shipping City',
    'Shipping State',
    'Shipping ZIP',
    'Shipping Name',
    'Shipping Phone',
    'Delivery Mode',

    // Additional Order Details
    'Payment Type',
    'Additional Instructions',
    'Secondary Dressing',

    // Documentation Paths
    'Patient ID Card Path',
    'Insurance Card Path',
    'AOB Path',
    'Rx Note Path',

    // Signature/Authorization
    'E-Signature Name',
    'E-Signature Title',
    'E-Signature Date',

    // Billing Tracking
    'Billed By',
    'Last Updated'
  ]);

  // Write data rows
  foreach ($orders as $order) {
    fputcsv($output, [
      // Order Identification
      $order['order_id'],
      $order['order_date'] ? date('m/d/Y', strtotime($order['order_date'])) : '',
      ucfirst($order['order_status']),
      $order['service_start_date'] ? date('m/d/Y', strtotime($order['service_start_date'])) : '',
      $order['evaluation_date'] ? date('m/d/Y', strtotime($order['evaluation_date'])) : '',

      // Product/Service Information
      $order['product_name'],
      $order['product_sku'] ?? '',
      $order['hcpcs_code'] ?? '',
      $order['product_description'] ?? '',
      $order['product_size'] ?? '',
      $order['quantity'] ?? '0',
      $order['unit_price'] ? '$' . number_format($order['unit_price'], 2) : '',
      $order['total_charge'] ? '$' . number_format($order['total_charge'], 2) : '',
      $order['frequency_per_week'] ?? '',
      $order['qty_per_change'] ?? '',
      $order['duration_days'] ?? '',
      $order['refills_allowed'] ?? '',

      // Patient Demographics
      $order['patient_last_name'],
      $order['patient_first_name'],
      $order['patient_dob'] ? date('m/d/Y', strtotime($order['patient_dob'])) : '',
      $order['patient_gender'] ?? '',
      $order['patient_mrn'] ?? '',
      $order['patient_ssn_last4'] ?? '',
      formatPhone($order['patient_phone'] ?? ''),
      $order['patient_email'] ?? '',

      // Patient Address
      $order['patient_address'] ?? '',
      $order['patient_city'] ?? '',
      $order['patient_state'] ?? '',
      $order['patient_zip'] ?? '',

      // Insurance/Payer Information
      $order['insurance_company'] ?? '',
      $order['member_id'] ?? '',
      $order['group_id'] ?? '',
      formatPhone($order['payer_phone'] ?? ''),
      $order['prior_auth_number'] ?? '',

      // Clinical/Diagnosis
      $order['icd10_primary'] ?? '',
      $order['icd10_secondary'] ?? '',
      $order['wound_location'] ?? '',
      $order['wound_laterality'] ?? '',
      $order['wound_notes'] ?? '',
      $order['wound_length_cm'] ?? '',
      $order['wound_width_cm'] ?? '',
      $order['wound_depth_cm'] ?? '',
      $order['wound_type'] ?? '',
      $order['wound_stage'] ?? '',

      // Provider Information
      $order['provider_last_name'] ?? '',
      $order['provider_first_name'] ?? '',
      $order['provider_npi'] ?? '',
      $order['provider_credential'] ?? '',
      $order['provider_specialty'] ?? '',
      formatPhone($order['provider_phone'] ?? ''),
      $order['provider_email'] ?? '',

      // Practice Information
      $order['practice_name'] ?? '',
      $order['practice_address'] ?? '',
      $order['practice_city'] ?? '',
      $order['practice_state'] ?? '',
      $order['practice_zip'] ?? '',
      formatPhone($order['practice_phone'] ?? ''),
      formatPhone($order['practice_fax'] ?? ''),
      $order['practice_tax_id'] ?? '',

      // Shipping/Delivery
      $order['shipping_address'] ?? '',
      $order['shipping_city'] ?? '',
      $order['shipping_state'] ?? '',
      $order['shipping_zip'] ?? '',
      $order['shipping_name'] ?? '',
      formatPhone($order['shipping_phone'] ?? ''),
      ucfirst($order['delivery_mode'] ?? ''),

      // Additional Order Details
      ucfirst($order['payment_type'] ?? ''),
      $order['additional_instructions'] ?? '',
      $order['secondary_dressing'] ?? '',

      // Documentation Paths
      $order['id_card_path'] ?? '',
      $order['ins_card_path'] ?? '',
      $order['aob_path'] ?? '',
      $order['rx_note_path'] ?? '',

      // Signature/Authorization
      $order['sign_name'] ?? '',
      $order['sign_title'] ?? '',
      $order['signed_at'] ? date('m/d/Y', strtotime($order['signed_at'])) : '',

      // Billing Tracking
      $order['billed_by'] === 'practice_dme' ? 'Practice DME' : 'CollagenDirect',
      $order['last_updated'] ? date('m/d/Y H:i', strtotime($order['last_updated'])) : ''
    ]);
  }

  fclose($output);
  exit;

} catch (PDOException $e) {
  error_log("Export Direct Bill Error: " . $e->getMessage());
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode([
    'success' => false,
    'error' => 'Database error: ' . $e->getMessage()
  ]);
  exit;
} catch (Exception $e) {
  error_log("Export Direct Bill Error: " . $e->getMessage());
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode([
    'success' => false,
    'error' => 'Export error: ' . $e->getMessage()
  ]);
  exit;
}

/**
 * Format phone number
 */
function formatPhone(?string $phone): string {
  if (!$phone) return '';

  // Remove all non-digit characters
  $digits = preg_replace('/\D/', '', $phone);

  // Format as (XXX) XXX-XXXX if 10 digits
  if (strlen($digits) === 10) {
    return sprintf('(%s) %s-%s',
      substr($digits, 0, 3),
      substr($digits, 3, 3),
      substr($digits, 6, 4)
    );
  }

  return $phone;
}

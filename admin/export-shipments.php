<?php
declare(strict_types=1);
require __DIR__ . '/auth.php'; require_admin();
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: /admin/shipments.php');
  exit;
}

verify_csrf();

$orderIds = json_decode($_POST['order_ids'] ?? '[]', true);

if (empty($orderIds) || !is_array($orderIds)) {
  header('Location: /admin/shipments.php');
  exit;
}

// Fetch order details for selected IDs
$placeholders = implode(',', array_fill(0, count($orderIds), '?'));
$sql = "
  SELECT
    o.id,
    o.product,
    o.qty_per_change as boxes_ordered,
    o.shipping_name,
    o.shipping_address,
    o.shipping_city,
    o.shipping_state,
    o.shipping_zip,
    o.shipping_phone,
    o.billed_by,
    o.payment_type,
    o.created_at,
    o.rx_note_name as tracking_number,
    o.rx_note_mime as carrier,
    p.first_name as patient_first,
    p.last_name as patient_last,
    u.practice_name
  FROM orders o
  LEFT JOIN patients p ON p.id = o.patient_id
  LEFT JOIN users u ON u.id = o.user_id
  WHERE o.id IN ($placeholders)
  ORDER BY o.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($orderIds);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($orders)) {
  header('Location: /admin/shipments.php');
  exit;
}

// Generate CSV (Excel-compatible)
$filename = 'shipments_' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

$output = fopen('php://output', 'w');

// Add BOM for Excel UTF-8 compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write header row
fputcsv($output, [
  'Order ID',
  'Order Type',
  'Date',
  'Recipient Name',
  'Street Address',
  'City',
  'State',
  'ZIP',
  'Phone',
  'Product',
  'Boxes Ordered',
  'Carrier',
  'Tracking Number'
]);

// Write data rows
foreach ($orders as $order) {
  $isWholesale = ($order['billed_by'] === 'practice_dme' || $order['payment_type'] === 'wholesale');
  $orderType = $isWholesale ? 'Wholesale' : 'Referral';

  // Determine recipient name
  if ($isWholesale) {
    $recipientName = $order['practice_name'] ?? 'Practice';
  } else {
    $recipientName = trim(($order['patient_first'] ?? '') . ' ' . ($order['patient_last'] ?? ''));
    if (empty($recipientName)) {
      $recipientName = $order['shipping_name'] ?? '';
    }
  }

  fputcsv($output, [
    $order['id'],
    $orderType,
    date('m/d/Y', strtotime($order['created_at'])),
    $recipientName,
    $order['shipping_address'] ?? '',
    $order['shipping_city'] ?? '',
    $order['shipping_state'] ?? '',
    $order['shipping_zip'] ?? '',
    $order['shipping_phone'] ?? '',
    $order['product'] ?? '',
    $order['boxes_ordered'] ?? '1',
    strtoupper($order['carrier'] ?? ''),
    $order['tracking_number'] ?? ''
  ]);
}

fclose($output);
exit;

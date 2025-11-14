<?php
// /public/admin/api-order-detail.php — Get order details for modal
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
if (function_exists('require_admin')) require_admin();

header('Content-Type: application/json');

$id = $_GET['id'] ?? '';

if (empty($id)) {
  echo json_encode(['ok' => false, 'error' => 'Order ID required']);
  exit;
}

try {
  $stmt = $pdo->prepare("
    SELECT
      o.id,
      o.created_at,
      o.product,
      o.shipments_remaining,
      o.product_price as unit_price,
      o.status,
      o.paid_at,
      o.invoice_sent_at,
      o.tracking_number,
      o.notes,
      u.practice_name,
      u.first_name as phys_first,
      u.last_name as phys_last,
      u.email as phys_email,
      p.first_name as pat_first,
      p.last_name as pat_last,
      CONCAT_WS(', ', o.shipping_address, o.shipping_city, o.shipping_state, o.shipping_zip) as shipping_address,
      pr.pieces_per_box,
      pr.price_wholesale
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN patients p ON o.patient_id = p.id
    LEFT JOIN products pr ON o.product_id = pr.id
    WHERE o.id = ?
  ");

  $stmt->execute([$id]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($order) {
    echo json_encode(['ok' => true, 'order' => $order]);
  } else {
    echo json_encode(['ok' => false, 'error' => 'Order not found']);
  }
} catch (Throwable $e) {
  error_log('[api-order-detail] Error: ' . $e->getMessage());
  echo json_encode(['ok' => false, 'error' => 'Database error']);
}

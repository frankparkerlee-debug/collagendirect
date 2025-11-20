<?php
require_once __DIR__ . '/db.php';
header('Content-Type: text/plain');

echo "=== FREQUENCY DATA CHECK ===\n\n";

$stmt = $pdo->query("
  SELECT
    id,
    product,
    frequency,
    frequency_per_week,
    duration_days,
    qty_per_change,
    billed_by,
    status
  FROM orders
  WHERE status NOT IN ('draft', 'rejected', 'cancelled')
  ORDER BY created_at DESC
  LIMIT 20
");

foreach ($stmt->fetchAll() as $order) {
  echo "Order: " . substr($order['id'], 0, 8) . "...\n";
  echo "  Product: " . ($order['product'] ?: 'N/A') . "\n";
  echo "  frequency (text): '" . ($order['frequency'] ?: 'NULL') . "'\n";
  echo "  frequency_per_week (int): " . ($order['frequency_per_week'] ?? 'NULL') . "\n";
  echo "  duration_days: " . ($order['duration_days'] ?? 'NULL') . "\n";
  echo "  qty_per_change: " . ($order['qty_per_change'] ?? 'NULL') . "\n";
  echo "  billed_by: " . ($order['billed_by'] ?? 'NULL') . "\n";
  echo "  status: " . $order['status'] . "\n\n";
}

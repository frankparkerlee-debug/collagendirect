<?php
// Test billing query
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/db.php';

echo "=== Billing Query Test ===\n\n";

try {
  // Check admin session
  if (empty($_SESSION['admin_id'])) {
    echo "ERROR: Not logged in\n";
    exit;
  }

  $adminId = $_SESSION['admin_id'];
  $adminRole = $_SESSION['admin_role'] ?? 'employee';

  echo "Admin ID: $adminId\n";
  echo "Admin Role: $adminRole\n\n";

  // Test date range
  $from = date('Y-m-d', strtotime('-6 months'));
  $to = date('Y-m-d');

  echo "Date Range: $from to $to\n\n";

  // Test query
  $sql = "
    SELECT
      o.id, o.user_id, o.patient_id, o.product_id, o.product, o.frequency,
      o.frequency_per_week, o.qty_per_change, o.duration_days, o.refills_allowed,
      o.shipments_remaining,
      o.product_price, o.created_at, o.rx_note_name AS tracking, o.rx_note_mime AS carrier,
      o.insurer_name, o.member_id, o.group_id, o.payer_phone,
      p.first_name, p.last_name, p.dob,
      pr.name AS prod_name, pr.size AS prod_size, pr.sku, pr.hcpcs_code AS cpt_code, pr.price_admin
    FROM orders o
    LEFT JOIN patients p ON p.id=o.patient_id
    LEFT JOIN products pr ON pr.id=o.product_id
    WHERE o.created_at BETWEEN :from AND (:to::date + INTERVAL '1 day')
      AND o.status NOT IN ('rejected','cancelled')
    ORDER BY o.created_at DESC
    LIMIT 5
  ";

  echo "Executing query...\n";
  $st = $pdo->prepare($sql);
  $st->execute(['from' => $from, 'to' => $to]);
  $rows = $st->fetchAll();

  echo "Found " . count($rows) . " rows\n\n";

  if (count($rows) > 0) {
    echo "Sample row:\n";
    $row = $rows[0];
    echo "  Order ID: " . ($row['id'] ?? 'NULL') . "\n";
    echo "  Patient: " . ($row['first_name'] ?? '') . " " . ($row['last_name'] ?? '') . "\n";
    echo "  Product: " . ($row['product'] ?? 'NULL') . "\n";
    echo "  Shipments Remaining: " . var_export($row['shipments_remaining'] ?? NULL, true) . " (type: " . gettype($row['shipments_remaining'] ?? null) . ")\n";
    echo "  Created: " . ($row['created_at'] ?? 'NULL') . "\n";

    echo "\n  All fields:\n";
    foreach ($row as $key => $value) {
      if (is_string($key)) {
        $type = gettype($value);
        $val = $value === null ? 'NULL' : (is_string($value) ? substr($value, 0, 50) : var_export($value, true));
        echo "    $key: $val ($type)\n";
      }
    }
  }

  echo "\n=== Test Complete ===\n";

} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

<?php
// Check what file paths are stored in orders table
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/db.php';

echo "=== Order File Paths Check ===\n\n";

try {
  // Get sample orders with file paths
  $sql = "
    SELECT
      o.id,
      o.patient_id,
      o.rx_note_name,
      o.rx_note_path,
      o.insurance_card_name,
      o.insurance_card_path,
      o.id_card_name,
      o.id_card_path,
      p.first_name,
      p.last_name
    FROM orders o
    LEFT JOIN patients p ON p.id = o.patient_id
    WHERE
      o.rx_note_path IS NOT NULL
      OR o.insurance_card_path IS NOT NULL
      OR o.id_card_path IS NOT NULL
    LIMIT 10
  ";

  $stmt = $pdo->query($sql);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "Found " . count($rows) . " orders with file attachments\n\n";

  foreach ($rows as $row) {
    echo "Order ID: " . ($row['id'] ?? 'N/A') . "\n";
    echo "Patient: " . ($row['first_name'] ?? '') . " " . ($row['last_name'] ?? '') . "\n";
    echo "RX Note: " . ($row['rx_note_path'] ?? 'none') . "\n";
    echo "Insurance: " . ($row['insurance_card_path'] ?? 'none') . "\n";
    echo "ID Card: " . ($row['id_card_path'] ?? 'none') . "\n";
    echo "---\n";
  }

  // Check if files exist on filesystem
  echo "\n=== Filesystem Check ===\n\n";
  foreach ($rows as $row) {
    if (!empty($row['rx_note_path'])) {
      $path = '/var/www/html' . $row['rx_note_path'];
      echo "RX Note: " . $row['rx_note_path'] . " - Exists: " . (file_exists($path) ? 'YES' : 'NO') . "\n";
    }
    if (!empty($row['insurance_card_path'])) {
      $path = '/var/www/html' . $row['insurance_card_path'];
      echo "Insurance: " . $row['insurance_card_path'] . " - Exists: " . (file_exists($path) ? 'YES' : 'NO') . "\n";
    }
    if (!empty($row['id_card_path'])) {
      $path = '/var/www/html' . $row['id_card_path'];
      echo "ID Card: " . $row['id_card_path'] . " - Exists: " . (file_exists($path) ? 'YES' : 'NO') . "\n";
    }
  }

} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Check Complete ===\n";
?>

<?php
require_once __DIR__.'/../config/db.php';
header('Content-Type: text/plain');

try {
  $stmt = $pdo->query("SELECT id, first_name, last_name, id_card_path, ins_card_path FROM patients WHERE id_card_path IS NOT NULL OR ins_card_path IS NOT NULL LIMIT 5");
  $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo "=== Patient Document Paths ===\n\n";
  foreach ($patients as $p) {
    echo "Patient: {$p['first_name']} {$p['last_name']}\n";
    echo "  ID Card Path: " . ($p['id_card_path'] ?? 'NULL') . "\n";
    echo "  Insurance Card Path: " . ($p['ins_card_path'] ?? 'NULL') . "\n";

    // Check if files exist
    if ($p['id_card_path']) {
      $fullPath = __DIR__ . '/../' . ltrim($p['id_card_path'], '/');
      echo "  ID Card Full Path: $fullPath\n";
      echo "  ID Card Exists: " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";
    }
    if ($p['ins_card_path']) {
      $fullPath = __DIR__ . '/../' . ltrim($p['ins_card_path'], '/');
      echo "  Ins Card Full Path: $fullPath\n";
      echo "  Ins Card Exists: " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";
    }
    echo "\n";
  }

  // Check uploads directory
  $uploadsDir = __DIR__ . '/../uploads';
  echo "=== Uploads Directory ===\n";
  echo "Uploads directory: $uploadsDir\n";
  echo "Exists: " . (is_dir($uploadsDir) ? 'YES' : 'NO') . "\n";
  echo "Readable: " . (is_readable($uploadsDir) ? 'YES' : 'NO') . "\n";

  if (is_dir($uploadsDir)) {
    echo "\nContents:\n";
    $files = scandir($uploadsDir);
    foreach ($files as $file) {
      if ($file !== '.' && $file !== '..') {
        echo "  - $file (is_dir: " . (is_dir("$uploadsDir/$file") ? 'YES' : 'NO') . ")\n";
      }
    }
  }

} catch (Exception $e) {
  echo "Error: " . $e->getMessage();
}

<?php
/**
 * Quick Check: Verify patient file columns exist
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

echo "<pre>";
echo "=== Verifying Patient File Columns ===\n\n";

try {
  // Check all file-related columns
  $columns = ['id_card_path', 'id_card_mime', 'ins_card_path', 'ins_card_mime', 'notes_path', 'notes_mime', 'aob_path'];

  foreach ($columns as $col) {
    $stmt = $pdo->prepare("
      SELECT column_name, data_type, is_nullable
      FROM information_schema.columns
      WHERE table_name = 'patients' AND column_name = ?
    ");
    $stmt->execute([$col]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
      echo "✓ $col exists ({$result['data_type']}, nullable: {$result['is_nullable']})\n";
    } else {
      echo "✗ $col MISSING!\n";
    }
  }

  echo "\n--- Sample Patient Data ---\n";
  $sample = $pdo->query("
    SELECT id, first_name, last_name, id_card_path, ins_card_path, notes_path, aob_path
    FROM patients
    WHERE id_card_path IS NOT NULL OR ins_card_path IS NOT NULL OR notes_path IS NOT NULL
    LIMIT 3
  ")->fetchAll(PDO::FETCH_ASSOC);

  if (empty($sample)) {
    echo "No patients with attachments found.\n";
  } else {
    foreach ($sample as $p) {
      echo "\nPatient: {$p['first_name']} {$p['last_name']}\n";
      echo "  ID Card: " . ($p['id_card_path'] ?: 'null') . "\n";
      echo "  Ins Card: " . ($p['ins_card_path'] ?: 'null') . "\n";
      echo "  Notes: " . ($p['notes_path'] ?: 'null') . "\n";
      echo "  AOB: " . ($p['aob_path'] ?: 'null') . "\n";
    }
  }

} catch (Throwable $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";

<?php
// /admin/expand-patient-state-column.php — Expand patients.state column to support longer status values
require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Expanding patients.state column ===\n\n";

try {
  // Check current column type
  $stmt = $pdo->query("
    SELECT data_type, character_maximum_length
    FROM information_schema.columns
    WHERE table_name = 'patients'
    AND column_name = 'state'
  ");
  $col = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$col) {
    echo "❌ Error: 'state' column not found in patients table\n";
    exit;
  }

  echo "Current state column type: {$col['data_type']}({$col['character_maximum_length']})\n\n";

  if ($col['character_maximum_length'] >= 50) {
    echo "✅ Column is already varchar(50) or larger - no migration needed\n";
    exit;
  }

  // Expand the column to varchar(50) to support longer status values
  echo "Expanding state column to varchar(50)...\n";
  $pdo->exec("ALTER TABLE patients ALTER COLUMN state TYPE VARCHAR(50)");
  echo "✅ Column expanded successfully\n\n";

  // Verify the change
  $stmt = $pdo->query("
    SELECT character_maximum_length
    FROM information_schema.columns
    WHERE table_name = 'patients'
    AND column_name = 'state'
  ");
  $newCol = $stmt->fetch(PDO::FETCH_ASSOC);
  echo "New state column type: varchar({$newCol['character_maximum_length']})\n\n";

  echo "=== Migration complete ===\n";
  echo "\nStatus values that now work:\n";
  echo "- benefits_expired (16 chars) ✅\n";
  echo "- no_coverage (11 chars) ✅\n";
  echo "- not_covered (11 chars) ✅\n";
  echo "- need_info (9 chars) ✅\n";
  echo "- approved (8 chars) ✅\n";
  echo "- inactive (8 chars) ✅\n";
  echo "- pending (7 chars) ✅\n";
  echo "- active (6 chars) ✅\n";
  echo "- new (3 chars) ✅\n";

} catch (PDOException $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit;
}

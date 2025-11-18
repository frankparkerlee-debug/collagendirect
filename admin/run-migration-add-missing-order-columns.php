<?php
/**
 * Add Missing Order Columns Migration
 * Adds columns that were referenced in code but missing from database
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

echo "=== Add Missing Order Columns Migration ===\n\n";

try {
  $pdo->beginTransaction();

  // Check what columns are missing
  echo "Step 1: Checking for missing columns...\n";

  $columns_to_add = [
    'notes_text' => 'TEXT',
    'additional_instructions' => 'TEXT',
    'secondary_dressing' => 'VARCHAR(255)',
    'last_eval_date' => 'DATE',
    'start_date' => 'DATE',
    'qty_per_change' => 'INTEGER',
    'duration_days' => 'INTEGER'
  ];

  $existing_columns = [];
  $stmt = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders'
  ");
  while ($row = $stmt->fetch()) {
    $existing_columns[] = $row['column_name'];
  }

  echo "  Existing columns: " . count($existing_columns) . "\n";

  $added = 0;
  foreach ($columns_to_add as $column => $type) {
    if (!in_array($column, $existing_columns)) {
      echo "  Adding column: $column ($type)...\n";
      $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS $column $type");
      $added++;
    } else {
      echo "  ✓ Column exists: $column\n";
    }
  }

  echo "\n";

  if ($added > 0) {
    echo "Step 2: Added $added missing columns\n\n";
  } else {
    echo "Step 2: All columns already exist\n\n";
  }

  $pdo->commit();

  echo "=== Migration Complete ===\n";
  echo "✓ Orders table now has all required columns\n";
  echo "✓ Order creation should now work correctly\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed:\n";
  echo "  Error: " . $e->getMessage() . "\n";
  exit(1);
}

#!/usr/bin/env php
<?php
/**
 * Run notification tables migration
 * Usage: php migrations/run-notification-migration.php
 */

declare(strict_types=1);

// Load database connection
require_once __DIR__ . '/../api/db.php';

echo "=== CollagenDirect Notification Tables Migration ===\n\n";

try {
  // Read SQL file
  $sqlFile = __DIR__ . '/create-notification-tables.sql';
  if (!file_exists($sqlFile)) {
    throw new Exception("SQL file not found: $sqlFile");
  }

  $sql = file_get_contents($sqlFile);
  echo "Loaded SQL file: $sqlFile\n";
  echo "File size: " . strlen($sql) . " bytes\n\n";

  // Execute migration
  echo "Executing migration...\n";
  $pdo->exec($sql);

  echo "\n✓ Migration completed successfully!\n\n";

  // Verify tables were created
  echo "Verifying tables...\n";
  $tables = [
    'order_delivery_confirmations',
    'order_status_changes'
  ];

  foreach ($tables as $table) {
    $stmt = $pdo->prepare("
      SELECT COUNT(*) as count
      FROM information_schema.tables
      WHERE table_name = ?
    ");
    $stmt->execute([$table]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
      echo "  ✓ Table '$table' exists\n";

      // Count rows
      $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
      $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
      echo "    Current rows: $count\n";
    } else {
      echo "  ✗ Table '$table' NOT FOUND\n";
    }
  }

  // Verify trigger was created
  echo "\nVerifying trigger...\n";
  $stmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM information_schema.triggers
    WHERE trigger_name = 'trigger_log_status_change'
  ");
  $result = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($result['count'] > 0) {
    echo "  ✓ Trigger 'trigger_log_status_change' exists\n";
  } else {
    echo "  ✗ Trigger 'trigger_log_status_change' NOT FOUND\n";
  }

  echo "\n=== Migration Complete ===\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  echo "  Code: " . $e->getCode() . "\n";
  exit(1);
} catch (Exception $e) {
  echo "\n✗ Error:\n";
  echo "  " . $e->getMessage() . "\n";
  exit(1);
}

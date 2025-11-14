<?php
/**
 * Migration: Add role and additional fields to users table
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== Add User Role Migration ===\n\n";

try {
  $pdo->beginTransaction();

  // Add role column if it doesn't exist
  echo "1. Adding role column to users table...\n";
  try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'physician'");
    echo "   ✓ Added role column\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') === false) {
      throw $e;
    }
    echo "   - role column already exists\n";
  }

  // Add address fields if they don't exist
  echo "\n2. Adding address fields to users table...\n";
  $addressFields = [
    'address' => "ALTER TABLE users ADD COLUMN IF NOT EXISTS address VARCHAR(255)",
    'city' => "ALTER TABLE users ADD COLUMN IF NOT EXISTS city VARCHAR(100)",
    'state' => "ALTER TABLE users ADD COLUMN IF NOT EXISTS state VARCHAR(2)",
    'zip' => "ALTER TABLE users ADD COLUMN IF NOT EXISTS zip VARCHAR(10)",
    'phone' => "ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20)"
  ];

  foreach ($addressFields as $field => $sql) {
    try {
      $pdo->exec($sql);
      echo "   ✓ Added $field column\n";
    } catch (PDOException $e) {
      if (strpos($e->getMessage(), 'already exists') === false) {
        throw $e;
      }
      echo "   - $field already exists\n";
    }
  }

  $pdo->commit();
  echo "\n✓ Migration completed successfully!\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  http_response_code(500);
  exit(1);
}

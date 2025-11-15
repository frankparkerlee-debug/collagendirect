<?php
/**
 * Migration: Add practice_physicians table for linking physicians to practice managers
 */
declare(strict_types=1);
require __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== Practice Physicians Table Migration ===\n\n";

try {
  // Check if table already exists
  $tableCheck = $pdo->query("
    SELECT table_name
    FROM information_schema.tables
    WHERE table_name = 'practice_physicians'
  ")->fetchColumn();

  if ($tableCheck) {
    echo "ℹ️  practice_physicians table already exists. Checking structure...\n\n";
  } else {
    echo "Creating practice_physicians table...\n";

    $pdo->exec("
      CREATE TABLE practice_physicians (
        id SERIAL PRIMARY KEY,
        practice_admin_id VARCHAR(64) NOT NULL,
        physician_id VARCHAR(64),
        first_name VARCHAR(255),
        last_name VARCHAR(255),
        physician_email VARCHAR(255),
        physician_npi VARCHAR(10),
        physician_license VARCHAR(100),
        physician_license_state VARCHAR(2),
        physician_license_expiry DATE,
        physician_phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (practice_admin_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (physician_id) REFERENCES users(id) ON DELETE CASCADE
      )
    ");

    echo "✓ practice_physicians table created\n";
  }

  // Check if we need to add missing columns
  $existingCols = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'practice_physicians'
  ")->fetchAll(PDO::FETCH_COLUMN);

  $requiredColumns = [
    'id' => 'SERIAL PRIMARY KEY',
    'practice_admin_id' => 'VARCHAR(64) NOT NULL',
    'physician_id' => 'VARCHAR(64)',
    'first_name' => 'VARCHAR(255)',
    'last_name' => 'VARCHAR(255)',
    'physician_email' => 'VARCHAR(255)',
    'physician_npi' => 'VARCHAR(10)',
    'physician_license' => 'VARCHAR(100)',
    'physician_license_state' => 'VARCHAR(2)',
    'physician_license_expiry' => 'DATE',
    'physician_phone' => 'VARCHAR(20)',
    'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'
  ];

  foreach ($requiredColumns as $colName => $colDef) {
    if (!in_array($colName, $existingCols)) {
      echo "Adding column: $colName...\n";
      // Extract just the type and constraints for ALTER TABLE
      $pdo->exec("ALTER TABLE practice_physicians ADD COLUMN $colName $colDef");
      echo "✓ Added $colName\n";
    }
  }

  // Create index for faster lookups
  echo "\nCreating indexes...\n";
  try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_practice_physicians_admin ON practice_physicians(practice_admin_id)");
    echo "✓ Created index on practice_admin_id\n";
  } catch (Throwable $e) {
    echo "ℹ️  Index already exists: " . $e->getMessage() . "\n";
  }

  try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_practice_physicians_physician ON practice_physicians(physician_id)");
    echo "✓ Created index on physician_id\n";
  } catch (Throwable $e) {
    echo "ℹ️  Index already exists: " . $e->getMessage() . "\n";
  }

  echo "\n✅ Migration completed successfully!\n";
  echo "\nNext steps:\n";
  echo "1. Practice admins can now link physicians to their practice\n";
  echo "2. Physicians linked via practice_physicians will show up in practice admin's patient list\n";

} catch (Throwable $e) {
  echo "\n❌ Migration failed!\n";
  echo "Error: " . $e->getMessage() . "\n";
  echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
  http_response_code(500);
}

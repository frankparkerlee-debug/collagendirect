<?php
/**
 * Migration: Add PTAN (Provider Transaction Access Number) field
 *
 * Captures PTAN numbers from physicians and practice admins during registration
 */
declare(strict_types=1);
require __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== PTAN Field Migration ===\n\n";

try {
  $pdo->beginTransaction();

  // Add ptan column to users table
  echo "1. Adding ptan column to users table...\n";

  $columnCheck = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'users'
    AND column_name = 'ptan'
  ")->fetchColumn();

  if (!$columnCheck) {
    $pdo->exec("
      ALTER TABLE users
      ADD COLUMN ptan VARCHAR(255) DEFAULT NULL
    ");
    echo "   ✓ Added ptan column\n";
  } else {
    echo "   ℹ️  ptan column already exists\n";
  }

  // Also add to practice_physicians table for additional physicians
  echo "\n2. Adding ptan column to practice_physicians table...\n";

  $ppColumnCheck = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'practice_physicians'
    AND column_name = 'physician_ptan'
  ")->fetchColumn();

  if (!$ppColumnCheck) {
    $pdo->exec("
      ALTER TABLE practice_physicians
      ADD COLUMN physician_ptan VARCHAR(255) DEFAULT NULL
    ");
    echo "   ✓ Added physician_ptan column to practice_physicians\n";
  } else {
    echo "   ℹ️  physician_ptan column already exists in practice_physicians\n";
  }

  $pdo->commit();
  echo "\n✅ Migration completed successfully!\n";
  echo "\nPTAN fields are now available for:\n";
  echo "  - users.ptan (for primary registrant)\n";
  echo "  - practice_physicians.physician_ptan (for additional physicians)\n";

} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}

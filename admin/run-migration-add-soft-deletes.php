<?php
/**
 * Migration: Add soft delete functionality
 *
 * Adds deleted_at and deleted_by columns to key tables
 * This allows manufacturers to "delete" data while superadmin can still access it
 */
declare(strict_types=1);
require __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== Soft Delete Migration ===\n\n";

try {
  $pdo->beginTransaction();

  $tables = ['users', 'patients', 'orders', 'practice_locations', 'practice_physicians'];

  foreach ($tables as $table) {
    echo "Processing table: $table\n";

    // Add deleted_at column
    $deletedAtCheck = $pdo->query("
      SELECT column_name
      FROM information_schema.columns
      WHERE table_name = '$table'
      AND column_name = 'deleted_at'
    ")->fetchColumn();

    if (!$deletedAtCheck) {
      $pdo->exec("ALTER TABLE $table ADD COLUMN deleted_at TIMESTAMP NULL");
      echo "  ✓ Added deleted_at column\n";
    } else {
      echo "  ℹ️  deleted_at already exists\n";
    }

    // Add deleted_by column (references admin_users.id for admin deletions, users.id for user deletions)
    $deletedByCheck = $pdo->query("
      SELECT column_name
      FROM information_schema.columns
      WHERE table_name = '$table'
      AND column_name = 'deleted_by'
    ")->fetchColumn();

    if (!$deletedByCheck) {
      $pdo->exec("ALTER TABLE $table ADD COLUMN deleted_by VARCHAR(255) NULL");
      echo "  ✓ Added deleted_by column\n";
    } else {
      echo "  ℹ️  deleted_by already exists\n";
    }

    // Create index for faster queries excluding deleted records
    try {
      $pdo->exec("CREATE INDEX IF NOT EXISTS idx_{$table}_deleted_at ON $table(deleted_at)");
      echo "  ✓ Created index on deleted_at\n";
    } catch (Throwable $e) {
      echo "  ℹ️  Index already exists\n";
    }

    echo "\n";
  }

  $pdo->commit();

  echo "✅ Migration completed successfully!\n";
  echo "\nWhat this enables:\n";
  echo "- Manufacturers can soft-delete records (sets deleted_at timestamp)\n";
  echo "- Superadmin can still see and restore deleted records\n";
  echo "- Audit trail preserved with deleted_by field\n";
  echo "- Regular queries exclude deleted records by default\n";

} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo "\n❌ Migration failed!\n";
  echo "Error: " . $e->getMessage() . "\n";
  echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
  http_response_code(500);
}

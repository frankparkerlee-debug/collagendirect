<?php
/**
 * Migration: Add paid_at column to orders table
 *
 * This tracks when wholesale orders are paid by practices.
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== ADDING paid_at COLUMN TO ORDERS TABLE ===\n\n";

try {
  global $pdo;

  // Check if column already exists
  $stmt = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name='orders' AND column_name='paid_at'
  ");

  if ($stmt->rowCount() > 0) {
    echo "✓ Column 'paid_at' already exists in orders table.\n\n";
    exit(0);
  }

  // Add the column
  echo "Adding 'paid_at' column...\n";
  $pdo->exec("
    ALTER TABLE orders
    ADD COLUMN paid_at TIMESTAMP NULL
  ");

  echo "✓ Column added successfully.\n\n";

  // Verify
  $stmt = $pdo->query("
    SELECT column_name, data_type, is_nullable
    FROM information_schema.columns
    WHERE table_name='orders' AND column_name='paid_at'
  ");

  $col = $stmt->fetch(PDO::FETCH_ASSOC);
  echo "Column details:\n";
  echo "  Name: {$col['column_name']}\n";
  echo "  Type: {$col['data_type']}\n";
  echo "  Nullable: {$col['is_nullable']}\n\n";

  echo "=== MIGRATION COMPLETE ===\n\n";
  echo "The paid_at column is now available for tracking wholesale order payments.\n";

} catch (PDOException $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit(1);
}

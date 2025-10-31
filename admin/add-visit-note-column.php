<?php
// /admin/add-visit-note-column.php — Add visit_note column to orders table
require_once __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Adding visit_note column to orders table ===\n\n";

try {
  // Check if column already exists
  $stmt = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders' AND column_name = 'visit_note'
  ");

  if ($stmt->rowCount() > 0) {
    echo "✅ Column 'visit_note' already exists in orders table\n";
    exit;
  }

  // Add the column
  echo "Adding visit_note column (TEXT)...\n";
  $pdo->exec("ALTER TABLE orders ADD COLUMN visit_note TEXT");

  echo "✅ Column added successfully\n\n";

  echo "=== Migration complete ===\n";
  echo "\nThe visit_note column can now store AI-generated clinical documentation\n";
  echo "for pre-authorization and insurance claims.\n";

} catch (PDOException $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
  exit;
}

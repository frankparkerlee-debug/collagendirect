<?php
/**
 * Add approval_score_color column to patients table
 * Stores RED, YELLOW, or GREEN score from AI analysis
 */

require_once __DIR__ . '/../api/db.php';

echo "<pre>";
echo "Adding approval_score_color column to patients table...\n\n";

try {
  // Add approval_score_color column
  $sql = "ALTER TABLE patients ADD COLUMN IF NOT EXISTS approval_score_color VARCHAR(10) DEFAULT NULL";
  $pdo->exec($sql);
  echo "✓ Added approval_score_color column\n";

  // Add index for filtering by score
  $sql = "CREATE INDEX IF NOT EXISTS idx_approval_score_color ON patients(approval_score_color)";
  $pdo->exec($sql);
  echo "✓ Added index on approval_score_color\n";

  echo "\nMigration completed successfully!\n\n";
  echo "The approval_score_color column can now store:\n";
  echo "- 'RED' (Low chance of approval)\n";
  echo "- 'YELLOW' (Average chance of approval)\n";
  echo "- 'GREEN' (High chance of approval)\n";
  echo "- NULL (Not yet scored)\n";

} catch (PDOException $e) {
  echo "✗ Error: " . $e->getMessage() . "\n";
  exit(1);
}

echo "</pre>";

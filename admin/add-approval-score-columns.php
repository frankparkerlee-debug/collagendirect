<?php
/**
 * Migration: Add approval score columns to patients table
 * Stores AI-generated approval likelihood scores
 */

require_once __DIR__ . '/db.php';

try {
  echo "Adding approval score columns to patients table...\n";

  // Add approval_score JSONB column to store score data
  $pdo->exec("
    ALTER TABLE patients
    ADD COLUMN IF NOT EXISTS approval_score JSONB
  ");
  echo "✓ Added approval_score column\n";

  // Add timestamp for when score was generated
  $pdo->exec("
    ALTER TABLE patients
    ADD COLUMN IF NOT EXISTS approval_score_at TIMESTAMP
  ");
  echo "✓ Added approval_score_at column\n";

  // Add index for faster queries
  $pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_patients_approval_score_at
    ON patients(approval_score_at DESC)
  ");
  echo "✓ Added index on approval_score_at\n";

  echo "\nMigration completed successfully!\n";
  echo "\nYou can now access this at: https://collagendirect.health/admin/add-approval-score-columns.php\n";

} catch (PDOException $e) {
  echo "ERROR: " . $e->getMessage() . "\n";
  exit(1);
}

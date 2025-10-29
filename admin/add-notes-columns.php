<?php
/**
 * Migration: Add notes_path and notes_mime columns to patients table
 *
 * Run this via: https://collagendirect.health/admin/add-notes-columns.php
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

echo "<pre>";
echo "=== Adding Clinical Notes Columns to Patients Table ===\n\n";

try {
  // Check if columns already exist
  $checkNotesPath = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'patients' AND column_name = 'notes_path'
  ")->fetchColumn();

  $checkNotesMime = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'patients' AND column_name = 'notes_mime'
  ")->fetchColumn();

  if ($checkNotesPath && $checkNotesMime) {
    echo "✓ Columns already exist - no changes needed\n";
  } else {
    // Add notes_path column
    if (!$checkNotesPath) {
      $pdo->exec("
        ALTER TABLE patients
        ADD COLUMN notes_path VARCHAR(255) DEFAULT NULL
      ");
      echo "✓ Added notes_path column\n";

      $pdo->exec("
        COMMENT ON COLUMN patients.notes_path IS 'Path to clinical notes file for pre-authorization'
      ");
    } else {
      echo "✓ notes_path column already exists\n";
    }

    // Add notes_mime column
    if (!$checkNotesMime) {
      $pdo->exec("
        ALTER TABLE patients
        ADD COLUMN notes_mime VARCHAR(100) DEFAULT NULL
      ");
      echo "✓ Added notes_mime column\n";

      $pdo->exec("
        COMMENT ON COLUMN patients.notes_mime IS 'MIME type of clinical notes file'
      ");
    } else {
      echo "✓ notes_mime column already exists\n";
    }
  }

  echo "\n✓ Migration completed successfully!\n";
  echo "\nClinical notes can now be uploaded from:\n";
  echo "- Patient Add Form: https://collagendirect.health/portal/?page=patient-add\n";
  echo "- Patient Edit Page: https://collagendirect.health/portal/?page=patient-edit&id=PATIENT_ID\n";

} catch (Throwable $e) {
  echo "✗ Error: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}

echo "</pre>";

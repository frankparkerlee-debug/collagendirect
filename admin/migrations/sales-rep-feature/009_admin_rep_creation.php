<?php
/**
 * Migration: Admin-Initiated Rep Creation (Phase 9)
 *
 * Adds columns for invite flow and direct add flow:
 * - sales_reps: invite_token, invite_token_expires_at, invited_by, 'invited' status
 * - rep_signed_documents: source, uploaded_by, document_file_path
 */

declare(strict_types=1);

// Use existing $pdo if available (from web runner), otherwise load CLI db
if (!isset($pdo)) {
  require_once __DIR__ . '/db_cli.php';
}

echo "=== Sales Rep Feature: Admin-Initiated Rep Creation (Phase 9) ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Add 'invited' to sales_reps status enum
  echo "1. Updating sales_reps status constraint...\n";

  // Drop existing constraint
  try {
    $pdo->exec("ALTER TABLE sales_reps DROP CONSTRAINT IF EXISTS sales_reps_status_check");
  } catch (PDOException $e) {
    // Constraint might not exist or have different name
    echo "   - Note: " . $e->getMessage() . "\n";
  }

  // Add updated constraint with 'invited' status
  $pdo->exec("
    ALTER TABLE sales_reps
    ADD CONSTRAINT sales_reps_status_check
    CHECK (status IN ('pending', 'invited', 'active', 'suspended', 'terminated'))
  ");
  echo "   ✓ Added 'invited' status to enum\n";

  // 2. Add invite columns to sales_reps
  echo "2. Adding invite columns to sales_reps...\n";

  // invite_token
  $checkCol = $pdo->query("
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_name='sales_reps' AND column_name='invite_token'
  ")->fetchColumn();
  if ($checkCol == 0) {
    $pdo->exec("ALTER TABLE sales_reps ADD COLUMN invite_token VARCHAR(64) UNIQUE");
    echo "   ✓ Added invite_token column\n";
  } else {
    echo "   - invite_token already exists\n";
  }

  // invite_token_expires_at
  $checkCol = $pdo->query("
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_name='sales_reps' AND column_name='invite_token_expires_at'
  ")->fetchColumn();
  if ($checkCol == 0) {
    $pdo->exec("ALTER TABLE sales_reps ADD COLUMN invite_token_expires_at TIMESTAMP WITH TIME ZONE");
    echo "   ✓ Added invite_token_expires_at column\n";
  } else {
    echo "   - invite_token_expires_at already exists\n";
  }

  // invited_by
  $checkCol = $pdo->query("
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_name='sales_reps' AND column_name='invited_by'
  ")->fetchColumn();
  if ($checkCol == 0) {
    $pdo->exec("ALTER TABLE sales_reps ADD COLUMN invited_by VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL");
    echo "   ✓ Added invited_by column\n";
  } else {
    echo "   - invited_by already exists\n";
  }

  // 3. Add source tracking columns to rep_signed_documents
  echo "3. Adding source tracking to rep_signed_documents...\n";

  // source
  $checkCol = $pdo->query("
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_name='rep_signed_documents' AND column_name='source'
  ")->fetchColumn();
  if ($checkCol == 0) {
    $pdo->exec("
      ALTER TABLE rep_signed_documents
      ADD COLUMN source VARCHAR(30) DEFAULT 'self_service'
      CHECK (source IN ('self_service', 'invite_completion', 'offline_upload', 'offline_attestation'))
    ");
    echo "   ✓ Added source column\n";
  } else {
    echo "   - source already exists\n";
  }

  // uploaded_by
  $checkCol = $pdo->query("
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_name='rep_signed_documents' AND column_name='uploaded_by'
  ")->fetchColumn();
  if ($checkCol == 0) {
    $pdo->exec("ALTER TABLE rep_signed_documents ADD COLUMN uploaded_by VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL");
    echo "   ✓ Added uploaded_by column\n";
  } else {
    echo "   - uploaded_by already exists\n";
  }

  // document_file_path
  $checkCol = $pdo->query("
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_name='rep_signed_documents' AND column_name='document_file_path'
  ")->fetchColumn();
  if ($checkCol == 0) {
    $pdo->exec("ALTER TABLE rep_signed_documents ADD COLUMN document_file_path VARCHAR(500)");
    echo "   ✓ Added document_file_path column\n";
  } else {
    echo "   - document_file_path already exists\n";
  }

  // 4. Create index on invite_token for fast lookups
  echo "4. Creating indexes...\n";
  $indexExists = $pdo->query("
    SELECT COUNT(*) FROM pg_indexes
    WHERE tablename = 'sales_reps' AND indexname = 'idx_sales_reps_invite_token'
  ")->fetchColumn();
  if ($indexExists == 0) {
    $pdo->exec("CREATE INDEX idx_sales_reps_invite_token ON sales_reps(invite_token) WHERE invite_token IS NOT NULL");
    echo "   ✓ Created invite_token index\n";
  } else {
    echo "   - Index already exists\n";
  }

  // 5. Add comments
  echo "5. Adding column comments...\n";
  $pdo->exec("COMMENT ON COLUMN sales_reps.invite_token IS 'Secure token for invite completion flow'");
  $pdo->exec("COMMENT ON COLUMN sales_reps.invite_token_expires_at IS 'Expiration time for invite token (7 days default)'");
  $pdo->exec("COMMENT ON COLUMN sales_reps.invited_by IS 'Admin user who sent the invite'");
  $pdo->exec("COMMENT ON COLUMN rep_signed_documents.source IS 'How document was signed: self_service, invite_completion, offline_upload, offline_attestation'");
  $pdo->exec("COMMENT ON COLUMN rep_signed_documents.uploaded_by IS 'Admin who uploaded offline document'");
  $pdo->exec("COMMENT ON COLUMN rep_signed_documents.document_file_path IS 'Path to uploaded document file'");
  echo "   ✓ Added column comments\n";

  $pdo->commit();

  echo "\n✓ Migration completed successfully!\n";
  echo "\nNew sales_reps columns: invite_token, invite_token_expires_at, invited_by\n";
  echo "New rep_signed_documents columns: source, uploaded_by, document_file_path\n";
  echo "New status value: 'invited'\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  throw $e;
}

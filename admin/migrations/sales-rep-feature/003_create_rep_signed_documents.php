<?php
/**
 * Migration: Create rep_signed_documents table
 *
 * Stores e-signature records for sales rep agreements.
 * Captures all compliance-required data: signature, timestamp,
 * IP address, user agent, and document version.
 */

declare(strict_types=1);

// Use existing $pdo if available (from web runner), otherwise load CLI db
if (!isset($pdo)) {
  require_once __DIR__ . '/db_cli.php';
}

echo "=== Sales Rep Feature: Create rep_signed_documents Table ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Check if table already exists
  $checkStmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM information_schema.tables
    WHERE table_name = 'rep_signed_documents'
  ");
  $exists = (int)$checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

  if ($exists) {
    echo "✓ Table rep_signed_documents already exists, skipping creation.\n";
    $pdo->commit();
    return;
  }

  // 2. Create rep_signed_documents table
  echo "1. Creating rep_signed_documents table...\n";
  $pdo->exec("
    CREATE TABLE rep_signed_documents (
      id SERIAL PRIMARY KEY,
      rep_id VARCHAR(64) NOT NULL REFERENCES sales_reps(id) ON DELETE CASCADE,
      document_type VARCHAR(50) NOT NULL
        CHECK (document_type IN ('rep_agreement', 'baa', 'nda', 'w9', 'other')),
      document_version VARCHAR(100) NOT NULL,
      signed_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
      ip_address VARCHAR(45),
      user_agent TEXT,
      signature_text VARCHAR(255) NOT NULL,
      signature_title VARCHAR(100),
      document_content TEXT,
      document_path VARCHAR(500),
      created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT unique_rep_document_version UNIQUE(rep_id, document_type, document_version)
    )
  ");
  echo "   ✓ Created rep_signed_documents table\n";

  // 3. Create indexes
  echo "2. Creating indexes...\n";
  $pdo->exec("CREATE INDEX idx_rep_signed_documents_rep_id ON rep_signed_documents(rep_id)");
  $pdo->exec("CREATE INDEX idx_rep_signed_documents_type ON rep_signed_documents(document_type)");
  $pdo->exec("CREATE INDEX idx_rep_signed_documents_signed_at ON rep_signed_documents(signed_at)");
  echo "   ✓ Created indexes\n";

  // 4. Add comments
  echo "3. Adding column comments...\n";
  $pdo->exec("COMMENT ON COLUMN rep_signed_documents.document_type IS 'Type of document: rep_agreement, baa (HIPAA), nda, w9, other'");
  $pdo->exec("COMMENT ON COLUMN rep_signed_documents.document_version IS 'Version identifier or hash of the document signed'");
  $pdo->exec("COMMENT ON COLUMN rep_signed_documents.signature_text IS 'Typed signature (full legal name)'");
  $pdo->exec("COMMENT ON COLUMN rep_signed_documents.ip_address IS 'IPv4 or IPv6 address at time of signing'");
  echo "   ✓ Added column comments\n";

  $pdo->commit();

  echo "\n✓ Migration completed successfully!\n";
  echo "\nTable: rep_signed_documents\n";
  echo "Purpose: E-signature records for compliance\n";
  echo "Document types: rep_agreement, baa, nda, w9, other\n";
  echo "Note: Stores all non-repudiation data for legal compliance\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  throw $e; // Re-throw for web runner to catch
}

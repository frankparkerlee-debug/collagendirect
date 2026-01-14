<?php
/**
 * Migration: Create sample_package_requests table
 *
 * This migration creates the table for tracking physician sample package requests.
 * Physicians can request sample packages through a public form, and admin staff
 * can review, approve, and ship them.
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== Sample Package Requests Migration ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Create sample_package_requests table
  echo "1. Creating sample_package_requests table...\n";

  $tableExists = $pdo->query("
    SELECT EXISTS (
      SELECT FROM information_schema.tables
      WHERE table_name = 'sample_package_requests'
    )
  ")->fetchColumn();

  if (!$tableExists) {
    $pdo->exec("
      CREATE TABLE sample_package_requests (
        id VARCHAR(64) PRIMARY KEY,

        -- Physician info
        first_name VARCHAR(120) NOT NULL,
        last_name VARCHAR(120) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(30),

        -- Practice info
        practice_name VARCHAR(255),
        specialty VARCHAR(120),
        npi VARCHAR(20),

        -- Shipping address
        ship_address VARCHAR(255),
        ship_city VARCHAR(120),
        ship_state VARCHAR(2),
        ship_zip VARCHAR(10),

        -- Request details
        notes TEXT,
        how_heard VARCHAR(120),

        -- Status tracking
        status VARCHAR(20) DEFAULT 'pending',
        reviewed_by INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
        reviewed_at TIMESTAMP,
        review_notes TEXT,
        shipped_at TIMESTAMP,
        tracking_number VARCHAR(100),

        -- Metadata
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    ");
    echo "   - Created sample_package_requests table\n";

    // Create indexes
    $pdo->exec("CREATE INDEX idx_sample_requests_email ON sample_package_requests(email)");
    $pdo->exec("CREATE INDEX idx_sample_requests_status ON sample_package_requests(status)");
    $pdo->exec("CREATE INDEX idx_sample_requests_created ON sample_package_requests(created_at)");
    echo "   - Created indexes\n";
  } else {
    echo "   - Table already exists\n";
  }

  // 2. Add status check constraint
  echo "\n2. Adding status check constraint...\n";
  try {
    $pdo->exec("
      ALTER TABLE sample_package_requests
      ADD CONSTRAINT sample_requests_status_check
      CHECK (status IN ('pending', 'approved', 'shipped', 'rejected'))
    ");
    echo "   - Added status constraint\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
      echo "   - Constraint already exists\n";
    } else {
      throw $e;
    }
  }

  $pdo->commit();

  echo "\n=== Migration completed successfully! ===\n\n";
  echo "The sample_package_requests table is now available.\n";
  echo "Status workflow: pending -> approved -> shipped (or rejected)\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n- Migration failed: " . $e->getMessage() . "\n";
  http_response_code(500);
  exit(1);
}

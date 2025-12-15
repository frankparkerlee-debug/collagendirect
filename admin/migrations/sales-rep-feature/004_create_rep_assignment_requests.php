<?php
/**
 * Migration: Create rep_assignment_requests table
 *
 * Tracks requests from sales reps to be assigned to clinics/practices.
 * Includes approval workflow with admin review.
 */

declare(strict_types=1);

// Use existing $pdo if available (from web runner), otherwise load CLI db
if (!isset($pdo)) {
  require_once __DIR__ . '/db_cli.php';
}

echo "=== Sales Rep Feature: Create rep_assignment_requests Table ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Check if table already exists
  $checkStmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM information_schema.tables
    WHERE table_name = 'rep_assignment_requests'
  ");
  $exists = (int)$checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

  if ($exists) {
    echo "✓ Table rep_assignment_requests already exists, skipping creation.\n";
    $pdo->commit();
    return;
  }

  // 2. Create rep_assignment_requests table
  echo "1. Creating rep_assignment_requests table...\n";
  $pdo->exec("
    CREATE TABLE rep_assignment_requests (
      id SERIAL PRIMARY KEY,
      rep_id VARCHAR(64) NOT NULL REFERENCES sales_reps(id) ON DELETE CASCADE,
      clinic_id VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'approved', 'denied')),
      rep_note TEXT,
      request_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
      reviewed_by VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL,
      reviewed_date TIMESTAMP WITH TIME ZONE,
      denial_reason TEXT,
      created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT unique_pending_request UNIQUE(rep_id, clinic_id, status)
    )
  ");
  echo "   ✓ Created rep_assignment_requests table\n";

  // 3. Create indexes
  echo "2. Creating indexes...\n";
  $pdo->exec("CREATE INDEX idx_rep_assignment_requests_rep_id ON rep_assignment_requests(rep_id)");
  $pdo->exec("CREATE INDEX idx_rep_assignment_requests_clinic_id ON rep_assignment_requests(clinic_id)");
  $pdo->exec("CREATE INDEX idx_rep_assignment_requests_status ON rep_assignment_requests(status)");
  $pdo->exec("CREATE INDEX idx_rep_assignment_requests_pending ON rep_assignment_requests(status) WHERE status = 'pending'");
  echo "   ✓ Created indexes\n";

  // 4. Create trigger for updated_at
  echo "3. Creating updated_at trigger...\n";
  $pdo->exec("
    CREATE OR REPLACE FUNCTION update_rep_assignment_requests_updated_at()
    RETURNS TRIGGER AS \$\$
    BEGIN
      NEW.updated_at = CURRENT_TIMESTAMP;
      RETURN NEW;
    END;
    \$\$ LANGUAGE plpgsql
  ");

  $pdo->exec("
    DROP TRIGGER IF EXISTS trigger_rep_assignment_requests_updated_at ON rep_assignment_requests
  ");

  $pdo->exec("
    CREATE TRIGGER trigger_rep_assignment_requests_updated_at
    BEFORE UPDATE ON rep_assignment_requests
    FOR EACH ROW
    EXECUTE FUNCTION update_rep_assignment_requests_updated_at()
  ");
  echo "   ✓ Created updated_at trigger\n";

  // 5. Add comments
  echo "4. Adding column comments...\n";
  $pdo->exec("COMMENT ON TABLE rep_assignment_requests IS 'Tracks sales rep requests to be assigned to clinics/practices'");
  $pdo->exec("COMMENT ON COLUMN rep_assignment_requests.clinic_id IS 'FK to users table - the practice/clinic being requested'");
  $pdo->exec("COMMENT ON COLUMN rep_assignment_requests.rep_note IS 'Note from rep explaining why they should be assigned'");
  echo "   ✓ Added column comments\n";

  $pdo->commit();

  echo "\n✓ Migration completed successfully!\n";
  echo "\nTable: rep_assignment_requests\n";
  echo "Purpose: Workflow for reps requesting clinic assignments\n";
  echo "Statuses: pending, approved, denied\n";
  echo "Note: clinic_id references users table (practices are users with role practice_admin)\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  throw $e; // Re-throw for web runner to catch
}

<?php
/**
 * Migration: Create sales_reps table
 *
 * Sales rep profile extending the users table with rep-specific data.
 * Sales reps are onboarded through the admin portal with their own
 * application and approval workflow.
 */

declare(strict_types=1);

// Use existing $pdo if available (from web runner), otherwise load CLI db
if (!isset($pdo)) {
  require_once __DIR__ . '/db_cli.php';
}

echo "=== Sales Rep Feature: Create sales_reps Table ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Check if table already exists
  $checkStmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM information_schema.tables
    WHERE table_name = 'sales_reps'
  ");
  $exists = (int)$checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

  if ($exists) {
    echo "✓ Table sales_reps already exists, skipping creation.\n";
    $pdo->commit();
    return;
  }

  // 2. Create sales_reps table
  echo "1. Creating sales_reps table...\n";
  $pdo->exec("
    CREATE TABLE sales_reps (
      id VARCHAR(64) PRIMARY KEY DEFAULT gen_random_uuid()::text,
      user_id VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      company_name VARCHAR(255),
      status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'active', 'suspended', 'terminated')),
      application_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
      approved_date TIMESTAMP WITH TIME ZONE,
      approved_by VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL,
      how_heard_about_us TEXT,
      notes TEXT,
      created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT unique_user_sales_rep UNIQUE(user_id)
    )
  ");
  echo "   ✓ Created sales_reps table\n";

  // 3. Create indexes
  echo "2. Creating indexes...\n";
  $pdo->exec("CREATE INDEX idx_sales_reps_user_id ON sales_reps(user_id)");
  $pdo->exec("CREATE INDEX idx_sales_reps_status ON sales_reps(status)");
  $pdo->exec("CREATE INDEX idx_sales_reps_approved_by ON sales_reps(approved_by)");
  $pdo->exec("CREATE INDEX idx_sales_reps_application_date ON sales_reps(application_date)");
  echo "   ✓ Created indexes\n";

  // 4. Create trigger for updated_at
  echo "3. Creating updated_at trigger...\n";
  $pdo->exec("
    CREATE OR REPLACE FUNCTION update_sales_reps_updated_at()
    RETURNS TRIGGER AS \$\$
    BEGIN
      NEW.updated_at = CURRENT_TIMESTAMP;
      RETURN NEW;
    END;
    \$\$ LANGUAGE plpgsql
  ");

  $pdo->exec("
    DROP TRIGGER IF EXISTS trigger_sales_reps_updated_at ON sales_reps
  ");

  $pdo->exec("
    CREATE TRIGGER trigger_sales_reps_updated_at
    BEFORE UPDATE ON sales_reps
    FOR EACH ROW
    EXECUTE FUNCTION update_sales_reps_updated_at()
  ");
  echo "   ✓ Created updated_at trigger\n";

  $pdo->commit();

  echo "\n✓ Migration completed successfully!\n";
  echo "\nTable: sales_reps\n";
  echo "Purpose: Extended profile for sales representatives\n";
  echo "FK: user_id -> users.id (each rep must have a user account)\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  throw $e; // Re-throw for web runner to catch
}

<?php
/**
 * Migration: Create rep_commission_payouts table
 *
 * Records actual payments made to sales reps for their
 * earned commissions. Links to ledger entries.
 */

declare(strict_types=1);

// Use existing $pdo if available (from web runner), otherwise load CLI db
if (!isset($pdo)) {
  require_once __DIR__ . '/db_cli.php';
}

echo "=== Sales Rep Feature: Create rep_commission_payouts Table ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Check if table already exists
  $checkStmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM information_schema.tables
    WHERE table_name = 'rep_commission_payouts'
  ");
  $exists = (int)$checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

  if ($exists) {
    echo "✓ Table rep_commission_payouts already exists, skipping creation.\n";
    $pdo->commit();
    return;
  }

  // 2. Create rep_commission_payouts table
  echo "1. Creating rep_commission_payouts table...\n";
  $pdo->exec("
    CREATE TABLE rep_commission_payouts (
      id SERIAL PRIMARY KEY,
      rep_id VARCHAR(64) NOT NULL REFERENCES sales_reps(id) ON DELETE CASCADE,
      amount DECIMAL(10,2) NOT NULL CHECK (amount > 0),
      payout_date DATE NOT NULL DEFAULT CURRENT_DATE,
      payment_method VARCHAR(20) NOT NULL
        CHECK (payment_method IN ('check', 'ach', 'wire', 'other')),
      reference_number VARCHAR(100),
      period_start DATE,
      period_end DATE,
      notes TEXT,
      processed_by VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL,
      created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT valid_payout_period CHECK (period_end IS NULL OR period_end >= period_start)
    )
  ");
  echo "   ✓ Created rep_commission_payouts table\n";

  // 3. Create indexes
  echo "2. Creating indexes...\n";
  $pdo->exec("CREATE INDEX idx_rep_commission_payouts_rep_id ON rep_commission_payouts(rep_id)");
  $pdo->exec("CREATE INDEX idx_rep_commission_payouts_payout_date ON rep_commission_payouts(payout_date)");
  $pdo->exec("CREATE INDEX idx_rep_commission_payouts_processed_by ON rep_commission_payouts(processed_by)");
  echo "   ✓ Created indexes\n";

  // 4. Add FK constraint to ledger table (after it exists)
  echo "3. Adding foreign key to rep_commission_ledger...\n";
  try {
    $pdo->exec("
      ALTER TABLE rep_commission_ledger
      ADD CONSTRAINT fk_ledger_payout
      FOREIGN KEY (payout_id) REFERENCES rep_commission_payouts(id) ON DELETE SET NULL
    ");
    echo "   ✓ Added foreign key constraint\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
      echo "   - Foreign key constraint already exists\n";
    } else {
      throw $e;
    }
  }

  // 5. Add comments
  echo "4. Adding column comments...\n";
  $pdo->exec("COMMENT ON TABLE rep_commission_payouts IS 'Records commission payments made to sales reps'");
  $pdo->exec("COMMENT ON COLUMN rep_commission_payouts.payment_method IS 'How payment was made: check, ach, wire, other'");
  $pdo->exec("COMMENT ON COLUMN rep_commission_payouts.reference_number IS 'Check number, ACH trace, wire reference, etc.'");
  $pdo->exec("COMMENT ON COLUMN rep_commission_payouts.period_start IS 'Start of commission period covered'");
  $pdo->exec("COMMENT ON COLUMN rep_commission_payouts.period_end IS 'End of commission period covered'");
  echo "   ✓ Added column comments\n";

  $pdo->commit();

  echo "\n✓ Migration completed successfully!\n";
  echo "\nTable: rep_commission_payouts\n";
  echo "Purpose: Record of payments made to sales reps\n";
  echo "Payment methods: check, ach, wire, other\n";
  echo "Note: Linked to ledger entries via payout_id\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  throw $e; // Re-throw for web runner to catch
}

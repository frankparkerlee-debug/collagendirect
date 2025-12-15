<?php
/**
 * Migration: Create rep_commission_ledger table
 *
 * Tracks earned commissions for each sales rep.
 * Created when payments are collected on orders from
 * clinics assigned to the rep.
 */

declare(strict_types=1);

// Use existing $pdo if available (from web runner), otherwise load CLI db
if (!isset($pdo)) {
  require_once __DIR__ . '/db_cli.php';
}

echo "=== Sales Rep Feature: Create rep_commission_ledger Table ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Check if table already exists
  $checkStmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM information_schema.tables
    WHERE table_name = 'rep_commission_ledger'
  ");
  $exists = (int)$checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

  if ($exists) {
    echo "✓ Table rep_commission_ledger already exists, skipping creation.\n";
    $pdo->commit();
    return;
  }

  // 2. Create rep_commission_ledger table
  echo "1. Creating rep_commission_ledger table...\n";
  $pdo->exec("
    CREATE TABLE rep_commission_ledger (
      id SERIAL PRIMARY KEY,
      rep_id VARCHAR(64) NOT NULL REFERENCES sales_reps(id) ON DELETE CASCADE,
      order_id VARCHAR(64) NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
      order_type VARCHAR(20) NOT NULL
        CHECK (order_type IN ('referral', 'wholesale')),
      payment_id INTEGER,
      clinic_id VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      payment_date DATE NOT NULL,
      collected_amount DECIMAL(10,2) NOT NULL CHECK (collected_amount >= 0),
      commission_rate DECIMAL(5,4) NOT NULL CHECK (commission_rate >= 0 AND commission_rate <= 1),
      commission_amount DECIMAL(10,2) NOT NULL CHECK (commission_amount >= 0),
      payout_id INTEGER,
      status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'paid', 'voided')),
      notes TEXT,
      created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT unique_order_commission UNIQUE(rep_id, order_id)
    )
  ");
  echo "   ✓ Created rep_commission_ledger table\n";

  // 3. Create indexes
  echo "2. Creating indexes...\n";
  $pdo->exec("CREATE INDEX idx_rep_commission_ledger_rep_id ON rep_commission_ledger(rep_id)");
  $pdo->exec("CREATE INDEX idx_rep_commission_ledger_order_id ON rep_commission_ledger(order_id)");
  $pdo->exec("CREATE INDEX idx_rep_commission_ledger_clinic_id ON rep_commission_ledger(clinic_id)");
  $pdo->exec("CREATE INDEX idx_rep_commission_ledger_payment_date ON rep_commission_ledger(payment_date)");
  $pdo->exec("CREATE INDEX idx_rep_commission_ledger_status ON rep_commission_ledger(status)");
  $pdo->exec("CREATE INDEX idx_rep_commission_ledger_payout_id ON rep_commission_ledger(payout_id)");
  $pdo->exec("CREATE INDEX idx_rep_commission_ledger_pending ON rep_commission_ledger(rep_id, status) WHERE status = 'pending'");
  echo "   ✓ Created indexes\n";

  // 4. Add comments
  echo "3. Adding column comments...\n";
  $pdo->exec("COMMENT ON TABLE rep_commission_ledger IS 'Tracks commission earnings per order for sales reps'");
  $pdo->exec("COMMENT ON COLUMN rep_commission_ledger.commission_rate IS 'Snapshot of rate at time of calculation (decimal 0-1)'");
  $pdo->exec("COMMENT ON COLUMN rep_commission_ledger.collected_amount IS 'Total amount collected from this order'");
  $pdo->exec("COMMENT ON COLUMN rep_commission_ledger.commission_amount IS 'Calculated commission: collected_amount * commission_rate'");
  $pdo->exec("COMMENT ON COLUMN rep_commission_ledger.payout_id IS 'FK to rep_commission_payouts when paid out'");
  $pdo->exec("COMMENT ON COLUMN rep_commission_ledger.status IS 'pending = not yet paid, paid = included in payout, voided = cancelled'");
  echo "   ✓ Added column comments\n";

  $pdo->commit();

  echo "\n✓ Migration completed successfully!\n";
  echo "\nTable: rep_commission_ledger\n";
  echo "Purpose: Individual commission entries per order\n";
  echo "Workflow: pending -> paid (via payout) or voided\n";
  echo "Note: commission_rate is snapshotted to preserve history\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  throw $e; // Re-throw for web runner to catch
}

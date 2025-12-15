<?php
/**
 * Migration: Create rep_commission_rates table
 *
 * Tracks commission rate history for each sales rep.
 * Rates can change over time, so we store effective dates
 * and who made the change for audit purposes.
 */

declare(strict_types=1);

// Use existing $pdo if available (from web runner), otherwise load CLI db
if (!isset($pdo)) {
  require_once __DIR__ . '/db_cli.php';
}

echo "=== Sales Rep Feature: Create rep_commission_rates Table ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Check if table already exists
  $checkStmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM information_schema.tables
    WHERE table_name = 'rep_commission_rates'
  ");
  $exists = (int)$checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;

  if ($exists) {
    echo "✓ Table rep_commission_rates already exists, skipping creation.\n";
    $pdo->commit();
    return;
  }

  // 2. Create rep_commission_rates table
  echo "1. Creating rep_commission_rates table...\n";
  $pdo->exec("
    CREATE TABLE rep_commission_rates (
      id SERIAL PRIMARY KEY,
      rep_id VARCHAR(64) NOT NULL REFERENCES sales_reps(id) ON DELETE CASCADE,
      rate DECIMAL(5,4) NOT NULL CHECK (rate >= 0 AND rate <= 1),
      effective_date DATE NOT NULL DEFAULT CURRENT_DATE,
      end_date DATE,
      created_by VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL,
      notes TEXT,
      created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT valid_date_range CHECK (end_date IS NULL OR end_date >= effective_date)
    )
  ");
  echo "   ✓ Created rep_commission_rates table\n";

  // 3. Create indexes
  echo "2. Creating indexes...\n";
  $pdo->exec("CREATE INDEX idx_rep_commission_rates_rep_id ON rep_commission_rates(rep_id)");
  $pdo->exec("CREATE INDEX idx_rep_commission_rates_effective_date ON rep_commission_rates(effective_date)");
  $pdo->exec("CREATE INDEX idx_rep_commission_rates_current ON rep_commission_rates(rep_id, effective_date) WHERE end_date IS NULL");
  echo "   ✓ Created indexes\n";

  // 4. Add comment for rate field
  echo "3. Adding column comments...\n";
  $pdo->exec("COMMENT ON COLUMN rep_commission_rates.rate IS 'Commission rate as decimal (e.g., 0.25 = 25%)'");
  echo "   ✓ Added column comments\n";

  $pdo->commit();

  echo "\n✓ Migration completed successfully!\n";
  echo "\nTable: rep_commission_rates\n";
  echo "Purpose: Commission rate history per sales rep\n";
  echo "Rate format: Decimal 0.00-1.00 (e.g., 0.25 = 25%)\n";
  echo "Note: Current rate is the one where end_date IS NULL\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  throw $e; // Re-throw for web runner to catch
}

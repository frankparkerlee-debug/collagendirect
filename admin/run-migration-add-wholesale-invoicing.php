<?php
/**
 * Migration: Add wholesale invoicing and practice balance tracking
 *
 * This migration adds:
 * 1. practice_balances table - tracks running balance per practice
 * 2. invoice fields to orders table - invoice_number, invoice_date, due_date, paid_date
 * 3. Aging calculation support
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== Wholesale Invoicing Migration ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Create practice_balances table
  echo "1. Creating practice_balances table...\n";
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS practice_balances (
      id SERIAL PRIMARY KEY,
      user_id VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
      current_balance DECIMAL(10,2) DEFAULT 0.00,
      balance_0_30_days DECIMAL(10,2) DEFAULT 0.00,
      balance_31_60_days DECIMAL(10,2) DEFAULT 0.00,
      balance_61_90_days DECIMAL(10,2) DEFAULT 0.00,
      balance_over_90_days DECIMAL(10,2) DEFAULT 0.00,
      last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      ordering_blocked BOOLEAN DEFAULT FALSE,
      blocked_reason TEXT,
      UNIQUE(user_id)
    )
  ");
  echo "   ✓ Created practice_balances table\n\n";

  // 2. Add invoice fields to orders table
  echo "2. Adding invoice fields to orders table...\n";

  $invoiceFields = [
    'invoice_number' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS invoice_number VARCHAR(50) UNIQUE",
    'invoice_date' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS invoice_date TIMESTAMP",
    'due_date' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS due_date TIMESTAMP",
    'payment_terms' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_terms VARCHAR(20) DEFAULT 'net30'",
    'amount_due' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS amount_due DECIMAL(10,2)",
    'amount_paid' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS amount_paid DECIMAL(10,2) DEFAULT 0.00",
    'balance_due' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS balance_due DECIMAL(10,2)"
  ];

  foreach ($invoiceFields as $field => $sql) {
    try {
      $pdo->exec($sql);
      echo "   ✓ Added $field column\n";
    } catch (PDOException $e) {
      if (strpos($e->getMessage(), 'already exists') === false) {
        throw $e;
      }
      echo "   - $field already exists\n";
    }
  }
  echo "\n";

  // 3. Create index on invoice_number
  echo "3. Creating indexes...\n";
  try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_invoice_number ON orders(invoice_number) WHERE invoice_number IS NOT NULL");
    echo "   ✓ Created index on invoice_number\n";
  } catch (PDOException $e) {
    echo "   - Index already exists\n";
  }

  try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_due_date ON orders(due_date) WHERE due_date IS NOT NULL");
    echo "   ✓ Created index on due_date\n";
  } catch (PDOException $e) {
    echo "   - Index already exists\n";
  }

  try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_practice_balances_user_id ON practice_balances(user_id)");
    echo "   ✓ Created index on practice_balances.user_id\n";
  } catch (PDOException $e) {
    echo "   - Index already exists\n";
  }
  echo "\n";

  // 4. Create function to calculate practice balances
  echo "4. Creating balance calculation function...\n";
  $pdo->exec("
    CREATE OR REPLACE FUNCTION update_practice_balance(practice_user_id INTEGER)
    RETURNS VOID AS $$
    DECLARE
      total_balance DECIMAL(10,2);
      bal_0_30 DECIMAL(10,2);
      bal_31_60 DECIMAL(10,2);
      bal_61_90 DECIMAL(10,2);
      bal_over_90 DECIMAL(10,2);
      should_block BOOLEAN;
    BEGIN
      -- Calculate balances by aging buckets
      SELECT
        COALESCE(SUM(CASE WHEN CURRENT_DATE - due_date::DATE <= 30 THEN balance_due ELSE 0 END), 0),
        COALESCE(SUM(CASE WHEN CURRENT_DATE - due_date::DATE BETWEEN 31 AND 60 THEN balance_due ELSE 0 END), 0),
        COALESCE(SUM(CASE WHEN CURRENT_DATE - due_date::DATE BETWEEN 61 AND 90 THEN balance_due ELSE 0 END), 0),
        COALESCE(SUM(CASE WHEN CURRENT_DATE - due_date::DATE > 90 THEN balance_due ELSE 0 END), 0)
      INTO bal_0_30, bal_31_60, bal_61_90, bal_over_90
      FROM orders
      WHERE user_id = practice_user_id
        AND billed_by = 'practice_dme'
        AND balance_due > 0
        AND due_date IS NOT NULL;

      total_balance := bal_0_30 + bal_31_60 + bal_61_90 + bal_over_90;
      should_block := bal_over_90 > 0;

      -- Insert or update practice balance
      INSERT INTO practice_balances (
        user_id,
        current_balance,
        balance_0_30_days,
        balance_31_60_days,
        balance_61_90_days,
        balance_over_90_days,
        ordering_blocked,
        blocked_reason,
        last_updated
      ) VALUES (
        practice_user_id,
        total_balance,
        bal_0_30,
        bal_31_60,
        bal_61_90,
        bal_over_90,
        should_block,
        CASE WHEN should_block THEN 'Outstanding balance over 90 days past due' ELSE NULL END,
        CURRENT_TIMESTAMP
      )
      ON CONFLICT (user_id) DO UPDATE SET
        current_balance = EXCLUDED.current_balance,
        balance_0_30_days = EXCLUDED.balance_0_30_days,
        balance_31_60_days = EXCLUDED.balance_31_60_days,
        balance_61_90_days = EXCLUDED.balance_61_90_days,
        balance_over_90_days = EXCLUDED.balance_over_90_days,
        ordering_blocked = EXCLUDED.ordering_blocked,
        blocked_reason = EXCLUDED.blocked_reason,
        last_updated = CURRENT_TIMESTAMP;
    END;
    $$ LANGUAGE plpgsql;
  ");
  echo "   ✓ Created update_practice_balance() function\n\n";

  // 5. Create trigger to auto-update balances when orders change
  echo "5. Creating trigger for automatic balance updates...\n";
  $pdo->exec("
    CREATE OR REPLACE FUNCTION trigger_update_practice_balance()
    RETURNS TRIGGER AS $$
    BEGIN
      IF TG_OP = 'DELETE' THEN
        PERFORM update_practice_balance(OLD.user_id);
        RETURN OLD;
      ELSE
        PERFORM update_practice_balance(NEW.user_id);
        RETURN NEW;
      END IF;
    END;
    $$ LANGUAGE plpgsql;
  ");

  $pdo->exec("
    DROP TRIGGER IF EXISTS trg_orders_balance_update ON orders;
    CREATE TRIGGER trg_orders_balance_update
    AFTER INSERT OR UPDATE OF balance_due, amount_paid, due_date OR DELETE ON orders
    FOR EACH ROW
    WHEN (OLD.billed_by = 'practice_dme' OR NEW.billed_by = 'practice_dme')
    EXECUTE FUNCTION trigger_update_practice_balance();
  ");
  echo "   ✓ Created trigger for automatic balance updates\n\n";

  $pdo->commit();
  echo "✓ Migration completed successfully!\n\n";
  echo "Next steps:\n";
  echo "- Wholesale orders will now generate invoices with 30-day payment terms\n";
  echo "- Practice balances will be tracked automatically\n";
  echo "- Practices with balances over 90 days past due will be blocked from ordering\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  http_response_code(500);
  exit(1);
}

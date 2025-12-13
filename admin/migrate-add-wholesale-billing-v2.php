<?php
/**
 * Migration: Add wholesale billing infrastructure v2
 *
 * Adds:
 * 1. wholesale_payments table - audit trail for all payments
 * 2. Practice-level billing settings in users table
 * 3. Invoice lifecycle columns in orders table
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Wholesale Billing Migration v2 ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Create wholesale_payments table for payment history/audit trail
  echo "1. Creating wholesale_payments table...\n";

  // Check if table exists
  $tableCheck = $pdo->query("
    SELECT COUNT(*) FROM information_schema.tables
    WHERE table_name = 'wholesale_payments'
  ")->fetchColumn();

  if ($tableCheck == 0) {
    $pdo->exec("
      CREATE TABLE wholesale_payments (
        id SERIAL PRIMARY KEY,
        order_id VARCHAR(64) REFERENCES orders(id) ON DELETE SET NULL,
        order_number VARCHAR(50),
        user_id VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50),
        reference_number VARCHAR(100),
        payment_date DATE NOT NULL,
        notes TEXT,
        recorded_by INTEGER REFERENCES admin_users(id) ON DELETE SET NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    ");
    echo "   ✓ Created wholesale_payments table\n";

    // Create indexes
    $pdo->exec("CREATE INDEX idx_ws_payments_order ON wholesale_payments(order_id)");
    $pdo->exec("CREATE INDEX idx_ws_payments_order_number ON wholesale_payments(order_number)");
    $pdo->exec("CREATE INDEX idx_ws_payments_user ON wholesale_payments(user_id)");
    $pdo->exec("CREATE INDEX idx_ws_payments_date ON wholesale_payments(payment_date)");
    echo "   ✓ Created indexes\n";
  } else {
    echo "   - wholesale_payments table already exists\n";
  }
  echo "\n";

  // 2. Add practice-level billing settings to users table
  echo "2. Adding practice billing settings to users table...\n";

  $userColumns = [
    'default_payment_terms' => "ALTER TABLE users ADD COLUMN IF NOT EXISTS default_payment_terms VARCHAR(20) DEFAULT 'net30'",
    'credit_limit' => "ALTER TABLE users ADD COLUMN IF NOT EXISTS credit_limit DECIMAL(10,2) DEFAULT NULL",
    'collection_flag' => "ALTER TABLE users ADD COLUMN IF NOT EXISTS collection_flag BOOLEAN DEFAULT FALSE",
    'billing_notes' => "ALTER TABLE users ADD COLUMN IF NOT EXISTS billing_notes TEXT",
    'billing_contact_name' => "ALTER TABLE users ADD COLUMN IF NOT EXISTS billing_contact_name VARCHAR(255)",
    'billing_contact_email' => "ALTER TABLE users ADD COLUMN IF NOT EXISTS billing_contact_email VARCHAR(255)",
    'billing_contact_phone' => "ALTER TABLE users ADD COLUMN IF NOT EXISTS billing_contact_phone VARCHAR(50)"
  ];

  foreach ($userColumns as $col => $sql) {
    try {
      $pdo->exec($sql);
      echo "   ✓ Added $col column\n";
    } catch (PDOException $e) {
      if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'duplicate column') !== false) {
        echo "   - $col already exists\n";
      } else {
        throw $e;
      }
    }
  }
  echo "\n";

  // 3. Add invoice lifecycle columns to orders table
  echo "3. Adding invoice lifecycle columns to orders table...\n";

  $orderColumns = [
    'invoice_status' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS invoice_status VARCHAR(30) DEFAULT 'pending'",
    'voided_at' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS voided_at TIMESTAMP",
    'voided_by' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS voided_by INTEGER",
    'void_reason' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS void_reason TEXT",
    'statement_sent_at' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS statement_sent_at TIMESTAMP",
    'collection_flag' => "ALTER TABLE orders ADD COLUMN IF NOT EXISTS collection_flag BOOLEAN DEFAULT FALSE"
  ];

  foreach ($orderColumns as $col => $sql) {
    try {
      $pdo->exec($sql);
      echo "   ✓ Added $col column\n";
    } catch (PDOException $e) {
      if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'duplicate column') !== false) {
        echo "   - $col already exists\n";
      } else {
        throw $e;
      }
    }
  }

  // Create index on invoice_status
  try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_invoice_status ON orders(invoice_status) WHERE billed_by = 'practice_dme'");
    echo "   ✓ Created index on invoice_status\n";
  } catch (PDOException $e) {
    echo "   - Index may already exist\n";
  }
  echo "\n";

  // 4. Set default payment terms for existing wholesale practices
  echo "4. Setting default payment terms for existing wholesale practices...\n";

  $updated = $pdo->exec("
    UPDATE users
    SET default_payment_terms = 'net30'
    WHERE default_payment_terms IS NULL
      AND account_type IN ('wholesale', 'dme_wholesale')
  ");
  echo "   ✓ Updated $updated practice(s) with default net30 terms\n\n";

  // 5. Update existing wholesale orders with invoice_status based on current state
  echo "5. Updating invoice_status for existing wholesale orders...\n";

  // Mark fully paid orders
  $paidCount = $pdo->exec("
    UPDATE orders
    SET invoice_status = 'paid'
    WHERE billed_by = 'practice_dme'
      AND paid_at IS NOT NULL
      AND (invoice_status IS NULL OR invoice_status = 'pending')
  ");
  echo "   ✓ Marked $paidCount order(s) as paid\n";

  // Mark partial payments
  $partialCount = $pdo->exec("
    UPDATE orders
    SET invoice_status = 'partial'
    WHERE billed_by = 'practice_dme'
      AND amount_paid > 0
      AND balance_due > 0
      AND paid_at IS NULL
      AND (invoice_status IS NULL OR invoice_status = 'pending')
  ");
  echo "   ✓ Marked $partialCount order(s) as partial\n";

  // Mark overdue orders (past due_date with balance)
  $overdueCount = $pdo->exec("
    UPDATE orders
    SET invoice_status = 'overdue'
    WHERE billed_by = 'practice_dme'
      AND due_date < CURRENT_DATE
      AND balance_due > 0
      AND paid_at IS NULL
      AND (invoice_status IS NULL OR invoice_status = 'pending')
  ");
  echo "   ✓ Marked $overdueCount order(s) as overdue\n";

  // Set invoiced status for shipped/delivered orders with balance
  $invoicedCount = $pdo->exec("
    UPDATE orders
    SET invoice_status = 'invoiced'
    WHERE billed_by = 'practice_dme'
      AND status IN ('in_transit', 'delivered', 'shipped')
      AND balance_due > 0
      AND paid_at IS NULL
      AND invoice_status = 'pending'
  ");
  echo "   ✓ Marked $invoicedCount order(s) as invoiced\n\n";

  $pdo->commit();

  echo "=== Migration Complete ===\n\n";
  echo "New capabilities:\n";
  echo "- wholesale_payments table tracks all payment history with audit trail\n";
  echo "- Practice-level payment terms (net15, net30, net45, net60)\n";
  echo "- Credit limits and collection flags per practice\n";
  echo "- Billing contact info per practice\n";
  echo "- Invoice lifecycle tracking (pending, invoiced, partial, paid, overdue, void)\n";
  echo "\nNext: Run the admin panel to configure practice payment terms.\n";

} catch (PDOException $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  http_response_code(500);
  exit(1);
}

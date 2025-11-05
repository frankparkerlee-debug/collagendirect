<?php
/**
 * Web-accessible migration runner for billing routing system
 *
 * SECURITY: This file should be deleted after migration is complete
 * or protected with additional authentication
 */

// Require authentication - only superadmin can run migrations
session_start();
require_once __DIR__ . '/../api/db.php';

// Check if user is authenticated and is superadmin
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  die("Unauthorized: Please log in as superadmin");
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['role'] !== 'superadmin') {
  http_response_code(403);
  die("Forbidden: Only superadmin can run migrations");
}

// Set content type to plain text for better readability
header('Content-Type: text/plain; charset=utf-8');

echo "=================================================\n";
echo "  Billing Routing System Migration\n";
echo "=================================================\n\n";

try {
  $pdo->beginTransaction();

  // 1. Add billed_by column to orders
  echo "1. Adding billed_by column to orders table...\n";
  $pdo->exec("
    ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS billed_by VARCHAR(50) DEFAULT 'collagen_direct'
  ");
  echo "   ✓ Column added\n\n";

  // 2. Create billing routes table
  echo "2. Creating practice_billing_routes table...\n";
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS practice_billing_routes (
      id SERIAL PRIMARY KEY,
      user_id VARCHAR(32) NOT NULL,
      insurer_name VARCHAR(255) NOT NULL,
      billing_route VARCHAR(50) NOT NULL,
      created_at TIMESTAMP DEFAULT NOW(),
      updated_at TIMESTAMP DEFAULT NOW(),
      CONSTRAINT fk_billing_routes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      CONSTRAINT unique_user_insurer UNIQUE(user_id, insurer_name)
    )
  ");

  $pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_billing_routes_user
    ON practice_billing_routes(user_id)
  ");
  echo "   ✓ Table created\n\n";

  // 3. Create transactions table
  echo "3. Creating practice_account_transactions table...\n";
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS practice_account_transactions (
      id SERIAL PRIMARY KEY,
      user_id VARCHAR(32) NOT NULL,
      order_id VARCHAR(32),
      transaction_type VARCHAR(50) NOT NULL,
      amount DECIMAL(10,2) NOT NULL,
      balance_after DECIMAL(10,2) NOT NULL,
      description TEXT,
      created_at TIMESTAMP DEFAULT NOW(),
      created_by VARCHAR(32),
      CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
      CONSTRAINT fk_transactions_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
    )
  ");

  $pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_practice_transactions_user
    ON practice_account_transactions(user_id)
  ");

  $pdo->exec("
    CREATE INDEX IF NOT EXISTS idx_practice_transactions_order
    ON practice_account_transactions(order_id)
  ");
  echo "   ✓ Table created\n\n";

  // 4. Add default_billing_route to users
  echo "4. Adding default_billing_route column to users...\n";
  $pdo->exec("
    ALTER TABLE users
    ADD COLUMN IF NOT EXISTS default_billing_route VARCHAR(50) DEFAULT 'collagen_direct'
  ");
  echo "   ✓ Column added\n\n";

  // 5. Backfill existing orders
  echo "5. Backfilling existing orders...\n";

  // Check if we have user_type column
  $hasUserType = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'users'
      AND column_name = 'user_type'
  ")->fetch();

  if ($hasUserType) {
    // Wholesale practices → practice_dme
    $wholesaleCount = $pdo->exec("
      UPDATE orders o
      SET billed_by = 'practice_dme'
      FROM users u
      WHERE o.user_id = u.id
        AND u.user_type = 'dme_wholesale'
        AND (o.billed_by IS NULL OR o.billed_by = 'collagen_direct')
    ");
    echo "   - Set $wholesaleCount wholesale practice orders to 'practice_dme'\n";
  }

  // Everyone else → collagen_direct (default)
  $defaultCount = $pdo->exec("
    UPDATE orders
    SET billed_by = 'collagen_direct'
    WHERE billed_by IS NULL
  ");
  echo "   - Set $defaultCount orders to 'collagen_direct' (default)\n";
  echo "   ✓ Existing orders backfilled\n\n";

  // 6. Create view for practice balances
  echo "6. Creating practice_account_balances view...\n";

  // Drop view if exists
  $pdo->exec("DROP VIEW IF EXISTS practice_account_balances");

  $pdo->exec("
    CREATE VIEW practice_account_balances AS
    SELECT
      user_id,
      SUM(amount) as current_balance,
      COUNT(*) as transaction_count,
      MAX(created_at) as last_transaction
    FROM practice_account_transactions
    GROUP BY user_id
  ");
  echo "   ✓ View created\n\n";

  $pdo->commit();

  echo "=================================================\n";
  echo "  ✅ Migration completed successfully!\n";
  echo "=================================================\n\n";

  // Show summary
  echo "Summary:\n";
  echo "--------\n";

  // Count orders by billing route
  $routeCounts = $pdo->query("
    SELECT billed_by, COUNT(*) as count
    FROM orders
    GROUP BY billed_by
    ORDER BY count DESC
  ")->fetchAll(PDO::FETCH_ASSOC);

  echo "Orders by billing route:\n";
  foreach ($routeCounts as $row) {
    $route = $row['billed_by'] ?: '(null)';
    echo "  - {$route}: {$row['count']} orders\n";
  }

  echo "\nNext steps:\n";
  echo "1. Practices can configure billing routes in Settings\n";
  echo "2. New orders will auto-route based on insurance company\n";
  echo "3. Direct bill orders skip admin review and use wholesale pricing\n";
  echo "4. Practices can export billing data for claim submission\n\n";

  echo "⚠️  IMPORTANT: Delete this file (run-billing-migration.php) after migration is complete!\n\n";

} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo "\n=================================================\n";
  echo "  ❌ Migration failed!\n";
  echo "=================================================\n\n";
  echo "Error: " . $e->getMessage() . "\n";
  echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
  http_response_code(500);
}

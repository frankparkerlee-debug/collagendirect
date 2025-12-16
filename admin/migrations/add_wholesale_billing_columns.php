<?php
/**
 * Migration: Add missing wholesale billing columns
 *
 * Adds columns that are required by billing-wholesale.php:
 * - orders.due_date
 * - users.default_payment_terms
 * - users.credit_limit
 * - users.collection_flag
 * - users.billing_notes
 * - users.billing_contact_name
 * - users.billing_contact_email
 * - users.billing_contact_phone
 */

declare(strict_types=1);

// Use existing $pdo if available (from web runner), otherwise load CLI db
if (!isset($pdo)) {
    require_once __DIR__ . '/../db.php';
}

echo "=== Migration: Add Wholesale Billing Columns ===\n\n";

try {
    $pdo->beginTransaction();

    // 1. Add due_date to orders table
    echo "1. Adding due_date column to orders table...\n";
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS due_date DATE");
        echo "   ✓ Added due_date column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'duplicate column') !== false) {
            echo "   - due_date already exists\n";
        } else {
            throw $e;
        }
    }

    // 2. Add practice billing settings to users table
    echo "\n2. Adding practice billing settings to users table...\n";

    $userColumns = [
        'default_payment_terms' => "VARCHAR(20) DEFAULT 'net30'",
        'credit_limit' => "DECIMAL(10,2) DEFAULT NULL",
        'collection_flag' => "BOOLEAN DEFAULT FALSE",
        'billing_notes' => "TEXT",
        'billing_contact_name' => "VARCHAR(255)",
        'billing_contact_email' => "VARCHAR(255)",
        'billing_contact_phone' => "VARCHAR(50)"
    ];

    foreach ($userColumns as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS {$col} {$def}");
            echo "   ✓ Added {$col} column\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'duplicate column') !== false) {
                echo "   - {$col} already exists\n";
            } else {
                throw $e;
            }
        }
    }

    // 3. Add invoice lifecycle columns to orders table
    echo "\n3. Adding invoice lifecycle columns to orders table...\n";

    $orderColumns = [
        'invoice_status' => "VARCHAR(30) DEFAULT 'pending'",
        'voided_at' => "TIMESTAMP",
        'voided_by' => "INTEGER",
        'void_reason' => "TEXT",
        'statement_sent_at' => "TIMESTAMP",
        'collection_flag' => "BOOLEAN DEFAULT FALSE"
    ];

    foreach ($orderColumns as $col => $def) {
        try {
            $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS {$col} {$def}");
            echo "   ✓ Added {$col} column\n";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'duplicate column') !== false) {
                echo "   - {$col} already exists\n";
            } else {
                throw $e;
            }
        }
    }

    // 4. Create wholesale_payments table if not exists
    echo "\n4. Creating wholesale_payments table...\n";
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
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_payments_order ON wholesale_payments(order_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_payments_order_number ON wholesale_payments(order_number)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_payments_user ON wholesale_payments(user_id)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ws_payments_date ON wholesale_payments(payment_date)");
        echo "   ✓ Created indexes\n";
    } else {
        echo "   - wholesale_payments table already exists\n";
    }

    // 5. Set default values for wholesale orders
    echo "\n5. Setting default values for wholesale orders...\n";

    // Set default payment terms for wholesale practices
    $updated = $pdo->exec("
        UPDATE users
        SET default_payment_terms = 'net30'
        WHERE default_payment_terms IS NULL
          AND account_type IN ('wholesale', 'dme_wholesale')
    ");
    echo "   ✓ Set default payment terms for {$updated} practice(s)\n";

    // Set due_date for wholesale orders that don't have one
    $dueDateUpdated = $pdo->exec("
        UPDATE orders
        SET due_date = DATE(created_at) + INTERVAL '30 days'
        WHERE billed_by = 'practice_dme'
          AND due_date IS NULL
    ");
    echo "   ✓ Set due_date for {$dueDateUpdated} order(s)\n";

    // Create index on invoice_status
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_invoice_status ON orders(invoice_status) WHERE billed_by = 'practice_dme'");
        echo "   ✓ Created index on invoice_status\n";
    } catch (PDOException $e) {
        echo "   - Index may already exist\n";
    }

    $pdo->commit();

    echo "\n✓ Migration completed successfully!\n";
    echo "\nThe billing-wholesale.php page should now work correctly.\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    throw $e;
}

<?php
/**
 * Migration: Add per-product commission table
 * Allows assigning fixed dollar commission amounts per product per rep
 * Run once via admin panel.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$admin = current_admin();
if (!$admin || ($admin['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    die('Superadmin access required');
}

header('Content-Type: text/html; charset=utf-8');

echo '<h2>Add Per-Product Commission Table</h2>';

// Create the table
$createTable = "
CREATE TABLE IF NOT EXISTS rep_product_commissions (
    id SERIAL PRIMARY KEY,
    rep_id VARCHAR(64) NOT NULL,
    product_id INT NOT NULL,
    commission_amount DECIMAL(10,2) NOT NULL,
    effective_date DATE DEFAULT CURRENT_DATE,
    end_date DATE NULL,
    set_by VARCHAR(64),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (rep_id, product_id, effective_date)
)
";

try {
    $pdo->exec($createTable);
    echo '<p>Table <code>rep_product_commissions</code> created (or already exists).</p>';
} catch (PDOException $e) {
    echo '<p style="color:red">Error creating table: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

// Add commission_type to rep_commission_rates if not exists
try {
    $check = $pdo->prepare("
        SELECT column_name FROM information_schema.columns
        WHERE table_name = 'rep_commission_rates' AND column_name = 'commission_type'
    ");
    $check->execute();
    if (!$check->fetch()) {
        $pdo->exec("ALTER TABLE rep_commission_rates ADD COLUMN commission_type VARCHAR(20) DEFAULT 'percentage'");
        echo '<p>Added <code>commission_type</code> column to <code>rep_commission_rates</code>.</p>';
    } else {
        echo '<p>Column <code>commission_type</code> already exists in <code>rep_commission_rates</code>.</p>';
    }
} catch (PDOException $e) {
    echo '<p style="color:red">Error adding commission_type column: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

// Add commission_type to rep_commission_ledger to track which method was used
try {
    $check = $pdo->prepare("
        SELECT column_name FROM information_schema.columns
        WHERE table_name = 'rep_commission_ledger' AND column_name = 'commission_type'
    ");
    $check->execute();
    if (!$check->fetch()) {
        $pdo->exec("ALTER TABLE rep_commission_ledger ADD COLUMN commission_type VARCHAR(20) DEFAULT 'percentage'");
        echo '<p>Added <code>commission_type</code> column to <code>rep_commission_ledger</code>.</p>';
    } else {
        echo '<p>Column <code>commission_type</code> already exists in <code>rep_commission_ledger</code>.</p>';
    }
} catch (PDOException $e) {
    echo '<p style="color:red">Error adding commission_type to ledger: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '<h3>Done</h3>';
echo '<p>The per-product commission system is ready. Use the Sales Rep Detail page to assign per-product dollar amounts.</p>';
echo '<p><a href="/admin/platform/distributors.php">Back to Distributors</a></p>';

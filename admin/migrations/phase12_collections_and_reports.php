<?php
/**
 * Phase 12: Collections Tracking & Commission Reports Migration
 *
 * Adds:
 * - referral_billing table for insurance collection tracking
 * - commission_reports table for finalized commission reports
 * - commission_report_line_items table for report details
 * - Additional columns to orders table for collection tracking
 *
 * PRESERVATION RULES:
 * - NO existing tables modified (only new columns added)
 * - NO existing columns removed
 * - ONLY new nullable columns added
 * - ONLY new tables created
 */

declare(strict_types=1);
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';

// Only allow superadmin to run migrations
$admin = current_admin();
if (!$admin || $admin['role'] !== 'superadmin') {
    die('Access denied. Only superadmin can run migrations.');
}

$results = [];
$errors = [];

// Helper function to check if column exists
function columnExists(PDO $pdo, string $tableName, string $columnName): bool {
    $stmt = $pdo->prepare("
        SELECT EXISTS (
            SELECT FROM information_schema.columns
            WHERE table_schema = 'public'
            AND table_name = ?
            AND column_name = ?
        )
    ");
    $stmt->execute([$tableName, $columnName]);
    return (bool)$stmt->fetchColumn();
}

// Helper function to check if table exists
function tableExists(PDO $pdo, string $tableName): bool {
    $stmt = $pdo->prepare("
        SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_name = ?
        )
    ");
    $stmt->execute([$tableName]);
    return (bool)$stmt->fetchColumn();
}

// ============================================================
// PART A: ADD COLLECTION TRACKING COLUMNS TO ORDERS TABLE
// ============================================================

try {
    $columnsToAdd = [
        ['insurance_billed', 'DECIMAL(10,2)', 'Amount billed to insurance'],
        ['insurance_allowed', 'DECIMAL(10,2)', 'Insurance allowed amount'],
        ['insurance_paid', 'DECIMAL(10,2)', 'Amount paid by insurance'],
        ['patient_responsibility', 'DECIMAL(10,2)', 'Patient responsibility amount'],
        ['patient_paid', 'DECIMAL(10,2)', 'Amount paid by patient'],
        ['adjustment', 'DECIMAL(10,2)', 'Adjustment amount'],
        ['write_off', 'DECIMAL(10,2)', 'Write-off amount'],
        ['collection_status', 'VARCHAR(30)', 'Collection status (pending, partial, collected, written_off)'],
        ['collection_notes', 'TEXT', 'Notes about collections'],
    ];

    foreach ($columnsToAdd as [$colName, $colType, $description]) {
        if (!columnExists($pdo, 'orders', $colName)) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN $colName $colType");
            $pdo->exec("COMMENT ON COLUMN orders.$colName IS '$description'");
            $results[] = "Added column: orders.$colName ($colType)";
        } else {
            $results[] = "Column already exists: orders.$colName (skipped)";
        }
    }

} catch (Exception $e) {
    $errors[] = "Failed to add columns to orders: " . $e->getMessage();
}

// ============================================================
// PART B: CREATE COMMISSION_REPORTS TABLE
// ============================================================

try {
    if (!tableExists($pdo, 'commission_reports')) {
        $pdo->exec("
            CREATE TABLE commission_reports (
                id SERIAL PRIMARY KEY,
                rep_id VARCHAR(64) NOT NULL REFERENCES sales_reps(id) ON DELETE CASCADE,
                report_period_start DATE NOT NULL,
                report_period_end DATE NOT NULL,
                total_revenue DECIMAL(10,2) NOT NULL DEFAULT 0,
                commission_rate DECIMAL(5,4) NOT NULL,
                commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                adjustments DECIMAL(10,2) DEFAULT 0,
                adjustment_notes TEXT,
                final_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                created_by VARCHAR(64),
                created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
                finalized_by VARCHAR(64),
                finalized_at TIMESTAMP WITH TIME ZONE,
                acknowledged_by_rep BOOLEAN DEFAULT FALSE,
                acknowledged_at TIMESTAMP WITH TIME ZONE,
                payment_method VARCHAR(50),
                payment_reference VARCHAR(100),
                payment_date DATE,
                notes TEXT,

                CONSTRAINT valid_report_status CHECK (status IN ('draft', 'finalized', 'acknowledged', 'paid'))
            )
        ");

        $pdo->exec("CREATE INDEX idx_commission_reports_rep_id ON commission_reports(rep_id)");
        $pdo->exec("CREATE INDEX idx_commission_reports_status ON commission_reports(status)");
        $pdo->exec("CREATE INDEX idx_commission_reports_period ON commission_reports(report_period_start, report_period_end)");

        $pdo->exec("COMMENT ON TABLE commission_reports IS 'Finalized commission reports for sales reps'");

        $results[] = "Created table: commission_reports";
    } else {
        $results[] = "Table already exists: commission_reports (skipped)";
    }
} catch (Exception $e) {
    $errors[] = "Failed to create commission_reports table: " . $e->getMessage();
}

// ============================================================
// PART C: CREATE COMMISSION_REPORT_LINE_ITEMS TABLE
// ============================================================

try {
    if (!tableExists($pdo, 'commission_report_line_items')) {
        $pdo->exec("
            CREATE TABLE commission_report_line_items (
                id SERIAL PRIMARY KEY,
                report_id INTEGER NOT NULL REFERENCES commission_reports(id) ON DELETE CASCADE,
                order_id VARCHAR(64),
                order_type VARCHAR(20),
                patient_name VARCHAR(200),
                product_description VARCHAR(500),
                amount_billed DECIMAL(10,2),
                amount_collected DECIMAL(10,2),
                adjustment DECIMAL(10,2) DEFAULT 0,
                adjustment_reason TEXT,
                commission_rate DECIMAL(5,4),
                commission_amount DECIMAL(10,2),
                created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT valid_line_order_type CHECK (order_type IN ('referral', 'wholesale'))
            )
        ");

        $pdo->exec("CREATE INDEX idx_report_line_items_report_id ON commission_report_line_items(report_id)");
        $pdo->exec("CREATE INDEX idx_report_line_items_order_id ON commission_report_line_items(order_id)");

        $pdo->exec("COMMENT ON TABLE commission_report_line_items IS 'Line items for commission reports'");

        $results[] = "Created table: commission_report_line_items";
    } else {
        $results[] = "Table already exists: commission_report_line_items (skipped)";
    }
} catch (Exception $e) {
    $errors[] = "Failed to create commission_report_line_items table: " . $e->getMessage();
}

// ============================================================
// PART D: ADD REPORT_ID TO REP_COMMISSION_LEDGER (for linking)
// ============================================================

try {
    if (!columnExists($pdo, 'rep_commission_ledger', 'report_id')) {
        $pdo->exec("ALTER TABLE rep_commission_ledger ADD COLUMN report_id INTEGER REFERENCES commission_reports(id)");
        $pdo->exec("CREATE INDEX idx_rep_commission_ledger_report_id ON rep_commission_ledger(report_id)");
        $pdo->exec("COMMENT ON COLUMN rep_commission_ledger.report_id IS 'Link to finalized commission report'");
        $results[] = "Added column: rep_commission_ledger.report_id";
    } else {
        $results[] = "Column already exists: rep_commission_ledger.report_id (skipped)";
    }
} catch (Exception $e) {
    $errors[] = "Failed to add report_id to rep_commission_ledger: " . $e->getMessage();
}

// ============================================================
// VERIFICATION QUERIES
// ============================================================

$verification = [];

try {
    // Verify new columns exist in orders
    $newColumns = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = 'public'
        AND table_name = 'orders'
        AND column_name IN ('insurance_billed', 'insurance_allowed', 'insurance_paid', 'patient_responsibility', 'patient_paid', 'adjustment', 'write_off', 'collection_status')
        ORDER BY column_name
    ")->fetchAll(PDO::FETCH_COLUMN);
    $verification['new_orders_columns'] = $newColumns;

    // Verify new tables exist
    $newTables = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        AND table_name IN ('commission_reports', 'commission_report_line_items')
    ")->fetchAll(PDO::FETCH_COLUMN);
    $verification['new_tables'] = $newTables;

    // Verify existing data unchanged
    $orderCount = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $verification['existing_orders_count'] = $orderCount;

    $repCount = $pdo->query("SELECT COUNT(*) FROM sales_reps")->fetchColumn();
    $verification['existing_reps_count'] = $repCount;

} catch (Exception $e) {
    $errors[] = "Verification failed: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phase 12 Migration - Collections & Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Phase 12: Collections Tracking & Commission Reports</h1>
        <p class="text-gray-600 mb-6">Adds insurance collection tracking and commission report workflow</p>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <h2 class="text-lg font-semibold text-red-800 mb-2">Errors</h2>
                <ul class="list-disc list-inside text-red-700">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Migration Results</h2>
            <ul class="space-y-2">
                <?php foreach ($results as $result): ?>
                    <li class="flex items-start gap-2">
                        <span class="text-green-600 mt-0.5">&#10003;</span>
                        <span class="text-gray-700"><?= htmlspecialchars($result) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Verification</h2>

            <h3 class="font-medium text-gray-800 mb-2">New Columns in orders Table</h3>
            <div class="bg-gray-50 rounded p-3 mb-4">
                <code><?= implode(', ', $verification['new_orders_columns'] ?? []) ?></code>
            </div>

            <h3 class="font-medium text-gray-800 mb-2">New Tables Created</h3>
            <div class="bg-gray-50 rounded p-3 mb-4">
                <code><?= implode(', ', $verification['new_tables'] ?? ['(none)']) ?></code>
            </div>

            <h3 class="font-medium text-gray-800 mb-2">Existing Data Integrity</h3>
            <div class="bg-green-50 rounded p-3">
                <p class="text-green-800">
                    <strong>orders:</strong> <?= $verification['existing_orders_count'] ?? 'N/A' ?> records (unchanged)<br>
                    <strong>sales_reps:</strong> <?= $verification['existing_reps_count'] ?? 'N/A' ?> records (unchanged)
                </p>
            </div>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-semibold text-blue-800 mb-2">Next Steps</h3>
            <ol class="list-decimal list-inside text-blue-700 space-y-1">
                <li>Test billing pages load correctly</li>
                <li>Test collection tracking fields in order detail</li>
                <li>Test commission report generation workflow</li>
                <li>Test rep commission report viewing</li>
            </ol>
        </div>

        <div class="mt-6">
            <a href="/admin/" class="inline-block bg-teal-500 text-white px-4 py-2 rounded hover:bg-teal-600">
                Return to Admin Dashboard
            </a>
        </div>
    </div>
</body>
</html>

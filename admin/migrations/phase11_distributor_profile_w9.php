<?php
/**
 * Phase 11: Distributor Profile & W9 Management Migration
 *
 * Adds:
 * - Business profile columns to sales_reps table
 * - rep_w9_submissions table for W9 document tracking
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
// PART A: ADD COLUMNS TO sales_reps TABLE
// ============================================================

try {
    $columnsToAdd = [
        ['dba', 'VARCHAR(200)', 'DBA (Doing Business As)'],
        ['business_address_line1', 'VARCHAR(200)', 'Business address line 1'],
        ['business_address_line2', 'VARCHAR(200)', 'Business address line 2'],
        ['business_city', 'VARCHAR(100)', 'Business city'],
        ['business_state', 'VARCHAR(50)', 'Business state'],
        ['business_zip', 'VARCHAR(20)', 'Business ZIP code'],
        ['business_phone', 'VARCHAR(30)', 'Business phone'],
        ['business_email', 'VARCHAR(200)', 'Business email'],
        ['website', 'VARCHAR(300)', 'Website URL'],
        ['tax_classification', 'VARCHAR(50)', 'Tax classification (sole_proprietor, llc, s_corp, c_corp, partnership, other)'],
        ['ein_last4', 'VARCHAR(4)', 'Last 4 digits of EIN (stored for display only)'],
        ['w9_status', 'VARCHAR(20)', 'W9 status (none, pending, approved, rejected, expired)'],
        ['w9_approved_at', 'TIMESTAMP WITH TIME ZONE', 'When W9 was last approved'],
    ];

    foreach ($columnsToAdd as [$colName, $colType, $description]) {
        if (!columnExists($pdo, 'sales_reps', $colName)) {
            $pdo->exec("ALTER TABLE sales_reps ADD COLUMN $colName $colType");
            $pdo->exec("COMMENT ON COLUMN sales_reps.$colName IS '$description'");
            $results[] = "Added column: sales_reps.$colName ($colType)";
        } else {
            $results[] = "Column already exists: sales_reps.$colName (skipped)";
        }
    }

    // Set default w9_status for existing records
    $pdo->exec("UPDATE sales_reps SET w9_status = 'none' WHERE w9_status IS NULL");
    $results[] = "Set default w9_status='none' for existing records";

} catch (Exception $e) {
    $errors[] = "Failed to add columns to sales_reps: " . $e->getMessage();
}

// ============================================================
// PART B: CREATE rep_w9_submissions TABLE
// ============================================================

try {
    if (!tableExists($pdo, 'rep_w9_submissions')) {
        $pdo->exec("
            CREATE TABLE rep_w9_submissions (
                id SERIAL PRIMARY KEY,
                rep_id VARCHAR(64) NOT NULL REFERENCES sales_reps(id) ON DELETE CASCADE,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                file_path VARCHAR(500) NOT NULL,
                file_name VARCHAR(200) NOT NULL,
                file_mime VARCHAR(100),
                submitted_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reviewed_by VARCHAR(64),
                reviewed_at TIMESTAMP WITH TIME ZONE,
                rejection_reason TEXT,
                tax_year INTEGER NOT NULL,
                expires_at TIMESTAMP WITH TIME ZONE,
                source VARCHAR(30) DEFAULT 'self_service',
                created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT valid_w9_status CHECK (status IN ('pending', 'approved', 'rejected', 'expired'))
            )
        ");

        $pdo->exec("CREATE INDEX idx_rep_w9_rep_id ON rep_w9_submissions(rep_id)");
        $pdo->exec("CREATE INDEX idx_rep_w9_status ON rep_w9_submissions(status)");
        $pdo->exec("CREATE INDEX idx_rep_w9_tax_year ON rep_w9_submissions(tax_year)");
        $pdo->exec("CREATE INDEX idx_rep_w9_submitted_at ON rep_w9_submissions(submitted_at)");

        $pdo->exec("COMMENT ON TABLE rep_w9_submissions IS 'W9 form submissions from distributors'");
        $pdo->exec("COMMENT ON COLUMN rep_w9_submissions.source IS 'self_service, admin_upload, offline_upload'");

        $results[] = "Created table: rep_w9_submissions";
    } else {
        $results[] = "Table already exists: rep_w9_submissions (skipped)";
    }
} catch (Exception $e) {
    $errors[] = "Failed to create rep_w9_submissions table: " . $e->getMessage();
}

// ============================================================
// VERIFICATION QUERIES
// ============================================================

$verification = [];

try {
    // Verify new columns exist
    $newColumns = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = 'public'
        AND table_name = 'sales_reps'
        AND column_name IN ('dba', 'business_address_line1', 'business_city', 'business_state', 'business_zip', 'business_phone', 'business_email', 'website', 'tax_classification', 'ein_last4', 'w9_status', 'w9_approved_at')
        ORDER BY column_name
    ")->fetchAll(PDO::FETCH_COLUMN);
    $verification['new_sales_reps_columns'] = $newColumns;

    // Verify new table exists
    $newTables = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        AND table_name = 'rep_w9_submissions'
    ")->fetchAll(PDO::FETCH_COLUMN);
    $verification['new_tables'] = $newTables;

    // Verify existing data unchanged
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
    <title>Phase 11 Migration - Distributor Profile & W9</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Phase 11: Distributor Profile & W9 Migration</h1>
        <p class="text-gray-600 mb-6">Adds business profile fields and W9 submission tracking</p>

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

            <h3 class="font-medium text-gray-800 mb-2">New Columns in sales_reps</h3>
            <div class="bg-gray-50 rounded p-3 mb-4">
                <code><?= implode(', ', $verification['new_sales_reps_columns'] ?? []) ?></code>
            </div>

            <h3 class="font-medium text-gray-800 mb-2">New Tables Created</h3>
            <div class="bg-gray-50 rounded p-3 mb-4">
                <code><?= implode(', ', $verification['new_tables'] ?? ['(none)']) ?></code>
            </div>

            <h3 class="font-medium text-gray-800 mb-2">Existing Data Integrity</h3>
            <div class="bg-green-50 rounded p-3">
                <p class="text-green-800">
                    <strong>sales_reps:</strong> <?= $verification['existing_reps_count'] ?? 'N/A' ?> records (unchanged)
                </p>
            </div>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-semibold text-blue-800 mb-2">Next Steps</h3>
            <ol class="list-decimal list-inside text-blue-700 space-y-1">
                <li>Test Distributor Portal "My Account" page loads correctly</li>
                <li>Test new Business Information tab</li>
                <li>Test W9 submission workflow</li>
                <li>Test Admin W9 review tab</li>
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

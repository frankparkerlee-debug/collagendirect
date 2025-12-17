<?php
/**
 * Phase 13: Employee Sales Rep View & Distributor Management
 *
 * Adds:
 * - managed_by_admin_id column to sales_reps (links distributors to employee sales reps)
 * - employee_rep_commission_rates table (override rates for distributor-sourced business)
 * - Updates admin_users to support rep view access
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
// PART A: ADD managed_by_admin_id TO sales_reps TABLE
// ============================================================

try {
    // Add managed_by_admin_id column to sales_reps (admin_users.id is INTEGER)
    if (!columnExists($pdo, 'sales_reps', 'managed_by_admin_id')) {
        $pdo->exec("ALTER TABLE sales_reps ADD COLUMN managed_by_admin_id INTEGER REFERENCES admin_users(id) ON DELETE SET NULL");
        $pdo->exec("CREATE INDEX idx_sales_reps_managed_by ON sales_reps(managed_by_admin_id)");
        $pdo->exec("COMMENT ON COLUMN sales_reps.managed_by_admin_id IS 'Employee sales rep (admin_user) who manages this distributor'");
        $results[] = "Added column: sales_reps.managed_by_admin_id";
    } else {
        $results[] = "Column already exists: sales_reps.managed_by_admin_id (skipped)";
    }
} catch (Exception $e) {
    $errors[] = "Failed to add managed_by_admin_id column: " . $e->getMessage();
}

// ============================================================
// PART B: ADD has_rep_view TO admin_users TABLE
// ============================================================

try {
    // Add has_rep_view column to admin_users to enable distributor portal access
    if (!columnExists($pdo, 'admin_users', 'has_rep_view')) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN has_rep_view BOOLEAN DEFAULT FALSE");
        $pdo->exec("COMMENT ON COLUMN admin_users.has_rep_view IS 'Employee can access distributor-style rep portal view'");
        $results[] = "Added column: admin_users.has_rep_view";
    } else {
        $results[] = "Column already exists: admin_users.has_rep_view (skipped)";
    }
} catch (Exception $e) {
    $errors[] = "Failed to add has_rep_view column: " . $e->getMessage();
}

// ============================================================
// PART C: CREATE employee_rep_commission_rates TABLE
// ============================================================

try {
    if (!tableExists($pdo, 'employee_rep_commission_rates')) {
        $pdo->exec("
            CREATE TABLE employee_rep_commission_rates (
                id SERIAL PRIMARY KEY,
                admin_user_id INTEGER NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
                rate_type VARCHAR(30) NOT NULL DEFAULT 'direct',
                commission_rate DECIMAL(5,4) NOT NULL DEFAULT 0.15,
                effective_date DATE NOT NULL DEFAULT CURRENT_DATE,
                end_date DATE,
                notes TEXT,
                created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
                created_by VARCHAR(64),

                CONSTRAINT valid_rate_type CHECK (rate_type IN ('direct', 'distributor_override')),
                CONSTRAINT valid_commission_rate CHECK (commission_rate >= 0 AND commission_rate <= 1)
            )
        ");

        $pdo->exec("CREATE INDEX idx_emp_rep_rates_admin_id ON employee_rep_commission_rates(admin_user_id)");
        $pdo->exec("CREATE INDEX idx_emp_rep_rates_type ON employee_rep_commission_rates(rate_type)");
        $pdo->exec("CREATE INDEX idx_emp_rep_rates_effective ON employee_rep_commission_rates(effective_date)");

        $pdo->exec("COMMENT ON TABLE employee_rep_commission_rates IS 'Commission rates for employee sales reps - direct accounts vs distributor override'");
        $pdo->exec("COMMENT ON COLUMN employee_rep_commission_rates.rate_type IS 'direct = direct physician accounts, distributor_override = commission on distributor-sourced accounts'");

        $results[] = "Created table: employee_rep_commission_rates";
    } else {
        $results[] = "Table already exists: employee_rep_commission_rates (skipped)";
    }
} catch (Exception $e) {
    $errors[] = "Failed to create employee_rep_commission_rates table: " . $e->getMessage();
}

// ============================================================
// PART D: CREATE employee_rep_ledger TABLE FOR TRACKING
// ============================================================

try {
    if (!tableExists($pdo, 'employee_rep_ledger')) {
        $pdo->exec("
            CREATE TABLE employee_rep_ledger (
                id SERIAL PRIMARY KEY,
                admin_user_id INTEGER NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
                order_id VARCHAR(64) REFERENCES orders(id) ON DELETE SET NULL,
                clinic_id VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL,
                distributor_id VARCHAR(64) REFERENCES sales_reps(id) ON DELETE SET NULL,

                source_type VARCHAR(30) NOT NULL DEFAULT 'direct',
                collected_amount DECIMAL(12,2) DEFAULT 0,
                commission_rate DECIMAL(5,4) DEFAULT 0,
                commission_amount DECIMAL(12,2) DEFAULT 0,

                payment_date DATE,
                notes TEXT,
                created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT valid_source_type CHECK (source_type IN ('direct', 'distributor_override'))
            )
        ");

        $pdo->exec("CREATE INDEX idx_emp_ledger_admin_id ON employee_rep_ledger(admin_user_id)");
        $pdo->exec("CREATE INDEX idx_emp_ledger_order_id ON employee_rep_ledger(order_id)");
        $pdo->exec("CREATE INDEX idx_emp_ledger_distributor ON employee_rep_ledger(distributor_id)");
        $pdo->exec("CREATE INDEX idx_emp_ledger_payment_date ON employee_rep_ledger(payment_date)");

        $pdo->exec("COMMENT ON TABLE employee_rep_ledger IS 'Commission ledger for employee sales reps - tracks both direct and distributor override commissions'");
        $pdo->exec("COMMENT ON COLUMN employee_rep_ledger.source_type IS 'direct = from direct physician accounts, distributor_override = from managed distributor accounts'");

        $results[] = "Created table: employee_rep_ledger";
    } else {
        $results[] = "Table already exists: employee_rep_ledger (skipped)";
    }
} catch (Exception $e) {
    $errors[] = "Failed to create employee_rep_ledger table: " . $e->getMessage();
}

// ============================================================
// PART E: ADD direct_assigned_to TO users TABLE
// ============================================================

try {
    // For direct physician accounts assigned to employee sales reps (admin_users.id is INTEGER)
    if (!columnExists($pdo, 'users', 'employee_rep_id')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN employee_rep_id INTEGER REFERENCES admin_users(id) ON DELETE SET NULL");
        $pdo->exec("CREATE INDEX idx_users_employee_rep ON users(employee_rep_id)");
        $pdo->exec("COMMENT ON COLUMN users.employee_rep_id IS 'Employee sales rep (admin_user) directly assigned to this physician/clinic'");
        $results[] = "Added column: users.employee_rep_id";
    } else {
        $results[] = "Column already exists: users.employee_rep_id (skipped)";
    }
} catch (Exception $e) {
    $errors[] = "Failed to add employee_rep_id column: " . $e->getMessage();
}

// ============================================================
// VERIFICATION QUERIES
// ============================================================

$verification = [];

try {
    // Verify new columns in sales_reps
    $salesRepCols = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = 'public'
        AND table_name = 'sales_reps'
        AND column_name = 'managed_by_admin_id'
    ")->fetchAll(PDO::FETCH_COLUMN);
    $verification['sales_reps_new_columns'] = $salesRepCols;

    // Verify new columns in admin_users
    $adminCols = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = 'public'
        AND table_name = 'admin_users'
        AND column_name = 'has_rep_view'
    ")->fetchAll(PDO::FETCH_COLUMN);
    $verification['admin_users_new_columns'] = $adminCols;

    // Verify new columns in users
    $userCols = $pdo->query("
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = 'public'
        AND table_name = 'users'
        AND column_name = 'employee_rep_id'
    ")->fetchAll(PDO::FETCH_COLUMN);
    $verification['users_new_columns'] = $userCols;

    // Verify new tables
    $newTables = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        AND table_name IN ('employee_rep_commission_rates', 'employee_rep_ledger')
    ")->fetchAll(PDO::FETCH_COLUMN);
    $verification['new_tables'] = $newTables;

    // Count existing records
    $repCount = $pdo->query("SELECT COUNT(*) FROM sales_reps")->fetchColumn();
    $adminCount = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'sales'")->fetchColumn();
    $verification['existing_distributors'] = $repCount;
    $verification['existing_employee_sales_reps'] = $adminCount;

} catch (Exception $e) {
    $errors[] = "Verification failed: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phase 13 Migration - Employee Rep View</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Phase 13: Employee Sales Rep View & Distributor Management</h1>
        <p class="text-gray-600 mb-6">Enables employee sales reps to manage distributors with different commission structures</p>

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
                <code><?= implode(', ', $verification['sales_reps_new_columns'] ?? ['(none)']) ?></code>
            </div>

            <h3 class="font-medium text-gray-800 mb-2">New Columns in admin_users</h3>
            <div class="bg-gray-50 rounded p-3 mb-4">
                <code><?= implode(', ', $verification['admin_users_new_columns'] ?? ['(none)']) ?></code>
            </div>

            <h3 class="font-medium text-gray-800 mb-2">New Columns in users</h3>
            <div class="bg-gray-50 rounded p-3 mb-4">
                <code><?= implode(', ', $verification['users_new_columns'] ?? ['(none)']) ?></code>
            </div>

            <h3 class="font-medium text-gray-800 mb-2">New Tables Created</h3>
            <div class="bg-gray-50 rounded p-3 mb-4">
                <code><?= implode(', ', $verification['new_tables'] ?? ['(none)']) ?></code>
            </div>

            <h3 class="font-medium text-gray-800 mb-2">Existing Data</h3>
            <div class="bg-green-50 rounded p-3">
                <p class="text-green-800">
                    <strong>Distributors (sales_reps):</strong> <?= $verification['existing_distributors'] ?? 'N/A' ?> records<br>
                    <strong>Employee Sales Reps (admin_users):</strong> <?= $verification['existing_employee_sales_reps'] ?? 'N/A' ?> records
                </p>
            </div>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-blue-800 mb-2">Schema Overview</h3>
            <div class="text-blue-700 text-sm space-y-2">
                <p><strong>sales_reps.managed_by_admin_id</strong> &rarr; Links distributors to their managing employee sales rep</p>
                <p><strong>admin_users.has_rep_view</strong> &rarr; Enables distributor portal access for employees</p>
                <p><strong>users.employee_rep_id</strong> &rarr; Direct physician accounts assigned to employee sales reps</p>
                <p><strong>employee_rep_commission_rates</strong> &rarr; Stores 'direct' and 'distributor_override' rates</p>
                <p><strong>employee_rep_ledger</strong> &rarr; Tracks commission earnings from both sources</p>
            </div>
        </div>

        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <h3 class="font-semibold text-yellow-800 mb-2">Next Steps</h3>
            <ol class="list-decimal list-inside text-yellow-700 space-y-1">
                <li>Enable rep view for Alina: UPDATE admin_users SET has_rep_view = TRUE WHERE email = 'alinaherrera29@gmail.com'</li>
                <li>Set commission rates in employee_rep_commission_rates table</li>
                <li>Assign distributors via sales_reps.managed_by_admin_id</li>
                <li>Assign direct physicians via users.employee_rep_id</li>
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

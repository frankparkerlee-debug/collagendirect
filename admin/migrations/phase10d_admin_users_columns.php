<?php
/**
 * Migration: Phase 10d - Add columns to admin_users table
 *
 * Adds optional columns for enhanced internal user management:
 * - phone: Contact phone number
 * - status: Account status (active, suspended, deactivated)
 * - require_pw_change: Flag to force password change on login
 * - last_login_at: Timestamp of last successful login
 * - updated_at: Timestamp of last record update
 *
 * PRESERVATION: These are all ADDITIVE changes - no existing columns modified.
 */
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../_header.php';

require_admin();

$admin = current_admin();
if (($admin['role'] ?? '') !== 'superadmin') {
    die('Super Admin access required');
}

$results = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    try {
        $pdo->beginTransaction();

        // ============================================================
        // PART A: ADD phone COLUMN
        // ============================================================
        $hasPhone = $pdo->query("
            SELECT column_name FROM information_schema.columns
            WHERE table_name = 'admin_users' AND column_name = 'phone'
        ")->fetchColumn();

        if (!$hasPhone) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN phone VARCHAR(50)");
            $results[] = "Added 'phone' column to admin_users";
        } else {
            $results[] = "'phone' column already exists";
        }

        // ============================================================
        // PART B: ADD status COLUMN
        // ============================================================
        $hasStatus = $pdo->query("
            SELECT column_name FROM information_schema.columns
            WHERE table_name = 'admin_users' AND column_name = 'status'
        ")->fetchColumn();

        if (!$hasStatus) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
            $results[] = "Added 'status' column to admin_users with default 'active'";
        } else {
            $results[] = "'status' column already exists";
        }

        // ============================================================
        // PART C: ADD require_pw_change COLUMN
        // ============================================================
        $hasRequirePwChange = $pdo->query("
            SELECT column_name FROM information_schema.columns
            WHERE table_name = 'admin_users' AND column_name = 'require_pw_change'
        ")->fetchColumn();

        if (!$hasRequirePwChange) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN require_pw_change BOOLEAN DEFAULT FALSE");
            $results[] = "Added 'require_pw_change' column to admin_users";
        } else {
            $results[] = "'require_pw_change' column already exists";
        }

        // ============================================================
        // PART D: ADD last_login_at COLUMN
        // ============================================================
        $hasLastLogin = $pdo->query("
            SELECT column_name FROM information_schema.columns
            WHERE table_name = 'admin_users' AND column_name = 'last_login_at'
        ")->fetchColumn();

        if (!$hasLastLogin) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN last_login_at TIMESTAMP WITH TIME ZONE");
            $results[] = "Added 'last_login_at' column to admin_users";
        } else {
            $results[] = "'last_login_at' column already exists";
        }

        // ============================================================
        // PART E: ADD updated_at COLUMN
        // ============================================================
        $hasUpdatedAt = $pdo->query("
            SELECT column_name FROM information_schema.columns
            WHERE table_name = 'admin_users' AND column_name = 'updated_at'
        ")->fetchColumn();

        if (!$hasUpdatedAt) {
            $pdo->exec("ALTER TABLE admin_users ADD COLUMN updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP");
            $results[] = "Added 'updated_at' column to admin_users";
        } else {
            $results[] = "'updated_at' column already exists";
        }

        // ============================================================
        // PART F: ADD INDEX ON status
        // ============================================================
        $hasStatusIndex = $pdo->query("
            SELECT indexname FROM pg_indexes
            WHERE tablename = 'admin_users' AND indexname = 'idx_admin_users_status'
        ")->fetchColumn();

        if (!$hasStatusIndex) {
            $pdo->exec("CREATE INDEX idx_admin_users_status ON admin_users(status)");
            $results[] = "Created index on admin_users.status";
        } else {
            $results[] = "Index on status already exists";
        }

        $pdo->commit();
        $results[] = "Migration completed successfully!";

    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = "Migration failed: " . $e->getMessage();
    }
}

// Get current table structure
$columns = [];
try {
    $columns = $pdo->query("
        SELECT column_name, data_type, column_default, is_nullable
        FROM information_schema.columns
        WHERE table_name = 'admin_users'
        ORDER BY ordinal_position
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errors[] = "Could not fetch table structure: " . $e->getMessage();
}
?>

<div class="max-w-4xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-2">Phase 10d: Admin Users Column Migration</h1>
    <p class="text-gray-600 mb-6">Adds optional columns for enhanced internal user management.</p>

    <?php if (!empty($results)): ?>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
        <h3 class="font-semibold text-green-800 mb-2">Migration Results</h3>
        <ul class="text-sm text-green-700 space-y-1">
            <?php foreach ($results as $result): ?>
            <li><?= htmlspecialchars($result) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <h3 class="font-semibold text-red-800 mb-2">Errors</h3>
        <ul class="text-sm text-red-700 space-y-1">
            <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="bg-white border rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Columns to Add</h2>
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left py-2 px-3">Column</th>
                    <th class="text-left py-2 px-3">Type</th>
                    <th class="text-left py-2 px-3">Default</th>
                    <th class="text-left py-2 px-3">Purpose</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-t">
                    <td class="py-2 px-3 font-mono">phone</td>
                    <td class="py-2 px-3">VARCHAR(50)</td>
                    <td class="py-2 px-3">NULL</td>
                    <td class="py-2 px-3 text-gray-600">Contact phone number</td>
                </tr>
                <tr class="border-t">
                    <td class="py-2 px-3 font-mono">status</td>
                    <td class="py-2 px-3">VARCHAR(20)</td>
                    <td class="py-2 px-3">'active'</td>
                    <td class="py-2 px-3 text-gray-600">active, suspended, deactivated</td>
                </tr>
                <tr class="border-t">
                    <td class="py-2 px-3 font-mono">require_pw_change</td>
                    <td class="py-2 px-3">BOOLEAN</td>
                    <td class="py-2 px-3">FALSE</td>
                    <td class="py-2 px-3 text-gray-600">Force password change on login</td>
                </tr>
                <tr class="border-t">
                    <td class="py-2 px-3 font-mono">last_login_at</td>
                    <td class="py-2 px-3">TIMESTAMP</td>
                    <td class="py-2 px-3">NULL</td>
                    <td class="py-2 px-3 text-gray-600">Last successful login</td>
                </tr>
                <tr class="border-t">
                    <td class="py-2 px-3 font-mono">updated_at</td>
                    <td class="py-2 px-3">TIMESTAMP</td>
                    <td class="py-2 px-3">CURRENT_TIMESTAMP</td>
                    <td class="py-2 px-3 text-gray-600">Last record update</td>
                </tr>
            </tbody>
        </table>

        <form method="POST" class="mt-6">
            <?= csrf_field() ?>
            <button type="submit" name="run_migration" value="1"
                    class="btn btn-primary">
                Run Migration
            </button>
        </form>
    </div>

    <?php if (!empty($columns)): ?>
    <div class="bg-white border rounded-lg p-6">
        <h2 class="text-lg font-semibold mb-4">Current admin_users Table Structure</h2>
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left py-2 px-3">Column</th>
                    <th class="text-left py-2 px-3">Type</th>
                    <th class="text-left py-2 px-3">Default</th>
                    <th class="text-left py-2 px-3">Nullable</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($columns as $col): ?>
                <tr class="border-t">
                    <td class="py-2 px-3 font-mono"><?= htmlspecialchars($col['column_name']) ?></td>
                    <td class="py-2 px-3"><?= htmlspecialchars($col['data_type']) ?></td>
                    <td class="py-2 px-3 text-gray-600 text-xs"><?= htmlspecialchars($col['column_default'] ?? '-') ?></td>
                    <td class="py-2 px-3"><?= $col['is_nullable'] === 'YES' ? 'Yes' : 'No' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="mt-6">
        <a href="/admin/platform/internal-users.php" class="text-blue-600 hover:underline">
            &larr; Go to Internal Users
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../_footer.php'; ?>

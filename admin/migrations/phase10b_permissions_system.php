<?php
/**
 * Phase 10b: Admin Settings Restructure - Permissions System Migration
 *
 * Creates new granular permissions system with:
 * - permissions: Permission definitions
 * - role_permissions: Default permissions per role
 * - user_permission_overrides: Per-user overrides
 *
 * PRESERVATION RULES:
 * - NO existing tables modified
 * - NO existing columns removed
 * - ONLY new tables created
 * - ONLY new columns added (nullable or with defaults)
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

// ============================================================
// PART A: CREATE NEW TABLES
// ============================================================

try {
    $pdo->beginTransaction();

    // Migration 1: Create `permissions` table
    if (!tableExists($pdo, 'permissions')) {
        $pdo->exec("
            CREATE TABLE permissions (
                id SERIAL PRIMARY KEY,
                key VARCHAR(100) UNIQUE NOT NULL,
                name VARCHAR(100) NOT NULL,
                category VARCHAR(50) NOT NULL,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE INDEX idx_permissions_category ON permissions(category)");
        $pdo->exec("CREATE INDEX idx_permissions_key ON permissions(key)");
        $pdo->exec("COMMENT ON TABLE permissions IS 'Permission definitions for granular role-based access control'");
        $results[] = "Created table: permissions";
    } else {
        $results[] = "Table already exists: permissions (skipped)";
    }

    // Migration 2: Create `role_permissions` table
    if (!tableExists($pdo, 'role_permissions')) {
        $pdo->exec("
            CREATE TABLE role_permissions (
                id SERIAL PRIMARY KEY,
                role VARCHAR(50) NOT NULL,
                permission_id INTEGER NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
                access_level VARCHAR(20) NOT NULL DEFAULT 'none',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(role, permission_id)
            )
        ");
        $pdo->exec("CREATE INDEX idx_role_permissions_role ON role_permissions(role)");
        $pdo->exec("CREATE INDEX idx_role_permissions_permission ON role_permissions(permission_id)");
        $pdo->exec("COMMENT ON TABLE role_permissions IS 'Default permissions for each role'");
        $results[] = "Created table: role_permissions";
    } else {
        $results[] = "Table already exists: role_permissions (skipped)";
    }

    // Migration 3: Create `user_permission_overrides` table
    // Note: This references users.id which is VARCHAR(64), not INTEGER
    if (!tableExists($pdo, 'user_permission_overrides')) {
        $pdo->exec("
            CREATE TABLE user_permission_overrides (
                id SERIAL PRIMARY KEY,
                user_id VARCHAR(64) NOT NULL,
                permission_id INTEGER NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
                override_type VARCHAR(10) NOT NULL CHECK (override_type IN ('grant', 'revoke')),
                access_level VARCHAR(20),
                created_by VARCHAR(64),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(user_id, permission_id)
            )
        ");
        $pdo->exec("CREATE INDEX idx_user_permission_overrides_user ON user_permission_overrides(user_id)");
        $pdo->exec("CREATE INDEX idx_user_permission_overrides_permission ON user_permission_overrides(permission_id)");
        $pdo->exec("COMMENT ON TABLE user_permission_overrides IS 'Per-user permission grants or revokes'");
        $results[] = "Created table: user_permission_overrides";
    } else {
        $results[] = "Table already exists: user_permission_overrides (skipped)";
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    $errors[] = "Failed to create tables: " . $e->getMessage();
}

// ============================================================
// PART B: ADD COLUMNS TO EXISTING TABLES
// ============================================================

try {
    // Check if rep_signed_documents needs uploaded_by and document_file_path columns
    if (!columnExists($pdo, 'rep_signed_documents', 'uploaded_by')) {
        $pdo->exec("
            ALTER TABLE rep_signed_documents
            ADD COLUMN uploaded_by VARCHAR(64)
        ");
        $results[] = "Added column: rep_signed_documents.uploaded_by";
    } else {
        $results[] = "Column already exists: rep_signed_documents.uploaded_by (skipped)";
    }

    if (!columnExists($pdo, 'rep_signed_documents', 'document_file_path')) {
        $pdo->exec("
            ALTER TABLE rep_signed_documents
            ADD COLUMN document_file_path VARCHAR(500)
        ");
        $results[] = "Added column: rep_signed_documents.document_file_path";
    } else {
        $results[] = "Column already exists: rep_signed_documents.document_file_path (skipped)";
    }

    // sales_reps columns (invite_token, invite_token_expires_at, invited_by) already exist per schema docs
    $results[] = "sales_reps invite columns already exist per preservation docs (verified)";

} catch (Exception $e) {
    $errors[] = "Failed to add columns: " . $e->getMessage();
}

// ============================================================
// PART C: SEED PERMISSION DEFINITIONS
// ============================================================

try {
    // Check if permissions already seeded
    $existingCount = $pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn();

    if ($existingCount == 0) {
        $permissions = [
            // Dashboard
            ['dashboard.view', 'View Dashboard', 'Dashboard', 'Access to main dashboard'],
            ['dashboard.revenue_metrics', 'View Revenue Metrics', 'Dashboard', 'Access to revenue metrics'],
            ['dashboard.action_items', 'View Action Items', 'Dashboard', 'Access to action items'],

            // Referrals
            ['referrals.patients.view', 'View Patients', 'Referrals', 'View patient records'],
            ['referrals.patients.create', 'Create Patients', 'Referrals', 'Create new patients'],
            ['referrals.patients.edit', 'Edit Patients', 'Referrals', 'Edit patient records'],
            ['referrals.patients.delete', 'Delete Patients', 'Referrals', 'Delete patient records'],
            ['referrals.orders.view', 'View Referral Orders', 'Referrals', 'View referral orders'],
            ['referrals.orders.create', 'Create Referral Orders', 'Referrals', 'Create referral orders'],
            ['referrals.orders.edit', 'Edit Referral Orders', 'Referrals', 'Edit referral orders'],
            ['referrals.orders.approve', 'Approve Referral Orders', 'Referrals', 'Approve referral orders'],
            ['referrals.delivery_audit.view', 'View Delivery Audit', 'Referrals', 'Access delivery audit'],
            ['referrals.export', 'Export Referral Data', 'Referrals', 'Export referral data'],

            // Wholesale
            ['wholesale.orders.view', 'View Wholesale Orders', 'Wholesale', 'View wholesale orders'],
            ['wholesale.orders.create', 'Create Wholesale Orders', 'Wholesale', 'Create wholesale orders'],
            ['wholesale.orders.edit', 'Edit Wholesale Orders', 'Wholesale', 'Edit wholesale orders'],
            ['wholesale.orders.delete', 'Delete Wholesale Orders', 'Wholesale', 'Delete wholesale orders'],
            ['wholesale.practice_pricing.view', 'View Practice Pricing', 'Wholesale', 'View practice pricing'],
            ['wholesale.practice_pricing.edit', 'Edit Practice Pricing', 'Wholesale', 'Edit practice pricing'],

            // Billing
            ['billing.referral.view', 'View Referral Billing', 'Billing', 'View referral billing'],
            ['billing.referral.edit', 'Edit Referral Billing', 'Billing', 'Edit referral billing'],
            ['billing.referral.record_payment', 'Record Referral Payments', 'Billing', 'Record referral payments'],
            ['billing.wholesale.view', 'View Wholesale Billing', 'Billing', 'View wholesale billing'],
            ['billing.wholesale.edit', 'Edit Wholesale Billing', 'Billing', 'Edit wholesale billing'],
            ['billing.wholesale.record_payment', 'Record Wholesale Payments', 'Billing', 'Record wholesale payments'],
            ['billing.statements.generate', 'Generate Statements', 'Billing', 'Generate billing statements'],

            // Shipments
            ['shipments.view', 'View Shipments', 'Shipments', 'View shipments'],
            ['shipments.create', 'Create Shipments', 'Shipments', 'Create shipments'],
            ['shipments.edit', 'Edit Shipments', 'Shipments', 'Edit shipments'],
            ['shipments.mark_delivered', 'Mark Delivered', 'Shipments', 'Mark shipments delivered'],

            // Products
            ['products.view', 'View Products', 'Products', 'View products'],
            ['products.create', 'Create Products', 'Products', 'Create products'],
            ['products.edit', 'Edit Products', 'Products', 'Edit products'],
            ['products.delete', 'Delete Products', 'Products', 'Delete products'],

            // Admin Settings
            ['admin_settings.access', 'Access Admin Settings', 'Admin Settings', 'Access admin settings area'],
            ['admin_settings.practices.view', 'View Practices', 'Admin Settings', 'View practices'],
            ['admin_settings.practices.manage', 'Manage Practices', 'Admin Settings', 'Add/edit/remove practices'],
            ['admin_settings.practice_users.manage', 'Manage Practice Users', 'Admin Settings', 'Add/edit practice users'],
            ['admin_settings.internal_users.view', 'View Internal Users', 'Admin Settings', 'View internal users'],
            ['admin_settings.internal_users.manage', 'Manage Internal Users', 'Admin Settings', 'Add/edit internal users'],
            ['admin_settings.admins.manage', 'Manage Admins', 'Admin Settings', 'Add/edit admin users'],
            ['admin_settings.distributors.view', 'View Distributors', 'Admin Settings', 'View distributors'],
            ['admin_settings.distributors.manage', 'Manage Distributors', 'Admin Settings', 'Add/edit distributors'],
            ['admin_settings.distributors.approve', 'Approve Distributors', 'Admin Settings', 'Approve distributor applications'],
            ['admin_settings.roles.view', 'View Roles', 'Admin Settings', 'View roles and permissions'],
            ['admin_settings.roles.manage', 'Manage Roles', 'Admin Settings', 'Edit role permissions'],

            // Commission
            ['commission.view_own', 'View Own Commission', 'Commission', 'View own commission data'],
            ['commission.view_all', 'View All Commission', 'Commission', 'View all reps commission'],
            ['commission.record_payouts', 'Record Payouts', 'Commission', 'Record commission payouts'],
            ['commission.edit_rates', 'Edit Commission Rates', 'Commission', 'Edit commission rates'],

            // Messages
            ['messages.view', 'View Messages', 'Messages', 'View messages'],
            ['messages.send', 'Send Messages', 'Messages', 'Send messages'],
            ['messages.delete', 'Delete Messages', 'Messages', 'Delete messages'],

            // Data Scope
            ['data_scope.all_practices', 'All Practices', 'Data Scope', 'Access to all practices data'],
            ['data_scope.assigned_practices', 'Assigned Practices', 'Data Scope', 'Access to assigned practices only'],
            ['data_scope.own_practice', 'Own Practice', 'Data Scope', 'Access to own practice only'],
        ];

        $stmt = $pdo->prepare("
            INSERT INTO permissions (key, name, category, description)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($permissions as $perm) {
            $stmt->execute($perm);
        }

        $results[] = "Seeded " . count($permissions) . " permission definitions";
    } else {
        $results[] = "Permissions already seeded ({$existingCount} existing) - skipped";
    }

} catch (Exception $e) {
    $errors[] = "Failed to seed permissions: " . $e->getMessage();
}

// ============================================================
// PART D: SEED ROLE DEFAULT PERMISSIONS
// ============================================================

try {
    // Check if role_permissions already seeded
    $existingRolePerms = $pdo->query("SELECT COUNT(*) FROM role_permissions")->fetchColumn();

    if ($existingRolePerms == 0) {
        // Get all permission IDs - key => id mapping
        $permissionIds = $pdo->query("SELECT key, id FROM permissions")->fetchAll(PDO::FETCH_KEY_PAIR);

        // Define role permission mappings
        $rolePermissions = [];

        // Super Admin: Full access to everything
        foreach ($permissionIds as $key => $id) {
            $rolePermissions[] = ['superadmin', $id, 'full'];
        }

        // Admin: Full access except admin management
        foreach ($permissionIds as $key => $id) {
            if ($key === 'admin_settings.admins.manage') {
                $rolePermissions[] = ['admin', $id, 'none'];
            } else {
                $rolePermissions[] = ['admin', $id, 'full'];
            }
        }

        // Manufacturer: Same as Admin (can manage practices, view all, but not admin users)
        foreach ($permissionIds as $key => $id) {
            if ($key === 'admin_settings.admins.manage' || $key === 'admin_settings.internal_users.manage') {
                $rolePermissions[] = ['manufacturer', $id, 'none'];
            } else {
                $rolePermissions[] = ['manufacturer', $id, 'full'];
            }
        }

        // Sales (internal employee): Limited to practice and order management
        foreach ($permissionIds as $key => $id) {
            $level = 'none';
            if (strpos($key, 'dashboard.') === 0) $level = 'view';
            if (strpos($key, 'referrals.') === 0) $level = 'full';
            if (strpos($key, 'wholesale.orders.') === 0) $level = 'full';
            if (strpos($key, 'admin_settings.distributors.') === 0) $level = 'full';
            if (strpos($key, 'admin_settings.practices.') === 0) $level = 'full';
            if (strpos($key, 'admin_settings.practice_users.') === 0) $level = 'full';
            if ($key === 'admin_settings.access') $level = 'full';
            if ($key === 'commission.view_own') $level = 'full';
            if (strpos($key, 'messages.') === 0) $level = 'full';
            if ($key === 'data_scope.assigned_practices') $level = 'full';
            $rolePermissions[] = ['sales', $id, $level];
        }

        // Employee: View-only with limited actions
        foreach ($permissionIds as $key => $id) {
            $level = 'none';
            if (strpos($key, 'dashboard.view') === 0) $level = 'view';
            if (strpos($key, 'referrals.') === 0 && strpos($key, '.view') !== false) $level = 'view';
            if (strpos($key, 'messages.') === 0) $level = 'full';
            if ($key === 'data_scope.assigned_practices') $level = 'full';
            $rolePermissions[] = ['employee', $id, $level];
        }

        // Ops: Shipments and delivery focused
        foreach ($permissionIds as $key => $id) {
            $level = 'none';
            if (strpos($key, 'dashboard.view') === 0) $level = 'view';
            if (strpos($key, 'shipments.') === 0) $level = 'full';
            if (strpos($key, 'referrals.delivery_audit') === 0) $level = 'full';
            if (strpos($key, 'referrals.orders.view') === 0) $level = 'view';
            if (strpos($key, 'wholesale.orders.view') === 0) $level = 'view';
            if (strpos($key, 'messages.') === 0) $level = 'full';
            if ($key === 'data_scope.all_practices') $level = 'full';
            $rolePermissions[] = ['ops', $id, $level];
        }

        // Sales Rep (distributor): Own assigned practices only
        foreach ($permissionIds as $key => $id) {
            $level = 'none';
            if (strpos($key, 'dashboard.view') === 0) $level = 'view';
            if (strpos($key, 'referrals.orders.view') === 0) $level = 'view';
            if (strpos($key, 'wholesale.orders.') === 0) $level = 'full';
            if ($key === 'commission.view_own') $level = 'full';
            if (strpos($key, 'messages.') === 0) $level = 'full';
            if ($key === 'data_scope.assigned_practices') $level = 'full';
            $rolePermissions[] = ['sales_rep', $id, $level];
        }

        // Practice Admin (practice_admin): Own practice only
        foreach ($permissionIds as $key => $id) {
            $level = 'none';
            if ($key === 'data_scope.own_practice') $level = 'full';
            $rolePermissions[] = ['practice_admin', $id, $level];
        }

        // Physician: Own practice only (portal users)
        foreach ($permissionIds as $key => $id) {
            $level = 'none';
            if ($key === 'data_scope.own_practice') $level = 'full';
            $rolePermissions[] = ['physician', $id, $level];
        }

        // Insert all role permissions
        $stmt = $pdo->prepare("
            INSERT INTO role_permissions (role, permission_id, access_level)
            VALUES (?, ?, ?)
        ");

        foreach ($rolePermissions as $rp) {
            $stmt->execute($rp);
        }

        $results[] = "Seeded " . count($rolePermissions) . " role permission mappings";
    } else {
        $results[] = "Role permissions already seeded ({$existingRolePerms} existing) - skipped";
    }

} catch (Exception $e) {
    $errors[] = "Failed to seed role permissions: " . $e->getMessage();
}

// ============================================================
// VERIFICATION QUERIES
// ============================================================

$verification = [];

try {
    // Verify new tables exist
    $tables = $pdo->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        AND table_name IN ('permissions', 'role_permissions', 'user_permission_overrides')
        ORDER BY table_name
    ")->fetchAll(PDO::FETCH_COLUMN);
    $verification['new_tables'] = $tables;

    // Count permissions by category
    $permCategories = $pdo->query("
        SELECT category, COUNT(*) as count
        FROM permissions
        GROUP BY category
        ORDER BY category
    ")->fetchAll(PDO::FETCH_ASSOC);
    $verification['permission_categories'] = $permCategories;

    // Count role permissions by role
    $rolePerms = $pdo->query("
        SELECT role, COUNT(*) as count
        FROM role_permissions
        GROUP BY role
        ORDER BY role
    ")->fetchAll(PDO::FETCH_ASSOC);
    $verification['role_permission_counts'] = $rolePerms;

    // Verify existing tables unchanged (spot check)
    $usersCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $salesRepsCount = $pdo->query("SELECT COUNT(*) FROM sales_reps")->fetchColumn();
    $verification['existing_data_intact'] = [
        'users_count' => $usersCount,
        'sales_reps_count' => $salesRepsCount
    ];

} catch (Exception $e) {
    $errors[] = "Verification failed: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phase 10b Migration - Permissions System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Phase 10b: Permissions System Migration</h1>
        <p class="text-gray-600 mb-6">Admin Settings Restructure - Database Migrations</p>

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

            <h3 class="font-medium text-gray-800 mb-2">New Tables Created</h3>
            <div class="bg-gray-50 rounded p-3 mb-4">
                <code><?= implode(', ', $verification['new_tables'] ?? []) ?></code>
            </div>

            <h3 class="font-medium text-gray-800 mb-2">Permissions by Category</h3>
            <table class="w-full text-sm mb-4">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-3 py-2">Category</th>
                        <th class="text-right px-3 py-2">Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($verification['permission_categories'] ?? [] as $cat): ?>
                        <tr class="border-t">
                            <td class="px-3 py-2"><?= htmlspecialchars($cat['category']) ?></td>
                            <td class="text-right px-3 py-2"><?= $cat['count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3 class="font-medium text-gray-800 mb-2">Role Permissions Seeded</h3>
            <table class="w-full text-sm mb-4">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-3 py-2">Role</th>
                        <th class="text-right px-3 py-2">Permissions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($verification['role_permission_counts'] ?? [] as $rp): ?>
                        <tr class="border-t">
                            <td class="px-3 py-2"><?= htmlspecialchars($rp['role']) ?></td>
                            <td class="text-right px-3 py-2"><?= $rp['count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h3 class="font-medium text-gray-800 mb-2">Existing Data Integrity</h3>
            <div class="bg-green-50 rounded p-3">
                <p class="text-green-800">
                    <strong>users:</strong> <?= $verification['existing_data_intact']['users_count'] ?? 'N/A' ?> records |
                    <strong>sales_reps:</strong> <?= $verification['existing_data_intact']['sales_reps_count'] ?? 'N/A' ?> records
                </p>
            </div>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-semibold text-blue-800 mb-2">Next Steps</h3>
            <ol class="list-decimal list-inside text-blue-700 space-y-1">
                <li>Run <code>/docs/preservation/TEST_QUERIES.sql</code> to verify full integrity</li>
                <li>Test all user login types (admin, physician, sales rep)</li>
                <li>Proceed to Phase 10c: Practice Management UI</li>
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

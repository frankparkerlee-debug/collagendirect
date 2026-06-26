<?php
/**
 * Roles & Permissions Management - Phase 10f
 *
 * Manages role templates and permissions for the admin portal.
 * Provides permission matrix viewing and editing capabilities.
 *
 * Access:
 * - Super Admin: Full access (view and edit)
 * - Admin: View only
 * - Others: Tab hidden entirely
 */
declare(strict_types=1);
require __DIR__ . '/../auth.php';
require_admin();

// Sales reps cannot access admin settings
if (function_exists('deny_sales_rep')) deny_sales_rep();

// Load permission helper
require_once __DIR__ . '/../lib/permissions.php';

// Check permission - must have roles view permission
if (!has_permission('admin_settings.roles.view')) {
    header('Location: /admin/index.php');
    exit;
}

$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$isSuperadmin = $adminRole === 'superadmin';
$canManageRoles = has_permission('admin_settings.roles.manage', 'full');

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Get current tab/view
$activeTab = $_GET['tab'] ?? 'roles';
$selectedRole = $_GET['role'] ?? '';
$editMode = isset($_GET['edit']) && $canManageRoles;

// ============================================================
// ROLE DESCRIPTIONS
// ============================================================
$roleDescriptions = [
    'superadmin' => [
        'name' => 'Super Admin',
        'description' => 'Full platform access with unrestricted permissions',
        'icon' => 'shield-check',
        'color' => 'purple'
    ],
    'admin' => [
        'name' => 'Admin',
        'description' => 'Full access except Super Admin management',
        'icon' => 'user-circle',
        'color' => 'blue'
    ],
    'manufacturer' => [
        'name' => 'Manufacturer',
        'description' => 'Product and distributor management',
        'icon' => 'office-building',
        'color' => 'indigo'
    ],
    'sales' => [
        'name' => 'Clinical Admin',
        'description' => 'Clinical document and practice management',
        'icon' => 'document-text',
        'color' => 'teal'
    ],
    'employee' => [
        'name' => 'Employee Salesperson',
        'description' => 'Practice and distributor management',
        'icon' => 'briefcase',
        'color' => 'green'
    ],
    'ops' => [
        'name' => 'Operations',
        'description' => 'Shipments and delivery management',
        'icon' => 'truck',
        'color' => 'orange'
    ],
    'sales_rep' => [
        'name' => 'Distributor',
        'description' => 'Own assigned practices only',
        'icon' => 'users',
        'color' => 'cyan'
    ],
    'practice_admin' => [
        'name' => 'Practice Manager',
        'description' => 'Own practice management (Portal)',
        'icon' => 'home',
        'color' => 'gray'
    ],
    'physician' => [
        'name' => 'Physician',
        'description' => 'Clinical functions only (Portal)',
        'icon' => 'heart',
        'color' => 'red'
    ]
];

// ============================================================
// HANDLE POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManageRoles) {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            // ========== UPDATE ROLE PERMISSIONS ==========
            case 'update_role_permissions':
                $role = $_POST['target_role'] ?? '';
                $permissions = $_POST['permissions'] ?? [];

                if (!$role) {
                    throw new Exception('Role is required');
                }

                // Protection: Cannot remove critical permissions from superadmin
                if ($role === 'superadmin') {
                    $criticalPerms = ['admin_settings.admins.manage', 'admin_settings.access', 'admin_settings.roles.manage'];
                    foreach ($criticalPerms as $criticalPerm) {
                        if (isset($permissions[$criticalPerm]) && $permissions[$criticalPerm] === 'none') {
                            throw new Exception("Cannot remove '$criticalPerm' from Super Admin");
                        }
                    }
                }

                // Get all permission IDs
                $allPerms = $pdo->query("SELECT id, key FROM permissions")->fetchAll(PDO::FETCH_KEY_PAIR);
                $allPerms = array_flip($allPerms); // key => id

                // Update role permissions
                foreach ($permissions as $permKey => $accessLevel) {
                    if (!isset($allPerms[$permKey])) continue;
                    $permId = $allPerms[$permKey];

                    // Check if exists
                    $exists = $pdo->prepare("SELECT id FROM role_permissions WHERE role = ? AND permission_id = ?");
                    $exists->execute([$role, $permId]);

                    if ($exists->fetch()) {
                        // Update
                        $pdo->prepare("UPDATE role_permissions SET access_level = ?, updated_at = NOW() WHERE role = ? AND permission_id = ?")
                            ->execute([$accessLevel, $role, $permId]);
                    } else {
                        // Insert
                        $pdo->prepare("INSERT INTO role_permissions (role, permission_id, access_level) VALUES (?, ?, ?)")
                            ->execute([$role, $permId, $accessLevel]);
                    }
                }

                $msg = 'Role permissions updated successfully';
                header("Location: /admin/platform/roles-permissions.php?tab=matrix&role=" . urlencode($role) . "&msg=" . urlencode($msg));
                exit;

            // ========== SAVE USER PERMISSION OVERRIDES ==========
            case 'save_user_overrides':
                $userId = $_POST['user_id'] ?? '';
                $overrides = $_POST['overrides'] ?? [];

                if (!$userId) {
                    throw new Exception('User ID is required');
                }

                // Clear existing overrides for this user
                $pdo->prepare("DELETE FROM user_permission_overrides WHERE user_id = ?")->execute([$userId]);

                // Insert new overrides (only non-inherit)
                $stmt = $pdo->prepare("
                    INSERT INTO user_permission_overrides (user_id, permission_id, override_type, access_level, created_by)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $allPerms = $pdo->query("SELECT key, id FROM permissions")->fetchAll(PDO::FETCH_KEY_PAIR);

                foreach ($overrides as $permKey => $overrideData) {
                    if (!isset($allPerms[$permKey])) continue;
                    $permId = $allPerms[$permKey];

                    $overrideType = $overrideData['type'] ?? 'inherit';
                    if ($overrideType === 'inherit') continue;

                    $accessLevel = $overrideType === 'grant' ? ($overrideData['level'] ?? 'full') : null;
                    $stmt->execute([$userId, $permId, $overrideType, $accessLevel, $admin['id']]);
                }

                $msg = 'User permission overrides saved successfully';
                header("Location: /admin/platform/roles-permissions.php?tab=overrides&user_id=" . urlencode($userId) . "&msg=" . urlencode($msg));
                exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ============================================================
// FETCH DATA FOR VIEWS
// ============================================================

// Get all permissions grouped by category
$permissionsByCategory = [];
try {
    $stmt = $pdo->query("SELECT id, key, name, category, description FROM permissions ORDER BY category, name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $cat = $row['category'];
        if (!isset($permissionsByCategory[$cat])) {
            $permissionsByCategory[$cat] = [];
        }
        $permissionsByCategory[$cat][] = $row;
    }
} catch (Exception $e) {
    $error = "Failed to load permissions: " . $e->getMessage();
}

// Get roles with user counts and permission summaries
$roles = [];
try {
    // Get distinct roles from role_permissions
    $rolesList = $pdo->query("SELECT DISTINCT role FROM role_permissions ORDER BY role")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($rolesList as $roleName) {
        // Count users with this role
        $userCount = 0;

        // Check admin_users table
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE role = ?");
        $stmt->execute([$roleName]);
        $userCount += (int)$stmt->fetchColumn();

        // For sales_rep, check users table with active sales_reps
        if ($roleName === 'sales_rep') {
            $stmt = $pdo->query("SELECT COUNT(*) FROM sales_reps WHERE status = 'active'");
            $userCount += (int)$stmt->fetchColumn();
        }

        // For practice_admin/physician, check users table
        if (in_array($roleName, ['practice_admin', 'physician'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
            $stmt->execute([$roleName]);
            $userCount += (int)$stmt->fetchColumn();
        }

        // Get permission summary
        $permSummary = $pdo->prepare("
            SELECT
                SUM(CASE WHEN access_level = 'full' THEN 1 ELSE 0 END) as full_count,
                SUM(CASE WHEN access_level = 'view' THEN 1 ELSE 0 END) as view_count,
                SUM(CASE WHEN access_level = 'none' THEN 1 ELSE 0 END) as none_count
            FROM role_permissions
            WHERE role = ?
        ");
        $permSummary->execute([$roleName]);
        $summary = $permSummary->fetch(PDO::FETCH_ASSOC);

        $roles[$roleName] = [
            'role' => $roleName,
            'info' => $roleDescriptions[$roleName] ?? ['name' => ucfirst($roleName), 'description' => '', 'icon' => 'user', 'color' => 'gray'],
            'user_count' => $userCount,
            'full_count' => (int)($summary['full_count'] ?? 0),
            'view_count' => (int)($summary['view_count'] ?? 0),
            'none_count' => (int)($summary['none_count'] ?? 0)
        ];
    }
} catch (Exception $e) {
    $error = "Failed to load roles: " . $e->getMessage();
}

// Get role permissions for selected role
$rolePermissions = [];
if ($selectedRole) {
    try {
        $stmt = $pdo->prepare("
            SELECT p.key, rp.access_level
            FROM role_permissions rp
            JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role = ?
        ");
        $stmt->execute([$selectedRole]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rolePermissions[$row['key']] = $row['access_level'];
        }
    } catch (Exception $e) {
        $error = "Failed to load role permissions: " . $e->getMessage();
    }
}

// Get users with overrides for the overrides tab
$usersWithOverrides = [];
if ($activeTab === 'overrides') {
    try {
        // Get admin users
        $stmt = $pdo->query("
            SELECT
                CAST(au.id AS VARCHAR) as id,
                au.name,
                au.email,
                au.role,
                'admin_user' as user_type,
                (SELECT COUNT(*) FROM user_permission_overrides upo WHERE upo.user_id = CAST(au.id AS VARCHAR)) as override_count
            FROM admin_users au
            WHERE au.role NOT IN ('superadmin')
            ORDER BY au.name
        ");
        $usersWithOverrides = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Try simpler query
        try {
            $stmt = $pdo->query("
                SELECT
                    au.id::text as id,
                    au.name,
                    au.email,
                    au.role,
                    'admin_user' as user_type
                FROM admin_users au
                WHERE au.role != 'superadmin'
                ORDER BY au.name
            ");
            $usersWithOverrides = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Add override counts
            foreach ($usersWithOverrides as &$user) {
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_permission_overrides WHERE user_id = ?");
                $countStmt->execute([$user['id']]);
                $user['override_count'] = (int)$countStmt->fetchColumn();
            }
        } catch (Exception $e2) {
            $error = "Failed to load users: " . $e2->getMessage();
        }
    }
}

// Get user overrides for specific user
$userOverrides = [];
$selectedUserId = $_GET['user_id'] ?? '';
$selectedUserInfo = null;
if ($selectedUserId && $activeTab === 'overrides') {
    try {
        // Get user info
        $stmt = $pdo->prepare("SELECT id, name, email, role FROM admin_users WHERE id = ?");
        $stmt->execute([$selectedUserId]);
        $selectedUserInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($selectedUserInfo) {
            // Get current overrides
            $stmt = $pdo->prepare("
                SELECT p.key, upo.override_type, upo.access_level
                FROM user_permission_overrides upo
                JOIN permissions p ON p.id = upo.permission_id
                WHERE upo.user_id = ?
            ");
            $stmt->execute([$selectedUserId]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $userOverrides[$row['key']] = [
                    'type' => $row['override_type'],
                    'level' => $row['access_level']
                ];
            }

            // Get role-based permissions for comparison
            $stmt = $pdo->prepare("
                SELECT p.key, rp.access_level
                FROM role_permissions rp
                JOIN permissions p ON p.id = rp.permission_id
                WHERE rp.role = ?
            ");
            $stmt->execute([$selectedUserInfo['role']]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!isset($userOverrides[$row['key']])) {
                    $userOverrides[$row['key']] = ['type' => 'inherit', 'role_level' => $row['access_level']];
                } else {
                    $userOverrides[$row['key']]['role_level'] = $row['access_level'];
                }
            }
        }
    } catch (Exception $e) {
        $error = "Failed to load user overrides: " . $e->getMessage();
    }
}

// Include header
require __DIR__ . '/../_header.php';
?>

<style>
.permission-matrix {
    width: 100%;
    border-collapse: collapse;
}
.permission-matrix th,
.permission-matrix td {
    padding: 0.75rem 0.5rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}
.permission-matrix th {
    background: #f9fafb;
    font-weight: 600;
    font-size: 0.75rem;
    text-transform: uppercase;
    color: #6b7280;
}
.permission-matrix tbody tr:hover {
    background: #f9fafb;
}
.category-header {
    background: #e6f2fb !important;
    font-weight: 600;
    color: #20419b;
}
.access-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}
.access-full { background: #d1fae5; color: #065f46; }
.access-view { background: #dbeafe; color: #1e40af; }
.access-none { background: #f3f4f6; color: #6b7280; }
.access-edit { background: #fef3c7; color: #92400e; }
.override-grant { background: #d1fae5; border: 2px solid #10b981; }
.override-revoke { background: #fee2e2; border: 2px solid #ef4444; }
.role-card {
    transition: all 0.2s ease;
}
.role-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}
.tab-btn {
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    color: #6b7280;
    border-bottom: 2px solid transparent;
    transition: all 0.2s;
}
.tab-btn:hover {
    color: #20419b;
}
.tab-btn.active {
    color: #20419b;
    border-bottom-color: #20419b;
}
</style>

<!-- Page Header -->
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Roles & Permissions</h1>
        <p class="text-gray-500 text-sm mt-1">Manage role-based access control and user permission overrides</p>
    </div>
    <?php if (!$canManageRoles): ?>
    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
        View Only
    </span>
    <?php endif; ?>
</div>

<!-- Messages -->
<?php if ($msg): ?>
<div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- Tabs -->
<div class="border-b border-gray-200 mb-6">
    <nav class="flex gap-4">
        <a href="?tab=roles" class="tab-btn <?= $activeTab === 'roles' ? 'active' : '' ?>">Role Templates</a>
        <a href="?tab=matrix<?= $selectedRole ? '&role=' . urlencode($selectedRole) : '' ?>" class="tab-btn <?= $activeTab === 'matrix' ? 'active' : '' ?>">Permission Matrix</a>
        <?php if ($canManageRoles): ?>
        <a href="?tab=overrides" class="tab-btn <?= $activeTab === 'overrides' ? 'active' : '' ?>">User Overrides</a>
        <?php endif; ?>
    </nav>
</div>

<?php if ($activeTab === 'roles'): ?>
<!-- ============================================================ -->
<!-- ROLE TEMPLATES VIEW -->
<!-- ============================================================ -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($roles as $roleName => $roleData): ?>
    <?php
    $info = $roleData['info'];
    $colorClasses = [
        'purple' => 'bg-purple-100 text-purple-600 border-purple-200',
        'blue' => 'bg-blue-100 text-blue-600 border-blue-200',
        'indigo' => 'bg-indigo-100 text-indigo-600 border-indigo-200',
        'teal' => 'bg-teal-100 text-teal-600 border-teal-200',
        'green' => 'bg-green-100 text-green-600 border-green-200',
        'orange' => 'bg-orange-100 text-orange-600 border-orange-200',
        'cyan' => 'bg-cyan-100 text-cyan-600 border-cyan-200',
        'gray' => 'bg-gray-100 text-gray-600 border-gray-200',
        'red' => 'bg-red-100 text-red-600 border-red-200',
    ];
    $colorClass = $colorClasses[$info['color']] ?? $colorClasses['gray'];
    ?>
    <div class="role-card bg-white rounded-lg border border-gray-200 p-5 shadow-sm">
        <div class="flex items-start justify-between mb-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg <?= $colorClass ?> flex items-center justify-center">
                    <?php if ($info['icon'] === 'shield-check'): ?>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    <?php elseif ($info['icon'] === 'user-circle'): ?>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <?php elseif ($info['icon'] === 'office-building'): ?>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    <?php elseif ($info['icon'] === 'document-text'): ?>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <?php elseif ($info['icon'] === 'briefcase'): ?>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    <?php elseif ($info['icon'] === 'truck'): ?>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></path></svg>
                    <?php elseif ($info['icon'] === 'users'): ?>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    <?php elseif ($info['icon'] === 'home'): ?>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    <?php elseif ($info['icon'] === 'heart'): ?>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path></svg>
                    <?php else: ?>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($info['name']) ?></h3>
                    <p class="text-xs text-gray-500"><?= htmlspecialchars($roleName) ?></p>
                </div>
            </div>
            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                <?= $roleData['user_count'] ?> user<?= $roleData['user_count'] !== 1 ? 's' : '' ?>
            </span>
        </div>
        <p class="text-sm text-gray-600 mb-4"><?= htmlspecialchars($info['description']) ?></p>
        <div class="flex items-center gap-2 mb-4 text-xs">
            <span class="access-badge access-full"><?= $roleData['full_count'] ?> Full</span>
            <span class="access-badge access-view"><?= $roleData['view_count'] ?> View</span>
            <span class="access-badge access-none"><?= $roleData['none_count'] ?> None</span>
        </div>
        <div class="flex gap-2">
            <a href="?tab=matrix&role=<?= urlencode($roleName) ?>" class="flex-1 text-center px-3 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                View Permissions
            </a>
            <?php if ($canManageRoles && $roleName !== 'superadmin'): ?>
            <a href="?tab=matrix&role=<?= urlencode($roleName) ?>&edit=1" class="flex-1 text-center px-3 py-2 text-sm font-medium text-white bg-teal-500 rounded-lg hover:bg-teal-600 transition">
                Edit
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php elseif ($activeTab === 'matrix'): ?>
<!-- ============================================================ -->
<!-- PERMISSION MATRIX VIEW -->
<!-- ============================================================ -->
<?php if (!$selectedRole): ?>
<div class="bg-white rounded-lg border border-gray-200 p-8 text-center">
    <svg class="w-12 h-12 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
    <h3 class="text-lg font-medium text-gray-900 mb-2">Select a Role</h3>
    <p class="text-gray-500 mb-4">Choose a role from the templates to view its permission matrix</p>
    <a href="?tab=roles" class="inline-flex items-center px-4 py-2 bg-teal-500 text-white rounded-lg hover:bg-teal-600 transition">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        View Role Templates
    </a>
</div>
<?php else: ?>
<?php $roleInfo = $roleDescriptions[$selectedRole] ?? ['name' => ucfirst($selectedRole), 'description' => '']; ?>

<!-- Header -->
<div class="bg-white rounded-lg border border-gray-200 p-4 mb-6 flex items-center justify-between">
    <div class="flex items-center gap-4">
        <a href="?tab=roles" class="text-gray-500 hover:text-gray-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        </a>
        <div>
            <h2 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($roleInfo['name']) ?></h2>
            <p class="text-sm text-gray-500"><?= $editMode ? 'Editing permissions' : 'Viewing permissions' ?></p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($editMode): ?>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-amber-100 text-amber-800">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
            Edit Mode
        </span>
        <?php else: ?>
        <?php if ($canManageRoles && $selectedRole !== 'superadmin'): ?>
        <a href="?tab=matrix&role=<?= urlencode($selectedRole) ?>&edit=1" class="inline-flex items-center px-4 py-2 bg-teal-500 text-white rounded-lg hover:bg-teal-600 transition text-sm font-medium">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
            Edit Permissions
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($editMode): ?>
<form method="post" id="permissionForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update_role_permissions">
    <input type="hidden" name="target_role" value="<?= htmlspecialchars($selectedRole) ?>">
<?php endif; ?>

<!-- Permission Matrix -->
<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <table class="permission-matrix">
        <thead>
            <tr>
                <th class="w-1/2">Permission</th>
                <th class="w-1/4">Description</th>
                <th class="w-1/4">Access Level</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($permissionsByCategory as $category => $permissions): ?>
            <tr class="category-header">
                <td colspan="3" class="font-semibold"><?= htmlspecialchars($category) ?></td>
            </tr>
            <?php foreach ($permissions as $perm): ?>
            <?php $currentLevel = $rolePermissions[$perm['key']] ?? 'none'; ?>
            <tr>
                <td>
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($perm['name']) ?></div>
                    <div class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($perm['key']) ?></div>
                </td>
                <td class="text-sm text-gray-600"><?= htmlspecialchars($perm['description'] ?? '') ?></td>
                <td>
                    <?php if ($editMode): ?>
                    <select name="permissions[<?= htmlspecialchars($perm['key']) ?>]" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-teal-500 focus:border-teal-500">
                        <option value="none" <?= $currentLevel === 'none' ? 'selected' : '' ?>>None</option>
                        <option value="view" <?= $currentLevel === 'view' ? 'selected' : '' ?>>View</option>
                        <option value="full" <?= $currentLevel === 'full' ? 'selected' : '' ?>>Full</option>
                    </select>
                    <?php else: ?>
                    <span class="access-badge access-<?= $currentLevel ?>"><?= ucfirst($currentLevel) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($editMode): ?>
<!-- Save/Cancel Buttons -->
<div class="mt-6 flex items-center justify-end gap-3 bg-white rounded-lg border border-gray-200 p-4">
    <a href="?tab=matrix&role=<?= urlencode($selectedRole) ?>" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
        Cancel
    </a>
    <button type="submit" class="px-4 py-2 bg-teal-500 text-white rounded-lg hover:bg-teal-600 transition">
        Save Changes
    </button>
</div>
</form>
<?php endif; ?>

<?php endif; ?>

<?php elseif ($activeTab === 'overrides' && $canManageRoles): ?>
<!-- ============================================================ -->
<!-- USER PERMISSION OVERRIDES VIEW -->
<!-- ============================================================ -->
<?php if (!$selectedUserId): ?>
<!-- User List -->
<div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
    <div class="p-4 border-b border-gray-200">
        <h3 class="font-semibold text-gray-900">Select User to Customize Permissions</h3>
        <p class="text-sm text-gray-500 mt-1">Override role-based permissions for specific users</p>
    </div>
    <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">User</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Role</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Overrides</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php foreach ($usersWithOverrides as $user): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3">
                    <div class="font-medium text-gray-900"><?= htmlspecialchars($user['name']) ?></div>
                    <div class="text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></div>
                </td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                        <?= htmlspecialchars($roleDescriptions[$user['role']]['name'] ?? ucfirst($user['role'])) ?>
                    </span>
                </td>
                <td class="px-4 py-3">
                    <?php if (($user['override_count'] ?? 0) > 0): ?>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                        <?= $user['override_count'] ?> custom
                    </span>
                    <?php else: ?>
                    <span class="text-gray-400 text-sm">None</span>
                    <?php endif; ?>
                </td>
                <td class="px-4 py-3 text-right">
                    <a href="?tab=overrides&user_id=<?= urlencode($user['id']) ?>" class="text-teal-600 hover:text-teal-700 font-medium text-sm">
                        Customize
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($usersWithOverrides)): ?>
            <tr>
                <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                    No users available for permission customization
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php else: ?>
<!-- User Override Editor -->
<?php if (!$selectedUserInfo): ?>
<div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
    User not found
</div>
<?php else: ?>
<?php $userRoleInfo = $roleDescriptions[$selectedUserInfo['role']] ?? ['name' => ucfirst($selectedUserInfo['role'])]; ?>

<!-- Header -->
<div class="bg-white rounded-lg border border-gray-200 p-4 mb-6 flex items-center justify-between">
    <div class="flex items-center gap-4">
        <a href="?tab=overrides" class="text-gray-500 hover:text-gray-700">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        </a>
        <div>
            <h2 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($selectedUserInfo['name']) ?></h2>
            <p class="text-sm text-gray-500">
                Base Role: <span class="font-medium"><?= htmlspecialchars($userRoleInfo['name']) ?></span>
            </p>
        </div>
    </div>
</div>

<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_user_overrides">
    <input type="hidden" name="user_id" value="<?= htmlspecialchars($selectedUserId) ?>">

    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="permission-matrix">
            <thead>
                <tr>
                    <th class="w-1/3">Permission</th>
                    <th class="w-1/6">Role Default</th>
                    <th class="w-1/2">Override</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permissionsByCategory as $category => $permissions): ?>
                <tr class="category-header">
                    <td colspan="3" class="font-semibold"><?= htmlspecialchars($category) ?></td>
                </tr>
                <?php foreach ($permissions as $perm): ?>
                <?php
                $override = $userOverrides[$perm['key']] ?? ['type' => 'inherit', 'role_level' => 'none'];
                $roleLevel = $override['role_level'] ?? 'none';
                $overrideType = $override['type'] ?? 'inherit';
                ?>
                <tr>
                    <td>
                        <div class="font-medium text-gray-900"><?= htmlspecialchars($perm['name']) ?></div>
                        <div class="text-xs text-gray-500 font-mono"><?= htmlspecialchars($perm['key']) ?></div>
                    </td>
                    <td>
                        <span class="access-badge access-<?= $roleLevel ?>"><?= ucfirst($roleLevel) ?></span>
                    </td>
                    <td>
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="overrides[<?= htmlspecialchars($perm['key']) ?>][type]" value="inherit" <?= $overrideType === 'inherit' ? 'checked' : '' ?> class="text-gray-500">
                                <span class="text-sm text-gray-600">Inherit</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="overrides[<?= htmlspecialchars($perm['key']) ?>][type]" value="grant" <?= $overrideType === 'grant' ? 'checked' : '' ?> class="text-green-500">
                                <span class="text-sm text-green-600">Grant</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="overrides[<?= htmlspecialchars($perm['key']) ?>][type]" value="revoke" <?= $overrideType === 'revoke' ? 'checked' : '' ?> class="text-red-500">
                                <span class="text-sm text-red-600">Revoke</span>
                            </label>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Save/Cancel Buttons -->
    <div class="mt-6 flex items-center justify-end gap-3 bg-white rounded-lg border border-gray-200 p-4">
        <a href="?tab=overrides" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
            Cancel
        </a>
        <button type="submit" class="px-4 py-2 bg-teal-500 text-white rounded-lg hover:bg-teal-600 transition">
            Save Overrides
        </button>
    </div>
</form>
<?php endif; ?>
<?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/../_footer.php'; ?>

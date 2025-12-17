<?php
/**
 * Internal Users Management - Phase 10d
 *
 * Manages internal admin users (employees, admins, manufacturers).
 * Preserves all existing functionality from users.php while adding
 * new organized structure.
 *
 * Role visibility rules:
 * - Super Admin: sees all internal users
 * - Admin: sees only Clinical Admin and Employee Salesperson
 * - Others: tab hidden entirely
 */
declare(strict_types=1);
require __DIR__ . '/../auth.php';
require_admin();

// Sales reps cannot access admin settings
if (function_exists('deny_sales_rep')) deny_sales_rep();

// Load permission helper
require_once __DIR__ . '/../lib/permissions.php';

// Check permission
if (!has_permission('admin_settings.internal_users.view')) {
    header('Location: /admin/index.php');
    exit;
}

require_once __DIR__ . '/../../api/lib/email_notifications.php';

$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$isSuperadmin = $adminRole === 'superadmin';
$isAdmin = in_array($adminRole, ['owner', 'superadmin', 'admin']);
$canManage = has_permission('admin_settings.internal_users.manage', 'full');
$canManageAdmins = has_permission('admin_settings.admins.manage', 'full'); // Super Admin only

$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Filters
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');

// ============================================================
// HANDLE POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            // ========== CREATE INTERNAL USER ==========
            case 'create_user':
                if (!$canManage) {
                    throw new Exception('Permission denied');
                }

                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $role = trim($_POST['role'] ?? '');
                $status = $_POST['status'] ?? 'active';
                $passwordMode = $_POST['password_mode'] ?? 'auto';
                $password = $_POST['password'] ?? '';
                $requirePwChange = isset($_POST['require_pw_change']);

                // Validate required fields
                if (!$name || !$email || !$role) {
                    throw new Exception('Name, email, and role are required');
                }

                // Check email uniqueness
                $existing = $pdo->prepare("SELECT id FROM admin_users WHERE email = ?");
                $existing->execute([$email]);
                if ($existing->fetch()) {
                    throw new Exception('Email already exists');
                }

                // Role visibility checks
                if (!$isSuperadmin && in_array($role, ['superadmin', 'admin'])) {
                    throw new Exception('You cannot create Admin or Super Admin users');
                }

                // Generate or use provided password
                if ($passwordMode === 'auto') {
                    $password = bin2hex(random_bytes(8)); // 16-char random password
                }

                if (empty($password)) {
                    throw new Exception('Password is required');
                }

                // Handle Employee Salesperson specific settings
                $hasRepView = false;
                if ($role === 'sales') {
                    $hasRepView = isset($_POST['enable_rep_portal']);
                }

                // Insert user
                $stmt = $pdo->prepare("
                    INSERT INTO admin_users (name, email, phone, role, password_hash, status, require_pw_change, has_rep_view, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $name,
                    $email,
                    $phone ?: null,
                    $role,
                    password_hash($password, PASSWORD_DEFAULT),
                    $status,
                    $requirePwChange ? 1 : 0,
                    $hasRepView ? 1 : 0
                ]);
                $newUserId = $pdo->lastInsertId();

                // Handle Employee Salesperson commission rates
                if ($role === 'sales' && $hasRepView) {
                    $directRate = floatval($_POST['direct_commission_rate'] ?? 15) / 100;
                    $overrideRate = floatval($_POST['override_commission_rate'] ?? 5) / 100;

                    // Insert direct commission rate
                    $pdo->prepare("
                        INSERT INTO employee_rep_commission_rates (admin_user_id, rate_type, commission_rate, effective_date, created_by)
                        VALUES (?, 'direct', ?, CURRENT_DATE, ?)
                    ")->execute([$newUserId, $directRate, $admin['id']]);

                    // Insert distributor override rate
                    $pdo->prepare("
                        INSERT INTO employee_rep_commission_rates (admin_user_id, rate_type, commission_rate, effective_date, created_by)
                        VALUES (?, 'distributor_override', ?, CURRENT_DATE, ?)
                    ")->execute([$newUserId, $overrideRate, $admin['id']]);
                }

                // Send welcome email
                $emailSent = false;
                if ($role === 'sales' && $hasRepView) {
                    $emailSent = send_employee_rep_welcome_email($email, $name, $password);
                } else {
                    $emailSent = send_physician_account_created_email($email, $name, $password);
                }

                $msg = 'Internal user created successfully';
                if ($emailSent) {
                    $msg .= ' - Welcome email sent';
                } else {
                    $msg .= ' - Warning: Email failed to send';
                    error_log("[internal-users.php] Failed to send welcome email to $email");
                }
                break;

            // ========== UPDATE INTERNAL USER ==========
            case 'update_user':
                if (!$canManage) {
                    throw new Exception('Permission denied');
                }

                $userId = (int)($_POST['user_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $role = trim($_POST['role'] ?? '');
                $status = $_POST['status'] ?? 'active';

                // Get current user data
                $currentUser = $pdo->prepare("SELECT * FROM admin_users WHERE id = ?");
                $currentUser->execute([$userId]);
                $currentUser = $currentUser->fetch(PDO::FETCH_ASSOC);

                if (!$currentUser) {
                    throw new Exception('User not found');
                }

                // Role change restrictions
                if (!$isSuperadmin) {
                    // Non-superadmins cannot edit superadmins or admins
                    if (in_array($currentUser['role'], ['superadmin', 'admin'])) {
                        throw new Exception('You cannot edit Admin or Super Admin users');
                    }
                    // Non-superadmins cannot change role to superadmin or admin
                    if (in_array($role, ['superadmin', 'admin'])) {
                        throw new Exception('You cannot change role to Admin or Super Admin');
                    }
                }

                // Super Admin protection: Cannot change own role away from superadmin
                if ($isSuperadmin && $userId == $admin['id'] && $role !== 'superadmin') {
                    throw new Exception('You cannot change your own role');
                }

                // Handle Employee Salesperson specific settings
                $hasRepView = false;
                if ($role === 'sales') {
                    $hasRepView = isset($_POST['enable_rep_portal']);
                }

                $stmt = $pdo->prepare("
                    UPDATE admin_users
                    SET name = ?, phone = ?, role = ?, status = ?, has_rep_view = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$name, $phone ?: null, $role, $status, $hasRepView ? 1 : 0, $userId]);

                // Handle commission rates for sales role
                if ($role === 'sales' && $hasRepView) {
                    $directRate = floatval($_POST['direct_commission_rate'] ?? 15) / 100;
                    $overrideRate = floatval($_POST['override_commission_rate'] ?? 5) / 100;

                    // Check if direct rate exists and update or insert
                    $existingDirect = $pdo->prepare("SELECT id FROM employee_rep_commission_rates WHERE admin_user_id = ? AND rate_type = 'direct' AND end_date IS NULL");
                    $existingDirect->execute([$userId]);
                    if ($existingDirect->fetch()) {
                        $pdo->prepare("UPDATE employee_rep_commission_rates SET commission_rate = ? WHERE admin_user_id = ? AND rate_type = 'direct' AND end_date IS NULL")
                            ->execute([$directRate, $userId]);
                    } else {
                        $pdo->prepare("INSERT INTO employee_rep_commission_rates (admin_user_id, rate_type, commission_rate, effective_date, created_by) VALUES (?, 'direct', ?, CURRENT_DATE, ?)")
                            ->execute([$userId, $directRate, $admin['id']]);
                    }

                    // Check if override rate exists and update or insert
                    $existingOverride = $pdo->prepare("SELECT id FROM employee_rep_commission_rates WHERE admin_user_id = ? AND rate_type = 'distributor_override' AND end_date IS NULL");
                    $existingOverride->execute([$userId]);
                    if ($existingOverride->fetch()) {
                        $pdo->prepare("UPDATE employee_rep_commission_rates SET commission_rate = ? WHERE admin_user_id = ? AND rate_type = 'distributor_override' AND end_date IS NULL")
                            ->execute([$overrideRate, $userId]);
                    } else {
                        $pdo->prepare("INSERT INTO employee_rep_commission_rates (admin_user_id, rate_type, commission_rate, effective_date, created_by) VALUES (?, 'distributor_override', ?, CURRENT_DATE, ?)")
                            ->execute([$userId, $overrideRate, $admin['id']]);
                    }
                }

                $msg = 'User updated successfully';
                break;

            // ========== RESET PASSWORD ==========
            case 'reset_password':
                if (!$canManage) {
                    throw new Exception('Permission denied');
                }

                $userId = (int)($_POST['user_id'] ?? 0);
                $newPassword = $_POST['new_password'] ?? '';

                if (empty($newPassword)) {
                    $newPassword = bin2hex(random_bytes(8));
                }

                // Get user for email
                $user = $pdo->prepare("SELECT email, name FROM admin_users WHERE id = ?");
                $user->execute([$userId]);
                $user = $user->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    throw new Exception('User not found');
                }

                $pdo->prepare("UPDATE admin_users SET password_hash = ?, require_pw_change = 1 WHERE id = ?")
                    ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);

                // Send password reset email
                $emailSent = send_physician_account_created_email($user['email'], $user['name'], $newPassword);

                $msg = 'Password reset successfully';
                if ($emailSent) {
                    $msg .= ' - Email sent with new credentials';
                }
                break;

            // ========== SUSPEND USER ==========
            case 'suspend_user':
                if (!$canManage) {
                    throw new Exception('Permission denied');
                }

                $userId = (int)($_POST['user_id'] ?? 0);

                // Super Admin protection: Cannot suspend yourself
                if ($userId == $admin['id']) {
                    throw new Exception('Cannot suspend your own account');
                }

                // Check if target is superadmin
                $target = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
                $target->execute([$userId]);
                $targetRole = $target->fetchColumn();

                if ($targetRole === 'superadmin') {
                    // Check if this is the last active superadmin
                    $superadminCount = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'superadmin' AND status = 'active'")->fetchColumn();
                    if ($superadminCount <= 1) {
                        throw new Exception('Cannot suspend the last Super Admin');
                    }
                }

                $pdo->prepare("UPDATE admin_users SET status = 'suspended', updated_at = NOW() WHERE id = ?")
                    ->execute([$userId]);

                $msg = 'User suspended successfully';
                break;

            // ========== REACTIVATE USER ==========
            case 'reactivate_user':
                if (!$canManage) {
                    throw new Exception('Permission denied');
                }

                $userId = (int)($_POST['user_id'] ?? 0);

                $pdo->prepare("UPDATE admin_users SET status = 'active', updated_at = NOW() WHERE id = ?")
                    ->execute([$userId]);

                $msg = 'User reactivated successfully';
                break;

            // ========== DEACTIVATE USER ==========
            case 'deactivate_user':
                if (!$canManage) {
                    throw new Exception('Permission denied');
                }

                $userId = (int)($_POST['user_id'] ?? 0);

                // Super Admin protection: Cannot deactivate yourself
                if ($userId == $admin['id']) {
                    throw new Exception('Cannot deactivate your own account');
                }

                // Check if target is superadmin
                $target = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
                $target->execute([$userId]);
                $targetRole = $target->fetchColumn();

                if ($targetRole === 'superadmin') {
                    // Check if this is the last active superadmin
                    $superadminCount = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'superadmin' AND status = 'active'")->fetchColumn();
                    if ($superadminCount <= 1) {
                        throw new Exception('Cannot deactivate the last Super Admin');
                    }
                }

                $pdo->prepare("UPDATE admin_users SET status = 'deactivated', updated_at = NOW() WHERE id = ?")
                    ->execute([$userId]);

                $msg = 'User deactivated successfully';
                break;

            // ========== DELETE USER ==========
            case 'delete_user':
                if (!$canManageAdmins) {
                    throw new Exception('Permission denied - Super Admin required');
                }

                $userId = (int)($_POST['user_id'] ?? 0);

                // Cannot delete yourself
                if ($userId == $admin['id']) {
                    throw new Exception('Cannot delete your own account');
                }

                // Check if last superadmin
                $target = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
                $target->execute([$userId]);
                $targetRole = $target->fetchColumn();

                if ($targetRole === 'superadmin') {
                    $superadminCount = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'superadmin'")->fetchColumn();
                    if ($superadminCount <= 1) {
                        throw new Exception('Cannot delete the last Super Admin');
                    }
                }

                $pdo->prepare("DELETE FROM admin_users WHERE id = ?")->execute([$userId]);

                $msg = 'User deleted permanently';
                break;
        }

        header('Location: /admin/platform/internal-users.php?msg=' . urlencode($msg));
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
        header('Location: /admin/platform/internal-users.php?error=' . urlencode($error));
        exit;
    }
}

// ============================================================
// BUILD QUERY FOR USER LIST
// ============================================================
$whereConditions = [];
$params = [];

// Role-based visibility
if ($isSuperadmin) {
    // Super Admin sees all
    $whereConditions[] = "1=1";
} else {
    // Admin sees only clinical admin (employee) and sales
    $whereConditions[] = "role IN ('employee', 'sales', 'ops')";
}

// Role filter
if ($roleFilter) {
    $whereConditions[] = "role = ?";
    $params[] = $roleFilter;
}

// Status filter
if ($statusFilter) {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
} else {
    // By default, hide deactivated users
    $whereConditions[] = "status != 'deactivated'";
}

// Search filter
if ($search) {
    $whereConditions[] = "(name ILIKE ? OR email ILIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = implode(' AND ', $whereConditions);

$usersQuery = "
    SELECT au.id, au.name, au.email, au.phone, au.role, au.status, au.created_at,
           COALESCE(au.last_login_at, au.created_at) as last_activity,
           au.has_rep_view,
           (SELECT commission_rate FROM employee_rep_commission_rates WHERE admin_user_id = au.id AND rate_type = 'direct' AND (end_date IS NULL OR end_date >= CURRENT_DATE) ORDER BY effective_date DESC LIMIT 1) as direct_rate,
           (SELECT commission_rate FROM employee_rep_commission_rates WHERE admin_user_id = au.id AND rate_type = 'distributor_override' AND (end_date IS NULL OR end_date >= CURRENT_DATE) ORDER BY effective_date DESC LIMIT 1) as override_rate
    FROM admin_users au
    WHERE $whereClause
    ORDER BY
        CASE au.status WHEN 'active' THEN 0 WHEN 'suspended' THEN 1 ELSE 2 END,
        au.created_at DESC
    LIMIT 300
";
$usersStmt = $pdo->prepare($usersQuery);
$usersStmt->execute($params);
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get role counts for filter badges
$roleCounts = [];
try {
    $countsQuery = "SELECT role, COUNT(*) as count FROM admin_users WHERE status != 'deactivated' GROUP BY role";
    $roleCounts = $pdo->query($countsQuery)->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    // Ignore
}

// Define available roles based on current user
$availableRoles = [];
if ($isSuperadmin) {
    $availableRoles = [
        'superadmin' => 'Super Admin',
        'admin' => 'Admin',
        'manufacturer' => 'Manufacturer',
        'sales' => 'Employee Salesperson',
        'ops' => 'Operations',
        'employee' => 'Clinical Admin'
    ];
} else {
    $availableRoles = [
        'sales' => 'Employee Salesperson',
        'ops' => 'Operations',
        'employee' => 'Clinical Admin'
    ];
}

// Role display names mapping
$roleDisplayNames = [
    'superadmin' => 'Super Admin',
    'admin' => 'Admin',
    'manufacturer' => 'Manufacturer',
    'sales' => 'Employee Salesperson',
    'ops' => 'Operations',
    'employee' => 'Clinical Admin'
];

// Status display
$statusDisplay = [
    'active' => ['label' => 'Active', 'class' => 'bg-green-100 text-green-800'],
    'suspended' => ['label' => 'Suspended', 'class' => 'bg-yellow-100 text-yellow-800'],
    'deactivated' => ['label' => 'Deactivated', 'class' => 'bg-gray-100 text-gray-500']
];
?>
<?php include __DIR__ . '/../_header.php'; ?>

<div class="max-w-7xl mx-auto">
    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Internal Users</h1>
            <p class="text-sm text-gray-500 mt-1">Manage admin portal users and permissions</p>
        </div>
        <?php if ($canManage): ?>
        <button onclick="document.getElementById('add-user-modal').showModal()"
                class="btn btn-primary flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
            </svg>
            Add Internal User
        </button>
        <?php endif; ?>
    </div>

    <!-- Messages -->
    <?php if ($msg): ?>
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg">
        <?= e($msg) ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg">
        <?= e($error) ?>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="bg-white border rounded-lg p-4 mb-4">
        <form method="get" class="flex flex-wrap items-center gap-4">
            <!-- Search -->
            <div class="flex-1 min-w-[200px]">
                <input type="text" name="search" value="<?= e($search) ?>"
                       placeholder="Search by name or email..."
                       class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>

            <!-- Role Filter -->
            <div>
                <select name="role" class="border rounded-lg px-3 py-2 text-sm">
                    <option value="">All Roles</option>
                    <?php foreach ($availableRoles as $roleKey => $roleLabel): ?>
                    <option value="<?= e($roleKey) ?>" <?= $roleFilter === $roleKey ? 'selected' : '' ?>>
                        <?= e($roleLabel) ?> (<?= $roleCounts[$roleKey] ?? 0 ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <select name="status" class="border rounded-lg px-3 py-2 text-sm">
                    <option value="">Active & Suspended</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active Only</option>
                    <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended Only</option>
                    <option value="deactivated" <?= $statusFilter === 'deactivated' ? 'selected' : '' ?>>Deactivated</option>
                </select>
            </div>

            <button type="submit" class="btn">Filter</button>
            <?php if ($search || $roleFilter || $statusFilter): ?>
            <a href="/admin/platform/internal-users.php" class="text-sm text-gray-500 hover:underline">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-white border rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Name</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Email</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Phone</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Role</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Status</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Created</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-600">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="7" class="py-8 text-center text-gray-500">
                        No users found matching your criteria.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($users as $user): ?>
                <?php
                    $isCurrentUser = ($user['id'] == $admin['id']);
                    $isProtectedRole = in_array($user['role'], ['superadmin', 'admin']);
                    $canEditThisUser = $canManage && ($isSuperadmin || !$isProtectedRole);
                    $statusInfo = $statusDisplay[$user['status']] ?? $statusDisplay['active'];
                ?>
                <tr class="border-b hover:bg-gray-50 <?= $isCurrentUser ? 'bg-blue-50/50' : '' ?>">
                    <td class="py-3 px-4">
                        <div class="font-medium text-gray-900">
                            <?= e($user['name']) ?>
                            <?php if ($isCurrentUser): ?>
                            <span class="text-xs text-blue-600 ml-1">(you)</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="py-3 px-4 text-gray-600"><?= e($user['email']) ?></td>
                    <td class="py-3 px-4 text-gray-600"><?= e($user['phone'] ?? '-') ?></td>
                    <td class="py-3 px-4">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                            <?= e($roleDisplayNames[$user['role']] ?? $user['role']) ?>
                        </span>
                    </td>
                    <td class="py-3 px-4">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $statusInfo['class'] ?>">
                            <?= $statusInfo['label'] ?>
                        </span>
                    </td>
                    <td class="py-3 px-4 text-gray-500 text-xs">
                        <?= date('M j, Y', strtotime($user['created_at'])) ?>
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-2">
                            <?php if ($canEditThisUser): ?>
                            <button onclick='openEditModal(<?= json_encode($user) ?>)'
                                    class="text-blue-600 hover:underline text-xs">Edit</button>

                            <?php if (!$isCurrentUser): ?>
                                <?php if ($user['status'] === 'active'): ?>
                                <form method="post" class="inline" onsubmit="return confirm('Suspend this user? They will not be able to log in.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="suspend_user">
                                    <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                    <button class="text-yellow-600 hover:underline text-xs">Suspend</button>
                                </form>
                                <?php elseif ($user['status'] === 'suspended'): ?>
                                <form method="post" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reactivate_user">
                                    <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                    <button class="text-green-600 hover:underline text-xs">Reactivate</button>
                                </form>
                                <?php endif; ?>

                                <?php if ($user['status'] !== 'deactivated'): ?>
                                <form method="post" class="inline" onsubmit="return confirm('Deactivate this user? This is a permanent status.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="deactivate_user">
                                    <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                    <button class="text-red-600 hover:underline text-xs">Deactivate</button>
                                </form>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-gray-400 text-xs">View only</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="mt-4 text-sm text-gray-500">
        Showing <?= count($users) ?> user(s)
    </div>
</div>

<!-- Add User Modal -->
<?php if ($canManage): ?>
<dialog id="add-user-modal" class="rounded-xl shadow-xl w-full max-w-lg p-0 backdrop:bg-black/50">
    <form method="post" class="p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_user">

        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Add Internal User</h2>
            <button type="button" onclick="document.getElementById('add-user-modal').close()"
                    class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="space-y-4">
            <!-- Basic Info -->
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="name" required
                           class="w-full border rounded-lg px-3 py-2">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" required
                           class="w-full border rounded-lg px-3 py-2">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                    <input type="tel" name="phone"
                           class="w-full border rounded-lg px-3 py-2">
                </div>
            </div>

            <!-- Role -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                <select name="role" id="add-user-role" required onchange="toggleSalesOptions('add')"
                        class="w-full border rounded-lg px-3 py-2">
                    <option value="">Select Role</option>
                    <?php foreach ($availableRoles as $roleKey => $roleLabel): ?>
                    <option value="<?= e($roleKey) ?>"><?= e($roleLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Employee Salesperson Options (hidden by default) -->
            <div id="add-sales-options" class="hidden bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                <h4 class="font-medium text-sm text-indigo-800 mb-3">Employee Sales Rep Portal</h4>
                <div class="space-y-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="enable_rep_portal" id="add-enable-rep-portal"
                               onchange="toggleCommissionFields('add')" class="rounded text-indigo-600">
                        <span class="ml-2 text-sm font-medium">Enable Sales Rep Portal Access</span>
                    </label>
                    <p class="text-xs text-gray-600 -mt-2 ml-6">Allows this employee to manage their own clinics and distributors</p>

                    <div id="add-commission-fields" class="hidden space-y-3 pl-6 border-l-2 border-indigo-200">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm text-gray-700 mb-1">Direct Commission Rate (%)</label>
                                <input type="number" name="direct_commission_rate" value="15" min="0" max="100" step="0.1"
                                       class="w-full border rounded-lg px-3 py-2 text-sm">
                                <p class="text-xs text-gray-500 mt-1">For clinics they onboard directly</p>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-700 mb-1">Override Commission Rate (%)</label>
                                <input type="number" name="override_commission_rate" value="5" min="0" max="100" step="0.1"
                                       class="w-full border rounded-lg px-3 py-2 text-sm">
                                <p class="text-xs text-gray-500 mt-1">For distributors they manage</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <div class="flex gap-4">
                    <label class="flex items-center">
                        <input type="radio" name="status" value="active" checked class="mr-2">
                        <span class="text-sm">Active</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="status" value="suspended" class="mr-2">
                        <span class="text-sm">Suspended</span>
                    </label>
                </div>
            </div>

            <!-- Password -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <div class="flex gap-4 mb-2">
                    <label class="flex items-center">
                        <input type="radio" name="password_mode" value="auto" checked
                               onchange="togglePasswordField('add')" class="mr-2">
                        <span class="text-sm">Auto-generate</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="password_mode" value="manual"
                               onchange="togglePasswordField('add')" class="mr-2">
                        <span class="text-sm">Set manually</span>
                    </label>
                </div>
                <div id="add-password-field" class="hidden">
                    <input type="password" name="password" id="add-password"
                           class="w-full border rounded-lg px-3 py-2" placeholder="Enter password">
                </div>
                <label class="flex items-center mt-2">
                    <input type="checkbox" name="require_pw_change" checked class="rounded">
                    <span class="ml-2 text-sm text-gray-600">Require password change on first login</span>
                </label>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
            <button type="button" onclick="document.getElementById('add-user-modal').close()"
                    class="btn">Cancel</button>
            <button type="submit" class="btn btn-primary">Create User</button>
        </div>
    </form>
</dialog>

<!-- Edit User Modal -->
<dialog id="edit-user-modal" class="rounded-xl shadow-xl w-full max-w-lg p-0 backdrop:bg-black/50">
    <form method="post" class="p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_user">
        <input type="hidden" name="user_id" id="edit-user-id">

        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold">Edit Internal User</h2>
            <button type="button" onclick="document.getElementById('edit-user-modal').close()"
                    class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="space-y-4">
            <!-- Basic Info -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                <input type="text" name="name" id="edit-name" required
                       class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" id="edit-email" disabled
                       class="w-full border rounded-lg px-3 py-2 bg-gray-50 text-gray-500">
                <p class="text-xs text-gray-500 mt-1">Email cannot be changed</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                <input type="tel" name="phone" id="edit-phone"
                       class="w-full border rounded-lg px-3 py-2">
            </div>

            <!-- Role -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                <select name="role" id="edit-role" required onchange="toggleSalesOptions('edit')"
                        class="w-full border rounded-lg px-3 py-2">
                    <?php foreach ($availableRoles as $roleKey => $roleLabel): ?>
                    <option value="<?= e($roleKey) ?>"><?= e($roleLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <p id="edit-role-warning" class="text-xs text-yellow-600 mt-1 hidden">
                    Warning: Changing role may affect this user's permissions and access.
                </p>
            </div>

            <!-- Employee Salesperson Options -->
            <div id="edit-sales-options" class="hidden bg-indigo-50 border border-indigo-200 rounded-lg p-4">
                <h4 class="font-medium text-sm text-indigo-800 mb-3">Employee Sales Rep Portal</h4>
                <div class="space-y-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="enable_rep_portal" id="edit-enable-rep-portal"
                               onchange="toggleCommissionFields('edit')" class="rounded text-indigo-600">
                        <span class="ml-2 text-sm font-medium">Enable Sales Rep Portal Access</span>
                    </label>
                    <p class="text-xs text-gray-600 -mt-2 ml-6">Allows this employee to manage their own clinics and distributors</p>

                    <div id="edit-commission-fields" class="hidden space-y-3 pl-6 border-l-2 border-indigo-200">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm text-gray-700 mb-1">Direct Commission Rate (%)</label>
                                <input type="number" name="direct_commission_rate" id="edit-direct-rate" value="15" min="0" max="100" step="0.1"
                                       class="w-full border rounded-lg px-3 py-2 text-sm">
                                <p class="text-xs text-gray-500 mt-1">For clinics they onboard directly</p>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-700 mb-1">Override Commission Rate (%)</label>
                                <input type="number" name="override_commission_rate" id="edit-override-rate" value="5" min="0" max="100" step="0.1"
                                       class="w-full border rounded-lg px-3 py-2 text-sm">
                                <p class="text-xs text-gray-500 mt-1">For distributors they manage</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <div class="flex gap-4">
                    <label class="flex items-center">
                        <input type="radio" name="status" id="edit-status-active" value="active" class="mr-2">
                        <span class="text-sm">Active</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="status" id="edit-status-suspended" value="suspended" class="mr-2">
                        <span class="text-sm">Suspended</span>
                    </label>
                </div>
            </div>

            <!-- Reset Password Section -->
            <div class="border-t pt-4">
                <button type="button" onclick="showResetPasswordModal()"
                        class="text-blue-600 hover:underline text-sm">
                    Reset Password
                </button>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
            <button type="button" onclick="document.getElementById('edit-user-modal').close()"
                    class="btn">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</dialog>

<!-- Reset Password Modal -->
<dialog id="reset-password-modal" class="rounded-xl shadow-xl w-full max-w-sm p-0 backdrop:bg-black/50">
    <form method="post" class="p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="user_id" id="reset-user-id">

        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold">Reset Password</h2>
            <button type="button" onclick="document.getElementById('reset-password-modal').close()"
                    class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <p class="text-sm text-gray-600 mb-4">
            Enter a new password or leave blank to auto-generate one. An email will be sent to the user with the new credentials.
        </p>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
            <input type="password" name="new_password"
                   placeholder="Leave blank to auto-generate"
                   class="w-full border rounded-lg px-3 py-2">
        </div>

        <div class="flex justify-end gap-3">
            <button type="button" onclick="document.getElementById('reset-password-modal').close()"
                    class="btn">Cancel</button>
            <button type="submit" class="btn btn-primary">Reset Password</button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<script>
// Toggle password field based on selection
function togglePasswordField(prefix) {
    const mode = document.querySelector(`input[name="password_mode"]:checked`).value;
    const field = document.getElementById(prefix + '-password-field');
    const input = document.getElementById(prefix + '-password');

    if (mode === 'manual') {
        field.classList.remove('hidden');
        input.required = true;
    } else {
        field.classList.add('hidden');
        input.required = false;
    }
}

// Toggle sales options based on role
function toggleSalesOptions(prefix) {
    const role = document.getElementById(prefix + '-user-role')?.value ||
                 document.getElementById(prefix + '-role')?.value;
    const options = document.getElementById(prefix + '-sales-options');

    if (role === 'sales') {
        options?.classList.remove('hidden');
    } else {
        options?.classList.add('hidden');
    }
}

// Toggle commission fields based on rep portal checkbox
function toggleCommissionFields(prefix) {
    const enabled = document.getElementById(prefix + '-enable-rep-portal').checked;
    const fields = document.getElementById(prefix + '-commission-fields');

    if (enabled) {
        fields.classList.remove('hidden');
    } else {
        fields.classList.add('hidden');
    }
}

// Open edit modal with user data
function openEditModal(user) {
    document.getElementById('edit-user-id').value = user.id;
    document.getElementById('edit-name').value = user.name;
    document.getElementById('edit-email').value = user.email;
    document.getElementById('edit-phone').value = user.phone || '';
    document.getElementById('edit-role').value = user.role;

    // Set status
    if (user.status === 'suspended') {
        document.getElementById('edit-status-suspended').checked = true;
    } else {
        document.getElementById('edit-status-active').checked = true;
    }

    // Toggle sales options
    toggleSalesOptions('edit');

    // Handle rep portal settings for sales role
    if (user.role === 'sales') {
        const repPortalCheckbox = document.getElementById('edit-enable-rep-portal');
        if (repPortalCheckbox) {
            repPortalCheckbox.checked = user.has_rep_view == 1;
            toggleCommissionFields('edit');
        }
        // Set commission rates if available
        if (user.direct_rate) {
            document.getElementById('edit-direct-rate').value = (user.direct_rate * 100).toFixed(1);
        }
        if (user.override_rate) {
            document.getElementById('edit-override-rate').value = (user.override_rate * 100).toFixed(1);
        }
    }

    // Store user ID for reset password
    document.getElementById('reset-user-id').value = user.id;

    document.getElementById('edit-user-modal').showModal();
}

// Show reset password modal
function showResetPasswordModal() {
    document.getElementById('edit-user-modal').close();
    document.getElementById('reset-password-modal').showModal();
}

// Add role for form handling
document.addEventListener('DOMContentLoaded', function() {
    // Set IDs for add form elements
    const addRoleSelect = document.querySelector('#add-user-modal select[name="role"]');
    if (addRoleSelect) addRoleSelect.id = 'add-user-role';
});
</script>

<?php include __DIR__ . '/../_footer.php'; ?>

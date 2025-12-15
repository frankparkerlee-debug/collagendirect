<?php
/**
 * Practice Management - Phase 10c
 *
 * Comprehensive practice, location, and practice user management.
 * Preserves all existing functionality from users.php while adding
 * new organized structure.
 *
 * Sub-tabs:
 * - Practices (default) - List/manage practices
 * - Locations - Flat view of all locations
 * - Practice Users - Flat view of practice admins/physicians
 */
declare(strict_types=1);
require __DIR__ . '/../auth.php';
require_admin();

// Sales reps cannot access admin settings
if (function_exists('deny_sales_rep')) deny_sales_rep();

// Load permission helper
require_once __DIR__ . '/../lib/permissions.php';

// Check permission
if (!has_permission('admin_settings.practices.view')) {
    header('Location: /admin/index.php');
    exit;
}

require_once __DIR__ . '/../../api/lib/email_notifications.php';
require_once __DIR__ . '/../config.php';

$admin = current_admin();
$adminRole = $admin['role'] ?? '';
$isSuperadmin = $adminRole === 'superadmin';
$isAdmin = in_array($adminRole, ['owner', 'superadmin', 'admin']);
$isManufacturer = $adminRole === 'manufacturer';
$canManage = has_permission('admin_settings.practices.manage', 'full');

// Sub-tab selection
$subtab = $_GET['subtab'] ?? 'practices';
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';

// Get active sales reps for assignment dropdown
$activeReps = [];
try {
    $activeReps = $pdo->query("
        SELECT sr.id, sr.user_id, u.first_name, u.last_name
        FROM sales_reps sr
        JOIN users u ON u.id = sr.user_id
        WHERE sr.status = 'active'
        ORDER BY u.first_name, u.last_name
    ")->fetchAll();
} catch (Throwable $e) {
    // Table may not exist yet
}

// Filters
$repFilter = $_GET['rep'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$stateFilter = $_GET['state'] ?? '';
$search = $_GET['search'] ?? '';

// US States array
$states = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'];

// ============================================================
// HANDLE POST ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // Permission check for management actions
    if (in_array($action, ['create_practice', 'update_practice', 'delete_practice', 'create_location', 'update_location', 'delete_location', 'create_user', 'update_user', 'delete_user', 'reset_password'])) {
        if (!$canManage) {
            header('Location: /admin/platform/practices.php?subtab=' . $subtab . '&error=' . urlencode('Permission denied'));
            exit;
        }
    }

    try {
        switch ($action) {
            // ========== PRACTICE ACTIONS ==========
            case 'create_practice':
                $practiceId = bin2hex(random_bytes(16));
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("
                    INSERT INTO users (id, email, password_hash, first_name, last_name, practice_name, phone,
                                       address, city, state, zip, role, account_type, status, assigned_rep_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'practice_admin', ?, 'active', ?, NOW())
                ");
                $stmt->execute([
                    $practiceId,
                    trim($_POST['email']),
                    $password,
                    trim($_POST['first_name']),
                    trim($_POST['last_name']),
                    trim($_POST['practice_name']),
                    trim($_POST['phone'] ?? ''),
                    trim($_POST['address'] ?? ''),
                    trim($_POST['city'] ?? ''),
                    trim($_POST['state'] ?? ''),
                    trim($_POST['zip'] ?? ''),
                    $_POST['account_type'] ?? 'referral',
                    $_POST['assigned_rep_id'] ?: null
                ]);

                // Create primary location if address provided
                if (!empty($_POST['address'])) {
                    $locStmt = $pdo->prepare("
                        INSERT INTO practice_locations (user_id, location_name, address, city, state, zip, phone, is_primary, is_active)
                        VALUES (?, 'Main Office', ?, ?, ?, ?, ?, TRUE, TRUE)
                    ");
                    $locStmt->execute([
                        $practiceId,
                        trim($_POST['address']),
                        trim($_POST['city'] ?? ''),
                        trim($_POST['state'] ?? ''),
                        trim($_POST['zip'] ?? ''),
                        trim($_POST['phone'] ?? '')
                    ]);
                }

                $msg = 'Practice created successfully';
                break;

            case 'update_practice':
                $fields = ['first_name', 'last_name', 'email', 'practice_name', 'phone', 'address', 'city', 'state', 'zip', 'account_type', 'npi', 'ptan', 'status'];
                $updates = [];
                $params = [];

                foreach ($fields as $f) {
                    if (isset($_POST[$f])) {
                        $updates[] = "$f = ?";
                        $params[] = trim($_POST[$f]);
                    }
                }

                // Handle assigned_rep_id
                if (isset($_POST['assigned_rep_id']) && ($isSuperadmin || $isManufacturer)) {
                    $updates[] = "assigned_rep_id = ?";
                    $params[] = $_POST['assigned_rep_id'] ?: null;
                }

                if (!empty($updates)) {
                    $params[] = $_POST['practice_id'];
                    $sql = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
                    $pdo->prepare($sql)->execute($params);
                }

                $msg = 'Practice updated successfully';
                break;

            case 'delete_practice':
                // Soft delete - just set status to deleted
                $pdo->prepare("UPDATE users SET status = 'deleted', updated_at = NOW() WHERE id = ?")->execute([$_POST['practice_id']]);
                $msg = 'Practice deleted';
                break;

            // ========== LOCATION ACTIONS ==========
            case 'create_location':
                $stmt = $pdo->prepare("
                    INSERT INTO practice_locations (user_id, location_name, address, city, state, zip, phone, is_primary, is_active)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE)
                ");
                $isPrimary = isset($_POST['is_primary']) ? 1 : 0;

                // If setting as primary, unset other primaries first
                if ($isPrimary) {
                    $pdo->prepare("UPDATE practice_locations SET is_primary = FALSE WHERE user_id = ?")->execute([$_POST['user_id']]);
                }

                $stmt->execute([
                    $_POST['user_id'],
                    trim($_POST['location_name']),
                    trim($_POST['address']),
                    trim($_POST['city']),
                    trim($_POST['state']),
                    trim($_POST['zip']),
                    trim($_POST['phone'] ?? ''),
                    $isPrimary
                ]);
                $msg = 'Location created successfully';
                $subtab = 'locations';
                break;

            case 'update_location':
                $isPrimary = isset($_POST['is_primary']) ? 1 : 0;
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                // Get current location's user_id
                $locInfo = $pdo->prepare("SELECT user_id FROM practice_locations WHERE id = ?");
                $locInfo->execute([$_POST['location_id']]);
                $locUserId = $locInfo->fetchColumn();

                // If setting as primary, unset other primaries first
                if ($isPrimary && $locUserId) {
                    $pdo->prepare("UPDATE practice_locations SET is_primary = FALSE WHERE user_id = ? AND id != ?")->execute([$locUserId, $_POST['location_id']]);
                }

                $stmt = $pdo->prepare("
                    UPDATE practice_locations
                    SET location_name = ?, address = ?, city = ?, state = ?, zip = ?, phone = ?, is_primary = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    trim($_POST['location_name']),
                    trim($_POST['address']),
                    trim($_POST['city']),
                    trim($_POST['state']),
                    trim($_POST['zip']),
                    trim($_POST['phone'] ?? ''),
                    $isPrimary,
                    $isActive,
                    $_POST['location_id']
                ]);
                $msg = 'Location updated successfully';
                $subtab = 'locations';
                break;

            case 'delete_location':
                // Check if it's the last location
                $locInfo = $pdo->prepare("SELECT user_id FROM practice_locations WHERE id = ?");
                $locInfo->execute([$_POST['location_id']]);
                $locUserId = $locInfo->fetchColumn();

                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM practice_locations WHERE user_id = ? AND is_active = TRUE");
                $countStmt->execute([$locUserId]);
                $locCount = $countStmt->fetchColumn();

                if ($locCount <= 1) {
                    $error = 'Cannot delete the last location for a practice';
                } else {
                    if ($isSuperadmin) {
                        $pdo->prepare("DELETE FROM practice_locations WHERE id = ?")->execute([$_POST['location_id']]);
                    } else {
                        $pdo->prepare("UPDATE practice_locations SET is_active = FALSE, updated_at = NOW() WHERE id = ?")->execute([$_POST['location_id']]);
                    }
                    $msg = 'Location deleted';
                }
                $subtab = 'locations';
                break;

            // ========== USER ACTIONS ==========
            case 'create_user':
                $userId = bin2hex(random_bytes(16));
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $role = $_POST['security_role'] === 'practice_manager' ? 'practice_admin' : 'physician';

                // Get practice info if adding to existing practice
                $practiceInfo = null;
                if (!empty($_POST['practice_id'])) {
                    $pStmt = $pdo->prepare("SELECT practice_name, address, city, state, zip, phone FROM users WHERE id = ?");
                    $pStmt->execute([$_POST['practice_id']]);
                    $practiceInfo = $pStmt->fetch();
                }

                $stmt = $pdo->prepare("
                    INSERT INTO users (id, email, password_hash, first_name, last_name, practice_name, phone,
                                       address, city, state, zip, role, account_type, status, npi, ptan,
                                       license, license_state, license_expiry, assigned_rep_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $userId,
                    trim($_POST['email']),
                    $password,
                    trim($_POST['first_name']),
                    trim($_POST['last_name']),
                    $practiceInfo ? $practiceInfo['practice_name'] : trim($_POST['practice_name'] ?? ''),
                    $practiceInfo ? $practiceInfo['phone'] : trim($_POST['phone'] ?? ''),
                    $practiceInfo ? $practiceInfo['address'] : trim($_POST['address'] ?? ''),
                    $practiceInfo ? $practiceInfo['city'] : trim($_POST['city'] ?? ''),
                    $practiceInfo ? $practiceInfo['state'] : trim($_POST['state'] ?? ''),
                    $practiceInfo ? $practiceInfo['zip'] : trim($_POST['zip'] ?? ''),
                    $role,
                    $_POST['account_type'] ?? 'referral',
                    trim($_POST['npi'] ?? ''),
                    trim($_POST['ptan'] ?? ''),
                    trim($_POST['license'] ?? ''),
                    trim($_POST['license_state'] ?? ''),
                    $_POST['license_expiry'] ?: null,
                    $_POST['assigned_rep_id'] ?: null
                ]);

                $msg = 'User created successfully';
                $subtab = 'users';
                break;

            case 'update_user':
                $fields = ['first_name', 'last_name', 'email', 'phone', 'npi', 'ptan', 'license', 'license_state', 'license_expiry', 'status', 'account_type'];
                $updates = [];
                $params = [];

                foreach ($fields as $f) {
                    if (isset($_POST[$f])) {
                        $updates[] = "$f = ?";
                        $params[] = $_POST[$f] === '' ? null : trim($_POST[$f]);
                    }
                }

                if (!empty($updates)) {
                    $params[] = $_POST['user_id'];
                    $sql = "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
                    $pdo->prepare($sql)->execute($params);
                }

                $msg = 'User updated successfully';
                $subtab = 'users';
                break;

            case 'delete_user':
                // Check if last practice admin
                $userInfo = $pdo->prepare("SELECT role, practice_name FROM users WHERE id = ?");
                $userInfo->execute([$_POST['user_id']]);
                $user = $userInfo->fetch();

                if ($user && $user['role'] === 'practice_admin') {
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE practice_name = ? AND role = 'practice_admin' AND status = 'active'");
                    $countStmt->execute([$user['practice_name']]);
                    if ($countStmt->fetchColumn() <= 1) {
                        $error = 'Cannot delete the last practice manager';
                        break;
                    }
                }

                $pdo->prepare("UPDATE users SET status = 'deleted', updated_at = NOW() WHERE id = ?")->execute([$_POST['user_id']]);
                $msg = 'User deleted';
                $subtab = 'users';
                break;

            case 'reset_password':
                if (!empty($_POST['new_password'])) {
                    $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?")->execute([$hash, $_POST['user_id']]);
                    $msg = 'Password reset successfully';
                }
                $subtab = 'users';
                break;

            case 'assign_rep':
                if ($isSuperadmin || $isManufacturer) {
                    $pdo->prepare("UPDATE users SET assigned_rep_id = ?, rep_assignment_date = NOW(), rep_assigned_by = 'admin_assign', updated_at = NOW() WHERE id = ?")
                        ->execute([$_POST['rep_id'] ?: null, $_POST['practice_id']]);
                    $msg = 'Representative assigned successfully';
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
        error_log("Practice Management Error: " . $e->getMessage());
    }

    // Redirect to prevent form resubmission
    $redirectParams = ['subtab' => $subtab];
    if ($msg) $redirectParams['msg'] = $msg;
    if ($error) $redirectParams['error'] = $error;
    header('Location: /admin/platform/practices.php?' . http_build_query($redirectParams));
    exit;
}

// ============================================================
// LOAD DATA
// ============================================================

// Practices list (practice_admin users)
$practicesQuery = "
    SELECT u.id, u.first_name, u.last_name, u.email, u.practice_name, u.phone,
           u.address, u.city, u.state, u.zip, u.account_type, u.status, u.npi, u.ptan,
           u.created_at, u.assigned_rep_id,
           (SELECT COUNT(*) FROM practice_locations pl WHERE pl.user_id = u.id AND pl.is_active = TRUE) as location_count,
           (SELECT COUNT(*) FROM users u2 WHERE u2.practice_name = u.practice_name AND u2.role IN ('physician', 'practice_admin') AND u2.status = 'active') as user_count,
           CASE WHEN sr.id IS NOT NULL THEN CONCAT(rep_user.first_name, ' ', rep_user.last_name) ELSE NULL END as rep_name
    FROM users u
    LEFT JOIN sales_reps sr ON sr.id = u.assigned_rep_id
    LEFT JOIN users rep_user ON rep_user.id = sr.user_id
    WHERE u.role = 'practice_admin'
";

$practiceParams = [];
if ($statusFilter) {
    $practicesQuery .= " AND u.status = ?";
    $practiceParams[] = $statusFilter;
} else {
    $practicesQuery .= " AND u.status != 'deleted'";
}
if ($repFilter === 'unassigned') {
    $practicesQuery .= " AND u.assigned_rep_id IS NULL";
} elseif ($repFilter) {
    $practicesQuery .= " AND u.assigned_rep_id = ?";
    $practiceParams[] = $repFilter;
}
if ($stateFilter) {
    $practicesQuery .= " AND u.state = ?";
    $practiceParams[] = $stateFilter;
}
if ($search) {
    $practicesQuery .= " AND (u.practice_name ILIKE ? OR u.email ILIKE ? OR u.npi ILIKE ?)";
    $searchTerm = '%' . $search . '%';
    $practiceParams[] = $searchTerm;
    $practiceParams[] = $searchTerm;
    $practiceParams[] = $searchTerm;
}
$practicesQuery .= " ORDER BY u.practice_name ASC LIMIT 300";

$practicesStmt = $pdo->prepare($practicesQuery);
$practicesStmt->execute($practiceParams);
$practices = $practicesStmt->fetchAll();

// Locations list
$locationsQuery = "
    SELECT pl.*, u.practice_name, u.first_name as owner_first, u.last_name as owner_last
    FROM practice_locations pl
    JOIN users u ON u.id = pl.user_id
    WHERE u.role = 'practice_admin'
";
if ($search && $subtab === 'locations') {
    $locationsQuery .= " AND (u.practice_name ILIKE ? OR pl.location_name ILIKE ? OR pl.city ILIKE ?)";
}
$locationsQuery .= " ORDER BY u.practice_name, pl.is_primary DESC, pl.location_name LIMIT 500";

if ($search && $subtab === 'locations') {
    $locStmt = $pdo->prepare($locationsQuery);
    $searchTerm = '%' . $search . '%';
    $locStmt->execute([$searchTerm, $searchTerm, $searchTerm]);
} else {
    $locStmt = $pdo->query($locationsQuery);
}
$locations = $locStmt->fetchAll();

// Practice Users list (physicians and practice_admins)
$usersQuery = "
    SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.practice_name,
           u.role, u.status, u.npi, u.ptan, u.license, u.license_state, u.license_expiry,
           u.account_type, u.created_at, u.last_login_at
    FROM users u
    WHERE u.role IN ('physician', 'practice_admin')
";
if ($statusFilter && $subtab === 'users') {
    $usersQuery .= " AND u.status = ?";
}
if ($search && $subtab === 'users') {
    $usersQuery .= " AND (u.first_name ILIKE ? OR u.last_name ILIKE ? OR u.email ILIKE ? OR u.practice_name ILIKE ?)";
}
$usersQuery .= " ORDER BY u.practice_name, u.role DESC, u.first_name LIMIT 500";

$userParams = [];
if ($statusFilter && $subtab === 'users') $userParams[] = $statusFilter;
if ($search && $subtab === 'users') {
    $searchTerm = '%' . $search . '%';
    $userParams = array_merge($userParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}
$usersStmt = $pdo->prepare($usersQuery);
$usersStmt->execute($userParams);
$practiceUsers = $usersStmt->fetchAll();

// Get practices list for dropdowns
$practicesList = $pdo->query("
    SELECT id, practice_name, CONCAT(first_name, ' ', last_name) as owner_name
    FROM users
    WHERE role = 'practice_admin' AND status = 'active'
    ORDER BY practice_name
")->fetchAll();

include __DIR__ . '/../_header.php';
?>

<div class="flex items-center justify-between mb-4">
    <div>
        <h1 class="text-xl font-semibold">Practice Management</h1>
        <p class="text-sm text-slate-500">Manage practices, locations, and practice users</p>
    </div>
</div>

<?php if ($msg): ?>
<div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">
    <?= e($msg) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
    <?= e($error) ?>
</div>
<?php endif; ?>

<!-- Sub-tabs -->
<div class="mb-4 border-b">
    <a class="inline-block px-4 py-2 border-b-2 <?= $subtab === 'practices' ? 'border-brand text-brand font-medium' : 'border-transparent text-slate-600 hover:text-slate-900' ?>"
       href="/admin/platform/practices.php?subtab=practices">Practices</a>
    <a class="inline-block px-4 py-2 border-b-2 <?= $subtab === 'locations' ? 'border-brand text-brand font-medium' : 'border-transparent text-slate-600 hover:text-slate-900' ?>"
       href="/admin/platform/practices.php?subtab=locations">Locations</a>
    <a class="inline-block px-4 py-2 border-b-2 <?= $subtab === 'users' ? 'border-brand text-brand font-medium' : 'border-transparent text-slate-600 hover:text-slate-900' ?>"
       href="/admin/platform/practices.php?subtab=users">Practice Users</a>
</div>

<?php if ($subtab === 'practices'): ?>
<!-- ============================================================ -->
<!-- PRACTICES TAB -->
<!-- ============================================================ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <!-- Filters -->
        <form method="get" class="mb-4 flex flex-wrap gap-3 items-center">
            <input type="hidden" name="subtab" value="practices">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search name, email, NPI..."
                   class="border rounded px-3 py-1.5 text-sm w-48">

            <select name="rep" class="border rounded px-3 py-1.5 text-sm">
                <option value="">All Reps</option>
                <option value="unassigned" <?= $repFilter === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                <?php foreach ($activeReps as $rep): ?>
                    <option value="<?= e($rep['id']) ?>" <?= $repFilter === $rep['id'] ? 'selected' : '' ?>><?= e($rep['first_name'] . ' ' . $rep['last_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <select name="status" class="border rounded px-3 py-1.5 text-sm">
                <option value="">All Status</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>

            <select name="state" class="border rounded px-3 py-1.5 text-sm">
                <option value="">All States</option>
                <?php foreach ($states as $st): ?>
                    <option value="<?= $st ?>" <?= $stateFilter === $st ? 'selected' : '' ?>><?= $st ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded text-sm">Filter</button>
            <?php if ($search || $repFilter || $statusFilter || $stateFilter): ?>
                <a href="/admin/platform/practices.php?subtab=practices" class="text-sm text-slate-500 hover:underline">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Practices Table -->
        <div class="bg-white border rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Practice</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Location</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Type</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Rep</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Stats</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Status</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($practices)): ?>
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No practices found</td></tr>
                    <?php endif; ?>
                    <?php foreach ($practices as $p): ?>
                    <tr class="border-b hover:bg-slate-50" id="practice-row-<?= e($p['id']) ?>">
                        <td class="px-4 py-3">
                            <div class="font-medium"><?= e($p['practice_name'] ?: 'Unnamed Practice') ?></div>
                            <div class="text-xs text-slate-500"><?= e($p['email']) ?></div>
                            <?php if ($p['npi']): ?>
                                <div class="text-xs text-slate-400">NPI: <?= e($p['npi']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <?php if ($p['city'] && $p['state']): ?>
                                <?= e($p['city']) ?>, <?= e($p['state']) ?>
                            <?php else: ?>
                                <span class="text-slate-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <?php
                            $typeLabel = ucfirst($p['account_type'] ?? 'referral');
                            if ($p['account_type'] === 'both') $typeLabel = 'Both';
                            ?>
                            <span class="text-xs"><?= e($typeLabel) ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($p['rep_name']): ?>
                                <span class="text-xs text-teal-600 font-medium"><?= e($p['rep_name']) ?></span>
                            <?php else: ?>
                                <span class="text-xs text-slate-400">Unassigned</span>
                            <?php endif; ?>
                            <?php if ($isSuperadmin || $isManufacturer): ?>
                                <button onclick="showRepModal('<?= e($p['id']) ?>', '<?= e(addslashes($p['practice_name'])) ?>', '<?= e($p['assigned_rep_id'] ?? '') ?>')"
                                        class="ml-1 text-blue-600 text-xs hover:underline">[change]</button>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <span title="Locations"><?= (int)$p['location_count'] ?> loc</span> /
                            <span title="Users"><?= (int)$p['user_count'] ?> users</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                <?= $p['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                <?= e($p['status'] ?? 'unknown') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <button onclick="togglePracticeDetails('<?= e($p['id']) ?>')" class="text-blue-600 text-xs hover:underline">Edit</button>
                            <?php if ($canManage): ?>
                            <form method="post" class="inline ml-2" onsubmit="return confirm('Delete this practice?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_practice">
                                <input type="hidden" name="practice_id" value="<?= e($p['id']) ?>">
                                <button class="text-red-600 text-xs hover:underline">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <!-- Expandable Details Row -->
                    <tr id="practice-details-<?= e($p['id']) ?>" class="hidden bg-slate-50">
                        <td colspan="7" class="px-4 py-4">
                            <form method="post" class="grid grid-cols-3 gap-4">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_practice">
                                <input type="hidden" name="practice_id" value="<?= e($p['id']) ?>">

                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">Practice Name</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" name="practice_name" value="<?= e($p['practice_name'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">Owner First Name</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" name="first_name" value="<?= e($p['first_name'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">Owner Last Name</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" name="last_name" value="<?= e($p['last_name'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">Email</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" type="email" name="email" value="<?= e($p['email'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">Phone</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" name="phone" value="<?= e($p['phone'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">NPI</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" name="npi" value="<?= e($p['npi'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">Address</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" name="address" value="<?= e($p['address'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">City</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" name="city" value="<?= e($p['city'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">State</label>
                                    <select class="border rounded px-2 py-1 w-full text-sm" name="state">
                                        <option value="">Select</option>
                                        <?php foreach ($states as $st): ?>
                                            <option value="<?= $st ?>" <?= ($p['state'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">ZIP</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" name="zip" value="<?= e($p['zip'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">Account Type</label>
                                    <select class="border rounded px-2 py-1 w-full text-sm" name="account_type">
                                        <option value="referral" <?= ($p['account_type'] ?? '') === 'referral' ? 'selected' : '' ?>>Referral</option>
                                        <option value="wholesale" <?= ($p['account_type'] ?? '') === 'wholesale' ? 'selected' : '' ?>>Wholesale</option>
                                        <option value="both" <?= ($p['account_type'] ?? '') === 'both' ? 'selected' : '' ?>>Both</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">Status</label>
                                    <select class="border rounded px-2 py-1 w-full text-sm" name="status">
                                        <option value="active" <?= ($p['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="pending" <?= ($p['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="inactive" <?= ($p['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>

                                <div class="col-span-3 flex gap-2 mt-2">
                                    <button type="submit" class="bg-brand text-white rounded px-4 py-1.5 text-sm">Save Changes</button>
                                    <button type="button" onclick="togglePracticeDetails('<?= e($p['id']) ?>')" class="bg-slate-200 rounded px-4 py-1.5 text-sm">Cancel</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Practice Form -->
    <?php if ($canManage): ?>
    <div>
        <div class="bg-white border rounded-lg p-4">
            <h3 class="font-semibold mb-3">Add Practice</h3>
            <form method="post" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_practice">

                <div>
                    <label class="text-xs text-slate-600 block mb-1">Practice Name *</label>
                    <input class="border rounded px-2 py-1.5 w-full text-sm" name="practice_name" required>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-slate-600 block mb-1">Owner First Name *</label>
                        <input class="border rounded px-2 py-1.5 w-full text-sm" name="first_name" required>
                    </div>
                    <div>
                        <label class="text-xs text-slate-600 block mb-1">Last Name *</label>
                        <input class="border rounded px-2 py-1.5 w-full text-sm" name="last_name" required>
                    </div>
                </div>

                <div>
                    <label class="text-xs text-slate-600 block mb-1">Email *</label>
                    <input class="border rounded px-2 py-1.5 w-full text-sm" type="email" name="email" required>
                </div>

                <div>
                    <label class="text-xs text-slate-600 block mb-1">Phone</label>
                    <input class="border rounded px-2 py-1.5 w-full text-sm" name="phone">
                </div>

                <div>
                    <label class="text-xs text-slate-600 block mb-1">Address</label>
                    <input class="border rounded px-2 py-1.5 w-full text-sm" name="address">
                </div>

                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="text-xs text-slate-600 block mb-1">City</label>
                        <input class="border rounded px-2 py-1.5 w-full text-sm" name="city">
                    </div>
                    <div>
                        <label class="text-xs text-slate-600 block mb-1">State</label>
                        <select class="border rounded px-2 py-1.5 w-full text-sm" name="state">
                            <option value="">-</option>
                            <?php foreach ($states as $st): ?>
                                <option value="<?= $st ?>"><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-600 block mb-1">ZIP</label>
                        <input class="border rounded px-2 py-1.5 w-full text-sm" name="zip">
                    </div>
                </div>

                <div>
                    <label class="text-xs text-slate-600 block mb-1">Account Type *</label>
                    <select class="border rounded px-2 py-1.5 w-full text-sm" name="account_type" required>
                        <option value="referral">Referral</option>
                        <option value="wholesale">Wholesale</option>
                        <option value="both">Referral & Wholesale</option>
                    </select>
                </div>

                <?php if (($isSuperadmin || $isManufacturer) && !empty($activeReps)): ?>
                <div>
                    <label class="text-xs text-slate-600 block mb-1">Assign to Rep</label>
                    <select class="border rounded px-2 py-1.5 w-full text-sm" name="assigned_rep_id">
                        <option value="">Unassigned</option>
                        <?php foreach ($activeReps as $rep): ?>
                            <option value="<?= e($rep['id']) ?>"><?= e($rep['first_name'] . ' ' . $rep['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div>
                    <label class="text-xs text-slate-600 block mb-1">Temporary Password *</label>
                    <input class="border rounded px-2 py-1.5 w-full text-sm" type="password" name="password" required>
                </div>

                <button type="submit" class="w-full bg-brand text-white rounded py-2 text-sm font-medium hover:bg-teal-600">
                    Create Practice
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($subtab === 'locations'): ?>
<!-- ============================================================ -->
<!-- LOCATIONS TAB -->
<!-- ============================================================ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <!-- Search -->
        <form method="get" class="mb-4 flex gap-3 items-center">
            <input type="hidden" name="subtab" value="locations">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search practice, location, city..."
                   class="border rounded px-3 py-1.5 text-sm w-64">
            <button type="submit" class="bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded text-sm">Search</button>
            <?php if ($search): ?>
                <a href="/admin/platform/practices.php?subtab=locations" class="text-sm text-slate-500 hover:underline">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Locations Table -->
        <div class="bg-white border rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Practice</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Location Name</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Address</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Phone</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Primary</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Status</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($locations)): ?>
                        <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No locations found</td></tr>
                    <?php endif; ?>
                    <?php foreach ($locations as $loc): ?>
                    <tr class="border-b hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-medium"><?= e($loc['practice_name'] ?? '') ?></div>
                        </td>
                        <td class="px-4 py-3"><?= e($loc['location_name']) ?></td>
                        <td class="px-4 py-3 text-xs">
                            <?= e($loc['address']) ?><br>
                            <?= e($loc['city']) ?>, <?= e($loc['state']) ?> <?= e($loc['zip']) ?>
                        </td>
                        <td class="px-4 py-3 text-xs"><?= e($loc['phone'] ?? '-') ?></td>
                        <td class="px-4 py-3"><?= $loc['is_primary'] ? '<span class="text-green-600">Yes</span>' : '' ?></td>
                        <td class="px-4 py-3">
                            <span class="<?= $loc['is_active'] ? 'text-green-600' : 'text-slate-400' ?>">
                                <?= $loc['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <?php if ($canManage): ?>
                            <button onclick="editLocationModal(<?= htmlspecialchars(json_encode($loc), ENT_QUOTES, 'UTF-8') ?>)"
                                    class="text-blue-600 text-xs hover:underline">Edit</button>
                            <form method="post" class="inline ml-2" onsubmit="return confirm('Delete this location?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_location">
                                <input type="hidden" name="location_id" value="<?= e($loc['id']) ?>">
                                <button class="text-red-600 text-xs hover:underline">Delete</button>
                            </form>
                            <?php else: ?>
                            <span class="text-slate-400 text-xs">View only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Location Form -->
    <?php if ($canManage): ?>
    <div>
        <div class="bg-white border rounded-lg p-4">
            <h3 class="font-semibold mb-3" id="location-form-title">Add Location</h3>
            <form method="post" class="space-y-3" id="location-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_location" id="location-action">
                <input type="hidden" name="location_id" value="" id="edit-location-id">

                <div>
                    <label class="text-xs text-slate-600 block mb-1">Practice *</label>
                    <select class="border rounded px-2 py-1.5 w-full text-sm" name="user_id" id="location-user-id" required>
                        <option value="">Select Practice</option>
                        <?php foreach ($practicesList as $p): ?>
                            <option value="<?= e($p['id']) ?>"><?= e($p['practice_name']) ?> (<?= e($p['owner_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="text-xs text-slate-600 block mb-1">Location Name *</label>
                    <input class="border rounded px-2 py-1.5 w-full text-sm" name="location_name" id="location-name" required>
                </div>

                <div>
                    <label class="text-xs text-slate-600 block mb-1">Address *</label>
                    <input class="border rounded px-2 py-1.5 w-full text-sm" name="address" id="location-address" required>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-slate-600 block mb-1">City *</label>
                        <input class="border rounded px-2 py-1.5 w-full text-sm" name="city" id="location-city" required>
                    </div>
                    <div>
                        <label class="text-xs text-slate-600 block mb-1">State *</label>
                        <select class="border rounded px-2 py-1.5 w-full text-sm" name="state" id="location-state" required>
                            <option value="">-</option>
                            <?php foreach ($states as $st): ?>
                                <option value="<?= $st ?>"><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-slate-600 block mb-1">ZIP *</label>
                        <input class="border rounded px-2 py-1.5 w-full text-sm" name="zip" id="location-zip" required>
                    </div>
                    <div>
                        <label class="text-xs text-slate-600 block mb-1">Phone</label>
                        <input class="border rounded px-2 py-1.5 w-full text-sm" name="phone" id="location-phone">
                    </div>
                </div>

                <div>
                    <label class="flex items-center text-sm">
                        <input type="checkbox" name="is_primary" id="location-is-primary" class="mr-2">
                        Set as primary location
                    </label>
                </div>

                <div id="location-active-field" style="display: none;">
                    <label class="flex items-center text-sm">
                        <input type="checkbox" name="is_active" id="location-is-active" class="mr-2" checked>
                        Active
                    </label>
                </div>

                <button type="submit" class="w-full bg-brand text-white rounded py-2 text-sm font-medium hover:bg-teal-600" id="location-submit-btn">
                    Create Location
                </button>
                <button type="button" onclick="resetLocationForm()" class="w-full bg-slate-100 text-slate-700 rounded py-2 text-sm" id="location-cancel-btn" style="display: none;">
                    Cancel
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($subtab === 'users'): ?>
<!-- ============================================================ -->
<!-- PRACTICE USERS TAB -->
<!-- ============================================================ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <!-- Filters -->
        <form method="get" class="mb-4 flex gap-3 items-center">
            <input type="hidden" name="subtab" value="users">
            <input type="text" name="search" value="<?= e($search) ?>" placeholder="Search name, email, practice..."
                   class="border rounded px-3 py-1.5 text-sm w-48">

            <select name="status" class="border rounded px-3 py-1.5 text-sm">
                <option value="">All Status</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>

            <button type="submit" class="bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded text-sm">Filter</button>
            <?php if ($search || $statusFilter): ?>
                <a href="/admin/platform/practices.php?subtab=users" class="text-sm text-slate-500 hover:underline">Clear</a>
            <?php endif; ?>
        </form>

        <!-- Users Table -->
        <div class="bg-white border rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Name</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Email</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Role</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Practice</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Status</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($practiceUsers)): ?>
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No users found</td></tr>
                    <?php endif; ?>
                    <?php foreach ($practiceUsers as $u): ?>
                    <tr class="border-b hover:bg-slate-50" id="user-row-<?= e($u['id']) ?>">
                        <td class="px-4 py-3">
                            <div class="font-medium"><?= e(trim($u['first_name'] . ' ' . $u['last_name'])) ?></div>
                            <?php if ($u['npi']): ?>
                                <div class="text-xs text-slate-400">NPI: <?= e($u['npi']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-xs"><?= e($u['email']) ?></td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded <?= $u['role'] === 'practice_admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' ?>">
                                <?= $u['role'] === 'practice_admin' ? 'Practice Manager' : 'Physician' ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs"><?= e($u['practice_name'] ?? '-') ?></td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                <?= $u['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                                <?= e($u['status'] ?? 'unknown') ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <button onclick="toggleUserDetails('<?= e($u['id']) ?>')" class="text-blue-600 text-xs hover:underline">Edit</button>
                            <?php if ($canManage): ?>
                            <form method="post" class="inline ml-2" onsubmit="return confirm('Delete this user?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                <button class="text-red-600 text-xs hover:underline">Delete</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <!-- Expandable Details Row -->
                    <tr id="user-details-<?= e($u['id']) ?>" class="hidden bg-slate-50">
                        <td colspan="6" class="px-4 py-4">
                            <form method="post" class="grid grid-cols-3 gap-4">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update_user">
                                <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">

                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">First Name</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" name="first_name" value="<?= e($u['first_name'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">Last Name</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" name="last_name" value="<?= e($u['last_name'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">Email</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" type="email" name="email" value="<?= e($u['email'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">Phone</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" name="phone" value="<?= e($u['phone'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">NPI</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" name="npi" value="<?= e($u['npi'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">PTAN</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" name="ptan" value="<?= e($u['ptan'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">License</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" name="license" value="<?= e($u['license'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">License State</label>
                                    <select class="border rounded px-2 py-1 w-full text-sm" name="license_state">
                                        <option value="">-</option>
                                        <?php foreach ($states as $st): ?>
                                            <option value="<?= $st ?>" <?= ($u['license_state'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">License Expiry</label>
                                    <input class="border rounded px-2 py-1 w-full text-sm" type="date" name="license_expiry" value="<?= e($u['license_expiry'] ?? '') ?>">
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">Account Type</label>
                                    <select class="border rounded px-2 py-1 w-full text-sm" name="account_type">
                                        <option value="referral" <?= ($u['account_type'] ?? '') === 'referral' ? 'selected' : '' ?>>Referral</option>
                                        <option value="wholesale" <?= ($u['account_type'] ?? '') === 'wholesale' ? 'selected' : '' ?>>Wholesale</option>
                                        <option value="both" <?= ($u['account_type'] ?? '') === 'both' ? 'selected' : '' ?>>Both</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-600 block mb-1">Status</label>
                                    <select class="border rounded px-2 py-1 w-full text-sm" name="status">
                                        <option value="active" <?= ($u['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="pending" <?= ($u['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="inactive" <?= ($u['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>

                                <div class="col-span-3 flex gap-2 mt-2">
                                    <button type="submit" class="bg-brand text-white rounded px-4 py-1.5 text-sm">Save Changes</button>
                                    <button type="button" onclick="toggleUserDetails('<?= e($u['id']) ?>')" class="bg-slate-200 rounded px-4 py-1.5 text-sm">Cancel</button>
                                </div>
                            </form>

                            <!-- Password Reset -->
                            <?php if ($canManage): ?>
                            <div class="mt-4 pt-4 border-t">
                                <form method="post" class="flex items-center gap-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?= e($u['id']) ?>">
                                    <input type="password" name="new_password" placeholder="New password" class="border rounded px-2 py-1 text-sm w-40" required>
                                    <button type="submit" class="bg-slate-600 text-white rounded px-3 py-1 text-sm">Reset Password</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add User Form -->
    <?php if ($canManage): ?>
    <div>
        <div class="bg-white border rounded-lg p-4">
            <h3 class="font-semibold mb-3">Add Practice User</h3>
            <form method="post" class="space-y-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_user">

                <div>
                    <label class="text-xs text-slate-600 block mb-1">Add to Practice</label>
                    <select class="border rounded px-2 py-1.5 w-full text-sm" name="practice_id">
                        <option value="">New Practice (enter below)</option>
                        <?php foreach ($practicesList as $p): ?>
                            <option value="<?= e($p['id']) ?>"><?= e($p['practice_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-slate-600 block mb-1">First Name *</label>
                        <input class="border rounded px-2 py-1.5 w-full text-sm" name="first_name" required>
                    </div>
                    <div>
                        <label class="text-xs text-slate-600 block mb-1">Last Name *</label>
                        <input class="border rounded px-2 py-1.5 w-full text-sm" name="last_name" required>
                    </div>
                </div>

                <div>
                    <label class="text-xs text-slate-600 block mb-1">Email *</label>
                    <input class="border rounded px-2 py-1.5 w-full text-sm" type="email" name="email" required>
                </div>

                <div>
                    <label class="text-xs text-slate-600 block mb-1">Role *</label>
                    <select class="border rounded px-2 py-1.5 w-full text-sm" name="security_role" required>
                        <option value="">Select Role</option>
                        <option value="practice_manager">Practice Manager</option>
                        <option value="physician">Physician</option>
                    </select>
                </div>

                <div>
                    <label class="text-xs text-slate-600 block mb-1">Account Type *</label>
                    <select class="border rounded px-2 py-1.5 w-full text-sm" name="account_type" required>
                        <option value="referral">Referral</option>
                        <option value="wholesale">Wholesale</option>
                        <option value="both">Referral & Wholesale</option>
                    </select>
                </div>

                <div>
                    <label class="text-xs text-slate-600 block mb-1">NPI</label>
                    <input class="border rounded px-2 py-1.5 w-full text-sm" name="npi" maxlength="10">
                </div>

                <div>
                    <label class="text-xs text-slate-600 block mb-1">Temporary Password *</label>
                    <input class="border rounded px-2 py-1.5 w-full text-sm" type="password" name="password" required>
                </div>

                <button type="submit" class="w-full bg-brand text-white rounded py-2 text-sm font-medium hover:bg-teal-600">
                    Create User
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Rep Assignment Modal -->
<dialog id="rep-modal" class="rounded-lg shadow-xl p-0 backdrop:bg-black/50" style="max-width: 400px; width: 90%;">
    <form method="post" class="p-6">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assign_rep">
        <input type="hidden" name="practice_id" id="rep-modal-practice-id">

        <h3 class="text-lg font-semibold mb-4">Assign Representative</h3>
        <p class="text-sm text-slate-600 mb-4">Assigning rep to: <strong id="rep-modal-practice-name"></strong></p>

        <select name="rep_id" class="border rounded px-3 py-2 w-full mb-4">
            <option value="">Unassigned</option>
            <?php foreach ($activeReps as $rep): ?>
                <option value="<?= e($rep['id']) ?>"><?= e($rep['first_name'] . ' ' . $rep['last_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <div class="flex justify-end gap-2">
            <button type="button" onclick="document.getElementById('rep-modal').close()" class="px-4 py-2 bg-slate-100 rounded text-sm">Cancel</button>
            <button type="submit" class="px-4 py-2 bg-brand text-white rounded text-sm">Save</button>
        </div>
    </form>
</dialog>

<script>
function togglePracticeDetails(id) {
    const row = document.getElementById('practice-details-' + id);
    if (row) row.classList.toggle('hidden');
}

function toggleUserDetails(id) {
    const row = document.getElementById('user-details-' + id);
    if (row) row.classList.toggle('hidden');
}

function showRepModal(practiceId, practiceName, currentRepId) {
    document.getElementById('rep-modal-practice-id').value = practiceId;
    document.getElementById('rep-modal-practice-name').textContent = practiceName;
    const select = document.querySelector('#rep-modal select[name="rep_id"]');
    if (select) select.value = currentRepId || '';
    document.getElementById('rep-modal').showModal();
}

function editLocationModal(loc) {
    document.getElementById('location-form-title').textContent = 'Edit Location';
    document.getElementById('location-action').value = 'update_location';
    document.getElementById('edit-location-id').value = loc.id;
    document.getElementById('location-user-id').value = loc.user_id;
    document.getElementById('location-user-id').disabled = true;
    document.getElementById('location-name').value = loc.location_name;
    document.getElementById('location-address').value = loc.address;
    document.getElementById('location-city').value = loc.city;
    document.getElementById('location-state').value = loc.state;
    document.getElementById('location-zip').value = loc.zip;
    document.getElementById('location-phone').value = loc.phone || '';
    document.getElementById('location-is-primary').checked = loc.is_primary == 1;
    document.getElementById('location-is-active').checked = loc.is_active == 1;
    document.getElementById('location-active-field').style.display = 'block';
    document.getElementById('location-submit-btn').textContent = 'Update Location';
    document.getElementById('location-cancel-btn').style.display = 'block';
}

function resetLocationForm() {
    document.getElementById('location-form-title').textContent = 'Add Location';
    document.getElementById('location-form').reset();
    document.getElementById('location-action').value = 'create_location';
    document.getElementById('edit-location-id').value = '';
    document.getElementById('location-user-id').disabled = false;
    document.getElementById('location-active-field').style.display = 'none';
    document.getElementById('location-submit-btn').textContent = 'Create Location';
    document.getElementById('location-cancel-btn').style.display = 'none';
}
</script>

<?php include __DIR__ . '/../_footer.php'; ?>

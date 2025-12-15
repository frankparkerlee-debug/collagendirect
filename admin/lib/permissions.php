<?php
/**
 * Permissions Library
 *
 * Granular permission checking system for admin portal.
 * Uses the new permissions tables created in Phase 10b.
 *
 * Usage:
 *   require_once __DIR__ . '/lib/permissions.php';
 *   if (has_permission('referrals.orders.edit')) { ... }
 *   if (can_access('full', 'products.create')) { ... }
 */

declare(strict_types=1);

/**
 * Check if current admin user has a specific permission
 *
 * @param string $permissionKey The permission key (e.g., 'referrals.orders.view')
 * @param string $minLevel Minimum access level required ('view', 'edit', 'full')
 * @return bool
 */
function has_permission(string $permissionKey, string $minLevel = 'view'): bool {
    global $pdo;
    $admin = current_admin();

    if (!$admin) {
        return false;
    }

    $role = $admin['role'] ?? '';

    // Superadmin always has full access
    if ($role === 'superadmin') {
        return true;
    }

    // Check if permissions tables exist (graceful fallback)
    try {
        $tableExists = $pdo->query("
            SELECT EXISTS (
                SELECT FROM information_schema.tables
                WHERE table_name = 'permissions'
            )
        ")->fetchColumn();

        if (!$tableExists) {
            // Fall back to legacy role-based checks
            return legacy_permission_check($role, $permissionKey);
        }
    } catch (Exception $e) {
        return legacy_permission_check($role, $permissionKey);
    }

    // Get permission ID
    $stmt = $pdo->prepare("SELECT id FROM permissions WHERE key = ?");
    $stmt->execute([$permissionKey]);
    $permissionId = $stmt->fetchColumn();

    if (!$permissionId) {
        // Unknown permission - deny by default
        return false;
    }

    // Check for user-specific override first
    $userId = $admin['id'] ?? null;
    if ($userId) {
        $stmt = $pdo->prepare("
            SELECT override_type, access_level
            FROM user_permission_overrides
            WHERE user_id = ? AND permission_id = ?
        ");
        $stmt->execute([$userId, $permissionId]);
        $override = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($override) {
            if ($override['override_type'] === 'revoke') {
                return false;
            }
            if ($override['override_type'] === 'grant') {
                return meets_access_level($override['access_level'], $minLevel);
            }
        }
    }

    // Check role-based permission
    $stmt = $pdo->prepare("
        SELECT access_level
        FROM role_permissions
        WHERE role = ? AND permission_id = ?
    ");
    $stmt->execute([$role, $permissionId]);
    $roleAccess = $stmt->fetchColumn();

    if (!$roleAccess) {
        return false;
    }

    return meets_access_level($roleAccess, $minLevel);
}

/**
 * Check if access level meets minimum requirement
 *
 * @param string $actualLevel The actual access level
 * @param string $requiredLevel The minimum required level
 * @return bool
 */
function meets_access_level(string $actualLevel, string $requiredLevel): bool {
    $levels = [
        'none' => 0,
        'view' => 1,
        'edit' => 2,
        'full' => 3
    ];

    $actual = $levels[$actualLevel] ?? 0;
    $required = $levels[$requiredLevel] ?? 1;

    return $actual >= $required;
}

/**
 * Legacy permission check for backward compatibility
 * Used when permissions tables don't exist
 *
 * @param string $role User role
 * @param string $permissionKey Permission key
 * @return bool
 */
function legacy_permission_check(string $role, string $permissionKey): bool {
    // Define legacy permission rules based on existing code
    $legacyRules = [
        'superadmin' => '*',  // Full access
        'admin' => [
            'dashboard.*',
            'referrals.*',
            'wholesale.*',
            'billing.*',
            'shipments.*',
            'products.*',
            'admin_settings.practices.*',
            'admin_settings.practice_users.*',
            'admin_settings.internal_users.*',
            'admin_settings.distributors.*',
            'admin_settings.roles.view',
            'admin_settings.access',
            'commission.*',
            'messages.*',
            'data_scope.all_practices'
        ],
        'manufacturer' => [
            'dashboard.*',
            'referrals.*',
            'wholesale.*',
            'billing.*',
            'shipments.*',
            'products.*',
            'admin_settings.practices.*',
            'admin_settings.practice_users.*',
            'admin_settings.distributors.*',
            'commission.*',
            'messages.*',
            'data_scope.all_practices'
        ],
        'sales' => [
            'dashboard.view',
            'referrals.*',
            'wholesale.orders.*',
            'admin_settings.distributors.*',
            'admin_settings.practices.*',
            'admin_settings.practice_users.*',
            'admin_settings.access',
            'commission.view_own',
            'messages.*',
            'data_scope.assigned_practices'
        ],
        'employee' => [
            'dashboard.view',
            'referrals.patients.view',
            'referrals.orders.view',
            'messages.*',
            'data_scope.assigned_practices'
        ],
        'ops' => [
            'dashboard.view',
            'shipments.*',
            'referrals.delivery_audit.*',
            'referrals.orders.view',
            'wholesale.orders.view',
            'messages.*',
            'data_scope.all_practices'
        ],
        'sales_rep' => [
            'dashboard.view',
            'referrals.orders.view',
            'wholesale.orders.*',
            'commission.view_own',
            'messages.*',
            'data_scope.assigned_practices'
        ]
    ];

    if (!isset($legacyRules[$role])) {
        return false;
    }

    $rules = $legacyRules[$role];

    // Superadmin has all permissions
    if ($rules === '*') {
        return true;
    }

    // Check each rule
    foreach ($rules as $rule) {
        if (permission_matches($rule, $permissionKey)) {
            return true;
        }
    }

    return false;
}

/**
 * Check if a permission pattern matches a key
 *
 * @param string $pattern Pattern with optional wildcard (*)
 * @param string $key Actual permission key
 * @return bool
 */
function permission_matches(string $pattern, string $key): bool {
    // Exact match
    if ($pattern === $key) {
        return true;
    }

    // Wildcard match (e.g., 'referrals.*' matches 'referrals.orders.view')
    if (str_ends_with($pattern, '.*')) {
        $prefix = substr($pattern, 0, -1); // Remove the '*'
        return str_starts_with($key, $prefix);
    }

    return false;
}

/**
 * Get all permissions for current user organized by category
 *
 * @return array Permissions grouped by category
 */
function get_user_permissions(): array {
    global $pdo;
    $admin = current_admin();

    if (!$admin) {
        return [];
    }

    $role = $admin['role'] ?? '';
    $userId = $admin['id'] ?? null;

    try {
        // Get all permissions with role-based access
        $stmt = $pdo->prepare("
            SELECT
                p.key,
                p.name,
                p.category,
                p.description,
                COALESCE(upo.access_level, rp.access_level, 'none') as access_level,
                CASE WHEN upo.id IS NOT NULL THEN TRUE ELSE FALSE END as has_override,
                upo.override_type
            FROM permissions p
            LEFT JOIN role_permissions rp ON rp.permission_id = p.id AND rp.role = ?
            LEFT JOIN user_permission_overrides upo ON upo.permission_id = p.id AND upo.user_id = ?
            ORDER BY p.category, p.name
        ");
        $stmt->execute([$role, $userId]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by category
        $grouped = [];
        foreach ($permissions as $perm) {
            $category = $perm['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $perm;
        }

        return $grouped;

    } catch (Exception $e) {
        error_log("get_user_permissions error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get all available roles with their permission counts
 *
 * @return array
 */
function get_all_roles(): array {
    global $pdo;

    try {
        $stmt = $pdo->query("
            SELECT
                rp.role,
                COUNT(*) as total_permissions,
                SUM(CASE WHEN rp.access_level = 'full' THEN 1 ELSE 0 END) as full_access,
                SUM(CASE WHEN rp.access_level = 'view' THEN 1 ELSE 0 END) as view_only,
                SUM(CASE WHEN rp.access_level = 'none' THEN 1 ELSE 0 END) as no_access
            FROM role_permissions rp
            GROUP BY rp.role
            ORDER BY rp.role
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Check if current user can perform action (alias for has_permission)
 *
 * @param string $action Permission key
 * @param string $level Access level
 * @return bool
 */
function can(string $action, string $level = 'view'): bool {
    return has_permission($action, $level);
}

/**
 * Require a permission or redirect/die
 *
 * @param string $permissionKey
 * @param string $minLevel
 * @param string|null $redirectUrl Optional redirect URL
 */
function require_permission(string $permissionKey, string $minLevel = 'view', ?string $redirectUrl = null): void {
    if (!has_permission($permissionKey, $minLevel)) {
        if ($redirectUrl) {
            header("Location: $redirectUrl");
            exit;
        }
        http_response_code(403);
        die('Access denied: You do not have permission to access this resource.');
    }
}

/**
 * Get effective permissions for a user (with caching)
 *
 * @param string|int $userId User ID
 * @param string $role User role
 * @return array Permission key => access level
 */
function get_effective_permissions($userId, string $role): array {
    global $pdo;

    // Check session cache first
    $cacheKey = "user_perms_{$userId}";
    if (isset($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey])) {
        return $_SESSION[$cacheKey];
    }

    $permissions = [];

    try {
        // Get role-based permissions
        $stmt = $pdo->prepare("
            SELECT p.key, rp.access_level
            FROM role_permissions rp
            JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role = ?
        ");
        $stmt->execute([$role]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[$row['key']] = $row['access_level'];
        }

        // Apply user overrides
        $stmt = $pdo->prepare("
            SELECT p.key, upo.override_type, upo.access_level
            FROM user_permission_overrides upo
            JOIN permissions p ON p.id = upo.permission_id
            WHERE upo.user_id = ?
        ");
        $stmt->execute([$userId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['override_type'] === 'revoke') {
                $permissions[$row['key']] = 'none';
            } elseif ($row['override_type'] === 'grant') {
                $permissions[$row['key']] = $row['access_level'];
            }
        }

        // Cache in session
        $_SESSION[$cacheKey] = $permissions;

    } catch (Exception $e) {
        error_log("get_effective_permissions error: " . $e->getMessage());
    }

    return $permissions;
}

/**
 * Clear user permission cache
 *
 * @param string|int|null $userId User ID (null clears all)
 */
function clear_permission_cache($userId = null): void {
    if ($userId !== null) {
        unset($_SESSION["user_perms_{$userId}"]);
    } else {
        // Clear all permission caches
        foreach ($_SESSION as $key => $value) {
            if (str_starts_with($key, 'user_perms_')) {
                unset($_SESSION[$key]);
            }
        }
    }
}

/**
 * Check permission using cached effective permissions (faster for multiple checks)
 *
 * @param string $permissionKey
 * @param string $minLevel
 * @return bool
 */
function has_permission_cached(string $permissionKey, string $minLevel = 'view'): bool {
    $admin = current_admin();
    if (!$admin) {
        return false;
    }

    $role = $admin['role'] ?? '';

    // Superadmin always has full access
    if ($role === 'superadmin') {
        return true;
    }

    $userId = $admin['id'] ?? null;
    if (!$userId) {
        return false;
    }

    $permissions = get_effective_permissions($userId, $role);
    $level = $permissions[$permissionKey] ?? 'none';

    return meets_access_level($level, $minLevel);
}

/**
 * Get user override count
 *
 * @param string|int $userId
 * @return int
 */
function get_user_override_count($userId): int {
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_permission_overrides WHERE user_id = ?");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Check if user has any permission overrides
 *
 * @param string|int $userId
 * @return bool
 */
function user_has_overrides($userId): bool {
    return get_user_override_count($userId) > 0;
}

/**
 * Get permission access level for display
 *
 * @param string $permissionKey
 * @return string Access level (none, view, edit, full)
 */
function get_permission_level(string $permissionKey): string {
    $admin = current_admin();
    if (!$admin) {
        return 'none';
    }

    $role = $admin['role'] ?? '';

    // Superadmin always has full access
    if ($role === 'superadmin') {
        return 'full';
    }

    $userId = $admin['id'] ?? null;
    if (!$userId) {
        return 'none';
    }

    $permissions = get_effective_permissions($userId, $role);
    return $permissions[$permissionKey] ?? 'none';
}

<?php
/**
 * Migration: Fix Sales Users Missing has_rep_view Flag
 *
 * This migration sets has_rep_view=true for all admin_users with role='sales'
 * who don't already have the flag set. This is required for proper redirect
 * to the employee-rep portal after login.
 *
 * Run: php admin/migrations/fix_sales_rep_view.php
 * Or visit: /admin/migrations/fix_sales_rep_view.php (as superadmin)
 */
declare(strict_types=1);

// Allow CLI or web execution
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    require_once __DIR__ . '/../auth.php';
    require_admin();

    $admin = current_admin();
    if (($admin['role'] ?? '') !== 'superadmin') {
        die('Access denied. Superadmin required.');
    }
}

require_once __DIR__ . '/../db.php';

function output($message, $isCli) {
    if ($isCli) {
        echo $message . "\n";
    } else {
        echo htmlspecialchars($message) . "<br>\n";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Migration: Fix Sales Rep View</title>";
    echo "<style>body{font-family:monospace;padding:20px;background:#1a1a2e;color:#eee;}</style>";
    echo "</head><body><h1>Migration: Fix Sales Rep View</h1><pre>";
}

output("=== Fix Sales Users Missing has_rep_view Flag ===", $isCli);
output("Started at: " . date('Y-m-d H:i:s'), $isCli);
output("", $isCli);

try {
    // First, show current state
    $stmt = $pdo->query("
        SELECT id, name, email, role, has_rep_view, status
        FROM admin_users
        WHERE role = 'sales'
        ORDER BY name
    ");
    $salesUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    output("Current sales users:", $isCli);
    output(str_repeat("-", 80), $isCli);

    $needsFix = [];
    foreach ($salesUsers as $user) {
        $hasRepView = $user['has_rep_view'] ? 'true' : 'false';
        $status = "[{$user['status']}]";
        $needsFixLabel = !$user['has_rep_view'] ? ' <-- NEEDS FIX' : '';

        output(sprintf(
            "ID: %-4s | %-25s | %-35s | has_rep_view: %-5s %s%s",
            $user['id'],
            substr($user['name'], 0, 25),
            substr($user['email'], 0, 35),
            $hasRepView,
            $status,
            $needsFixLabel
        ), $isCli);

        if (!$user['has_rep_view']) {
            $needsFix[] = $user;
        }
    }

    output("", $isCli);
    output(str_repeat("-", 80), $isCli);
    output("Total sales users: " . count($salesUsers), $isCli);
    output("Users needing fix: " . count($needsFix), $isCli);
    output("", $isCli);

    if (count($needsFix) === 0) {
        output("No users need to be fixed. All sales users already have has_rep_view=true.", $isCli);
    } else {
        // Apply the fix
        output("Applying fix...", $isCli);
        output("", $isCli);

        $updateStmt = $pdo->prepare("
            UPDATE admin_users
            SET has_rep_view = true, updated_at = NOW()
            WHERE id = ?
        ");

        $fixed = 0;
        foreach ($needsFix as $user) {
            $updateStmt->execute([$user['id']]);
            $fixed++;
            output("  Fixed: {$user['name']} ({$user['email']})", $isCli);
        }

        output("", $isCli);
        output("Successfully updated {$fixed} user(s).", $isCli);
        output("", $isCli);
        output("IMPORTANT: These users must log out and log back in for the change to take effect.", $isCli);
    }

    output("", $isCli);
    output("Migration completed at: " . date('Y-m-d H:i:s'), $isCli);

} catch (Exception $e) {
    output("", $isCli);
    output("ERROR: " . $e->getMessage(), $isCli);
    exit(1);
}

if (!$isCli) {
    echo "</pre>";
    echo "<p><a href='/admin/platform/internal-users.php' style='color:#00d4ff;'>Back to Internal Users</a></p>";
    echo "</body></html>";
}

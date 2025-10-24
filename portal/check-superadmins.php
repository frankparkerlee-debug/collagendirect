<?php
declare(strict_types=1);
/**
 * Check Superadmin Users
 *
 * Quick utility to see which superadmin users exist in the system
 */

$SECRET_KEY = getenv('MIGRATION_SECRET') ?: 'change-me-in-production';
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $SECRET_KEY) {
    http_response_code(403);
    die('Access denied. Provide ?key=SECRET in URL');
}

require __DIR__ . '/../api/db.php';

echo "<h1>Superadmin Users Check</h1>";
echo "<style>body { font-family: system-ui; padding: 2rem; } table { border-collapse: collapse; width: 100%; margin: 1rem 0; } th, td { border: 1px solid #ddd; padding: 0.5rem; text-align: left; } th { background: #f0f0f0; }</style>";

// Check users table for superadmins
echo "<h2>Users Table (Portal) - Superadmins</h2>";
$portalUsers = $pdo->query("
    SELECT id, email, first_name, last_name, role, created_at
    FROM users
    WHERE role = 'superadmin'
    ORDER BY email
")->fetchAll(PDO::FETCH_ASSOC);

if (count($portalUsers) > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Role</th><th>Created</th></tr>";
    foreach ($portalUsers as $u) {
        echo "<tr>";
        echo "<td>{$u['id']}</td>";
        echo "<td>{$u['email']}</td>";
        echo "<td>{$u['first_name']} {$u['last_name']}</td>";
        echo "<td>{$u['role']}</td>";
        echo "<td>{$u['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No superadmin users found in portal.</p>";
}

// Check admin_users table
echo "<h2>Admin Users Table</h2>";
$adminUsers = $pdo->query("
    SELECT id, email, name, role, created_at
    FROM admin_users
    ORDER BY email
")->fetchAll(PDO::FETCH_ASSOC);

if (count($adminUsers) > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Email</th><th>Name</th><th>Role</th><th>Created</th></tr>";
    foreach ($adminUsers as $u) {
        echo "<tr>";
        echo "<td>{$u['id']}</td>";
        echo "<td>{$u['email']}</td>";
        echo "<td>{$u['name']}</td>";
        echo "<td>{$u['role']}</td>";
        echo "<td>{$u['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No users found in admin_users table.</p>";
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p><strong>Portal Superadmins:</strong> " . count($portalUsers) . "</p>";
echo "<p><strong>Admin Users:</strong> " . count($adminUsers) . "</p>";
echo "<p><strong>Status:</strong> All portal superadmins can now access admin interface with their existing credentials (no separate login needed).</p>";

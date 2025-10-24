<?php
declare(strict_types=1);
/**
 * Unify Authentication System
 *
 * This script copies superadmin users from the `users` table into `admin_users`
 * so they can access the admin interface without separate credentials.
 */

$SECRET_KEY = getenv('MIGRATION_SECRET') ?: 'change-me-in-production';
$provided_key = $_GET['key'] ?? '';

if ($provided_key !== $SECRET_KEY) {
    http_response_code(403);
    die('Access denied. Provide ?key=SECRET in URL');
}

require __DIR__ . '/db.php';

echo "<h1>Unifying Authentication System</h1>";
echo "<p>Copying superadmin users from 'users' table to 'admin_users' table...</p>";

// Find all superadmin users in the users table
$superadmins = $pdo->query("
    SELECT id, email, first_name, last_name, password_hash, role
    FROM users
    WHERE role = 'superadmin'
")->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Found " . count($superadmins) . " superadmin user(s) in users table:</p>";
echo "<ul>";

foreach ($superadmins as $user) {
    echo "<li>{$user['email']} - {$user['first_name']} {$user['last_name']}</li>";

    // Check if already exists in admin_users
    $exists = $pdo->prepare("SELECT id FROM admin_users WHERE email = ?");
    $exists->execute([$user['email']]);
    $adminUser = $exists->fetch();

    if ($adminUser) {
        echo " → Already exists in admin_users<br>";
    } else {
        // Copy to admin_users
        $fullName = trim($user['first_name'] . ' ' . $user['last_name']);
        $stmt = $pdo->prepare("
            INSERT INTO admin_users (email, password_hash, name, role)
            VALUES (?, ?, ?, 'superadmin')
        ");
        $stmt->execute([
            $user['email'],
            $user['password_hash'],
            $fullName
        ]);
        echo " → <strong style='color: green'>Created in admin_users</strong><br>";
    }
}

echo "</ul>";
echo "<hr>";
echo "<h2>Current admin_users:</h2>";
$adminUsers = $pdo->query("SELECT email, name, role FROM admin_users ORDER BY email")->fetchAll(PDO::FETCH_ASSOC);
echo "<ul>";
foreach ($adminUsers as $au) {
    echo "<li>{$au['email']} ({$au['name']}) - Role: {$au['role']}</li>";
}
echo "</ul>";

echo "<p style='margin-top: 2rem; padding: 1rem; background: #d1fae5; border: 1px solid #10b981; border-radius: 0.5rem;'>";
echo "✓ Authentication system unified! Superadmin users can now log in to both portal and admin.";
echo "</p>";

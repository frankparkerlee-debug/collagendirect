<?php
// One-time migration endpoint - run via browser and then delete this file
declare(strict_types=1);

require __DIR__ . '/db.php';

// Simple security check - only run if not already migrated
$checkStmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_name = 'users' AND column_name = 'role'");
$checkStmt->execute();
$exists = $checkStmt->fetch();

if ($exists) {
    echo "<h2>Migration already completed!</h2>";
    echo "<p>The 'role' column already exists in the users table.</p>";
    echo "<p><strong>You can safely delete this file: /api/migrate.php</strong></p>";
    exit;
}

echo "<h2>Running Migration: Add role column to users table</h2>";
echo "<hr>";

try {
    // Add role column
    echo "<p>1. Adding role column to users table...</p>";
    $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'physician'");
    echo "<p style='color:green'>✓ Column added successfully</p>";

    // Update existing users
    echo "<p>2. Setting default role for existing users...</p>";
    $stmt = $pdo->exec("UPDATE users SET role = 'physician' WHERE role IS NULL");
    echo "<p style='color:green'>✓ Updated $stmt users</p>";

    // Set practice admins
    echo "<p>3. Setting practice admins...</p>";
    $stmt = $pdo->prepare("UPDATE users SET role = 'practice_admin' WHERE email IN (?, ?)");
    $stmt->execute(['sparkingmatt@gmail.com', 'parker@senecawest.com']);
    echo "<p style='color:green'>✓ Set sparkingmatt@gmail.com and parker@senecawest.com as practice_admin</p>";

    // Create index
    echo "<p>4. Creating index on role column...</p>";
    try {
        $pdo->exec("CREATE INDEX idx_users_role ON users(role)");
        echo "<p style='color:green'>✓ Index created</p>";
    } catch (Exception $e) {
        echo "<p style='color:orange'>⚠ Index already exists or couldn't be created (this is okay)</p>";
    }

    echo "<hr>";
    echo "<h3 style='color:green'>Migration completed successfully!</h3>";

    // Show current practice admins
    echo "<h4>Current practice admins:</h4><ul>";
    $admins = $pdo->query("SELECT id, email, first_name, last_name, role FROM users WHERE role = 'practice_admin'")->fetchAll();
    foreach ($admins as $admin) {
        echo "<li>{$admin['email']} ({$admin['first_name']} {$admin['last_name']})</li>";
    }
    echo "</ul>";

    echo "<p><strong>IMPORTANT: Delete this file now: /api/migrate.php</strong></p>";

} catch (Exception $e) {
    echo "<p style='color:red'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
}

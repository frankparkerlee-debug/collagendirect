<?php
// Migration script to add role column to users table
declare(strict_types=1);

require __DIR__ . '/api/db.php';

echo "Running migration: Add role column to users table\n";
echo "================================================\n\n";

try {
    // Add role column if it doesn't exist
    echo "1. Adding role column to users table...\n";
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'physician'");
    echo "   ✓ Column added or already exists\n\n";

    // Update existing users to be physicians by default
    echo "2. Setting default role for existing users...\n";
    $stmt = $pdo->exec("UPDATE users SET role = 'physician' WHERE role IS NULL");
    echo "   ✓ Updated $stmt users\n\n";

    // Set specific users as practice admins
    echo "3. Setting practice admins...\n";
    $stmt = $pdo->prepare("UPDATE users SET role = 'practice_admin' WHERE email IN (?, ?)");
    $stmt->execute(['sparkingmatt@gmail.com', 'parker@senecawest.com']);
    echo "   ✓ Set sparkingmatt@gmail.com and parker@senecawest.com as practice_admin\n\n";

    // Add index for faster role lookups
    echo "4. Creating index on role column...\n";
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_role ON users(role)");
        echo "   ✓ Index created\n\n";
    } catch (Exception $e) {
        // Index might already exist, that's okay
        echo "   ✓ Index already exists\n\n";
    }

    echo "================================================\n";
    echo "Migration completed successfully!\n\n";

    // Show current practice admins
    echo "Current practice admins:\n";
    $admins = $pdo->query("SELECT id, email, first_name, last_name, role FROM users WHERE role = 'practice_admin'")->fetchAll();
    foreach ($admins as $admin) {
        echo "  - {$admin['email']} ({$admin['first_name']} {$admin['last_name']})\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

<?php
/**
 * Web-accessible migration runner
 * Access via: https://your-domain.com/admin/migrate.php?run=physician-fields
 */

declare(strict_types=1);

// Require authentication
session_start();
require __DIR__ . '/../api/db.php';

// Check if user is logged in and is superadmin
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized: Please log in first');
}

// Check if user is superadmin
$checkAdmin = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$checkAdmin->execute([$_SESSION['user_id']]);
$userRole = $checkAdmin->fetchColumn();

if ($userRole !== 'superadmin') {
    http_response_code(403);
    die('Forbidden: Only superadmins can run migrations');
}

// Get migration to run
$migration = $_GET['run'] ?? '';

header('Content-Type: text/plain');

if ($migration === 'physician-fields') {
    echo "Running physician fields migration for orders table...\n\n";

    try {
        // Add physician_id reference to practice_physicians
        echo "Adding physician_id column...\n";
        $pdo->exec("
            ALTER TABLE orders
            ADD COLUMN IF NOT EXISTS physician_id INT REFERENCES practice_physicians(id)
        ");
        echo "✓ physician_id added\n\n";

        // Add physician NPI (for PDF generation and billing)
        echo "Adding physician_npi column...\n";
        $pdo->exec("
            ALTER TABLE orders
            ADD COLUMN IF NOT EXISTS physician_npi VARCHAR(20)
        ");
        echo "✓ physician_npi added\n\n";

        // Add physician license number (for PDF generation and compliance)
        echo "Adding physician_license column...\n";
        $pdo->exec("
            ALTER TABLE orders
            ADD COLUMN IF NOT EXISTS physician_license VARCHAR(50)
        ");
        echo "✓ physician_license added\n\n";

        // Add physician license state
        echo "Adding physician_license_state column...\n";
        $pdo->exec("
            ALTER TABLE orders
            ADD COLUMN IF NOT EXISTS physician_license_state VARCHAR(2)
        ");
        echo "✓ physician_license_state added\n\n";

        echo "✅ Migration complete!\n\n";
        echo "These columns will be populated when practice admins select a physician from their roster.\n";
        echo "For orders created by individual physicians, these fields will remain NULL and the system\n";
        echo "will fall back to the user's NPI/License from the users table.\n";

    } catch (PDOException $e) {
        echo "❌ Migration failed: " . $e->getMessage() . "\n";
        http_response_code(500);
    }
} else {
    echo "Available migrations:\n";
    echo "  - physician-fields: Add physician fields to orders table\n\n";
    echo "Usage: /admin/migrate.php?run=physician-fields\n";
}

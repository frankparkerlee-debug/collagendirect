<?php
/**
 * Migration: Role-Based Permissions System
 *
 * This migration adds support for:
 * - Manufacturer role: Can view/approve/reject/comment on patients and billing
 * - Employee role: Can see/edit specific physicians assigned by superadmin
 * - Practice isolation: Practice admins only see their practice's patients
 * - Employee-physician assignments table
 */

declare(strict_types=1);
require __DIR__ . '/api/db.php';

// Migration secret
$MIGRATION_SECRET = getenv('MIGRATION_SECRET') ?: 'change-me-in-production';

if (php_sapi_name() !== 'cli') {
    $provided = $_GET['secret'] ?? '';
    if (!hash_equals($MIGRATION_SECRET, $provided)) {
        http_response_code(403);
        die('Forbidden');
    }
}

echo "=== CollagenDirect Role Permissions Migration ===\n\n";

try {
    $pdo->beginTransaction();

    echo "Step 1: Adding admin_type column for /admin users...\n";
    // admin_type: 'superadmin', 'employee', 'manufacturer'
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS admin_type VARCHAR(50) DEFAULT NULL
    ");
    echo "  ✓ Added admin_type column\n";

    echo "\nStep 2: Creating employee_physician_access table...\n";
    // Track which physicians each employee can access
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS employee_physician_access (
            id SERIAL PRIMARY KEY,
            employee_id VARCHAR(64) NOT NULL,
            physician_id VARCHAR(64) NOT NULL,
            assigned_by VARCHAR(64) NOT NULL,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            UNIQUE(employee_id, physician_id)
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_emp_phys_employee ON employee_physician_access(employee_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_emp_phys_physician ON employee_physician_access(physician_id)");
    echo "  ✓ Created employee_physician_access table\n";

    echo "\nStep 3: Creating patient_comments table for manufacturer notes...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS patient_comments (
            id SERIAL PRIMARY KEY,
            patient_id VARCHAR(64) NOT NULL,
            order_id VARCHAR(64),
            commenter_id VARCHAR(64) NOT NULL,
            commenter_name VARCHAR(255),
            commenter_role VARCHAR(50),
            comment_type VARCHAR(50) NOT NULL,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patient_comments_patient ON patient_comments(patient_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patient_comments_order ON patient_comments(order_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patient_comments_created ON patient_comments(created_at DESC)");
    echo "  ✓ Created patient_comments table\n";

    echo "\nStep 4: Adding manufacturer action tracking columns to orders...\n";
    $pdo->exec("
        ALTER TABLE orders
        ADD COLUMN IF NOT EXISTS reviewed_by VARCHAR(64) DEFAULT NULL
    ");
    $pdo->exec("
        ALTER TABLE orders
        ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMP DEFAULT NULL
    ");
    $pdo->exec("
        ALTER TABLE orders
        ADD COLUMN IF NOT EXISTS reviewer_notes TEXT DEFAULT NULL
    ");
    echo "  ✓ Added review tracking columns to orders\n";

    echo "\nStep 5: Updating existing superadmin users...\n";
    // Set admin_type for existing superadmins
    $pdo->exec("
        UPDATE users
        SET admin_type = 'superadmin'
        WHERE role = 'superadmin' AND admin_type IS NULL
    ");
    echo "  ✓ Updated existing superadmin users\n";

    echo "\nStep 6: Creating practice_physicians table if not exists...\n";
    // This may already exist from previous migration, but ensure it's here
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS practice_physicians (
            id SERIAL PRIMARY KEY,
            practice_admin_id VARCHAR(64) NOT NULL,
            physician_id VARCHAR(64) NOT NULL,
            first_name VARCHAR(255) NOT NULL,
            last_name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            license VARCHAR(100),
            license_state VARCHAR(10),
            license_expiry DATE,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(practice_admin_id, email)
        )
    ");
    echo "  ✓ Ensured practice_physicians table exists\n";

    $pdo->commit();

    echo "\n=== Migration completed successfully! ===\n\n";
    echo "Summary of changes:\n";
    echo "  ✓ Added admin_type column to users (superadmin/employee/manufacturer)\n";
    echo "  ✓ Created employee_physician_access table\n";
    echo "  ✓ Created patient_comments table for manufacturer feedback\n";
    echo "  ✓ Added review tracking to orders table\n";
    echo "  ✓ Updated existing superadmin users\n";
    echo "  ✓ Ensured practice_physicians table exists\n";
    echo "\nNext steps:\n";
    echo "  1. Update admin panel UI for role-based views\n";
    echo "  2. Create practice management page in portal\n";
    echo "  3. Implement patient filtering by practice\n";
    echo "  4. Add manufacturer approval workflow\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

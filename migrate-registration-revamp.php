<?php
/**
 * Migration: Registration Revamp for User Types and Roles
 *
 * This migration adds support for:
 * 1a. Practice Manager/Admin (non-DME)
 * 1b. Physician (non-DME, part of practice)
 * 2a. DME Hybrid Referrer
 * 2b. DME Wholesale Only
 */

declare(strict_types=1);
require __DIR__ . '/api/db.php';

// Migration secret - set this in your environment
$MIGRATION_SECRET = getenv('MIGRATION_SECRET') ?: 'change-me-in-production';

if (php_sapi_name() !== 'cli') {
    // Web access protection
    $provided = $_GET['secret'] ?? '';
    if (!hash_equals($MIGRATION_SECRET, $provided)) {
        http_response_code(403);
        die('Forbidden');
    }
}

echo "=== CollagenDirect Registration Revamp Migration ===\n\n";

try {
    // Start transaction
    $pdo->beginTransaction();

    echo "Step 1: Adding new columns to users table...\n";

    // Add user_type field to distinguish registration types
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS user_type VARCHAR(50) DEFAULT 'practice_admin'
    ");
    echo "  ✓ Added user_type column\n";

    // Add practice_id to link physicians to their practice manager
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS practice_id VARCHAR(64) DEFAULT NULL
    ");
    echo "  ✓ Added practice_id column\n";

    // Add parent_user_id for physician -> practice manager relationship
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS parent_user_id VARCHAR(64) DEFAULT NULL
    ");
    echo "  ✓ Added parent_user_id column\n";

    // Ensure role column exists (may have been added by previous migration)
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'physician'
    ");
    echo "  ✓ Ensured role column exists\n";

    // Add is_referral_only flag (may have been added by previous migration)
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS is_referral_only BOOLEAN DEFAULT FALSE
    ");
    echo "  ✓ Ensured is_referral_only column exists\n";

    // Add has_dme_license flag
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS has_dme_license BOOLEAN DEFAULT FALSE
    ");
    echo "  ✓ Added has_dme_license column\n";

    // Add is_hybrid flag for DME users who do both referral and direct billing
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS is_hybrid BOOLEAN DEFAULT FALSE
    ");
    echo "  ✓ Added is_hybrid column\n";

    // Add fields for practice manager registration
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS can_manage_physicians BOOLEAN DEFAULT FALSE
    ");
    echo "  ✓ Added can_manage_physicians column\n";

    // Add additional practice details fields
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS address VARCHAR(255) DEFAULT NULL
    ");
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS city VARCHAR(120) DEFAULT NULL
    ");
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS state VARCHAR(10) DEFAULT NULL
    ");
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS zip VARCHAR(15) DEFAULT NULL
    ");
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS tax_id VARCHAR(50) DEFAULT NULL
    ");
    echo "  ✓ Added address and practice detail columns\n";

    // Add license fields (may already exist from register.php)
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS license VARCHAR(100) DEFAULT NULL
    ");
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS license_state VARCHAR(10) DEFAULT NULL
    ");
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS license_expiry DATE DEFAULT NULL
    ");
    echo "  ✓ Ensured license columns exist\n";

    // Add DME license fields (may already exist)
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS dme_number VARCHAR(100) DEFAULT NULL
    ");
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS dme_state VARCHAR(10) DEFAULT NULL
    ");
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS dme_expiry DATE DEFAULT NULL
    ");
    echo "  ✓ Ensured DME license columns exist\n";

    // Add agreement signature fields (may already exist)
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS sign_name VARCHAR(255) DEFAULT NULL
    ");
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS sign_title VARCHAR(255) DEFAULT NULL
    ");
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS sign_date DATE DEFAULT NULL
    ");
    echo "  ✓ Ensured signature columns exist\n";

    // Add phone field (may already exist)
    $pdo->exec("
        ALTER TABLE users
        ADD COLUMN IF NOT EXISTS phone VARCHAR(50) DEFAULT NULL
    ");
    echo "  ✓ Ensured phone column exists\n";

    echo "\nStep 2: Creating indexes for new columns...\n";

    // Add index for practice relationships
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_practice_id ON users(practice_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_parent_user_id ON users(parent_user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_user_type ON users(user_type)");
    echo "  ✓ Created indexes\n";

    echo "\nStep 3: Migrating existing data...\n";

    // Set user_type based on existing account_type
    // referral -> practice_admin (they can refer, no DME)
    // wholesale -> dme_wholesale (they have DME and only direct bill)
    // hybrid -> dme_hybrid (they have DME and do both)
    $pdo->exec("
        UPDATE users
        SET user_type = CASE
            WHEN account_type = 'referral' THEN 'practice_admin'
            WHEN account_type = 'wholesale' THEN 'dme_wholesale'
            WHEN account_type = 'hybrid' THEN 'dme_hybrid'
            ELSE 'practice_admin'
        END
        WHERE user_type IS NULL OR user_type = 'practice_admin'
    ");

    // Set has_dme_license flag based on account_type
    $pdo->exec("
        UPDATE users
        SET has_dme_license = TRUE
        WHERE account_type IN ('wholesale', 'hybrid')
    ");

    // Set is_hybrid flag
    $pdo->exec("
        UPDATE users
        SET is_hybrid = TRUE
        WHERE account_type = 'hybrid' OR user_type = 'dme_hybrid'
    ");

    // Set is_referral_only flag for non-DME users
    $pdo->exec("
        UPDATE users
        SET is_referral_only = TRUE
        WHERE account_type = 'referral' AND (has_dme_license = FALSE OR has_dme_license IS NULL)
    ");

    // Set can_manage_physicians for practice admins and superadmins
    $pdo->exec("
        UPDATE users
        SET can_manage_physicians = TRUE
        WHERE role IN ('practice_admin', 'superadmin') OR user_type = 'practice_admin'
    ");

    echo "  ✓ Migrated existing user data\n";

    echo "\nStep 4: Creating practice_physicians table for multi-physician practices...\n";

    // Create table to store multiple physicians for a practice manager
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS practice_physicians (
            id SERIAL PRIMARY KEY,
            practice_manager_id VARCHAR(64) NOT NULL,
            physician_first_name VARCHAR(120) NOT NULL,
            physician_last_name VARCHAR(120) NOT NULL,
            physician_npi VARCHAR(20) NOT NULL,
            physician_license VARCHAR(100) NOT NULL,
            physician_license_state VARCHAR(10) NOT NULL,
            physician_license_expiry DATE NOT NULL,
            physician_email VARCHAR(255) DEFAULT NULL,
            physician_phone VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (practice_manager_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "  ✓ Created practice_physicians table\n";

    // Create index
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_practice_physicians_manager ON practice_physicians(practice_manager_id)");
    echo "  ✓ Created indexes for practice_physicians\n";

    // Commit transaction
    $pdo->commit();

    echo "\n✅ Migration completed successfully!\n\n";
    echo "Summary of changes:\n";
    echo "- Added user_type column to distinguish registration types\n";
    echo "- Added practice_id and parent_user_id for practice hierarchies\n";
    echo "- Added flags: has_dme_license, is_hybrid, can_manage_physicians\n";
    echo "- Added practice detail columns (address, city, state, zip, tax_id)\n";
    echo "- Created practice_physicians table for multi-physician practices\n";
    echo "- Migrated existing user data to new structure\n";

} catch (Throwable $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

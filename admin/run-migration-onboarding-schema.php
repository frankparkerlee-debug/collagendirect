<?php
/**
 * Migration: Ensure all required schema for onboarding wizard
 *
 * This migration ensures:
 * 1. Users table has all required columns for profile saving
 * 2. practice_locations table exists with correct schema
 * 3. practice_physicians table exists with correct schema
 * 4. rep_assigned_by check constraint includes 'employee_onboard'
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== Onboarding Schema Migration ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Add required columns to users table
  echo "1. Ensuring users table has required columns...\n";

  // license column
  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS license VARCHAR(100)");
  echo "   ✓ license column\n";

  // license_state column
  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS license_state VARCHAR(10)");
  echo "   ✓ license_state column\n";

  // credential_type column
  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS credential_type VARCHAR(10) DEFAULT 'MD'");
  echo "   ✓ credential_type column\n";

  // sign_name column (for agreements)
  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS sign_name VARCHAR(255)");
  echo "   ✓ sign_name column\n";

  // sign_title column (for agreements)
  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS sign_title VARCHAR(255)");
  echo "   ✓ sign_title column\n";

  // sign_date column (for agreements)
  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS sign_date DATE");
  echo "   ✓ sign_date column\n";

  // signed_ip column (for agreements)
  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS signed_ip VARCHAR(50)");
  echo "   ✓ signed_ip column\n";

  // role column
  $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'physician'");
  echo "   ✓ role column\n";

  echo "\n2. Ensuring practice_locations table exists...\n";

  // Create practice_locations table if it doesn't exist
  $tableExists = $pdo->query("
    SELECT EXISTS (
      SELECT FROM information_schema.tables
      WHERE table_name = 'practice_locations'
    )
  ")->fetchColumn();

  if (!$tableExists) {
    $pdo->exec("
      CREATE TABLE practice_locations (
        id SERIAL PRIMARY KEY,
        user_id VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        location_name VARCHAR(255) NOT NULL,
        address VARCHAR(255) NOT NULL,
        city VARCHAR(100) NOT NULL,
        state VARCHAR(2) NOT NULL,
        zip VARCHAR(10) NOT NULL,
        phone VARCHAR(20),
        is_primary BOOLEAN DEFAULT FALSE,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT unique_location_per_practice UNIQUE(user_id, location_name)
      )
    ");
    echo "   ✓ Created practice_locations table\n";

    // Create indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_practice_locations_user_id ON practice_locations(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_practice_locations_active ON practice_locations(is_active) WHERE is_active = TRUE");
    echo "   ✓ Created indexes\n";
  } else {
    echo "   - practice_locations table already exists\n";
  }

  echo "\n3. Ensuring practice_physicians table exists...\n";

  // Create practice_physicians table if it doesn't exist
  $physTableExists = $pdo->query("
    SELECT EXISTS (
      SELECT FROM information_schema.tables
      WHERE table_name = 'practice_physicians'
    )
  ")->fetchColumn();

  if (!$physTableExists) {
    $pdo->exec("
      CREATE TABLE practice_physicians (
        id SERIAL PRIMARY KEY,
        practice_user_id VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        physician_name VARCHAR(255) NOT NULL,
        npi VARCHAR(20),
        license_number VARCHAR(50),
        address TEXT,
        city VARCHAR(100),
        state VARCHAR(50),
        zip VARCHAR(20),
        phone VARCHAR(50),
        signature_text TEXT,
        signature_image_path TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    ");
    echo "   ✓ Created practice_physicians table\n";

    // Create indexes
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_practice_physicians_practice ON practice_physicians(practice_user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_practice_physicians_active ON practice_physicians(practice_user_id, is_active)");
    echo "   ✓ Created indexes\n";
  } else {
    echo "   - practice_physicians table already exists\n";

    // Ensure required columns exist
    $pdo->exec("ALTER TABLE practice_physicians ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE");
    $pdo->exec("ALTER TABLE practice_physicians ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    $pdo->exec("ALTER TABLE practice_physicians ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    echo "   ✓ Ensured required columns exist\n";
  }

  // 4. Fix rep_assigned_by check constraint to include 'employee_onboard'
  echo "\n4. Updating rep_assigned_by check constraint...\n";

  // Check if column exists and has a constraint
  $constraintCheck = $pdo->query("
    SELECT constraint_name
    FROM information_schema.constraint_column_usage
    WHERE table_name = 'users'
    AND column_name = 'rep_assigned_by'
  ")->fetch();

  if ($constraintCheck) {
    // Drop old constraint and add new one with employee_onboard
    try {
      $pdo->exec("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_rep_assigned_by_check");
      echo "   ✓ Dropped old constraint\n";
    } catch (PDOException $e) {
      // Constraint might not exist, that's OK
      echo "   - No existing constraint to drop\n";
    }

    $pdo->exec("
      ALTER TABLE users ADD CONSTRAINT users_rep_assigned_by_check
      CHECK (rep_assigned_by IS NULL OR rep_assigned_by IN
        ('self_onboard', 'admin_assign', 'approved_request', 'employee_onboard'))
    ");
    echo "   ✓ Added updated constraint with employee_onboard\n";
  } else {
    // Column might not have a constraint, just ensure we can use employee_onboard
    echo "   - No constraint found on rep_assigned_by column\n";
    // Try to add the proper constraint anyway
    try {
      $pdo->exec("
        ALTER TABLE users ADD CONSTRAINT users_rep_assigned_by_check
        CHECK (rep_assigned_by IS NULL OR rep_assigned_by IN
          ('self_onboard', 'admin_assign', 'approved_request', 'employee_onboard'))
      ");
      echo "   ✓ Added constraint with employee_onboard\n";
    } catch (PDOException $e) {
      if (strpos($e->getMessage(), 'already exists') !== false) {
        // Constraint exists, try to replace it
        $pdo->exec("ALTER TABLE users DROP CONSTRAINT users_rep_assigned_by_check");
        $pdo->exec("
          ALTER TABLE users ADD CONSTRAINT users_rep_assigned_by_check
          CHECK (rep_assigned_by IS NULL OR rep_assigned_by IN
            ('self_onboard', 'admin_assign', 'approved_request', 'employee_onboard'))
        ");
        echo "   ✓ Replaced constraint with employee_onboard\n";
      }
    }
  }

  $pdo->commit();
  echo "\n✓ Migration completed successfully!\n\n";
  echo "The onboarding wizard should now work correctly.\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  http_response_code(500);
  exit(1);
}

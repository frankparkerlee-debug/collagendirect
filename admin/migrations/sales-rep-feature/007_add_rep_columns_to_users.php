<?php
/**
 * Migration: Add sales rep assignment columns to users table
 *
 * Adds columns to track which sales rep is assigned to each
 * clinic/practice, when, and how the assignment was made.
 */

declare(strict_types=1);

// Use existing $pdo if available (from web runner), otherwise load CLI db
if (!isset($pdo)) {
  require_once __DIR__ . '/db_cli.php';
}

echo "=== Sales Rep Feature: Add Rep Assignment Columns to Users Table ===\n\n";

try {
  $pdo->beginTransaction();

  // Helper function to check if column exists
  function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("
      SELECT COUNT(*) as count
      FROM information_schema.columns
      WHERE table_name = ?
      AND column_name = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
  }

  // 1. Add assigned_rep_id column
  echo "1. Adding assigned_rep_id column...\n";
  if (columnExists($pdo, 'users', 'assigned_rep_id')) {
    echo "   - Column assigned_rep_id already exists\n";
  } else {
    $pdo->exec("
      ALTER TABLE users
      ADD COLUMN assigned_rep_id VARCHAR(64) REFERENCES sales_reps(id) ON DELETE SET NULL
    ");
    echo "   ✓ Added assigned_rep_id column\n";
  }

  // 2. Add rep_assignment_date column
  echo "2. Adding rep_assignment_date column...\n";
  if (columnExists($pdo, 'users', 'rep_assignment_date')) {
    echo "   - Column rep_assignment_date already exists\n";
  } else {
    $pdo->exec("
      ALTER TABLE users
      ADD COLUMN rep_assignment_date TIMESTAMP WITH TIME ZONE
    ");
    echo "   ✓ Added rep_assignment_date column\n";
  }

  // 3. Add rep_assigned_by column (method of assignment)
  echo "3. Adding rep_assigned_by column...\n";
  if (columnExists($pdo, 'users', 'rep_assigned_by')) {
    echo "   - Column rep_assigned_by already exists\n";
  } else {
    $pdo->exec("
      ALTER TABLE users
      ADD COLUMN rep_assigned_by VARCHAR(30)
        CHECK (rep_assigned_by IS NULL OR rep_assigned_by IN ('self_onboard', 'admin_assign', 'approved_request'))
    ");
    echo "   ✓ Added rep_assigned_by column\n";
  }

  // 4. Add rep_assigned_by_user_id column
  echo "4. Adding rep_assigned_by_user_id column...\n";
  if (columnExists($pdo, 'users', 'rep_assigned_by_user_id')) {
    echo "   - Column rep_assigned_by_user_id already exists\n";
  } else {
    $pdo->exec("
      ALTER TABLE users
      ADD COLUMN rep_assigned_by_user_id VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL
    ");
    echo "   ✓ Added rep_assigned_by_user_id column\n";
  }

  // 5. Create indexes for new columns
  echo "5. Creating indexes...\n";
  try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_assigned_rep_id ON users(assigned_rep_id)");
    echo "   ✓ Created index on assigned_rep_id\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
      echo "   - Index on assigned_rep_id already exists\n";
    } else {
      throw $e;
    }
  }

  try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_rep_assignment_date ON users(rep_assignment_date)");
    echo "   ✓ Created index on rep_assignment_date\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
      echo "   - Index on rep_assignment_date already exists\n";
    } else {
      throw $e;
    }
  }

  // 6. Add comments
  echo "6. Adding column comments...\n";
  $pdo->exec("COMMENT ON COLUMN users.assigned_rep_id IS 'FK to sales_reps - the rep assigned to this clinic/practice'");
  $pdo->exec("COMMENT ON COLUMN users.rep_assignment_date IS 'When the rep was assigned to this clinic'");
  $pdo->exec("COMMENT ON COLUMN users.rep_assigned_by IS 'How assignment was made: self_onboard, admin_assign, approved_request'");
  $pdo->exec("COMMENT ON COLUMN users.rep_assigned_by_user_id IS 'User who performed the assignment (admin or rep)'");
  echo "   ✓ Added column comments\n";

  $pdo->commit();

  echo "\n✓ Migration completed successfully!\n";
  echo "\nColumns added to users table:\n";
  echo "  - assigned_rep_id: FK to sales_reps\n";
  echo "  - rep_assignment_date: When assigned\n";
  echo "  - rep_assigned_by: Method (self_onboard, admin_assign, approved_request)\n";
  echo "  - rep_assigned_by_user_id: Who assigned\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  throw $e; // Re-throw for web runner to catch
}

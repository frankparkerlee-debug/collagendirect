<?php
/**
 * Run patient status migration
 * Adds state, status_comment, status_updated_at, status_updated_by columns
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../api/db.php';

echo "=== Patient Status Migration ===\n\n";

try {
  // Check if columns already exist
  echo "Step 1: Checking existing schema...\n";

  $stmt = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'patients'
      AND column_name IN ('state', 'status_comment', 'status_updated_at', 'status_updated_by')
    ORDER BY column_name
  ");
  $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

  if (count($existing) > 0) {
    echo "  Found existing columns: " . implode(', ', $existing) . "\n";
  } else {
    echo "  No status columns found yet\n";
  }
  echo "\n";

  // Run the migration
  echo "Step 2: Running migration...\n";

  $sql = "
    -- Add state column to track patient authorization status
    DO \$\$
    BEGIN
      IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'patients' AND column_name = 'state'
      ) THEN
        ALTER TABLE patients ADD COLUMN state VARCHAR(50) DEFAULT 'pending';
        RAISE NOTICE 'Added state column';
      ELSE
        RAISE NOTICE 'state column already exists';
      END IF;
    END \$\$;

    -- Add comment column for manufacturer feedback
    DO \$\$
    BEGIN
      IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'patients' AND column_name = 'status_comment'
      ) THEN
        ALTER TABLE patients ADD COLUMN status_comment TEXT;
        RAISE NOTICE 'Added status_comment column';
      ELSE
        RAISE NOTICE 'status_comment column already exists';
      END IF;
    END \$\$;

    -- Add timestamp for when status was last changed
    DO \$\$
    BEGIN
      IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'patients' AND column_name = 'status_updated_at'
      ) THEN
        ALTER TABLE patients ADD COLUMN status_updated_at TIMESTAMP;
        RAISE NOTICE 'Added status_updated_at column';
      ELSE
        RAISE NOTICE 'status_updated_at column already exists';
      END IF;
    END \$\$;

    -- Add who changed the status (admin user id)
    DO \$\$
    BEGIN
      IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'patients' AND column_name = 'status_updated_by'
      ) THEN
        ALTER TABLE patients ADD COLUMN status_updated_by VARCHAR(64);
        RAISE NOTICE 'Added status_updated_by column';
      ELSE
        RAISE NOTICE 'status_updated_by column already exists';
      END IF;
    END \$\$;

    -- Create index for filtering by status
    CREATE INDEX IF NOT EXISTS idx_patients_state ON patients(state);

    -- Update existing patients to have 'active' state if they have orders
    UPDATE patients
    SET state = 'active'
    WHERE state IS NULL
      AND EXISTS (SELECT 1 FROM orders WHERE orders.patient_id = patients.id);

    -- Set remaining NULL states to 'pending'
    UPDATE patients SET state = 'pending' WHERE state IS NULL;
  ";

  // Execute the migration
  $pdo->exec($sql);
  echo "  ✓ Migration SQL executed\n\n";

  // Verify
  echo "Step 3: Verifying columns...\n";
  $stmt = $pdo->query("
    SELECT column_name, data_type, column_default
    FROM information_schema.columns
    WHERE table_name = 'patients'
      AND column_name IN ('state', 'status_comment', 'status_updated_at', 'status_updated_by')
    ORDER BY column_name
  ");
  $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($columns as $col) {
    echo "  - {$col['column_name']}: {$col['data_type']}";
    if ($col['column_default']) {
      echo " (default: {$col['column_default']})";
    }
    echo "\n";
  }
  echo "\n";

  // Check patient states
  echo "Step 4: Checking patient states...\n";
  $stmt = $pdo->query("
    SELECT state, COUNT(*) as count
    FROM patients
    GROUP BY state
    ORDER BY state
  ");
  $states = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($states as $s) {
    echo "  - {$s['state']}: {$s['count']} patients\n";
  }
  echo "\n";

  echo "=== Migration Complete ===\n";
  echo "✓ All patient status columns added successfully\n";
  echo "✓ Existing patients categorized as 'active' or 'pending'\n";
  echo "✓ Ready to use pre-authorization workflow\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  exit(1);
}

<?php
/**
 * Migration: Add physician location selection
 *
 * Allows physicians to select which practice location they're working from
 * when creating patients/orders
 */
declare(strict_types=1);
require __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== Physician Location Selection Migration ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Add current_location_id to users table
  echo "1. Adding current_location_id to users table...\n";

  $columnCheck = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'users'
    AND column_name = 'current_location_id'
  ")->fetchColumn();

  if (!$columnCheck) {
    $pdo->exec("
      ALTER TABLE users
      ADD COLUMN current_location_id INTEGER REFERENCES practice_locations(id) ON DELETE SET NULL
    ");
    echo "   ✓ Added current_location_id column\n";
  } else {
    echo "   ℹ️  current_location_id column already exists\n";
  }

  // 2. Add default_location_id to users table (for their preferred location)
  echo "\n2. Adding default_location_id to users table...\n";

  $defaultLocCheck = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'users'
    AND column_name = 'default_location_id'
  ")->fetchColumn();

  if (!$defaultLocCheck) {
    $pdo->exec("
      ALTER TABLE users
      ADD COLUMN default_location_id INTEGER REFERENCES practice_locations(id) ON DELETE SET NULL
    ");
    echo "   ✓ Added default_location_id column\n";
  } else {
    echo "   ℹ️  default_location_id column already exists\n";
  }

  // 3. Add location_id to patients table (to track where patient was seen)
  echo "\n3. Adding location_id to patients table...\n";

  $patientLocCheck = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'patients'
    AND column_name = 'location_id'
  ")->fetchColumn();

  if (!$patientLocCheck) {
    $pdo->exec("
      ALTER TABLE patients
      ADD COLUMN location_id INTEGER REFERENCES practice_locations(id) ON DELETE SET NULL
    ");
    echo "   ✓ Added location_id column to patients\n";
  } else {
    echo "   ℹ️  location_id column already exists in patients\n";
  }

  // 4. Add location_id to orders table (to track where order was placed from)
  echo "\n4. Adding location_id to orders table...\n";

  $orderLocCheck = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders'
    AND column_name = 'location_id'
  ")->fetchColumn();

  if (!$orderLocCheck) {
    $pdo->exec("
      ALTER TABLE orders
      ADD COLUMN location_id INTEGER REFERENCES practice_locations(id) ON DELETE SET NULL
    ");
    echo "   ✓ Added location_id column to orders\n";
  } else {
    echo "   ℹ️  location_id column already exists in orders\n";
  }

  // 5. Create index for faster location lookups
  echo "\n5. Creating indexes...\n";

  try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_current_location ON users(current_location_id)");
    echo "   ✓ Created index on users.current_location_id\n";
  } catch (Throwable $e) {
    echo "   ℹ️  Index already exists\n";
  }

  try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_default_location ON users(default_location_id)");
    echo "   ✓ Created index on users.default_location_id\n";
  } catch (Throwable $e) {
    echo "   ℹ️  Index already exists\n";
  }

  try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patients_location ON patients(location_id)");
    echo "   ✓ Created index on patients.location_id\n";
  } catch (Throwable $e) {
    echo "   ℹ️  Index already exists\n";
  }

  try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_location ON orders(location_id)");
    echo "   ✓ Created index on orders.location_id\n";
  } catch (Throwable $e) {
    echo "   ℹ️  Index already exists\n";
  }

  $pdo->commit();

  echo "\n✅ Migration completed successfully!\n";
  echo "\nWhat this enables:\n";
  echo "- Physicians can select which location they're practicing from\n";
  echo "- Each patient record tracks the location where they were seen\n";
  echo "- Orders track the location they were placed from\n";
  echo "- Users can set a default location for quicker workflow\n";

} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo "\n❌ Migration failed!\n";
  echo "Error: " . $e->getMessage() . "\n";
  echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
  http_response_code(500);
}

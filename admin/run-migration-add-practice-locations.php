<?php
/**
 * Migration: Add practice locations/addresses management
 *
 * Allows practices to manage multiple facility addresses for ordering (user_id VARCHAR(64))
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== Practice Locations Migration ===\n\n";

try {
  $pdo->beginTransaction();

  // 1. Create practice_locations table
  echo "1. Creating practice_locations table...\n";
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS practice_locations (
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
  echo "   ✓ Created practice_locations table\n\n";

  // 2. Create indexes
  echo "2. Creating indexes...\n";
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_practice_locations_user_id ON practice_locations(user_id)");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_practice_locations_active ON practice_locations(is_active) WHERE is_active = TRUE");
  echo "   ✓ Created indexes\n\n";

  // 3. Add location_id to orders table
  echo "3. Adding location_id to orders table...\n";
  try {
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS location_id INTEGER REFERENCES practice_locations(id) ON DELETE SET NULL");
    echo "   ✓ Added location_id column\n";
  } catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') === false) {
      throw $e;
    }
    echo "   - location_id column already exists\n";
  }
  echo "\n";

  // 4. Migrate existing user addresses to practice_locations
  echo "4. Migrating existing practice addresses...\n";
  $usersWithAddresses = $pdo->query("
    SELECT id, practice_name, address, city, state, zip, phone
    FROM users
    WHERE role IN ('practice_admin', 'physician')
      AND address IS NOT NULL
      AND address != ''
      AND NOT EXISTS (
        SELECT 1 FROM practice_locations WHERE user_id = users.id
      )
  ")->fetchAll(PDO::FETCH_ASSOC);

  $migratedCount = 0;
  foreach ($usersWithAddresses as $user) {
    $locationName = !empty($user['practice_name']) ? $user['practice_name'] . ' - Main' : 'Main Office';

    $pdo->prepare("
      INSERT INTO practice_locations (user_id, location_name, address, city, state, zip, phone, is_primary, is_active)
      VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, TRUE)
      ON CONFLICT (user_id, location_name) DO NOTHING
    ")->execute([
      $user['id'],
      $locationName,
      $user['address'],
      $user['city'],
      $user['state'],
      $user['zip'],
      $user['phone']
    ]);

    $migratedCount++;
  }

  echo "   ✓ Migrated $migratedCount existing addresses to practice_locations\n\n";

  $pdo->commit();
  echo "✓ Migration completed successfully!\n\n";
  echo "Next steps:\n";
  echo "- Practice admins can now manage multiple locations via Practice Settings\n";
  echo "- Users can select delivery location during wholesale ordering\n";
  echo "- Existing addresses have been preserved as 'Main' locations\n";

} catch (PDOException $e) {
  $pdo->rollBack();
  echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
  http_response_code(500);
  exit(1);
}

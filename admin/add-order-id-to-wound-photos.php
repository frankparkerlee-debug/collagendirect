<?php
/**
 * Migration: Add order_id to wound_photos table
 *
 * Allows linking wound photos to specific treatment orders
 * so photos can be grouped by order in the patient profile
 */

require_once __DIR__ . '/../api/db.php';

echo "<pre>\n";
echo "=== Adding order_id to wound_photos ===\n\n";

try {
  // Check if column already exists
  $check = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name='wound_photos' AND column_name='order_id'
  ")->fetch();

  if ($check) {
    echo "✓ order_id column already exists\n";
  } else {
    echo "Adding order_id column to wound_photos...\n";
    $pdo->exec("
      ALTER TABLE wound_photos
      ADD COLUMN order_id VARCHAR(64) REFERENCES orders(id) ON DELETE SET NULL
    ");
    echo "✓ order_id column added\n\n";

    // Add index
    echo "Adding index for order_id...\n";
    $pdo->exec("
      CREATE INDEX IF NOT EXISTS idx_wound_photos_order ON wound_photos(order_id)
    ");
    echo "✓ Index added\n\n";
  }

  // Also add order_id to photo_requests table
  $checkRequest = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name='photo_requests' AND column_name='order_id'
  ")->fetch();

  if ($checkRequest) {
    echo "✓ order_id column already exists in photo_requests\n";
  } else {
    echo "Adding order_id column to photo_requests...\n";
    $pdo->exec("
      ALTER TABLE photo_requests
      ADD COLUMN order_id VARCHAR(64) REFERENCES orders(id) ON DELETE SET NULL
    ");
    echo "✓ order_id column added to photo_requests\n\n";

    // Add index
    echo "Adding index for photo_requests.order_id...\n";
    $pdo->exec("
      CREATE INDEX IF NOT EXISTS idx_photo_requests_order ON photo_requests(order_id)
    ");
    echo "✓ Index added\n\n";
  }

  echo "=== Migration Complete! ===\n\n";
  echo "Wound photos can now be linked to specific treatment orders.\n";
  echo "This allows photos to be grouped by order in the patient profile.\n";

} catch (Exception $e) {
  echo "✗ ERROR: " . $e->getMessage() . "\n";
  echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
  exit(1);
}

echo "</pre>";

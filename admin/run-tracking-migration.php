<?php
/**
 * Add tracking_number and carrier columns to orders table
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../api/db.php';

echo "=== Add Tracking Columns Migration ===\n\n";

try {
  // Check if columns already exist
  echo "Step 1: Checking existing schema...\n";

  $stmt = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders'
      AND column_name IN ('tracking_number', 'carrier')
    ORDER BY column_name
  ");
  $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

  if (count($existing) === 2) {
    echo "  ✓ Columns already exist: " . implode(', ', $existing) . "\n";
    echo "\n=== Migration Already Complete ===\n";
    exit(0);
  } else if (count($existing) > 0) {
    echo "  Found partial columns: " . implode(', ', $existing) . "\n";
  } else {
    echo "  No tracking columns found yet\n";
  }
  echo "\n";

  // Run the migration
  echo "Step 2: Adding tracking columns...\n";

  $sql = "
    -- Add tracking_number and carrier columns
    DO \$\$
    BEGIN
      IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'orders' AND column_name = 'tracking_number'
      ) THEN
        ALTER TABLE orders ADD COLUMN tracking_number VARCHAR(100);
        RAISE NOTICE 'Added tracking_number column';
      ELSE
        RAISE NOTICE 'tracking_number column already exists';
      END IF;

      IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'orders' AND column_name = 'carrier'
      ) THEN
        ALTER TABLE orders ADD COLUMN carrier VARCHAR(50);
        RAISE NOTICE 'Added carrier column';
      ELSE
        RAISE NOTICE 'carrier column already exists';
      END IF;
    END \$\$;

    -- Create index for tracking lookups
    CREATE INDEX IF NOT EXISTS idx_orders_tracking ON orders(tracking_number);

    -- Add comments for documentation
    COMMENT ON COLUMN orders.tracking_number IS 'Shipping tracking number (e.g. 1Z999AA10123456784)';
    COMMENT ON COLUMN orders.carrier IS 'Shipping carrier name (e.g. UPS, FedEx, USPS)';
  ";

  // Execute the migration
  $pdo->exec($sql);
  echo "  ✓ Migration SQL executed\n\n";

  // Verify
  echo "Step 3: Verifying columns...\n";
  $stmt = $pdo->query("
    SELECT column_name, data_type, character_maximum_length
    FROM information_schema.columns
    WHERE table_name = 'orders'
      AND column_name IN ('tracking_number', 'carrier')
    ORDER BY column_name
  ");
  $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($columns as $col) {
    $length = $col['character_maximum_length'] ? "({$col['character_maximum_length']})" : "";
    echo "  - {$col['column_name']}: {$col['data_type']}{$length}\n";
  }
  echo "\n";

  echo "=== Migration Complete ===\n";
  echo "✓ tracking_number and carrier columns added\n";
  echo "✓ Index created for fast lookups\n";
  echo "✓ Ready to track shipments\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  exit(1);
}

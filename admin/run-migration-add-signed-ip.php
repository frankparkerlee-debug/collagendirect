<?php
/**
 * Add signed_ip column to users table
 * Tracks the IP address from where the user registered and e-signed the agreements
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

echo "=== Adding Signed IP Column ===\n\n";

try {
  echo "Step 1: Adding signed_ip column to users table...\n";
  $pdo->exec("
    ALTER TABLE users
    ADD COLUMN IF NOT EXISTS signed_ip VARCHAR(45) DEFAULT NULL
  ");
  echo "  ✓ signed_ip column added (stores IPv4 or IPv6 address)\n\n";

  echo "Step 2: Adding column comment...\n";
  $pdo->exec("
    COMMENT ON COLUMN users.signed_ip IS 'IP address from which user registered and e-signed BAA and Master Services Agreement'
  ");
  echo "  ✓ Column comment added\n\n";

  echo "=== Migration Complete ===\n";
  echo "✓ Users table now tracks registration IP address for agreement signatures\n";
  echo "✓ Future registrations will capture IP address automatically\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  echo "  Code: " . $e->getCode() . "\n";
  exit(1);
}

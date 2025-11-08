<?php
/**
 * Database Migration: Order Form Enhancements
 * Adds new columns needed for enhanced order form functionality
 * Created: 2025-11-07
 */

declare(strict_types=1);
require __DIR__ . '/../api/db.php';

echo "Running order form enhancements migration...\n\n";

try {
  // 1. Exudate level
  echo "Adding exudate_level column...\n";
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS exudate_level VARCHAR(20)");
  echo "✓ exudate_level added\n";

  // 2. Baseline wound photo
  echo "Adding baseline wound photo columns...\n";
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS baseline_wound_photo_path TEXT");
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS baseline_wound_photo_mime VARCHAR(100)");
  echo "✓ Baseline wound photo columns added\n";

  // 3. Duration days (may already exist)
  echo "Adding duration_days column...\n";
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS duration_days INT");
  echo "✓ duration_days added\n";

  // 4. Verify existing columns
  echo "\nVerifying existing columns...\n";
  $stmt = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders'
    AND column_name IN ('tracking_number', 'secondary_dressing', 'wounds_data', 'frequency')
    ORDER BY column_name
  ");
  $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

  foreach (['tracking_number', 'secondary_dressing', 'wounds_data', 'frequency'] as $col) {
    if (in_array($col, $existing)) {
      echo "✓ $col exists\n";
    } else {
      echo "⚠ WARNING: $col does NOT exist - may need manual migration\n";
    }
  }

  echo "\n✅ Migration complete!\n";

} catch (PDOException $e) {
  echo "❌ Migration failed: " . $e->getMessage() . "\n";
  exit(1);
}

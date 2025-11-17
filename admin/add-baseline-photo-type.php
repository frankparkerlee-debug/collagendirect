<?php
/**
 * Add photo_type column to wound_photos table
 * Distinguishes between baseline photos (from orders) and review photos (billable)
 */

declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/db.php';

echo "=== Adding Baseline Photo Type Column ===\n\n";

try {
  echo "Step 1: Adding photo_type column...\n";
  $pdo->exec("
    ALTER TABLE wound_photos
    ADD COLUMN IF NOT EXISTS photo_type VARCHAR(20) DEFAULT 'review'
  ");
  echo "  ✓ photo_type column added (values: 'baseline' or 'review')\n\n";

  echo "Step 2: Marking existing portal_order photos as baseline...\n";
  $updated = $pdo->exec("
    UPDATE wound_photos
    SET photo_type = 'baseline'
    WHERE uploaded_via = 'portal_order'
      AND reviewed_at IS NOT NULL
  ");
  echo "  ✓ Updated {$updated} photos as 'baseline' type\n\n";

  echo "Step 3: Setting default for photos uploaded via other methods...\n";
  $pdo->exec("
    UPDATE wound_photos
    SET photo_type = 'review'
    WHERE photo_type IS NULL
       OR photo_type = ''
  ");
  echo "  ✓ Remaining photos set to 'review' type\n\n";

  echo "Step 4: Adding index for photo_type...\n";
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_wound_photos_type ON wound_photos(photo_type)");
  echo "  ✓ Index created for faster filtering by photo type\n\n";

  echo "Step 5: Adding column comment...\n";
  $pdo->exec("
    COMMENT ON COLUMN wound_photos.photo_type IS 'Photo classification: baseline (non-billable, from order) or review (billable, submitted for review)';
  ");
  echo "  ✓ Column comment added\n\n";

  // Show summary
  $summary = $pdo->query("
    SELECT
      photo_type,
      COUNT(*) as count,
      COUNT(*) FILTER (WHERE reviewed_at IS NOT NULL) as reviewed
    FROM wound_photos
    GROUP BY photo_type
    ORDER BY photo_type
  ")->fetchAll();

  echo "=== Migration Complete ===\n";
  foreach ($summary as $row) {
    echo "  {$row['photo_type']}: {$row['count']} photos ({$row['reviewed']} reviewed)\n";
  }
  echo "\n";
  echo "✓ Baseline photos (from orders) are now explicitly tagged\n";
  echo "✓ Baseline photos are excluded from billable photo reviews\n";
  echo "✓ Review photos can be filtered and billed separately\n";

} catch (PDOException $e) {
  echo "\n✗ Database error:\n";
  echo "  Message: " . $e->getMessage() . "\n";
  echo "  Code: " . $e->getCode() . "\n";
  exit(1);
}

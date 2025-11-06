<?php
/**
 * Run wound_photos schema fix migration
 * This adds order_id and updated_at columns
 */

require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "🔧 Running wound_photos schema fix migration...\n\n";

try {
    // Add order_id and updated_at to wound_photos
    echo "Adding order_id and updated_at columns to wound_photos...\n";
    $pdo->exec("
        ALTER TABLE wound_photos
        ADD COLUMN IF NOT EXISTS order_id VARCHAR(64),
        ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT NOW()
    ");
    echo "✓ Columns added\n\n";

    // Add indexes
    echo "Adding indexes...\n";
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_wound_photos_order_id ON wound_photos(order_id)");
    echo "✓ wound_photos order_id index created\n";

    // Add order_id to photo_requests
    echo "\nAdding order_id column to photo_requests...\n";
    $pdo->exec("ALTER TABLE photo_requests ADD COLUMN IF NOT EXISTS order_id VARCHAR(64)");
    echo "✓ Column added\n";

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_photo_requests_order_id ON photo_requests(order_id)");
    echo "✓ photo_requests order_id index created\n";

    // Update wound_photos timestamps
    echo "\nUpdating timestamps...\n";
    $stmt = $pdo->exec("
        UPDATE wound_photos
        SET updated_at = uploaded_at
        WHERE updated_at IS NULL
    ");
    echo "✓ Updated $stmt rows\n";

    echo "\n✅ Migration complete!\n\n";
    echo "Schema updates:\n";
    echo "  - Added order_id column to wound_photos\n";
    echo "  - Added updated_at column to wound_photos\n";
    echo "  - Added order_id column to photo_requests\n";
    echo "  - Added indexes on order_id columns\n";
    echo "  - Updated timestamps\n";

} catch (PDOException $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

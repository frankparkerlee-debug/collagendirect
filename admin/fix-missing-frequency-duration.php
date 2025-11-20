<?php
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain');

echo "=== FIXING MISSING FREQUENCY AND DURATION VALUES ===\n\n";

// Check how many orders are affected
$stmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM orders
    WHERE status NOT IN ('rejected', 'cancelled', 'draft')
    AND (billed_by IS NULL OR billed_by != 'practice_dme')
    AND (frequency_per_week IS NULL OR frequency_per_week = 0 OR duration_days IS NULL OR duration_days = 0)
");
$affected = (int)$stmt->fetch()['count'];

echo "Orders with missing frequency/duration: $affected\n\n";

if ($affected === 0) {
    echo "✓ No orders need fixing. All referral orders have valid frequency_per_week and duration_days values.\n";
    exit;
}

echo "Applying fix:\n";
echo "- Setting frequency_per_week = 7 (daily) where NULL or 0\n";
echo "- Setting duration_days = 30 where NULL or 0\n";
echo "- Only affecting referral orders (billed_by != 'practice_dme')\n\n";

try {
    // Update orders with missing frequency_per_week
    $stmt = $pdo->exec("
        UPDATE orders
        SET frequency_per_week = 7
        WHERE status NOT IN ('rejected', 'cancelled', 'draft')
        AND (billed_by IS NULL OR billed_by != 'practice_dme')
        AND (frequency_per_week IS NULL OR frequency_per_week = 0)
    ");
    echo "✓ Updated frequency_per_week for $stmt orders\n";

    // Update orders with missing duration_days
    $stmt = $pdo->exec("
        UPDATE orders
        SET duration_days = 30
        WHERE status NOT IN ('rejected', 'cancelled', 'draft')
        AND (billed_by IS NULL OR billed_by != 'practice_dme')
        AND (duration_days IS NULL OR duration_days = 0)
    ");
    echo "✓ Updated duration_days for $stmt orders\n";

    // Update orders with missing qty_per_change (should be at least 1)
    $stmt = $pdo->exec("
        UPDATE orders
        SET qty_per_change = 1
        WHERE status NOT IN ('rejected', 'cancelled', 'draft')
        AND (billed_by IS NULL OR billed_by != 'practice_dme')
        AND (qty_per_change IS NULL OR qty_per_change = 0)
    ");
    echo "✓ Updated qty_per_change for $stmt orders\n";

    echo "\n✓ Migration complete!\n";
    echo "\nReferral orders will now show correct revenue on the dashboard.\n";

} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

<?php
/**
 * Migration: Add Calculated Fields to Orders Table
 *
 * This migration adds columns to store calculated values at order creation time,
 * eliminating the need to recalculate on every page load and ensuring historical
 * data integrity when rates change.
 *
 * New columns:
 * - total_pieces: Total pieces needed for the order
 * - boxes_to_ship: Number of boxes to ship (ceil of pieces/pieces_per_box)
 * - billable_pieces: Pieces for insurance billing (usually same as total_pieces)
 * - expected_revenue: Revenue calculated at order time
 * - expected_cost: Cost calculated at order time
 * - cpt_rate_used: Medicare rate used at order time (for referral)
 * - price_per_box_used: Price per box used at order time (for wholesale)
 *
 * Run: php admin/migrations/add_order_calculated_fields.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/revenue_calculator.php';

echo "=== Migration: Add Order Calculated Fields ===\n\n";

// Step 1: Add new columns if they don't exist
echo "Step 1: Adding new columns to orders table...\n";

$columns = [
    'total_pieces' => 'INTEGER',
    'boxes_to_ship' => 'INTEGER',
    'billable_pieces' => 'INTEGER',
    'expected_revenue' => 'DECIMAL(10,2)',
    'expected_cost' => 'DECIMAL(10,2)',
    'cpt_rate_used' => 'DECIMAL(10,4)',
    'price_per_box_used' => 'DECIMAL(10,2)'
];

foreach ($columns as $column => $type) {
    try {
        $exists = $pdo->query("
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_name = 'orders' AND column_name = '$column'
        ")->fetchColumn();

        if ($exists) {
            echo "  ✓ Column '$column' already exists\n";
        } else {
            $pdo->exec("ALTER TABLE orders ADD COLUMN $column $type");
            echo "  + Added column '$column' ($type)\n";
        }
    } catch (Throwable $e) {
        echo "  ✗ Error adding column '$column': " . $e->getMessage() . "\n";
    }
}

// Step 2: Load reimbursement rates
echo "\nStep 2: Loading reimbursement rates...\n";
$rates = load_reimbursement_rates($pdo);
echo "  Loaded " . count($rates) . " HCPCS rates\n";

// Step 3: Backfill existing orders
echo "\nStep 3: Backfilling existing orders...\n";

// Get all orders that need backfilling (where total_pieces is NULL)
$stmt = $pdo->query("
    SELECT
        o.id,
        o.billed_by,
        o.product_price,
        o.frequency_per_week,
        o.duration_days,
        o.refills_allowed,
        o.qty_per_change,
        o.wounds_data,
        o.cpt,
        p.pieces_per_box,
        p.cost_per_box,
        p.price_wholesale,
        p.hcpcs_code,
        p.medicare_allowable,
        u.account_type,
        pp.custom_price as practice_custom_price,
        pp.cost_per_box as practice_cost_per_box
    FROM orders o
    LEFT JOIN products p ON p.id = o.product_id
    LEFT JOIN users u ON u.id = o.user_id
    LEFT JOIN practice_pricing pp ON pp.user_id = o.user_id AND pp.product_id = o.product_id
    WHERE o.total_pieces IS NULL
    ORDER BY o.created_at DESC
");

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($orders);
echo "  Found $total orders to backfill\n";

if ($total === 0) {
    echo "  No orders need backfilling.\n";
} else {
    $updateStmt = $pdo->prepare("
        UPDATE orders SET
            total_pieces = ?,
            boxes_to_ship = ?,
            billable_pieces = ?,
            expected_revenue = ?,
            expected_cost = ?,
            cpt_rate_used = ?,
            price_per_box_used = ?
        WHERE id = ?
    ");

    $success = 0;
    $errors = 0;
    $batchSize = 100;
    $processed = 0;

    foreach ($orders as $order) {
        try {
            // Use the revenue calculator
            $calc = calculate_order_revenue($order, $rates);

            // Determine price per box used
            $pricePerBoxUsed = null;
            if ($calc['is_wholesale']) {
                $pricePerBoxUsed = $calc['revenue'] / max(1, $calc['boxes']);
            }

            $updateStmt->execute([
                $calc['pieces'],
                $calc['boxes'],
                $calc['pieces'], // billable_pieces = pieces for now
                $calc['revenue'],
                $calc['cost'],
                $calc['cpt_rate'],
                $pricePerBoxUsed,
                $order['id']
            ]);

            $success++;
        } catch (Throwable $e) {
            $errors++;
            if ($errors <= 5) {
                echo "  ✗ Error updating order {$order['id']}: " . $e->getMessage() . "\n";
            }
        }

        $processed++;
        if ($processed % $batchSize === 0) {
            $pct = round(($processed / $total) * 100);
            echo "  Progress: $processed / $total ($pct%)\n";
        }
    }

    echo "\n  Backfill complete:\n";
    echo "    ✓ Successfully updated: $success orders\n";
    if ($errors > 0) {
        echo "    ✗ Errors: $errors orders\n";
    }
}

// Step 4: Add indexes for common queries
echo "\nStep 4: Adding indexes...\n";

$indexes = [
    'idx_orders_expected_revenue' => 'expected_revenue',
    'idx_orders_boxes_to_ship' => 'boxes_to_ship'
];

foreach ($indexes as $indexName => $column) {
    try {
        $exists = $pdo->query("
            SELECT COUNT(*) FROM pg_indexes
            WHERE tablename = 'orders' AND indexname = '$indexName'
        ")->fetchColumn();

        if ($exists) {
            echo "  ✓ Index '$indexName' already exists\n";
        } else {
            $pdo->exec("CREATE INDEX $indexName ON orders ($column)");
            echo "  + Created index '$indexName' on '$column'\n";
        }
    } catch (Throwable $e) {
        echo "  ✗ Error creating index '$indexName': " . $e->getMessage() . "\n";
    }
}

// Step 5: Verification
echo "\nStep 5: Verification...\n";

$stats = $pdo->query("
    SELECT
        COUNT(*) as total_orders,
        COUNT(total_pieces) as with_pieces,
        COUNT(expected_revenue) as with_revenue,
        SUM(expected_revenue) as total_revenue,
        AVG(boxes_to_ship) as avg_boxes
    FROM orders
    WHERE status NOT IN ('rejected', 'cancelled', 'draft')
")->fetch(PDO::FETCH_ASSOC);

echo "  Total orders: {$stats['total_orders']}\n";
echo "  Orders with calculated pieces: {$stats['with_pieces']}\n";
echo "  Orders with calculated revenue: {$stats['with_revenue']}\n";
echo "  Total expected revenue: $" . number_format((float)$stats['total_revenue'], 2) . "\n";
echo "  Average boxes per order: " . number_format((float)$stats['avg_boxes'], 1) . "\n";

echo "\n=== Migration Complete ===\n";
echo "\nNext steps:\n";
echo "1. Update api/portal/orders.create.php to calculate and store values at creation\n";
echo "2. Update api/lib/create_multi_product_orders.php similarly\n";
echo "3. Update display pages to use stored values (with fallback to calculation)\n";

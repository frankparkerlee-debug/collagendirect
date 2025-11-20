<?php
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Referral Revenue Debug</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; padding: 2rem; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1f2937; margin-bottom: 0.5rem; }
        h2 { color: #374151; margin-top: 2rem; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 0.875rem; }
        th, td { padding: 0.5rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #374151; position: sticky; top: 0; }
        tr:hover { background: #f9fafb; }
        code { background: #f3f4f6; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem; }
        .warning { color: #dc2626; font-weight: bold; }
        .ok { color: #16a34a; }
        .status { padding: 1rem; border-radius: 6px; margin: 1rem 0; }
        .error { background: #fee2e2; border: 2px solid #ef4444; color: #991b1b; }
        .info { background: #dbeafe; border: 2px solid #3b82f6; color: #1e40af; }
        .warning-box { background: #fef3c7; border: 2px solid #f59e0b; color: #92400e; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Referral Revenue Debug</h1>
        <p>Analyzing why referral orders show $0.00 revenue on dashboard</p>

<?php
// Get referral orders (billed_by != 'practice_dme')
$stmt = $pdo->query("
    SELECT
        o.id,
        o.order_number,
        o.created_at,
        o.billed_by,
        o.payment_type,
        o.status,
        o.product,
        o.product_price,
        o.frequency_per_week,
        o.duration_days,
        o.qty_per_change,
        o.refills_allowed,
        o.shipments_remaining,
        pr.pieces_per_box,
        pr.hcpcs_code,
        u.practice_name
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    LEFT JOIN products pr ON pr.id = o.product_id
    WHERE o.status NOT IN ('rejected', 'cancelled', 'draft')
    AND (o.billed_by IS NULL OR o.billed_by != 'practice_dme')
    ORDER BY o.created_at DESC
    LIMIT 20
");
$referralOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<div class='status info'>";
echo "<strong>Found " . count($referralOrders) . " referral orders</strong><br>";
echo "Showing most recent 20 orders where billed_by IS NULL or billed_by != 'practice_dme'";
echo "</div>";

if (count($referralOrders) === 0) {
    echo "<div class='status error'>";
    echo "<strong>⚠️ NO REFERRAL ORDERS FOUND!</strong><br>";
    echo "This is why referral revenue is $0.00. All orders in the database have billed_by = 'practice_dme' (wholesale).";
    echo "</div>";
} else {
    // Analyze each order to see why revenue might be $0
    echo "<h2>Revenue Calculation Analysis</h2>";
    echo "<table>";
    echo "<thead><tr>";
    echo "<th>Order #</th>";
    echo "<th>Practice</th>";
    echo "<th>Product</th>";
    echo "<th>Price</th>";
    echo "<th>FPW</th>";
    echo "<th>Days</th>";
    echo "<th>Qty</th>";
    echo "<th>PPB</th>";
    echo "<th>Total Boxes</th>";
    echo "<th>Calculated Revenue</th>";
    echo "<th>Status</th>";
    echo "</tr></thead>";
    echo "<tbody>";

    $totalRevenue = 0;
    $ordersWithZeroRevenue = 0;

    foreach ($referralOrders as $order) {
        $fpw = (int)($order['frequency_per_week'] ?? 0);
        $qty = max(1, (int)($order['qty_per_change'] ?? 1));
        $days = max(0, (int)($order['duration_days'] ?? 0));
        $refills = max(0, (int)($order['refills_allowed'] ?? 0));
        $pieces_per_box = max(1, (int)($order['pieces_per_box'] ?? 10));
        $price_per_box = (float)($order['product_price'] ?? 0);

        // Calculate using same formula as dashboard
        $changes_per_day = $fpw / 7.0;
        $total_changes = $changes_per_day * $days * (1 + $refills);
        $total_pieces_needed = $total_changes * $qty;
        $total_boxes = (int)ceil($total_pieces_needed / $pieces_per_box);

        $boxes_remaining = (int)($order['shipments_remaining'] ?? 0);
        $boxes_delivered = max(0, $total_boxes - $boxes_remaining);

        $order_earned = $boxes_delivered * $price_per_box;
        $order_projected = $boxes_remaining * $price_per_box;
        $order_total = $order_earned + $order_projected;

        $totalRevenue += $order_total;
        if ($order_total == 0) {
            $ordersWithZeroRevenue++;
        }

        // Determine if this order has issues
        $hasIssue = ($total_boxes == 0 || $price_per_box == 0);
        $rowClass = $hasIssue ? 'warning' : 'ok';

        echo "<tr>";
        echo "<td>" . htmlspecialchars($order['order_number'] ?? substr($order['id'], 0, 8)) . "</td>";
        echo "<td style='font-size: 0.75rem;'>" . htmlspecialchars($order['practice_name'] ?? 'N/A') . "</td>";
        echo "<td style='font-size: 0.75rem;'>" . htmlspecialchars($order['product'] ?? 'N/A') . "</td>";
        echo "<td class='$rowClass'>$" . number_format($price_per_box, 2) . "</td>";
        echo "<td class='" . ($fpw == 0 ? 'warning' : 'ok') . "'>" . $fpw . "</td>";
        echo "<td class='" . ($days == 0 ? 'warning' : 'ok') . "'>" . $days . "</td>";
        echo "<td>" . $qty . "</td>";
        echo "<td>" . $pieces_per_box . "</td>";
        echo "<td class='" . ($total_boxes == 0 ? 'warning' : 'ok') . "'>" . $total_boxes . "</td>";
        echo "<td class='" . ($order_total == 0 ? 'warning' : 'ok') . "' style='font-weight: bold;'>$" . number_format($order_total, 2) . "</td>";
        echo "<td style='font-size: 0.75rem;'>" . htmlspecialchars($order['status']) . "</td>";
        echo "</tr>";

        if ($hasIssue) {
            echo "<tr style='background: #fef3c7;'>";
            echo "<td colspan='11' style='font-size: 0.75rem; padding-left: 2rem;'>";
            echo "<strong>Issue:</strong> ";
            if ($fpw == 0) echo "• Frequency per week is 0 ";
            if ($days == 0) echo "• Duration days is 0 ";
            if ($price_per_box == 0) echo "• Product price is 0 ";
            echo "<br><strong>Fix:</strong> ";
            if ($fpw == 0 || $days == 0) {
                echo "Update order with frequency_per_week and duration_days values";
            }
            if ($price_per_box == 0) {
                echo "Update order.product_price or ensure product has price_admin set";
            }
            echo "</td>";
            echo "</tr>";
        }
    }

    echo "</tbody></table>";

    echo "<div class='status " . ($ordersWithZeroRevenue > 0 ? 'warning-box' : 'info') . "'>";
    echo "<strong>Summary:</strong><br>";
    echo "• Total referral orders analyzed: " . count($referralOrders) . "<br>";
    echo "• Orders with $0.00 revenue: <strong>" . $ordersWithZeroRevenue . "</strong><br>";
    echo "• Calculated total revenue: <strong>$" . number_format($totalRevenue, 2) . "</strong><br>";
    echo "</div>";

    if ($ordersWithZeroRevenue > 0) {
        echo "<div class='status error'>";
        echo "<strong>⚠️ Problem Identified!</strong><br>";
        echo "Referral orders are missing critical fields: <code>frequency_per_week</code> and/or <code>duration_days</code>.<br>";
        echo "Without these values, the revenue calculation formula returns 0 boxes, resulting in $0.00 revenue.<br><br>";
        echo "<strong>Root Cause:</strong> The referral order creation form ([portal/index.php?page=orders](api/portal/orders.create.php)) may not be saving these fields correctly.";
        echo "</div>";
    }
}

// Check if these columns exist in orders table
echo "<h2>Database Schema Check</h2>";
$stmt = $pdo->query("
    SELECT column_name, data_type, is_nullable
    FROM information_schema.columns
    WHERE table_name = 'orders'
    AND column_name IN ('frequency_per_week', 'duration_days', 'qty_per_change', 'billed_by', 'payment_type', 'shipments_remaining')
    ORDER BY column_name
");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<thead><tr><th>Column</th><th>Data Type</th><th>Nullable</th></tr></thead>";
echo "<tbody>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td><code>" . htmlspecialchars($col['column_name']) . "</code></td>";
    echo "<td>" . htmlspecialchars($col['data_type']) . "</td>";
    echo "<td>" . htmlspecialchars($col['is_nullable']) . "</td>";
    echo "</tr>";
}
echo "</tbody></table>";

?>
    </div>
</body>
</html>

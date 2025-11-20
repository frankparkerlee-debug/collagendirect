<?php
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Revenue Breakdown Diagnostic</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; padding: 2rem; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1f2937; margin-bottom: 0.5rem; }
        h2 { color: #374151; margin-top: 2rem; }
        .status { padding: 1rem; border-radius: 6px; margin: 1rem 0; }
        .success { background: #dcfce7; border: 2px solid #22c55e; color: #166534; }
        .error { background: #fee2e2; border: 2px solid #ef4444; color: #991b1b; }
        .warning { background: #fef3c7; border: 2px solid #f59e0b; color: #92400e; }
        .info { background: #dbeafe; border: 2px solid #3b82f6; color: #1e40af; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #374151; }
        tr:hover { background: #f9fafb; }
        code { background: #f3f4f6; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem; }
        .metric { display: inline-block; padding: 0.5rem 1rem; margin: 0.5rem; border-radius: 6px; }
        .metric-wholesale { background: #fed7aa; border: 2px solid #f97316; }
        .metric-referral { background: #ddd6fe; border: 2px solid #8b5cf6; }
        .metric-total { background: #bfdbfe; border: 2px solid #3b82f6; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Revenue Breakdown Diagnostic</h1>
        <p>Analyzing order revenue by billing type (wholesale vs referral)</p>

<?php
// Get order counts and revenue by billed_by field
$stmt = $pdo->query("
    SELECT
        billed_by,
        COUNT(*) as order_count,
        SUM(product_price) as total_revenue,
        AVG(product_price) as avg_price
    FROM orders
    WHERE status NOT IN ('rejected', 'cancelled', 'draft')
    GROUP BY billed_by
");
$breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h2>Revenue by Billing Type</h2>";
echo "<table>";
echo "<thead><tr>";
echo "<th>Billed By</th>";
echo "<th>Order Count</th>";
echo "<th>Total Revenue</th>";
echo "<th>Avg Price</th>";
echo "</tr></thead>";
echo "<tbody>";

$totalOrders = 0;
$totalRevenue = 0;
$wholesaleRevenue = 0;
$referralRevenue = 0;

foreach ($breakdown as $row) {
    $billedBy = $row['billed_by'] ?: 'NULL/Empty';
    $count = (int)$row['order_count'];
    $revenue = (float)$row['total_revenue'];
    $avg = (float)$row['avg_price'];

    $totalOrders += $count;
    $totalRevenue += $revenue;

    if ($row['billed_by'] === 'practice_dme') {
        $wholesaleRevenue += $revenue;
    } else {
        $referralRevenue += $revenue;
    }

    echo "<tr>";
    echo "<td><code>" . htmlspecialchars($billedBy) . "</code></td>";
    echo "<td>" . number_format($count) . "</td>";
    echo "<td>$" . number_format($revenue, 2) . "</td>";
    echo "<td>$" . number_format($avg, 2) . "</td>";
    echo "</tr>";
}

echo "</tbody></table>";

echo "<div>";
echo "<div class='metric metric-total'><strong>Total Orders:</strong> " . number_format($totalOrders) . "</div>";
echo "<div class='metric metric-total'><strong>Total Revenue:</strong> $" . number_format($totalRevenue, 2) . "</div>";
echo "<div class='metric metric-wholesale'><strong>Wholesale Revenue:</strong> $" . number_format($wholesaleRevenue, 2) . "</div>";
echo "<div class='metric metric-referral'><strong>Referral Revenue:</strong> $" . number_format($referralRevenue, 2) . "</div>";
echo "</div>";

// Check if any referral orders exist
$stmt = $pdo->query("
    SELECT COUNT(*) as count
    FROM orders
    WHERE status NOT IN ('rejected', 'cancelled', 'draft')
    AND (billed_by IS NULL OR billed_by = 'collagen_direct')
");
$referralCount = (int)$stmt->fetch()['count'];

if ($referralCount === 0) {
    echo "<div class='status error'>";
    echo "<strong>⚠️ Problem Found!</strong><br>";
    echo "There are NO referral orders (billed_by = 'collagen_direct' or NULL) in the database.<br>";
    echo "This explains why referral revenue is showing as $0.00 on the dashboard.";
    echo "</div>";
} else {
    echo "<div class='status success'>";
    echo "<strong>✓ Referral Orders Found</strong><br>";
    echo "Found $referralCount referral orders in the database.";
    echo "</div>";
}

// Sample orders for each type
echo "<h2>Sample Orders by Type</h2>";

echo "<h3>Wholesale Orders (billed_by = 'practice_dme')</h3>";
$stmt = $pdo->query("
    SELECT id, created_at, product_price, billed_by, status, order_number
    FROM orders
    WHERE billed_by = 'practice_dme'
    AND status NOT IN ('rejected', 'cancelled', 'draft')
    ORDER BY created_at DESC
    LIMIT 5
");
$wholesaleOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($wholesaleOrders) > 0) {
    echo "<table>";
    echo "<thead><tr><th>Order Number</th><th>Created</th><th>Price</th><th>Status</th><th>Billed By</th></tr></thead>";
    echo "<tbody>";
    foreach ($wholesaleOrders as $order) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($order['order_number'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($order['created_at']) . "</td>";
        echo "<td>$" . number_format($order['product_price'], 2) . "</td>";
        echo "<td>" . htmlspecialchars($order['status']) . "</td>";
        echo "<td><code>" . htmlspecialchars($order['billed_by']) . "</code></td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<p class='status info'>No wholesale orders found.</p>";
}

echo "<h3>Referral Orders (billed_by = 'collagen_direct' or NULL)</h3>";
$stmt = $pdo->query("
    SELECT id, created_at, product_price, billed_by, status, order_number
    FROM orders
    WHERE (billed_by IS NULL OR billed_by = 'collagen_direct')
    AND status NOT IN ('rejected', 'cancelled', 'draft')
    ORDER BY created_at DESC
    LIMIT 5
");
$referralOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($referralOrders) > 0) {
    echo "<table>";
    echo "<thead><tr><th>Order Number</th><th>Created</th><th>Price</th><th>Status</th><th>Billed By</th></tr></thead>";
    echo "<tbody>";
    foreach ($referralOrders as $order) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($order['order_number'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($order['created_at']) . "</td>";
        echo "<td>$" . number_format($order['product_price'], 2) . "</td>";
        echo "<td>" . htmlspecialchars($order['status']) . "</td>";
        echo "<td><code>" . htmlspecialchars($order['billed_by'] ?: 'NULL') . "</code></td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<p class='status warning'><strong>⚠️ No referral orders found!</strong> This is why referral revenue is $0.00</p>";
}

// Check how orders are being created
echo "<h2>Order Creation Analysis</h2>";
echo "<p>Checking how the billed_by field is being set during order creation...</p>";

// Check payment_type field existence
$stmt = $pdo->query("
    SELECT column_name, data_type
    FROM information_schema.columns
    WHERE table_name = 'orders'
    AND column_name IN ('billed_by', 'payment_type', 'order_type')
");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<div class='status info'>";
echo "<strong>Relevant Order Columns:</strong><br>";
foreach ($columns as $col) {
    echo "• " . htmlspecialchars($col['column_name']) . " (" . htmlspecialchars($col['data_type']) . ")<br>";
}
echo "</div>";

// Check if payment_type affects billed_by
$stmt = $pdo->query("
    SELECT
        billed_by,
        CASE
            WHEN order_number LIKE 'WS-%' THEN 'wholesale'
            ELSE 'referral'
        END as order_type,
        COUNT(*) as count
    FROM orders
    WHERE status NOT IN ('rejected', 'cancelled', 'draft')
    GROUP BY billed_by, order_type
");
$typeAnalysis = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Order Type vs Billed By</h3>";
echo "<table>";
echo "<thead><tr><th>Billed By</th><th>Order Type (by order_number)</th><th>Count</th></tr></thead>";
echo "<tbody>";
foreach ($typeAnalysis as $row) {
    echo "<tr>";
    echo "<td><code>" . htmlspecialchars($row['billed_by'] ?: 'NULL') . "</code></td>";
    echo "<td>" . htmlspecialchars($row['order_type']) . "</td>";
    echo "<td>" . htmlspecialchars($row['count']) . "</td>";
    echo "</tr>";
}
echo "</tbody></table>";

echo "<hr style='margin: 2rem 0;'>";
echo "<h2>Diagnosis</h2>";
echo "<div class='status info'>";
echo "<strong>Expected Behavior:</strong><br>";
echo "• <strong>Wholesale orders</strong> (order_number starts with WS-) should have billed_by = 'practice_dme'<br>";
echo "• <strong>Referral orders</strong> (regular patient orders) should have billed_by = 'collagen_direct' or NULL<br>";
echo "<br>";
echo "<strong>Dashboard Calculation:</strong><br>";
echo "• Line 127-128 in admin/index.php checks: <code>\$billedBy = \$order['billed_by'] ?? 'collagen_direct';</code><br>";
echo "• Line 128: <code>\$isWholesale = (\$billedBy === 'practice_dme');</code><br>";
echo "• Lines 189-193: Revenue is split based on \$isWholesale flag<br>";
echo "</div>";

?>
    </div>
</body>
</html>

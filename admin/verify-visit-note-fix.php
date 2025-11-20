<?php
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Visit Note Fix Verification</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; padding: 2rem; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1f2937; margin-bottom: 0.5rem; }
        .status { padding: 1rem; border-radius: 6px; margin: 1rem 0; }
        .success { background: #dcfce7; border: 2px solid #22c55e; color: #166534; }
        .error { background: #fee2e2; border: 2px solid #ef4444; color: #991b1b; }
        .info { background: #dbeafe; border: 2px solid #3b82f6; color: #1e40af; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #374151; }
        tr:hover { background: #f9fafb; }
        .file-ok { color: #22c55e; font-weight: bold; }
        .file-missing { color: #ef4444; font-weight: bold; }
        code { background: #f3f4f6; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Visit Note Fix Verification</h1>
        <p>Checking if the PostgreSQL CONCAT fix resolved the visit note upload issue.</p>

<?php
// Get the 10 most recent orders
$stmt = $pdo->prepare("
    SELECT
        o.id,
        o.created_at,
        o.rx_note_path,
        o.rx_note_name,
        o.additional_instructions,
        o.patient_id,
        p.first_name,
        p.last_name,
        p.patient_number
    FROM orders o
    LEFT JOIN patients p ON p.id = o.patient_id
    ORDER BY o.created_at DESC
    LIMIT 10
");
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fixTime = strtotime('2025-11-20 11:57:54'); // When the PostgreSQL fix was deployed
$ordersAfterFix = array_filter($orders, fn($o) => strtotime($o['created_at']) > $fixTime);
$ordersBeforeFix = array_filter($orders, fn($o) => strtotime($o['created_at']) <= $fixTime);

$withVisitNote = count(array_filter($ordersAfterFix, fn($o) => !empty($o['rx_note_path'])));
$withoutVisitNote = count($ordersAfterFix) - $withVisitNote;

echo "<div class='status info'>";
echo "<strong>Fix Deployed:</strong> 2025-11-20 at 11:57:54 AM CST<br>";
echo "<strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "<br>";
echo "<strong>Orders Created After Fix:</strong> " . count($ordersAfterFix) . "<br>";
echo "<strong>Orders Created Before Fix:</strong> " . count($ordersBeforeFix);
echo "</div>";

if (count($ordersAfterFix) === 0) {
    echo "<div class='status error'>";
    echo "<strong>⚠️ No New Orders Yet</strong><br>";
    echo "No orders have been created since the fix was deployed. Please create a test order with a visit note attached.";
    echo "</div>";
} else {
    if ($withVisitNote > 0) {
        echo "<div class='status success'>";
        echo "<strong>✓ Fix is Working!</strong><br>";
        echo "$withVisitNote out of " . count($ordersAfterFix) . " orders created after the fix have visit notes.";
        echo "</div>";
    } else {
        echo "<div class='status error'>";
        echo "<strong>✗ Fix May Not Be Working</strong><br>";
        echo "None of the " . count($ordersAfterFix) . " orders created after the fix have visit notes.<br>";
        echo "This could mean: (1) No visit notes were uploaded with these orders, or (2) The fix didn't work.";
        echo "</div>";
    }
}

echo "<h2>Recent Orders (Last 10)</h2>";
echo "<table>";
echo "<thead><tr>";
echo "<th>Created</th>";
echo "<th>Patient</th>";
echo "<th>Order ID</th>";
echo "<th>Visit Note Status</th>";
echo "<th>Debug Info</th>";
echo "</tr></thead>";
echo "<tbody>";

foreach ($orders as $order) {
    $isAfterFix = strtotime($order['created_at']) > $fixTime;
    $hasVisitNote = !empty($order['rx_note_path']);
    $statusClass = $hasVisitNote ? 'file-ok' : 'file-missing';
    $statusText = $hasVisitNote ? '✓ Has Visit Note' : '✗ No Visit Note';
    $rowColor = $isAfterFix ? '#fffbeb' : '#ffffff'; // Highlight orders after fix

    echo "<tr style='background: $rowColor;'>";
    echo "<td>";
    echo date('m/d/Y H:i:s', strtotime($order['created_at']));
    if ($isAfterFix) echo " <span style='color: #f59e0b; font-weight: bold;'>AFTER FIX</span>";
    echo "</td>";
    echo "<td>" . htmlspecialchars($order['patient_number'] ?? 'N/A') . " - " . htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) . "</td>";
    echo "<td><code>" . htmlspecialchars(substr($order['id'], 0, 8)) . "...</code></td>";
    echo "<td class='$statusClass'>$statusText</td>";

    // Check for debug info
    $hasDebug = !empty($order['additional_instructions']) && strpos($order['additional_instructions'], 'DEBUG-') !== false;
    if ($hasDebug) {
        // Extract just the DEBUG line
        preg_match('/DEBUG-\d+:.*?(?=\n|$)/', $order['additional_instructions'], $matches);
        $debugLine = $matches[0] ?? 'Found debug info';
        echo "<td style='font-size: 0.75rem; color: #6b7280;'>" . htmlspecialchars($debugLine) . "</td>";
    } else {
        echo "<td style='color: #9ca3af;'>—</td>";
    }
    echo "</tr>";

    // Show file details if present
    if ($hasVisitNote) {
        echo "<tr style='background: #f0fdf4;'>";
        echo "<td colspan='5' style='font-size: 0.875rem; padding-left: 2rem;'>";
        echo "<strong>Path:</strong> " . htmlspecialchars($order['rx_note_path']) . "<br>";
        echo "<strong>Filename:</strong> " . htmlspecialchars($order['rx_note_name'] ?? 'N/A') . "<br>";

        // Check if file exists
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $order['rx_note_path'];
        $persistentPath = '/opt/render/project/src' . $order['rx_note_path'];
        $fileExists = file_exists($filePath) || file_exists($persistentPath);
        $fileClass = $fileExists ? 'file-ok' : 'file-missing';
        $fileText = $fileExists ? '✓ File exists on disk' : '✗ File not found on disk';
        echo "<strong class='$fileClass'>$fileText</strong>";
        echo "</td>";
        echo "</tr>";
    }
}

echo "</tbody></table>";

echo "<hr style='margin: 2rem 0;'>";
echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li>Create a <strong>new test order</strong> with a visit note attached</li>";
echo "<li>Refresh this page after the order is created</li>";
echo "<li>The new order should appear at the top with <span class='file-ok'>✓ Has Visit Note</span></li>";
echo "<li>Check the <strong>Debug Info</strong> column to see what files were detected during upload</li>";
echo "</ol>";

?>
    </div>
</body>
</html>

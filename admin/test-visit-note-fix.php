<?php
require_once __DIR__ . '/db.php';

echo "<h2>Visit Note Fix Verification</h2>";
echo "<p>This page checks if visit notes are being saved correctly after the fix.</p>";

// Get the 5 most recent orders
$stmt = $pdo->prepare("
  SELECT
    id,
    created_at,
    rx_note_path,
    rx_note_name,
    patient_id,
    (SELECT CONCAT(first_name, ' ', last_name) FROM patients WHERE id = orders.patient_id) as patient_name
  FROM orders
  ORDER BY created_at DESC
  LIMIT 10
");
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Recent Orders (Last 10):</h3>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f5f5f5;'>";
echo "<th>Created</th>";
echo "<th>Patient</th>";
echo "<th>Order ID</th>";
echo "<th>Visit Note Status</th>";
echo "</tr>";

foreach ($orders as $order) {
    $hasVisitNote = !empty($order['rx_note_path']);
    $statusColor = $hasVisitNote ? '#10b981' : '#dc2626';
    $statusText = $hasVisitNote ? '✓ Has Visit Note' : '✗ No Visit Note';

    echo "<tr>";
    echo "<td>" . date('m/d/Y H:i', strtotime($order['created_at'])) . "</td>";
    echo "<td>" . htmlspecialchars($order['patient_name'] ?? 'Unknown') . "</td>";
    echo "<td style='font-family: monospace; font-size: 0.8em;'>" . htmlspecialchars(substr($order['id'], 0, 8)) . "...</td>";
    echo "<td style='color: $statusColor; font-weight: bold;'>$statusText</td>";
    echo "</tr>";

    if ($hasVisitNote) {
        echo "<tr style='background: #f0fdf4;'>";
        echo "<td colspan='4' style='font-size: 0.85em; padding-left: 2rem;'>";
        echo "📄 <strong>Path:</strong> " . htmlspecialchars($order['rx_note_path']) . "<br>";
        echo "📝 <strong>Filename:</strong> " . htmlspecialchars($order['rx_note_name'] ?? 'N/A');

        // Check if file exists
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $order['rx_note_path'];
        $persistentPath = '/opt/render/project/src' . $order['rx_note_path'];
        $fileExists = file_exists($filePath) || file_exists($persistentPath);
        $fileStatusColor = $fileExists ? '#10b981' : '#dc2626';
        $fileStatusText = $fileExists ? '✓ File exists on disk' : '✗ File not found on disk';

        echo "<br>💾 <strong style='color: $fileStatusColor;'>$fileStatusText</strong>";
        echo "</td>";
        echo "</tr>";
    }
}

echo "</table>";

echo "<hr>";
echo "<h3>Summary:</h3>";
$withVisitNote = count(array_filter($orders, fn($o) => !empty($o['rx_note_path'])));
$withoutVisitNote = count($orders) - $withVisitNote;

echo "<div style='display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;'>";
echo "<div style='background: #f0fdf4; padding: 1rem; border-radius: 6px; border: 2px solid #10b981;'>";
echo "<div style='font-size: 2rem; font-weight: bold; color: #10b981;'>$withVisitNote</div>";
echo "<div>Orders with Visit Notes</div>";
echo "</div>";
echo "<div style='background: #fef2f2; padding: 1rem; border-radius: 6px; border: 2px solid #dc2626;'>";
echo "<div style='font-size: 2rem; font-weight: bold; color: #dc2626;'>$withoutVisitNote</div>";
echo "<div>Orders without Visit Notes</div>";
echo "</div>";
echo "</div>";

echo "<hr>";
echo "<h3>Instructions:</h3>";
echo "<ol>";
echo "<li>Create a <strong>new test order</strong> with a visit note attached</li>";
echo "<li>Refresh this page after the order is created</li>";
echo "<li>The new order should appear at the top with <span style='color: #10b981; font-weight: bold;'>✓ Has Visit Note</span></li>";
echo "<li>If it shows <span style='color: #dc2626; font-weight: bold;'>✗ No Visit Note</span>, the fix didn't work</li>";
echo "</ol>";

echo "<p style='margin-top: 2rem; padding: 1rem; background: #fef3c7; border-left: 4px solid #f59e0b;'>";
echo "<strong>Note:</strong> Orders created <em>before</em> the fix (before " . date('m/d/Y H:i') . ") will still show as missing visit notes. ";
echo "Only orders created <em>after</em> the deployment will have visit notes saved correctly.";
echo "</p>";
?>

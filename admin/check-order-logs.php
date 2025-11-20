<?php
require_once __DIR__ . '/db.php';

$orderId = '800938d0b7d89c066c495a358e3c28cb';
$stmt = $pdo->prepare("SELECT id, rx_note_path, rx_note_name, rx_note_mime, patient_id, created_at FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Order Upload Diagnostic</h2>";
echo "<p><strong>Order ID:</strong> " . htmlspecialchars($orderId) . "</p>";

if ($order) {
    echo "<h3>Database Status:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>Created</td><td>" . htmlspecialchars($order['created_at']) . "</td></tr>";
    echo "<tr><td>rx_note_path</td><td>" . htmlspecialchars($order['rx_note_path'] ?: 'NULL') . "</td></tr>";
    echo "<tr><td>rx_note_name</td><td>" . htmlspecialchars($order['rx_note_name'] ?: 'NULL') . "</td></tr>";
    echo "<tr><td>rx_note_mime</td><td>" . htmlspecialchars($order['rx_note_mime'] ?: 'NULL') . "</td></tr>";
    echo "</table>";

    if (!$order['rx_note_path']) {
        echo "<p style='color: red; font-weight: bold;'>⚠️ Visit note path is NULL in database</p>";
    } else {
        echo "<p style='color: green; font-weight: bold;'>✓ Visit note path exists in database</p>";

        // Check if file exists on disk
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $order['rx_note_path'];
        $persistentPath = '/opt/render/project/src' . $order['rx_note_path'];

        echo "<h3>File System Check:</h3>";
        echo "<p><strong>Web root path:</strong> " . htmlspecialchars($filePath) . " - " . (file_exists($filePath) ? '✓ EXISTS' : '✗ NOT FOUND') . "</p>";
        echo "<p><strong>Persistent disk path:</strong> " . htmlspecialchars($persistentPath) . " - " . (file_exists($persistentPath) ? '✓ EXISTS' : '✗ NOT FOUND') . "</p>";
    }
} else {
    echo "<p style='color: red;'>Order not found</p>";
}

echo "<hr>";
echo "<h3>Recent Server Logs (last 50 lines containing 'orders.create'):</h3>";
echo "<pre style='background: #f5f5f5; padding: 1rem; border: 1px solid #ddd; overflow-x: auto;'>";

// Try to read error logs - this will vary by server setup
$logPaths = [
    '/var/log/apache2/error.log',
    '/var/log/httpd/error_log',
    '/opt/render/project/src/logs/error.log',
    ini_get('error_log')
];

$foundLogs = false;
foreach ($logPaths as $logPath) {
    if ($logPath && file_exists($logPath) && is_readable($logPath)) {
        $foundLogs = true;
        echo "=== Log file: $logPath ===\n";
        $logs = shell_exec("tail -100 " . escapeshellarg($logPath) . " | grep 'orders.create' | tail -50");
        if ($logs) {
            echo htmlspecialchars($logs);
        } else {
            echo "No 'orders.create' entries found in last 100 lines\n";
        }
        echo "\n";
    }
}

if (!$foundLogs) {
    echo "Could not locate error logs. Tried:\n";
    foreach ($logPaths as $logPath) {
        echo "  - " . htmlspecialchars($logPath) . "\n";
    }
    echo "\nNote: Logs may not be accessible from web context for security reasons.\n";
}

echo "</pre>";

<?php
require_once __DIR__ . '/db.php';

$orderId = 'fa7613daca51b9aece4b6054f65bae38';
$stmt = $pdo->prepare("SELECT id, rx_note_path, rx_note_name, rx_note_mime, patient_id, created_at FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Order Visit Note Diagnostic</h2>";

if ($order) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>Order ID</td><td>" . htmlspecialchars($order['id']) . "</td></tr>";
    echo "<tr><td>Created</td><td>" . htmlspecialchars($order['created_at']) . "</td></tr>";
    echo "<tr><td>Patient ID</td><td>" . htmlspecialchars($order['patient_id']) . "</td></tr>";
    echo "<tr><td>rx_note_path</td><td>" . htmlspecialchars($order['rx_note_path'] ?: 'NULL') . "</td></tr>";
    echo "<tr><td>rx_note_name</td><td>" . htmlspecialchars($order['rx_note_name'] ?: 'NULL') . "</td></tr>";
    echo "<tr><td>rx_note_mime</td><td>" . htmlspecialchars($order['rx_note_mime'] ?: 'NULL') . "</td></tr>";
    echo "</table>";

    // Check if file exists
    if ($order['rx_note_path']) {
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $order['rx_note_path'];
        echo "<p><strong>File path on disk:</strong> " . htmlspecialchars($filePath) . "</p>";
        echo "<p><strong>File exists:</strong> " . (file_exists($filePath) ? 'YES' : 'NO') . "</p>";

        // Also check persistent disk path
        $persistentPath = '/opt/render/project/src' . $order['rx_note_path'];
        echo "<p><strong>Persistent disk path:</strong> " . htmlspecialchars($persistentPath) . "</p>";
        echo "<p><strong>Persistent disk exists:</strong> " . (file_exists($persistentPath) ? 'YES' : 'NO') . "</p>";
    } else {
        echo "<p style='color: red;'><strong>NO VISIT NOTE PATH IN DATABASE - This is the problem!</strong></p>";
    }
} else {
    echo "<p style='color: red;'>Order not found</p>";
}

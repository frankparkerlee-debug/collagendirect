<?php
require_once __DIR__ . '/../api/db.php';

$group_id = $_GET['id'] ?? '811d7d993f3a04baa221954eaff4fbd7';

echo "<h1>Order Group: " . htmlspecialchars($group_id) . "</h1>";

// Get order group
$stmt = $pdo->prepare("SELECT * FROM order_groups WHERE id = ?");
$stmt->execute([$group_id]);
$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    die("<p>Order group not found</p>");
}

echo "<h2>Order Group Record:</h2>";
echo "<table border='1' cellpadding='5'><tr><th>Field</th><th>Value</th></tr>";
foreach ($group as $key => $value) {
    echo "<tr><td>" . htmlspecialchars($key) . "</td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
}
echo "</table>";

echo "<h2>Orders in This Group:</h2>";
$stmt = $pdo->prepare("SELECT id, product, product_type, wound_index, status FROM orders WHERE order_group_id = ? ORDER BY wound_index, product_type");
$stmt->execute([$group_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Total orders: " . count($orders) . "</p>";
echo "<table border='1' cellpadding='5'><tr><th>Order ID</th><th>Product</th><th>Product Type</th><th>Wound Index</th><th>Status</th></tr>";
foreach ($orders as $o) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars(substr($o['id'], 0, 8)) . "...</td>";
    echo "<td>" . htmlspecialchars($o['product']) . "</td>";
    echo "<td>" . htmlspecialchars($o['product_type'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($o['wound_index'] ?? 'NULL') . "</td>";
    echo "<td>" . htmlspecialchars($o['status']) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Check if visit note file exists
if (!empty($group['visit_note_path'])) {
    $visit_note_path = $group['visit_note_path'];
    echo "<h2>Visit Note File Check:</h2>";
    echo "<p>Stored path: <code>" . htmlspecialchars($visit_note_path) . "</code></p>";

    // Check both possible locations
    $paths_to_check = [
        '/var/www/html' . $visit_note_path,
        '/opt/render/project/src/uploads' . str_replace('/uploads', '', $visit_note_path),
        __DIR__ . '/..' . $visit_note_path
    ];

    foreach ($paths_to_check as $path) {
        $exists = file_exists($path);
        echo "<p>Path: <code>" . htmlspecialchars($path) . "</code> - " . ($exists ? "✓ EXISTS" : "✗ NOT FOUND") . "</p>";
        if ($exists) {
            echo "<p>File size: " . filesize($path) . " bytes</p>";
        }
    }
}

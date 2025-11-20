<?php
require_once __DIR__ . '/db.php';

$orderId = 'cd79bbda9815d4147bd3cea50f21afac';
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: text/plain');

echo "=== FULL ORDER DATA ===\n";
echo "Order ID: $orderId\n";
echo "Created: " . ($order['created_at'] ?? 'NOT FOUND') . "\n\n";

if ($order) {
    echo "All columns:\n";
    foreach ($order as $key => $value) {
        $displayValue = $value === null ? 'NULL' : (is_string($value) && strlen($value) > 100 ? substr($value, 0, 100) . '...' : $value);
        echo "  $key: $displayValue\n";
    }
} else {
    echo "ORDER NOT FOUND!\n";
}

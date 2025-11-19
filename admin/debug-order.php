<?php
// Debug script to check order data
require_once __DIR__ . '/../api/db.php';

$order_id = $_GET['order_id'] ?? '42d80f6d665589006563be48119b4420';

echo "<h1>Order Debug: " . htmlspecialchars($order_id) . "</h1>";

// Get the order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h2>Order Record</h2>";
echo "<pre>";
print_r($order);
echo "</pre>";

if (!empty($order['order_group_id'])) {
    echo "<h2>All Orders in Group: " . htmlspecialchars($order['order_group_id']) . "</h2>";
    $stmt = $pdo->prepare("SELECT id, product, product_type, wound_index, cpt, product_price FROM orders WHERE order_group_id = ? ORDER BY wound_index, CASE product_type WHEN 'primary' THEN 1 WHEN 'secondary' THEN 2 WHEN 'additional' THEN 3 ELSE 4 END");
    $stmt->execute([$order['order_group_id']]);
    $group_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    print_r($group_orders);
    echo "</pre>";

    echo "<h2>Order Group Record</h2>";
    $stmt = $pdo->prepare("SELECT * FROM order_groups WHERE id = ?");
    $stmt->execute([$order['order_group_id']]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($group);
    echo "</pre>";
}

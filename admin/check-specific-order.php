<?php
require_once __DIR__ . '/../api/db.php';
header('Content-Type: text/plain');

$order_id = '42d80f6d665589006563be48119b4420';

echo "=== CHECKING ORDER: $order_id ===\n\n";

// Get the main order
$stmt = $pdo->prepare("SELECT id, order_group_id, product, product_type, wound_index, status, created_at FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

echo "Main Order:\n";
print_r($order);

if (!empty($order['order_group_id'])) {
    echo "\n=== ALL ORDERS IN GROUP: {$order['order_group_id']} ===\n";
    $stmt = $pdo->prepare("SELECT id, product, product_type, wound_index, status, created_at FROM orders WHERE order_group_id = ? ORDER BY created_at");
    $stmt->execute([$order['order_group_id']]);
    $all_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Total orders in group: " . count($all_orders) . "\n\n";
    foreach ($all_orders as $i => $o) {
        echo "Order " . ($i + 1) . ":\n";
        print_r($o);
        echo "\n";
    }
} else {
    echo "\n=== NO ORDER GROUP - Checking if this is a group parent ===\n";
    $stmt = $pdo->prepare("SELECT id, product, product_type, wound_index, status, created_at FROM orders WHERE order_group_id = ?");
    $stmt->execute([$order_id]);
    $child_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($child_orders) > 0) {
        echo "Found " . count($child_orders) . " child orders:\n\n";
        foreach ($child_orders as $i => $o) {
            echo "Child Order " . ($i + 1) . ":\n";
            print_r($o);
            echo "\n";
        }
    } else {
        echo "No child orders found. This is a single-product order.\n";
    }
}

echo "\n=== ORDER_GROUPS TABLE ===\n";
if (!empty($order['order_group_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM order_groups WHERE id = ?");
    $stmt->execute([$order['order_group_id']]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($group) {
        print_r($group);
    } else {
        echo "No order_groups record found for group ID: {$order['order_group_id']}\n";
    }
} else {
    echo "Order has no order_group_id\n";
}

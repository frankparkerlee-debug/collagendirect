<?php
require_once __DIR__ . '/../api/db.php';
header('Content-Type: text/plain');

$order_id = '42d80f6d665589006563be48119b4420';

echo "=== ORDER RECORD ===\n";
$stmt = $pdo->prepare("SELECT id, order_group_id, product, product_type, wound_index, status FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($order);

if (!empty($order['order_group_id'])) {
    echo "\n=== ALL ORDERS IN GROUP ===\n";
    $stmt = $pdo->prepare("SELECT id, product, product_type, wound_index FROM orders WHERE order_group_id = ?");
    $stmt->execute([$order['order_group_id']]);
    $all_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($all_orders);
} else {
    echo "\n=== CHECKING IF THIS ORDER IS IN A GROUP (by ID) ===\n";
    $stmt = $pdo->prepare("SELECT id, product, product_type, wound_index, order_group_id FROM orders WHERE id = ? OR order_group_id = ?");
    $stmt->execute([$order_id, $order_id]);
    $related = $stmt->fetchAll(PDO::FETCH_ASSOC);
    print_r($related);
}

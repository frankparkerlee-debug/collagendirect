<?php
/**
 * One-time script to delete specific test orders by ID
 * Security: Simple confirmation parameter
 */

// Security check
$confirm = $_GET['confirm'] ?? '';
if ($confirm !== 'yes') {
    http_response_code(403);
    die('Access denied. Add ?confirm=yes to URL to run this deletion.');
}

header('Content-Type: text/plain; charset=utf-8');

// Load database connection
require_once __DIR__ . '/../api/db.php';

// Order IDs to delete
$orderIds = [
    '80573dd83a7d8f69a9c0316911937718',
    'd8b121198ec6e6628fb189f4a833aea8',
    'c57e7444586f266b261ca3c304eb25b2',
    '38c2de1a867bc31a50dd61239f8d54bb',
    'c8c9448bb2fd14516c3964da98c1ac2c'
];

echo "Deleting " . count($orderIds) . " orders...\n\n";

try {
    $pdo->beginTransaction();

    // Check orders before deletion
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = $pdo->prepare("
        SELECT id, patient_id, product, status, review_status, created_at
        FROM orders
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($orderIds);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($orders) . " order(s) to delete:\n";
    foreach ($orders as $order) {
        echo "  - {$order['id']}\n";
        echo "    Product: {$order['product']}\n";
        echo "    Status: {$order['status']} / {$order['review_status']}\n";
        echo "    Created: {$order['created_at']}\n\n";
    }

    if (empty($orders)) {
        echo "No orders found with those IDs.\n";
        $pdo->rollBack();
        exit;
    }

    // Delete related records first
    $stmt = $pdo->prepare("DELETE FROM order_alerts WHERE order_id IN ($placeholders)");
    $stmt->execute($orderIds);
    $alertsDeleted = $stmt->rowCount();
    echo "Deleted {$alertsDeleted} order alert(s)\n";

    // Delete order_revisions if exists
    try {
        $stmt = $pdo->prepare("DELETE FROM order_revisions WHERE order_id IN ($placeholders)");
        $stmt->execute($orderIds);
        $revisionsDeleted = $stmt->rowCount();
        echo "Deleted {$revisionsDeleted} order revision(s)\n";
    } catch (PDOException $e) {
        echo "Note: order_revisions table not found (this is OK)\n";
    }

    // Delete order_history if exists
    try {
        $stmt = $pdo->prepare("DELETE FROM order_history WHERE order_id IN ($placeholders)");
        $stmt->execute($orderIds);
        $historyDeleted = $stmt->rowCount();
        echo "Deleted {$historyDeleted} order history record(s)\n";
    } catch (PDOException $e) {
        echo "Note: order_history table not found (this is OK)\n";
    }

    // Delete the orders
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id IN ($placeholders)");
    $stmt->execute($orderIds);
    $ordersDeleted = $stmt->rowCount();
    echo "Deleted {$ordersDeleted} order(s)\n";

    $pdo->commit();

    echo "\n✓ Successfully deleted orders\n";
    echo "Summary:\n";
    echo "  - Orders: {$ordersDeleted}\n";
    echo "  - Alerts: {$alertsDeleted}\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

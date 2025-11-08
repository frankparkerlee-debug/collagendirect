<?php
/**
 * Execute SQL to delete 5 specific test orders
 * Security: Simple confirmation parameter
 */

// Security check
$confirm = $_GET['confirm'] ?? '';
if ($confirm !== 'yes') {
    http_response_code(403);
    die('Access denied. Add ?confirm=yes to URL to run this deletion.');
}

header('Content-Type: text/plain; charset=utf-8');

// Direct database connection
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'collagen_db';
$DB_USER = getenv('DB_USER') ?: 'postgres';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_PORT = getenv('DB_PORT') ?: '5432';

try {
    $pdo = new PDO(
        "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}",
        $DB_USER,
        $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Deleting 5 test orders...\n\n";

    $pdo->beginTransaction();

    // Order IDs to delete
    $orderIds = [
        '80573dd83a7d8f69a9c0316911937718',
        'd8b121198ec6e6628fb189f4a833aea8',
        'c57e7444586f266b261ca3c304eb25b2',
        '38c2de1a867bc31a50dd61239f8d54bb',
        'c8c9448bb2fd14516c3964da98c1ac2c'
    ];

    // Show what we're deleting
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $stmt = $pdo->prepare("
        SELECT id, patient_id, product, status, review_status, created_at
        FROM orders
        WHERE id IN ($placeholders)
    ");
    $stmt->execute($orderIds);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($orders) . " order(s):\n";
    foreach ($orders as $order) {
        echo "  - {$order['id']}\n";
        echo "    Product: " . ($order['product'] ?? 'N/A') . "\n";
        echo "    Status: {$order['status']} / {$order['review_status']}\n";
        echo "    Created: {$order['created_at']}\n\n";
    }

    if (empty($orders)) {
        echo "No orders found with those IDs.\n";
        $pdo->rollBack();
        exit;
    }

    // Delete order_alerts
    $stmt = $pdo->prepare("DELETE FROM order_alerts WHERE order_id IN ($placeholders)");
    $stmt->execute($orderIds);
    $alertsDeleted = $stmt->rowCount();

    // Delete order_revisions (ignore if table doesn't exist)
    $revisionsDeleted = 0;
    try {
        $stmt = $pdo->prepare("DELETE FROM order_revisions WHERE order_id IN ($placeholders)");
        $stmt->execute($orderIds);
        $revisionsDeleted = $stmt->rowCount();
    } catch (PDOException $e) {
        // Table might not exist
    }

    // Delete order_history (ignore if table doesn't exist)
    $historyDeleted = 0;
    try {
        $stmt = $pdo->prepare("DELETE FROM order_history WHERE order_id IN ($placeholders)");
        $stmt->execute($orderIds);
        $historyDeleted = $stmt->rowCount();
    } catch (PDOException $e) {
        // Table might not exist
    }

    // Delete the orders
    $stmt = $pdo->prepare("DELETE FROM orders WHERE id IN ($placeholders)");
    $stmt->execute($orderIds);
    $ordersDeleted = $stmt->rowCount();

    $pdo->commit();

    echo "✓ Deletion complete!\n\n";
    echo "Summary:\n";
    echo "  - Orders deleted: {$ordersDeleted}\n";
    echo "  - Alerts deleted: {$alertsDeleted}\n";
    echo "  - Revisions deleted: {$revisionsDeleted}\n";
    echo "  - History deleted: {$historyDeleted}\n";

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}

#!/usr/bin/env php
<?php
/**
 * CLI script to delete all orders for a specific patient by MRN
 * Usage: php delete-orders-by-mrn.php CD-20251027-DCDA
 */
declare(strict_types=1);

// Get MRN from command line
$mrn = $argv[1] ?? '';

if (empty($mrn)) {
    echo "Usage: php delete-orders-by-mrn.php <MRN>\n";
    echo "Example: php delete-orders-by-mrn.php CD-20251027-DCDA\n";
    exit(1);
}

// Load database connection
require_once __DIR__ . '/db-cli.php';

try {
    $pdo->beginTransaction();

    // 1. Find the patient
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, mrn FROM patients WHERE mrn = ?");
    $stmt->execute([$mrn]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $pdo->rollBack();
        echo "ERROR: Patient with MRN '{$mrn}' not found.\n";
        exit(1);
    }

    echo "Found patient:\n";
    echo "  ID: {$patient['id']}\n";
    echo "  Name: {$patient['first_name']} {$patient['last_name']}\n";
    echo "  MRN: {$patient['mrn']}\n\n";

    // 2. Find all orders
    $stmt = $pdo->prepare("
        SELECT id, status, review_status, created_at
        FROM orders
        WHERE patient_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$patient['id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        $pdo->rollBack();
        echo "No orders found for this patient.\n";
        exit(0);
    }

    echo "Found " . count($orders) . " order(s):\n";
    foreach ($orders as $order) {
        echo "  - {$order['id']} | Status: {$order['status']} | Review: {$order['review_status']} | Created: {$order['created_at']}\n";
    }
    echo "\n";

    // 3. Delete order_alerts
    $stmt = $pdo->prepare("DELETE FROM order_alerts WHERE order_id IN (SELECT id FROM orders WHERE patient_id = ?)");
    $stmt->execute([$patient['id']]);
    $alertsDeleted = $stmt->rowCount();
    echo "Deleted {$alertsDeleted} order alert(s)\n";

    // 4. Delete order_history (if exists)
    try {
        $stmt = $pdo->prepare("DELETE FROM order_history WHERE order_id IN (SELECT id FROM orders WHERE patient_id = ?)");
        $stmt->execute([$patient['id']]);
        $historyDeleted = $stmt->rowCount();
        echo "Deleted {$historyDeleted} order history record(s)\n";
    } catch (PDOException $e) {
        // Table might not exist
        echo "Note: order_history table not found (this is OK)\n";
    }

    // 5. Delete orders
    $stmt = $pdo->prepare("DELETE FROM orders WHERE patient_id = ?");
    $stmt->execute([$patient['id']]);
    $ordersDeleted = $stmt->rowCount();
    echo "Deleted {$ordersDeleted} order(s)\n";

    $pdo->commit();

    echo "\n✓ Successfully deleted all orders for patient {$mrn}\n";
    echo "Summary:\n";
    echo "  - Orders: {$ordersDeleted}\n";
    echo "  - Alerts: {$alertsDeleted}\n";

    exit(0);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

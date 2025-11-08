<?php
/**
 * Admin script to delete all orders for a specific patient
 * Usage: Run via browser or CLI with patient MRN parameter
 */
declare(strict_types=1);

// Load database connection
require_once __DIR__ . '/../api/db.php';

// Check authentication - must be superadmin
if (empty($_SESSION['user_id'])) {
    die("Error: Not authenticated. Please log in as superadmin.\n");
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'superadmin') {
    die("Error: Superadmin access required.\n");
}

// Get patient MRN from command line or query string
$patientMrn = $_GET['mrn'] ?? $_POST['mrn'] ?? ($argv[1] ?? '');

if (empty($patientMrn)) {
    die("Error: Patient MRN required. Usage: ?mrn=CD-20251027-DCDA\n");
}

try {
    $pdo->beginTransaction();

    // First, find the patient
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, mrn FROM patients WHERE mrn = ?");
    $stmt->execute([$patientMrn]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient) {
        $pdo->rollBack();
        die("Error: Patient with MRN '{$patientMrn}' not found.\n");
    }

    echo "Found patient: {$patient['first_name']} {$patient['last_name']} (ID: {$patient['id']}, MRN: {$patient['mrn']})\n\n";

    // Find all orders for this patient
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
        exit;
    }

    echo "Found " . count($orders) . " order(s) to delete:\n";
    foreach ($orders as $order) {
        echo "  - Order ID: {$order['id']}, Status: {$order['status']}, Review: {$order['review_status']}, Created: {$order['created_at']}\n";
    }
    echo "\n";

    // Delete related data first (to maintain referential integrity)

    // 1. Delete order alerts
    $stmt = $pdo->prepare("DELETE FROM order_alerts WHERE order_id IN (SELECT id FROM orders WHERE patient_id = ?)");
    $stmt->execute([$patient['id']]);
    $alertsDeleted = $stmt->rowCount();
    echo "Deleted {$alertsDeleted} order alert(s)\n";

    // 2. Delete order history/audit logs if they exist
    $stmt = $pdo->prepare("DELETE FROM order_history WHERE order_id IN (SELECT id FROM orders WHERE patient_id = ?)");
    $stmt->execute([$patient['id']]);
    $historyDeleted = $stmt->rowCount();
    echo "Deleted {$historyDeleted} order history record(s)\n";

    // 3. Delete the orders themselves
    $stmt = $pdo->prepare("DELETE FROM orders WHERE patient_id = ?");
    $stmt->execute([$patient['id']]);
    $ordersDeleted = $stmt->rowCount();
    echo "Deleted {$ordersDeleted} order(s)\n";

    $pdo->commit();

    echo "\n✓ Successfully deleted all orders for patient {$patient['mrn']}\n";
    echo "Summary:\n";
    echo "  - Orders deleted: {$ordersDeleted}\n";
    echo "  - Alerts deleted: {$alertsDeleted}\n";
    echo "  - History records deleted: {$historyDeleted}\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete patient orders error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

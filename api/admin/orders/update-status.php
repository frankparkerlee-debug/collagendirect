<?php
// api/admin/orders/update-status.php
declare(strict_types=1);
require __DIR__ . '/../../db.php';
require_csrf();

// Require superadmin authentication
if (!isset($_SESSION['user_id'])) {
    json_out(401, ['error' => 'Unauthorized']);
}

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['role'] !== 'superadmin') {
    json_out(403, ['error' => 'Superadmin access required']);
}

try {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
} catch (Throwable $e) {
    json_out(400, ['error' => 'Invalid JSON']);
}

$orderId = (string)($data['order_id'] ?? '');
$newStatus = (string)($data['status'] ?? '');
$notes = (string)($data['notes'] ?? '');
$trackingCode = (string)($data['tracking_code'] ?? '');
$carrier = (string)($data['carrier'] ?? '');
$cashPrice = isset($data['cash_price']) ? (float)$data['cash_price'] : null;

if (!$orderId || !$newStatus) {
    json_out(400, ['error' => 'order_id and status required']);
}

// Validate status
$validStatuses = [
    'draft', 'submitted', 'under_review', 'incomplete',
    'verification_pending', 'cash_price_required', 'cash_price_approved',
    'approved', 'in_production', 'shipped', 'delivered',
    'terminated', 'cancelled'
];

if (!in_array($newStatus, $validStatuses)) {
    json_out(400, ['error' => 'Invalid status value']);
}

try {
    $pdo->beginTransaction();

    // Update order status
    $updateFields = ['status = ?', 'reviewed_at = NOW()', 'reviewed_by = ?'];
    $params = [$newStatus, $_SESSION['user_id']];

    if ($notes) {
        $updateFields[] = 'review_notes = ?';
        $params[] = $notes;
    }

    if ($trackingCode && $carrier) {
        $updateFields[] = 'tracking_code = ?';
        $updateFields[] = 'carrier = ?';
        $params[] = $trackingCode;
        $params[] = $carrier;
    }

    if ($cashPrice !== null) {
        $updateFields[] = 'cash_price = ?';
        $params[] = $cashPrice;
    }

    $params[] = $orderId;

    $sql = "UPDATE orders SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        json_out(404, ['error' => 'Order not found']);
    }

    // If status is cash_price_required, create an alert for the physician
    if ($newStatus === 'cash_price_required') {
        // Get order details to find physician
        $stmt = $pdo->prepare("SELECT user_id, patient_id FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if ($order) {
            $stmt = $pdo->prepare("
                INSERT INTO order_alerts (order_id, alert_type, message, severity, recipient_role)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $orderId,
                'cash_price_required',
                'Insurance verification failed. Cash price option available: $' . number_format($cashPrice, 2),
                'critical',
                'physician'
            ]);
        }
    }

    $pdo->commit();

    json_out(200, [
        'success' => true,
        'order_id' => $orderId,
        'new_status' => $newStatus
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Status update error: " . $e->getMessage());
    json_out(500, [
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}

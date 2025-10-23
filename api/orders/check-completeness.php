<?php
// api/orders/check-completeness.php
declare(strict_types=1);
require __DIR__ . '/../db.php';
require_csrf();

// Require authentication
if (!isset($_SESSION['user_id'])) {
    json_out(401, ['error' => 'Unauthorized']);
}

try {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
} catch (Throwable $e) {
    json_out(400, ['error' => 'Invalid JSON']);
}

$orderId = (string)($data['order_id'] ?? '');

if (!$orderId) {
    json_out(400, ['error' => 'order_id required']);
}

try {
    // Verify user has access to this order
    $stmt = $pdo->prepare("
        SELECT id FROM orders
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$orderId, $_SESSION['user_id']]);

    if (!$stmt->fetch()) {
        json_out(403, ['error' => 'Access denied']);
    }

    // Call the completeness check function
    $stmt = $pdo->prepare("SELECT * FROM check_order_completeness(?)");
    $stmt->execute([$orderId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        json_out(404, ['error' => 'Order not found']);
    }

    // Parse the missing_fields array from PostgreSQL format
    $missingFields = [];
    if (!empty($result['missing_fields'])) {
        // PostgreSQL returns arrays as {item1,item2,item3}
        $arrayStr = trim($result['missing_fields'], '{}');
        if ($arrayStr) {
            $missingFields = explode(',', $arrayStr);
        }
    }

    json_out(200, [
        'is_complete' => (bool)$result['is_complete'],
        'missing_fields' => $missingFields,
        'order_id' => $orderId
    ]);

} catch (Exception $e) {
    error_log("Completeness check error: " . $e->getMessage());
    json_out(500, ['error' => 'Server error']);
}

<?php
/**
 * Submit a draft order for admin review
 * Changes status from 'draft' to 'submitted'
 * Changes review_status from 'draft' to 'pending_admin_review'
 */
declare(strict_types=1);

// Start output buffering to prevent any HTML errors from leaking
ob_start();

require __DIR__ . '/../db.php';

// Clear any buffered output and set JSON header
ob_end_clean();
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$userId = $_SESSION['user_id'];

// Get order ID
$orderId = trim($_POST['order_id'] ?? $_GET['order_id'] ?? '');

if (empty($orderId)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Order ID required']);
  exit;
}

try {
  // Verify order exists and belongs to this physician
  $stmt = $pdo->prepare("
    SELECT id, review_status, patient_id, status
    FROM orders
    WHERE id = ? AND user_id = ?
  ");
  $stmt->execute([$orderId, $userId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$order) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Order not found or access denied']);
    exit;
  }

  // Verify it's actually a draft
  if ($order['review_status'] !== 'draft') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Order is not a draft (current status: ' . ($order['review_status'] ?? 'null') . ')']);
    exit;
  }

  // Update status and review_status when submitting draft
  $stmt = $pdo->prepare("
    UPDATE orders
    SET status = 'submitted',
        review_status = 'pending_admin_review',
        updated_at = NOW()
    WHERE id = ? AND user_id = ?
  ");
  $stmt->execute([$orderId, $userId]);

  if ($stmt->rowCount() === 0) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to update order status']);
    exit;
  }

  echo json_encode([
    'ok' => true,
    'message' => 'Order submitted successfully for admin review',
    'order_id' => $orderId
  ]);

} catch (Exception $e) {
  error_log("Submit draft error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Server error']);
}

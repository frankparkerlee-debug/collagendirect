<?php
/**
 * API endpoint for admin to review and approve/reject/request changes to orders
 * This is the final step in the order approval workflow
 */

header('Content-Type: application/json');
session_start();

try {
  require_once __DIR__ . '/../db.php';
  require_once __DIR__ . '/../lib/email_notifications.php';
} catch (Exception $e) {
  echo json_encode(['ok' => false, 'error' => 'Failed to load dependencies: ' . $e->getMessage()]);
  exit;
}

// Check authentication and admin role
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? '';

if ($userRole !== 'superadmin') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'Admin access required']);
  exit;
}

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
  $data = $_POST;
}

$orderId = $data['order_id'] ?? '';
$action = $data['action'] ?? ''; // approve, request_changes, reject
$notes = $data['notes'] ?? null;

if (empty($orderId)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Order ID required']);
  exit;
}

if (!in_array($action, ['approve', 'request_changes', 'reject'])) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid action. Must be: approve, request_changes, or reject']);
  exit;
}

try {
  $pdo->beginTransaction();

  // Get current order with physician info
  $stmt = $pdo->prepare("
    SELECT
      o.*,
      u.first_name as physician_first_name,
      u.last_name as physician_last_name,
      u.email as physician_email,
      p.first_name as patient_first_name,
      p.last_name as patient_last_name
    FROM orders o
    INNER JOIN users u ON o.user_id = u.id
    LEFT JOIN patients p ON o.patient_id = p.id
    WHERE o.id = ?
  ");
  $stmt->execute([$orderId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$order) {
    $pdo->rollBack();
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Order not found']);
    exit;
  }

  // Determine new status based on action
  $newStatus = match($action) {
    'approve' => 'approved',
    'request_changes' => 'needs_revision',
    'reject' => 'rejected',
    default => null
  };

  if (!$newStatus) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid action']);
    exit;
  }

  // Build update query
  $updateSql = "
    UPDATE orders
    SET
      review_status = ?,
      reviewed_by = ?,
      reviewed_at = NOW(),
      review_notes = ?,
      updated_at = NOW()
  ";

  $params = [$newStatus, $userId, $notes];

  // Lock order if approved or rejected
  if (in_array($action, ['approve', 'reject'])) {
    $updateSql .= ", locked_at = NOW(), locked_by = ?";
    $params[] = $userId;
  } else {
    // Unlock if requesting changes
    $updateSql .= ", locked_at = NULL, locked_by = NULL";
  }

  $updateSql .= " WHERE id = ?";
  $params[] = $orderId;

  $pdo->prepare($updateSql)->execute($params);

  // Record this review action in order_revisions
  $revisionStmt = $pdo->prepare("
    INSERT INTO order_revisions
      (order_id, changed_by, changed_at, changes, reason, ai_suggested)
    VALUES (?, ?, NOW(), ?::jsonb, ?, FALSE)
  ");
  $revisionStmt->execute([
    $orderId,
    $userId,
    json_encode([
      'review_status' => [
        'old' => $order['review_status'],
        'new' => $newStatus
      ],
      'action' => $action
    ]),
    "Admin review: $action" . ($notes ? " - $notes" : '')
  ]);

  $pdo->commit();

  // Send notification email to physician
  try {
    $physicianName = trim(($order['physician_first_name'] ?? '') . ' ' . ($order['physician_last_name'] ?? ''));
    $patientName = trim(($order['patient_first_name'] ?? '') . ' ' . ($order['patient_last_name'] ?? ''));
    $physicianEmail = $order['physician_email'] ?? null;

    if ($physicianEmail) {
      sendOrderReviewNotification([
        'physician_email' => $physicianEmail,
        'physician_name' => $physicianName,
        'order_id' => $orderId,
        'patient_name' => $patientName,
        'action' => $action,
        'review_notes' => $notes,
        'product_name' => $order['product'] ?? ''
      ]);
    }
  } catch (Exception $emailErr) {
    error_log("Failed to send order review notification: " . $emailErr->getMessage());
    // Don't fail the request if email fails
  }

  echo json_encode([
    'ok' => true,
    'message' => 'Order reviewed successfully',
    'new_status' => $newStatus,
    'action' => $action
  ]);

} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log("Order review error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Failed to review order']);
}

/**
 * Send email notification to physician about order review
 */
function sendOrderReviewNotification(array $data): void {
  $action = $data['action'];
  $physicianEmail = $data['physician_email'];
  $physicianName = $data['physician_name'];
  $orderId = $data['order_id'];
  $patientName = $data['patient_name'];
  $productName = $data['product_name'];
  $reviewNotes = $data['review_notes'] ?? '';

  // Determine subject and message based on action
  $subject = match($action) {
    'approve' => "Order Approved - $orderId",
    'request_changes' => "Order Revision Requested - $orderId",
    'reject' => "Order Rejected - $orderId",
    default => "Order Update - $orderId"
  };

  $message = match($action) {
    'approve' => "Your order for $patientName has been approved and will be processed shortly.",
    'request_changes' => "Your order for $patientName requires revisions before it can be approved. Please review the feedback and update the order.",
    'reject' => "Your order for $patientName has been rejected. Please contact us if you have questions.",
    default => "Your order status has been updated."
  };

  $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
    .header { background-color: #0066cc; color: white; padding: 20px; text-align: center; }
    .content { padding: 20px; background-color: #f9f9f9; }
    .info { margin: 15px 0; padding: 10px; background-color: white; border-left: 4px solid #0066cc; }
    .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
    .notes { margin: 15px 0; padding: 15px; background-color: #fff3cd; border-left: 4px solid #ffc107; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>Order Review Notification</h1>
    </div>
    <div class="content">
      <p>Dear {$physicianName},</p>
      <p>{$message}</p>

      <div class="info">
        <strong>Order Details:</strong><br>
        Order ID: {$orderId}<br>
        Patient: {$patientName}<br>
        Product: {$productName}<br>
        Status: {$action}
      </div>

HTML;

  if ($reviewNotes) {
    $html .= <<<HTML
      <div class="notes">
        <strong>Reviewer Notes:</strong><br>
        {$reviewNotes}
      </div>
HTML;
  }

  if ($action === 'request_changes') {
    $html .= <<<HTML
      <p>
        <a href="https://collagendirect.health/portal" style="display: inline-block; padding: 10px 20px; background-color: #0066cc; color: white; text-decoration: none; border-radius: 5px;">
          Edit Order
        </a>
      </p>
HTML;
  }

  $html .= <<<HTML
      <p>If you have any questions, please contact us at <a href="mailto:support@collagendirect.health">support@collagendirect.health</a>.</p>
    </div>
    <div class="footer">
      <p>CollagenDirect - Premium Collagen Wound Care</p>
      <p><a href="https://collagendirect.health">collagendirect.health</a></p>
    </div>
  </div>
</body>
</html>
HTML;

  // Send email using SendGrid
  send_email($physicianEmail, $subject, $html);
}

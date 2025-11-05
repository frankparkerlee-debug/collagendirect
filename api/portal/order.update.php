<?php
/**
 * API endpoint to update an existing order
 * Only allows updates when order is in draft or needs_revision status
 * Implements edit lock checks and revision tracking
 */

header('Content-Type: application/json');
session_start();

try {
  require_once __DIR__ . '/../db.php';
} catch (Exception $e) {
  echo json_encode(['ok' => false, 'error' => 'Failed to load database: ' . $e->getMessage()]);
  exit;
}

// Check authentication
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'physician';

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
  $data = $_POST;
}

$orderId = $data['order_id'] ?? '';
$updates = $data['updates'] ?? [];
$acceptAiSuggestions = ($data['accept_ai_suggestions'] ?? false) === true;
$reason = $data['reason'] ?? null;

if (empty($orderId)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Order ID required']);
  exit;
}

if (empty($updates) && !$acceptAiSuggestions) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'No updates provided']);
  exit;
}

try {
  $pdo->beginTransaction();

  // Get current order
  $stmt = $pdo->prepare("
    SELECT
      id, user_id, review_status, locked_at, locked_by,
      ai_suggestions, ai_suggestions_accepted,
      patient_id, product, product_id, frequency,
      wound_location, wound_laterality, wound_notes, wounds_data,
      delivery_mode, shipping_name, shipping_phone,
      shipping_address, shipping_city, shipping_state, shipping_zip,
      insurer_name, member_id, group_id, payer_phone,
      payment_type, prior_auth
    FROM orders
    WHERE id = ?
  ");
  $stmt->execute([$orderId]);
  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$order) {
    $pdo->rollBack();
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Order not found']);
    exit;
  }

  // Check permissions
  $canEdit = canEditOrder($order, $userId, $userRole);
  if (!$canEdit['allowed']) {
    $pdo->rollBack();
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => $canEdit['reason']]);
    exit;
  }

  // If accepting AI suggestions, merge them into updates
  if ($acceptAiSuggestions && !empty($order['ai_suggestions'])) {
    $aiSuggestions = json_decode($order['ai_suggestions'], true);
    if (is_array($aiSuggestions) && isset($aiSuggestions['suggestions'])) {
      foreach ($aiSuggestions['suggestions'] as $suggestion) {
        $field = $suggestion['field'] ?? null;
        $suggestedValue = $suggestion['suggested_value'] ?? null;
        if ($field && $suggestedValue) {
          $updates[$field] = $suggestedValue;
        }
      }
    }
  }

  // Build update query dynamically
  $allowedFields = [
    'frequency', 'wound_location', 'wound_laterality', 'wound_notes', 'wounds_data',
    'delivery_mode', 'shipping_name', 'shipping_phone',
    'shipping_address', 'shipping_city', 'shipping_state', 'shipping_zip',
    'insurer_name', 'member_id', 'group_id', 'payer_phone',
    'payment_type', 'prior_auth'
  ];

  $setClauses = [];
  $params = [];
  $changes = [];

  foreach ($updates as $field => $value) {
    if (!in_array($field, $allowedFields)) {
      continue; // Skip non-updatable fields
    }

    $oldValue = $order[$field] ?? null;
    if ($oldValue !== $value) {
      $setClauses[] = "$field = ?";
      $params[] = $value;
      $changes[$field] = [
        'old' => $oldValue,
        'new' => $value
      ];
    }
  }

  if (empty($setClauses)) {
    $pdo->rollBack();
    echo json_encode(['ok' => true, 'message' => 'No changes detected']);
    exit;
  }

  // Add updated_at timestamp
  $setClauses[] = "updated_at = NOW()";

  // Update AI suggestions acceptance if applicable
  if ($acceptAiSuggestions) {
    $setClauses[] = "ai_suggestions_accepted = TRUE";
    $setClauses[] = "ai_suggestions_accepted_at = NOW()";
  }

  // Execute update
  $params[] = $orderId;
  $updateSql = "UPDATE orders SET " . implode(', ', $setClauses) . " WHERE id = ?";
  $pdo->prepare($updateSql)->execute($params);

  // Record revision in order_revisions table
  if (!empty($changes)) {
    $revisionStmt = $pdo->prepare("
      INSERT INTO order_revisions
        (order_id, changed_by, changed_at, changes, reason, ai_suggested)
      VALUES (?, ?, NOW(), ?::jsonb, ?, ?)
    ");
    $revisionStmt->execute([
      $orderId,
      $userId,
      json_encode($changes),
      $reason,
      $acceptAiSuggestions
    ]);
  }

  $pdo->commit();

  echo json_encode([
    'ok' => true,
    'message' => 'Order updated successfully',
    'changes_count' => count($changes)
  ]);

} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log("Order update error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Failed to update order']);
}

/**
 * Check if user can edit this order
 */
function canEditOrder(array $order, string $userId, string $userRole): array {
  // Superadmin can always edit (with logging)
  if ($userRole === 'superadmin') {
    return ['allowed' => true];
  }

  // Check if user owns this order
  if ($order['user_id'] !== $userId) {
    return [
      'allowed' => false,
      'reason' => 'You do not have permission to edit this order'
    ];
  }

  // Check if order is locked
  if (!empty($order['locked_at'])) {
    return [
      'allowed' => false,
      'reason' => 'This order has been locked and cannot be edited'
    ];
  }

  // Check review status
  $editableStatuses = ['draft', 'needs_revision'];
  if (!in_array($order['review_status'], $editableStatuses)) {
    return [
      'allowed' => false,
      'reason' => 'This order cannot be edited in its current status: ' . $order['review_status']
    ];
  }

  return ['allowed' => true];
}

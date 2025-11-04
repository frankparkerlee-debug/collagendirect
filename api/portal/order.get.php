<?php
/**
 * API endpoint to retrieve a single order for viewing/editing
 */

// Load database connection (includes session handling)
try {
  require_once __DIR__ . '/../db.php';
} catch (Exception $e) {
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'Failed to load database: ' . $e->getMessage()]);
  exit;
}

header('Content-Type: application/json');

// Check authentication
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'physician';

// Get order ID
$orderId = '';
if (isset($_GET['order_id'])) {
  $orderId = trim($_GET['order_id']);
} elseif (isset($_POST['order_id'])) {
  $orderId = trim($_POST['order_id']);
}

if (empty($orderId)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Order ID required']);
  exit;
}

try {
  // Get order with permission check
  if ($userRole === 'superadmin') {
    // Superadmin can view any order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
  } else {
    // Regular physicians can only view their own orders
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
  }

  $order = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$order) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Order not found or access denied']);
    exit;
  }

  // Decode JSON fields if present
  if (!empty($order['ai_suggestions'])) {
    $order['ai_suggestions'] = json_decode($order['ai_suggestions'], true);
  }

  echo json_encode([
    'ok' => true,
    'order' => $order
  ]);

} catch (Exception $e) {
  error_log("Get order error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Failed to retrieve order']);
}

<?php
// /public/api/portal/metrics.php
declare(strict_types=1);
require __DIR__ . '/../db.php';
header('Content-Type: application/json');

try {
  if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'unauthorized']); exit;
  }
  $uid = $_SESSION['user_id'];

  // total patients owned by this physician/user
  $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM patients WHERE user_id = ?");
  $stmt->execute([$uid]);
  $totalPatients = (int)($stmt->fetchColumn() ?: 0);

  // pending approvals = orders that are 'submitted' (or 'pending') for this physician
  $stmt = $pdo->prepare("
    SELECT COUNT(*) AS c
    FROM orders
    WHERE user_id = ?
      AND (status IN ('submitted','pending','pending_approval'))
  ");
  $stmt->execute([$uid]);
  $pendingApprovals = (int)($stmt->fetchColumn() ?: 0);

  // active orders = approved but not yet delivered (no delivered_at)
  $stmt = $pdo->prepare("
    SELECT COUNT(*) AS c
    FROM orders
    WHERE user_id = ?
      AND status IN ('approved','in_fulfillment','shipped')
      AND delivered_at IS NULL
  ");
  $stmt->execute([$uid]);
  $activeOrders = (int)($stmt->fetchColumn() ?: 0);

  // shipped this week = shipped_at within current ISO week
  $stmt = $pdo->prepare("
    SELECT COUNT(*) AS c
    FROM orders
    WHERE user_id = ?
      AND shipped_at >= DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE())+0) DAY)
  ");
  $stmt->execute([$uid]);
  $shippedThisWeek = (int)($stmt->fetchColumn() ?: 0);

  echo json_encode([
    'ok' => true,
    'data' => [
      'total_patients'     => $totalPatients,
      'pending_approvals'  => $pendingApprovals,
      'active_orders'      => $activeOrders,
      'shipped_this_week'  => $shippedThisWeek
    ]
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'server_error']);
}

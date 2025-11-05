<?php
/**
 * Billing Routes API
 * Manages insurance-based billing routing for hybrid DME practices
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

// Verify authentication
$user = verifyAuth();
if (!$user) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

$userId = $user['id'];
$userRole = $user['role'];

// Only allow practice_admin and above to manage billing routes
if (!in_array($userRole, ['practice_admin', 'physician', 'superadmin'])) {
  http_response_code(403);
  echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
  exit;
}

$action = $_REQUEST['action'] ?? '';

try {
  switch ($action) {
    case 'routes.get':
      // Get billing routes for current user
      $stmt = $pdo->prepare("
        SELECT id, insurer_name, billing_route, created_at, updated_at
        FROM practice_billing_routes
        WHERE user_id = ?
        ORDER BY insurer_name
      ");
      $stmt->execute([$userId]);
      $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

      echo json_encode([
        'success' => true,
        'routes' => $routes
      ]);
      break;

    case 'routes.set':
      // Set billing route for an insurer
      $insurerName = $_POST['insurer_name'] ?? '';
      $billingRoute = $_POST['billing_route'] ?? '';

      if (!$insurerName || !$billingRoute) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
      }

      if (!in_array($billingRoute, ['collagen_direct', 'practice_dme'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid billing route']);
        exit;
      }

      // Upsert billing route
      $stmt = $pdo->prepare("
        INSERT INTO practice_billing_routes (user_id, insurer_name, billing_route, updated_at)
        VALUES (?, ?, ?, NOW())
        ON CONFLICT (user_id, insurer_name)
        DO UPDATE SET
          billing_route = EXCLUDED.billing_route,
          updated_at = NOW()
        RETURNING id, insurer_name, billing_route
      ");
      $stmt->execute([$userId, $insurerName, $billingRoute]);
      $route = $stmt->fetch(PDO::FETCH_ASSOC);

      echo json_encode([
        'success' => true,
        'route' => $route
      ]);
      break;

    case 'routes.delete':
      // Delete billing route (revert to default)
      $insurerName = $_POST['insurer_name'] ?? '';

      if (!$insurerName) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing insurer_name']);
        exit;
      }

      $stmt = $pdo->prepare("
        DELETE FROM practice_billing_routes
        WHERE user_id = ? AND insurer_name = ?
      ");
      $stmt->execute([$userId, $insurerName]);

      echo json_encode(['success' => true]);
      break;

    case 'routes.bulk_set':
      // Set multiple routes at once
      $routes = json_decode($_POST['routes'] ?? '[]', true);

      if (!is_array($routes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid routes data']);
        exit;
      }

      $pdo->beginTransaction();

      try {
        // Delete all existing routes for this user
        $pdo->prepare("DELETE FROM practice_billing_routes WHERE user_id = ?")
          ->execute([$userId]);

        // Insert new routes
        $insertStmt = $pdo->prepare("
          INSERT INTO practice_billing_routes (user_id, insurer_name, billing_route)
          VALUES (?, ?, ?)
        ");

        foreach ($routes as $route) {
          if (!isset($route['insurer_name']) || !isset($route['billing_route'])) {
            continue;
          }

          if (!in_array($route['billing_route'], ['collagen_direct', 'practice_dme'])) {
            continue;
          }

          $insertStmt->execute([
            $userId,
            $route['insurer_name'],
            $route['billing_route']
          ]);
        }

        $pdo->commit();

        echo json_encode(['success' => true]);
      } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
      }
      break;

    case 'default_route.get':
      // Get user's default billing route
      $stmt = $pdo->prepare("
        SELECT default_billing_route
        FROM users
        WHERE id = ?
      ");
      $stmt->execute([$userId]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);

      echo json_encode([
        'success' => true,
        'default_route' => $result['default_billing_route'] ?? 'collagen_direct'
      ]);
      break;

    case 'default_route.set':
      // Set user's default billing route
      $defaultRoute = $_POST['default_route'] ?? '';

      if (!in_array($defaultRoute, ['collagen_direct', 'practice_dme'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid default route']);
        exit;
      }

      $stmt = $pdo->prepare("
        UPDATE users
        SET default_billing_route = ?
        WHERE id = ?
      ");
      $stmt->execute([$defaultRoute, $userId]);

      echo json_encode(['success' => true]);
      break;

    case 'account_balance.get':
      // Get wholesale account balance
      $stmt = $pdo->prepare("
        SELECT
          current_balance,
          transaction_count,
          last_transaction
        FROM practice_account_balances
        WHERE user_id = ?
      ");
      $stmt->execute([$userId]);
      $balance = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$balance) {
        $balance = [
          'current_balance' => '0.00',
          'transaction_count' => 0,
          'last_transaction' => null
        ];
      }

      echo json_encode([
        'success' => true,
        'balance' => $balance
      ]);
      break;

    case 'transactions.list':
      // List account transactions
      $limit = intval($_GET['limit'] ?? 50);
      $offset = intval($_GET['offset'] ?? 0);

      $stmt = $pdo->prepare("
        SELECT
          id,
          order_id,
          transaction_type,
          amount,
          balance_after,
          description,
          created_at,
          created_by
        FROM practice_account_transactions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
      ");
      $stmt->execute([$userId, $limit, $offset]);
      $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Get total count
      $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM practice_account_transactions
        WHERE user_id = ?
      ");
      $countStmt->execute([$userId]);
      $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

      echo json_encode([
        'success' => true,
        'transactions' => $transactions,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
      ]);
      break;

    case 'route.determine':
      // Determine billing route for a given insurance company
      $insurerName = $_GET['insurer_name'] ?? '';

      if (!$insurerName) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing insurer_name']);
        exit;
      }

      // Check for specific route
      $stmt = $pdo->prepare("
        SELECT billing_route
        FROM practice_billing_routes
        WHERE user_id = ? AND insurer_name = ?
      ");
      $stmt->execute([$userId, $insurerName]);
      $route = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($route) {
        $billingRoute = $route['billing_route'];
      } else {
        // Fall back to default
        $stmt = $pdo->prepare("
          SELECT default_billing_route
          FROM users
          WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $billingRoute = $user['default_billing_route'] ?? 'collagen_direct';
      }

      echo json_encode([
        'success' => true,
        'billing_route' => $billingRoute,
        'insurer_name' => $insurerName
      ]);
      break;

    default:
      http_response_code(400);
      echo json_encode(['success' => false, 'error' => 'Invalid action']);
      break;
  }

} catch (PDOException $e) {
  error_log("Billing Routes API Error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Database error: ' . $e->getMessage()
  ]);
} catch (Exception $e) {
  error_log("Billing Routes API Error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Server error: ' . $e->getMessage()
  ]);
}

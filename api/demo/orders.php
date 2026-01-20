<?php
/**
 * Demo Orders API
 * CRUD operations for demo orders (synthetic data only)
 */
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../admin/db.php';

// Check for valid demo session
if (empty($_SESSION['demo_mode']) || empty($_SESSION['demo_session_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No active demo session']);
    exit;
}

$sessionId = $_SESSION['demo_session_id'];
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // List orders or get single order
            $orderId = $_GET['id'] ?? null;

            if ($orderId) {
                // Get single order with patient info
                $stmt = $pdo->prepare("
                    SELECT o.*,
                           p.first_name AS patient_first_name,
                           p.last_name AS patient_last_name,
                           p.mrn AS patient_mrn
                    FROM demo_orders o
                    LEFT JOIN demo_patients p ON p.id = o.demo_patient_id
                    WHERE o.id = ? AND o.demo_session_id = ?
                ");
                $stmt->execute([$orderId, $sessionId]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$order) {
                    http_response_code(404);
                    echo json_encode(['ok' => false, 'error' => 'Order not found']);
                    exit;
                }

                echo json_encode(['ok' => true, 'order' => $order]);
            } else {
                // List all orders with patient info
                $status = $_GET['status'] ?? '';
                $patientId = $_GET['patient_id'] ?? '';

                $sql = "
                    SELECT o.*,
                           p.first_name AS patient_first_name,
                           p.last_name AS patient_last_name,
                           p.mrn AS patient_mrn
                    FROM demo_orders o
                    LEFT JOIN demo_patients p ON p.id = o.demo_patient_id
                    WHERE o.demo_session_id = ?
                ";
                $params = [$sessionId];

                if ($status) {
                    $sql .= " AND o.status = ?";
                    $params[] = $status;
                }

                if ($patientId) {
                    $sql .= " AND o.demo_patient_id = ?";
                    $params[] = $patientId;
                }

                $sql .= " ORDER BY o.created_at DESC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['ok' => true, 'orders' => $orders, 'count' => count($orders)]);
            }
            break;

        case 'POST':
            // Create new order
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['patient_id'])) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Patient ID required']);
                exit;
            }

            // Verify patient exists in this session
            $patientCheck = $pdo->prepare("SELECT * FROM demo_patients WHERE id = ? AND demo_session_id = ?");
            $patientCheck->execute([$input['patient_id'], $sessionId]);
            $patient = $patientCheck->fetch(PDO::FETCH_ASSOC);

            if (!$patient) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Patient not found']);
                exit;
            }

            $orderId = bin2hex(random_bytes(16));

            // Count existing orders to generate order number
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM demo_orders WHERE demo_session_id = ?");
            $countStmt->execute([$sessionId]);
            $orderCount = (int)$countStmt->fetchColumn();
            $orderNumber = 'DEMO-' . date('Ymd') . '-' . str_pad((string)($orderCount + 1), 3, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("
                INSERT INTO demo_orders (
                    id, demo_session_id, demo_patient_id, order_number,
                    product, product_id, product_size, quantity, status,
                    payment_type, billed_by, delivery_mode, frequency,
                    shipping_name, shipping_address, shipping_city, shipping_state, shipping_zip
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $orderId,
                $sessionId,
                $input['patient_id'],
                $orderNumber,
                $input['product'] ?? 'CollagenMatrix',
                $input['product_id'] ?? 1,
                $input['product_size'] ?? '4x4 cm',
                $input['quantity'] ?? 1,
                'submitted',
                $input['payment_type'] ?? 'referral',
                $input['billed_by'] ?? 'collagen_direct',
                $input['delivery_mode'] ?? 'patient',
                $input['frequency'] ?? 'Weekly',
                ($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''),
                $patient['address'] ?? '',
                $patient['city'] ?? '',
                $patient['state'] ?? '',
                $patient['zip'] ?? ''
            ]);

            echo json_encode([
                'ok' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'message' => 'Order created successfully'
            ]);
            break;

        case 'PUT':
            // Update order (mainly for status changes)
            $input = json_decode(file_get_contents('php://input'), true);
            $orderId = $input['id'] ?? $_GET['id'] ?? null;

            if (!$orderId) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Order ID required']);
                exit;
            }

            // Verify order belongs to this session
            $check = $pdo->prepare("SELECT id FROM demo_orders WHERE id = ? AND demo_session_id = ?");
            $check->execute([$orderId, $sessionId]);
            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Order not found']);
                exit;
            }

            // Build update query
            $allowedFields = ['status', 'tracking_number', 'delivery_mode', 'frequency'];
            $updates = [];
            $params = [];

            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updates[] = "{$field} = ?";
                    $params[] = $input[$field];
                }
            }

            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'No fields to update']);
                exit;
            }

            $updates[] = "updated_at = NOW()";
            $params[] = $orderId;
            $params[] = $sessionId;

            $sql = "UPDATE demo_orders SET " . implode(', ', $updates) . " WHERE id = ? AND demo_session_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['ok' => true, 'message' => 'Order updated successfully']);
            break;

        case 'DELETE':
            // Delete order
            $orderId = $_GET['id'] ?? null;

            if (!$orderId) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Order ID required']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM demo_orders WHERE id = ? AND demo_session_id = ?");
            $stmt->execute([$orderId, $sessionId]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Order not found']);
                exit;
            }

            echo json_encode(['ok' => true, 'message' => 'Order deleted successfully']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    }

} catch (Throwable $e) {
    error_log('[demo/orders] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}

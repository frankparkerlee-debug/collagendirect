<?php
/**
 * Preauthorization API Endpoint
 *
 * Handles preauthorization requests for insurance coverage.
 * This is triggered when CollagenDirect (manufacturer) receives a new order.
 *
 * ENDPOINTS:
 * - POST /api/preauth.php?action=preauth.processOrder - Start preauth for an order
 * - POST /api/preauth.php?action=preauth.checkStatus - Check preauth status
 * - POST /api/preauth.php?action=preauth.updateStatus - Update preauth status (manual)
 * - POST /api/preauth.php?action=preauth.retryFailed - Retry failed preauth requests
 * - GET /api/preauth.php?action=preauth.getByOrder - Get preauth for an order
 * - GET /api/preauth.php?action=preauth.getByPatient - Get all preauths for a patient
 *
 * @package CollagenDirect
 */

header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/services/PreAuthAgent.php';
require_once __DIR__ . '/services/PreAuthEligibilityChecker.php';
session_start();

// CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!$csrfToken || !isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No action specified']);
    exit;
}

// Initialize services
$db = getDbConnection();
$agent = new PreAuthAgent();
$eligibilityChecker = new PreAuthEligibilityChecker($db);

// Route actions
switch ($action) {

    /**
     * Process a new order for preauthorization
     * Trigger point: When CollagenDirect receives an order from physician
     */
    case 'preauth.processOrder':
        $orderId = $_POST['order_id'] ?? null;

        if (!$orderId) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'order_id is required']);
            exit;
        }

        // Check authorization - only manufacturer admins can trigger preauth
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superadmin', 'manufacturer', 'admin'])) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $result = $agent->processOrder($orderId);
        echo json_encode($result);
        break;

    /**
     * Check status of a preauth request
     */
    case 'preauth.checkStatus':
        $preauthRequestId = $_POST['preauth_request_id'] ?? null;

        if (!$preauthRequestId) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'preauth_request_id is required']);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM preauth_requests WHERE id = :id");
        $stmt->execute([':id' => $preauthRequestId]);
        $preauth = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$preauth) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Preauth request not found']);
            exit;
        }

        echo json_encode([
            'ok' => true,
            'preauth' => $preauth
        ]);
        break;

    /**
     * Update preauth status manually
     */
    case 'preauth.updateStatus':
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superadmin', 'manufacturer', 'admin'])) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $preauthRequestId = $_POST['preauth_request_id'] ?? null;
        $status = $_POST['status'] ?? null;
        $preauthNumber = $_POST['preauth_number'] ?? null;
        $notes = $_POST['notes'] ?? null;

        if (!$preauthRequestId || !$status) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'preauth_request_id and status are required']);
            exit;
        }

        $validStatuses = ['pending', 'submitted', 'approved', 'denied', 'expired', 'cancelled', 'need_info'];
        if (!in_array($status, $validStatuses)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Invalid status value']);
            exit;
        }

        // Update preauth request
        $updateData = [
            'status' => $status,
            'updated_by' => $_SESSION['user_id']
        ];

        if ($preauthNumber) {
            $updateData['preauth_number'] = $preauthNumber;
        }

        if ($status === 'submitted' && !$preauthNumber) {
            $updateData['submission_date'] = date('Y-m-d H:i:s');
        } elseif ($status === 'approved') {
            $updateData['approval_date'] = date('Y-m-d H:i:s');
        } elseif ($status === 'denied') {
            $updateData['denial_date'] = date('Y-m-d H:i:s');
        }

        $fields = [];
        $params = [':id' => $preauthRequestId];

        foreach ($updateData as $key => $value) {
            $fields[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }

        $sql = "UPDATE preauth_requests SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        // Log the manual update
        $stmt = $db->prepare("
            SELECT log_preauth_action(:preauth_request_id, :action, :actor_type, :actor_id, :actor_name, :success, :error_message, :metadata)
        ");

        $stmt->execute([
            ':preauth_request_id' => $preauthRequestId,
            ':action' => 'manual_status_update',
            ':actor_type' => 'admin',
            ':actor_id' => $_SESSION['user_id'],
            ':actor_name' => $_SESSION['email'] ?? 'Admin',
            ':success' => true,
            ':error_message' => null,
            ':metadata' => json_encode(['new_status' => $status, 'notes' => $notes])
        ]);

        echo json_encode([
            'ok' => true,
            'message' => 'Preauth status updated successfully'
        ]);
        break;

    /**
     * Get preauth request by order ID
     */
    case 'preauth.getByOrder':
        $orderId = $_GET['order_id'] ?? null;

        if (!$orderId) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'order_id is required']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT * FROM preauth_requests
            WHERE order_id = :order_id
            ORDER BY created_at DESC
        ");
        $stmt->execute([':order_id' => $orderId]);
        $preauths = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'preauths' => $preauths
        ]);
        break;

    /**
     * Get all preauth requests for a patient
     */
    case 'preauth.getByPatient':
        $patientId = $_GET['patient_id'] ?? null;

        if (!$patientId) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'patient_id is required']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT pr.*, o.wound_type, o.product_name
            FROM preauth_requests pr
            JOIN orders o ON pr.order_id = o.id
            WHERE pr.patient_id = :patient_id
            ORDER BY pr.created_at DESC
        ");
        $stmt->execute([':patient_id' => $patientId]);
        $preauths = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'preauths' => $preauths
        ]);
        break;

    /**
     * Retry failed preauth requests
     */
    case 'preauth.retryFailed':
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superadmin', 'manufacturer', 'admin'])) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $result = $agent->processRetryQueue();
        echo json_encode($result);
        break;

    /**
     * Record manual eligibility verification
     */
    case 'preauth.recordEligibility':
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superadmin', 'manufacturer', 'admin'])) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $memberId = $_POST['member_id'] ?? null;
        $carrierName = $_POST['carrier_name'] ?? null;
        $eligible = filter_var($_POST['eligible'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $notes = $_POST['notes'] ?? '';

        if (!$memberId || !$carrierName) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'member_id and carrier_name are required']);
            exit;
        }

        $result = $eligibilityChecker->recordManualVerification([
            'member_id' => $memberId,
            'carrier_name' => $carrierName,
            'eligible' => $eligible,
            'notes' => $notes,
            'verified_by' => $_SESSION['user_id']
        ]);

        echo json_encode($result);
        break;

    /**
     * Get preauth audit log
     */
    case 'preauth.getAuditLog':
        $preauthRequestId = $_GET['preauth_request_id'] ?? null;

        if (!$preauthRequestId) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'preauth_request_id is required']);
            exit;
        }

        $stmt = $db->prepare("
            SELECT * FROM preauth_audit_log
            WHERE preauth_request_id = :preauth_request_id
            ORDER BY created_at DESC
        ");
        $stmt->execute([':preauth_request_id' => $preauthRequestId]);
        $auditLog = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'audit_log' => $auditLog
        ]);
        break;

    /**
     * Get preauth statistics for dashboard
     */
    case 'preauth.getStats':
        if (!isset($_SESSION['user_id'])) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $stmt = $db->query("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'denied' THEN 1 ELSE 0 END) as denied,
                SUM(CASE WHEN status = 'need_info' THEN 1 ELSE 0 END) as need_info,
                SUM(CASE WHEN auto_submitted THEN 1 ELSE 0 END) as auto_submitted
            FROM preauth_requests
            WHERE created_at > NOW() - INTERVAL '30 days'
        ");

        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'stats' => $stats
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
        break;
}

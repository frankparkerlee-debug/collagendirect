<?php
/**
 * Demo Patients API
 * CRUD operations for demo patients (synthetic data only)
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
            // List patients or get single patient
            $patientId = $_GET['id'] ?? null;

            if ($patientId) {
                // Get single patient
                $stmt = $pdo->prepare("
                    SELECT * FROM demo_patients
                    WHERE id = ? AND demo_session_id = ?
                ");
                $stmt->execute([$patientId, $sessionId]);
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$patient) {
                    http_response_code(404);
                    echo json_encode(['ok' => false, 'error' => 'Patient not found']);
                    exit;
                }

                echo json_encode(['ok' => true, 'patient' => $patient]);
            } else {
                // List all patients
                $search = $_GET['search'] ?? '';

                $sql = "SELECT * FROM demo_patients WHERE demo_session_id = ?";
                $params = [$sessionId];

                if ($search) {
                    $sql .= " AND (LOWER(first_name) LIKE LOWER(?) OR LOWER(last_name) LIKE LOWER(?) OR mrn LIKE ?)";
                    $searchParam = "%{$search}%";
                    $params[] = $searchParam;
                    $params[] = $searchParam;
                    $params[] = $searchParam;
                }

                $sql .= " ORDER BY created_at DESC";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['ok' => true, 'patients' => $patients, 'count' => count($patients)]);
            }
            break;

        case 'POST':
            // Create new patient
            $input = json_decode(file_get_contents('php://input'), true);

            $requiredFields = ['first_name', 'last_name'];
            foreach ($requiredFields as $field) {
                if (empty($input[$field])) {
                    http_response_code(400);
                    echo json_encode(['ok' => false, 'error' => "Missing required field: {$field}"]);
                    exit;
                }
            }

            $patientId = bin2hex(random_bytes(16));
            $mrn = 'DEMO-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

            $stmt = $pdo->prepare("
                INSERT INTO demo_patients (
                    id, demo_session_id, first_name, last_name, dob, sex, mrn,
                    phone, email, address, city, state, zip,
                    insurance_provider, insurance_member_id, insurance_group_number,
                    wound_location, wound_type
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $patientId,
                $sessionId,
                trim($input['first_name']),
                trim($input['last_name']),
                $input['dob'] ?? null,
                $input['sex'] ?? null,
                $mrn,
                $input['phone'] ?? null,
                $input['email'] ?? null,
                $input['address'] ?? null,
                $input['city'] ?? null,
                $input['state'] ?? null,
                $input['zip'] ?? null,
                $input['insurance_provider'] ?? null,
                $input['insurance_member_id'] ?? null,
                $input['insurance_group_number'] ?? null,
                $input['wound_location'] ?? null,
                $input['wound_type'] ?? null
            ]);

            echo json_encode([
                'ok' => true,
                'patient_id' => $patientId,
                'mrn' => $mrn,
                'message' => 'Patient created successfully'
            ]);
            break;

        case 'PUT':
            // Update patient
            $input = json_decode(file_get_contents('php://input'), true);
            $patientId = $input['id'] ?? $_GET['id'] ?? null;

            if (!$patientId) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Patient ID required']);
                exit;
            }

            // Verify patient belongs to this session
            $check = $pdo->prepare("SELECT id FROM demo_patients WHERE id = ? AND demo_session_id = ?");
            $check->execute([$patientId, $sessionId]);
            if (!$check->fetch()) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Patient not found']);
                exit;
            }

            // Build update query dynamically
            $allowedFields = [
                'first_name', 'last_name', 'dob', 'sex', 'phone', 'email',
                'address', 'city', 'state', 'zip',
                'insurance_provider', 'insurance_member_id', 'insurance_group_number',
                'wound_location', 'wound_type'
            ];

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
            $params[] = $patientId;
            $params[] = $sessionId;

            $sql = "UPDATE demo_patients SET " . implode(', ', $updates) . " WHERE id = ? AND demo_session_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['ok' => true, 'message' => 'Patient updated successfully']);
            break;

        case 'DELETE':
            // Delete patient
            $patientId = $_GET['id'] ?? null;

            if (!$patientId) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Patient ID required']);
                exit;
            }

            // Delete associated orders first
            $pdo->prepare("DELETE FROM demo_orders WHERE demo_patient_id = ? AND demo_session_id = ?")
                ->execute([$patientId, $sessionId]);

            // Delete patient
            $stmt = $pdo->prepare("DELETE FROM demo_patients WHERE id = ? AND demo_session_id = ?");
            $stmt->execute([$patientId, $sessionId]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['ok' => false, 'error' => 'Patient not found']);
                exit;
            }

            echo json_encode(['ok' => true, 'message' => 'Patient deleted successfully']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    }

} catch (Throwable $e) {
    error_log('[demo/patients] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error']);
}

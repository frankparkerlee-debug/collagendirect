<?php
/**
 * Demo Tour Progress API
 * Saves and retrieves tour progress for demo sessions
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
            // Get tour progress
            $stmt = $pdo->prepare("
                SELECT tour_completed, tour_step_reached
                FROM demo_sessions
                WHERE id = ?
            ");
            $stmt->execute([$sessionId]);
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'ok' => true,
                'tour_completed' => (bool)($progress['tour_completed'] ?? false),
                'tour_step_reached' => (int)($progress['tour_step_reached'] ?? 0)
            ]);
            break;

        case 'POST':
            // Update tour progress
            $input = json_decode(file_get_contents('php://input'), true);

            $tourStep = isset($input['step']) ? (int)$input['step'] : null;
            $tourCompleted = isset($input['completed']) ? (bool)$input['completed'] : null;

            $updates = [];
            $params = [];

            if ($tourStep !== null) {
                $updates[] = "tour_step_reached = ?";
                $params[] = $tourStep;
            }

            if ($tourCompleted !== null) {
                $updates[] = "tour_completed = ?";
                // PostgreSQL needs 't'/'f' or 1/0 for boolean, not PHP bool
                $params[] = $tourCompleted ? 't' : 'f';
            }

            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'No progress data provided']);
                exit;
            }

            $params[] = $sessionId;
            $sql = "UPDATE demo_sessions SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['ok' => true, 'message' => 'Tour progress saved']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    }

} catch (Throwable $e) {
    error_log('[demo/tour] Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}

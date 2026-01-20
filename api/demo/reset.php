<?php
/**
 * Demo Session Reset API
 * Clears all demo data and re-seeds fresh synthetic data
 */
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../admin/db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check for valid demo session
if (empty($_SESSION['demo_mode']) || empty($_SESSION['demo_session_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No active demo session']);
    exit;
}

$sessionId = $_SESSION['demo_session_id'];

try {
    $pdo->beginTransaction();

    // Delete existing demo data (orders first due to FK constraint)
    $pdo->prepare("DELETE FROM demo_orders WHERE demo_session_id = ?")->execute([$sessionId]);
    $pdo->prepare("DELETE FROM demo_patients WHERE demo_session_id = ?")->execute([$sessionId]);

    // Reset tour progress
    $pdo->prepare("UPDATE demo_sessions SET tour_completed = FALSE, tour_step_reached = 0 WHERE id = ?")->execute([$sessionId]);

    $pdo->commit();

    // Re-seed fresh data
    require_once __DIR__ . '/seed-data.php';
    $result = seedDemoData($pdo, $sessionId);

    echo json_encode([
        'ok' => true,
        'message' => 'Demo reset successfully',
        'data' => $result
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[demo/reset] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to reset demo']);
}

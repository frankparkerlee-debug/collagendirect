<?php
/**
 * Demo Portal Login API
 * Email-only access for demo portal - no password required
 *
 * Anyone can access by providing a valid email address.
 * Email is tracked for analytics purposes.
 */
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../admin/db.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$name = trim($input['name'] ?? '');

// Validate email
if (!$email) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Email is required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address']);
    exit;
}

try {
    // Generate a unique user ID for this demo session (based on email)
    $demoUserId = 'demo_' . md5(strtolower($email));

    // Clean up any previous demo sessions for this email
    $pdo->prepare("DELETE FROM demo_sessions WHERE user_id = ?")->execute([$demoUserId]);

    // Create new demo session
    $sessionId = bin2hex(random_bytes(16));

    // Try to insert with demo_email/demo_name columns first (if migration has been run)
    // Fall back to basic insert if columns don't exist
    try {
        $stmt = $pdo->prepare("
            INSERT INTO demo_sessions (id, user_id, started_at, expires_at, demo_email, demo_name)
            VALUES (?, ?, NOW(), NOW() + INTERVAL '24 hours', ?, ?)
        ");
        $stmt->execute([$sessionId, $demoUserId, $email, $name ?: null]);
    } catch (PDOException $colErr) {
        // Columns don't exist yet - use basic insert
        $stmt = $pdo->prepare("
            INSERT INTO demo_sessions (id, user_id, started_at, expires_at)
            VALUES (?, ?, NOW(), NOW() + INTERVAL '24 hours')
        ");
        $stmt->execute([$sessionId, $demoUserId]);
    }

    // Determine display name
    $displayName = $name ?: explode('@', $email)[0];

    // Set session variables
    $_SESSION['demo_mode'] = true;
    $_SESSION['demo_user_id'] = $demoUserId;
    $_SESSION['demo_session_id'] = $sessionId;
    $_SESSION['demo_user_name'] = $displayName;
    $_SESSION['demo_user_email'] = $email;
    $_SESSION['demo_user_type'] = 'guest';

    // Seed initial demo data
    require_once __DIR__ . '/seed-data.php';
    seedDemoData($pdo, $sessionId);

    // Log demo access for analytics
    error_log("[demo/login] Demo access: $email ($displayName)");

    echo json_encode([
        'ok' => true,
        'redirect' => '/demo-portal/',
        'user' => [
            'name' => $displayName,
            'email' => $email
        ],
        'session_id' => $sessionId
    ]);

} catch (Throwable $e) {
    error_log('[demo/login] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error. Please try again.']);
}

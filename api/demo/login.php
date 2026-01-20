<?php
/**
 * Demo Portal Login API
 * Authenticates demo users and creates demo sessions
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

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Email and password are required']);
    exit;
}

try {
    // Look up demo user
    $stmt = $pdo->prepare("
        SELECT id, email, password_hash, first_name, last_name, company_name, is_active
        FROM demo_users
        WHERE LOWER(email) = LOWER(?)
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Invalid email or password']);
        exit;
    }

    if (!$user['is_active']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'This demo account has been deactivated']);
        exit;
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Invalid email or password']);
        exit;
    }

    // Update last login
    $pdo->prepare("UPDATE demo_users SET last_login_at = NOW() WHERE id = ?")->execute([$user['id']]);

    // Create new demo session
    $sessionId = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("
        INSERT INTO demo_sessions (id, demo_user_id, started_at, expires_at)
        VALUES (?, ?, NOW(), NOW() + INTERVAL '24 hours')
    ");
    $stmt->execute([$sessionId, $user['id']]);

    // Set session variables
    $_SESSION['demo_mode'] = true;
    $_SESSION['demo_user_id'] = $user['id'];
    $_SESSION['demo_session_id'] = $sessionId;
    $_SESSION['demo_user_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $_SESSION['demo_company'] = $user['company_name'] ?? '';

    // Seed initial demo data
    require_once __DIR__ . '/seed-data.php';
    seedDemoData($pdo, $sessionId);

    echo json_encode([
        'ok' => true,
        'redirect' => '/demo-portal/',
        'user' => [
            'name' => $_SESSION['demo_user_name'],
            'company' => $_SESSION['demo_company']
        ],
        'session_id' => $sessionId
    ]);

} catch (Throwable $e) {
    error_log('[demo/login] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server error. Please try again.']);
}

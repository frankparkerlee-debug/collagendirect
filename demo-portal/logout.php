<?php
/**
 * Demo Portal Logout
 * Deletes demo session data and redirects to login
 */
declare(strict_types=1);

require_once __DIR__ . '/../admin/db.php';

// Delete demo session and all associated data (CASCADE handles patients/orders)
if (!empty($_SESSION['demo_session_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM demo_sessions WHERE id = ?");
        $stmt->execute([$_SESSION['demo_session_id']]);
    } catch (Throwable $e) {
        error_log('[demo/logout] Cleanup error: ' . $e->getMessage());
    }
}

// Clear demo session variables
unset($_SESSION['demo_mode']);
unset($_SESSION['demo_admin_id']);
unset($_SESSION['demo_session_id']);
unset($_SESSION['demo_user_name']);

// Redirect to login
header('Location: /demo-portal/login.html');
exit;

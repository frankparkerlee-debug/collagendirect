<?php
/**
 * Run Demo Portal Migration
 * Removes foreign key constraint to allow guest demo users
 *
 * DELETE THIS FILE AFTER RUNNING
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// Require superadmin
$admin = current_admin();
if (!$admin || ($admin['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    die('Superadmin access required');
}

$results = [];
$errors = [];

try {
    // Drop foreign key constraint
    try {
        $pdo->exec("ALTER TABLE demo_sessions DROP CONSTRAINT IF EXISTS demo_sessions_user_id_fkey");
        $results[] = "Dropped foreign key constraint: demo_sessions_user_id_fkey";
    } catch (Throwable $e) {
        $errors[] = "Drop constraint: " . $e->getMessage();
    }

    // Add demo_email column
    try {
        $pdo->exec("ALTER TABLE demo_sessions ADD COLUMN IF NOT EXISTS demo_email VARCHAR(255)");
        $results[] = "Added column: demo_email";
    } catch (Throwable $e) {
        $errors[] = "Add demo_email: " . $e->getMessage();
    }

    // Add demo_name column
    try {
        $pdo->exec("ALTER TABLE demo_sessions ADD COLUMN IF NOT EXISTS demo_name VARCHAR(255)");
        $results[] = "Added column: demo_name";
    } catch (Throwable $e) {
        $errors[] = "Add demo_name: " . $e->getMessage();
    }

    // Create index
    try {
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_demo_sessions_email ON demo_sessions(demo_email)");
        $results[] = "Created index: idx_demo_sessions_email";
    } catch (Throwable $e) {
        $errors[] = "Create index: " . $e->getMessage();
    }

    // Update table comment
    try {
        $pdo->exec("COMMENT ON TABLE demo_sessions IS 'Demo sessions - supports both authenticated users and email-only guest access. 24-hour auto-expiry for HIPAA compliance.'");
        $results[] = "Updated table comment";
    } catch (Throwable $e) {
        $errors[] = "Update comment: " . $e->getMessage();
    }

} catch (Throwable $e) {
    $errors[] = "General error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Demo Portal Migration</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        h1 { color: #333; }
        .success { color: #059669; background: #d1fae5; padding: 10px; border-radius: 6px; margin: 5px 0; }
        .error { color: #dc2626; background: #fee2e2; padding: 10px; border-radius: 6px; margin: 5px 0; }
        .warning { color: #d97706; background: #fef3c7; padding: 10px; border-radius: 6px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>Demo Portal Migration</h1>

    <h2>Results:</h2>
    <?php foreach ($results as $r): ?>
        <div class="success"><?= htmlspecialchars($r) ?></div>
    <?php endforeach; ?>

    <?php if ($errors): ?>
        <h2>Errors:</h2>
        <?php foreach ($errors as $e): ?>
            <div class="error"><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($errors)): ?>
        <div class="warning">
            <strong>Migration complete!</strong><br>
            Please delete this file: <code>/admin/run-demo-migration.php</code>
        </div>
    <?php endif; ?>

    <p><a href="/demo-portal/login.html">Test Demo Portal Login</a></p>
</body>
</html>

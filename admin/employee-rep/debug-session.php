<?php
/**
 * Employee Rep Session Diagnostics
 *
 * This tool displays detailed session and database information to help
 * diagnose authentication and ID type issues.
 */
declare(strict_types=1);
require_once __DIR__ . '/../auth.php';

// Only allow access if logged in
if (!isset($_SESSION['admin'])) {
    header('Location: /admin/login.php');
    exit;
}

$admin = $_SESSION['admin'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Diagnostics - CollagenDirect</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #333; border-bottom: 2px solid #0066cc; padding-bottom: 10px; }
        h2 { color: #0066cc; margin-top: 30px; }
        .card { background: white; border-radius: 8px; padding: 20px; margin: 15px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border-left: 4px solid #28a745; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 600; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        .mono { font-family: monospace; word-break: break-all; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #0066cc; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <a href="/admin/employee-rep/" class="back-link">&larr; Back to Employee Portal</a>
        <h1>Session Diagnostics</h1>
        <p>This page helps diagnose authentication and session issues.</p>

        <?php
        // Analyze the session ID
        $sessionId = $admin['id'] ?? null;
        $isInteger = is_numeric($sessionId) && strlen((string)$sessionId) <= 10;
        $isUUID = is_string($sessionId) && strlen($sessionId) === 32 && ctype_xdigit($sessionId);
        ?>

        <h2>1. Session Analysis</h2>
        <div class="card <?php echo $isInteger ? 'success' : 'error'; ?>">
            <h3>Session ID Type Check</h3>
            <table>
                <tr>
                    <th>Property</th>
                    <th>Value</th>
                    <th>Status</th>
                </tr>
                <tr>
                    <td>Session ID</td>
                    <td class="mono"><code><?php echo htmlspecialchars((string)$sessionId); ?></code></td>
                    <td>
                        <?php if ($isInteger): ?>
                            <span class="badge badge-success">INTEGER</span>
                        <?php elseif ($isUUID): ?>
                            <span class="badge badge-danger">UUID (PROBLEM!)</span>
                        <?php else: ?>
                            <span class="badge badge-warning">UNKNOWN</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>ID Length</td>
                    <td><?php echo strlen((string)$sessionId); ?> characters</td>
                    <td>
                        <?php if (strlen((string)$sessionId) <= 10): ?>
                            <span class="badge badge-success">OK</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Too Long</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Is Numeric</td>
                    <td><?php echo is_numeric($sessionId) ? 'Yes' : 'No'; ?></td>
                    <td>
                        <?php if (is_numeric($sessionId)): ?>
                            <span class="badge badge-success">OK</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Not Numeric</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <?php if (!$isInteger): ?>
            <p style="color: #dc3545; font-weight: bold; margin-top: 15px;">
                Your session contains a UUID instead of an INTEGER. This means you logged in via the
                <code>users</code> table instead of <code>admin_users</code> table.
                <strong>Please log out and log back in.</strong>
            </p>
            <?php endif; ?>
        </div>

        <h2>2. Full Session Data</h2>
        <div class="card info">
            <h3>$_SESSION['admin'] Contents</h3>
            <table>
                <tr>
                    <th>Key</th>
                    <th>Value</th>
                    <th>Type</th>
                </tr>
                <?php foreach ($admin as $key => $value): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($key); ?></code></td>
                    <td class="mono"><?php echo htmlspecialchars(is_bool($value) ? ($value ? 'true' : 'false') : (string)$value); ?></td>
                    <td><?php echo gettype($value); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="card warning">
            <h3>$_SESSION['user_id'] Also Set</h3>
            <p>Value: <code class="mono"><?php echo htmlspecialchars($_SESSION['user_id']); ?></code></p>
            <p>This indicates a login from the <code>users</code> table occurred.</p>
        </div>
        <?php endif; ?>

        <h2>3. Database Verification</h2>
        <?php
        global $pdo;
        $email = $admin['email'] ?? '';

        // Check admin_users table
        $adminUserStmt = $pdo->prepare("SELECT id, email, name, role, has_rep_view FROM admin_users WHERE email = ?");
        $adminUserStmt->execute([$email]);
        $adminUserRecord = $adminUserStmt->fetch(PDO::FETCH_ASSOC);

        // Check users table
        $userStmt = $pdo->prepare("SELECT id, email, first_name, last_name, role FROM users WHERE email = ?");
        $userStmt->execute([$email]);
        $userRecord = $userStmt->fetch(PDO::FETCH_ASSOC);
        ?>

        <div class="card <?php echo $adminUserRecord ? 'success' : 'error'; ?>">
            <h3>admin_users Table</h3>
            <?php if ($adminUserRecord): ?>
            <table>
                <tr>
                    <th>Column</th>
                    <th>Value</th>
                </tr>
                <?php foreach ($adminUserRecord as $key => $value): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($key); ?></code></td>
                    <td class="mono"><?php echo htmlspecialchars(is_bool($value) ? ($value ? 'true' : 'false') : (string)($value ?? 'NULL')); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <p style="color: #28a745; margin-top: 10px;">
                <strong>User exists in admin_users with INTEGER id: <?php echo $adminUserRecord['id']; ?></strong>
            </p>
            <?php else: ?>
            <p style="color: #dc3545;">
                <strong>NO RECORD FOUND</strong> for email: <code><?php echo htmlspecialchars($email); ?></code>
            </p>
            <p>This user does not exist in the <code>admin_users</code> table. They need to be added there for the employee portal to work correctly.</p>
            <?php endif; ?>
        </div>

        <div class="card <?php echo $userRecord ? 'warning' : 'info'; ?>">
            <h3>users Table</h3>
            <?php if ($userRecord): ?>
            <table>
                <tr>
                    <th>Column</th>
                    <th>Value</th>
                </tr>
                <?php foreach ($userRecord as $key => $value): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($key); ?></code></td>
                    <td class="mono"><?php echo htmlspecialchars(is_bool($value) ? ($value ? 'true' : 'false') : (string)($value ?? 'NULL')); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
            <p style="color: #856404; margin-top: 10px;">
                <strong>Warning:</strong> User also exists in users table with UUID: <code><?php echo $userRecord['id']; ?></code>
            </p>
            <p>If both tables have the same email, the login will use <code>admin_users</code> first (if password matches).</p>
            <?php else: ?>
            <p style="color: #17a2b8;">No record found in users table for this email. This is fine for employee-only accounts.</p>
            <?php endif; ?>
        </div>

        <h2>4. Diagnosis Summary</h2>
        <div class="card">
            <?php
            $issues = [];
            $recommendations = [];

            if (!$isInteger) {
                $issues[] = "Session ID is a UUID instead of INTEGER";
                $recommendations[] = "Log out completely and log back in";
            }

            if (!$adminUserRecord) {
                $issues[] = "User does not exist in admin_users table";
                $recommendations[] = "Add user to admin_users table with has_rep_view=true";
            }

            if ($adminUserRecord && $userRecord) {
                $recommendations[] = "User exists in both tables - ensure passwords match or remove from users table";
            }

            if ($adminUserRecord && empty($adminUserRecord['has_rep_view'])) {
                $issues[] = "User exists in admin_users but has_rep_view is not enabled";
                $recommendations[] = "Set has_rep_view=true in admin_users for this user";
            }

            if (empty($issues)):
            ?>
            <p style="color: #28a745; font-size: 18px;"><strong>No issues detected.</strong></p>
            <p>Your session appears to be correctly configured with an INTEGER id from admin_users.</p>
            <?php else: ?>
            <h3 style="color: #dc3545;">Issues Found:</h3>
            <ul>
                <?php foreach ($issues as $issue): ?>
                <li style="color: #dc3545;"><?php echo htmlspecialchars($issue); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <?php if (!empty($recommendations)): ?>
            <h3 style="color: #0066cc; margin-top: 20px;">Recommendations:</h3>
            <ol>
                <?php foreach ($recommendations as $rec): ?>
                <li><?php echo htmlspecialchars($rec); ?></li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </div>

        <h2>5. Quick Actions</h2>
        <div class="card">
            <p><a href="/admin/logout.php" style="color: #dc3545; font-weight: bold;">Log Out Now</a> - Clear session and log back in</p>
            <p><a href="/admin/employee-rep/" style="color: #0066cc;">Return to Employee Portal</a></p>
        </div>

        <div class="card info" style="margin-top: 30px;">
            <p style="font-size: 12px; color: #666;">
                Generated: <?php echo date('Y-m-d H:i:s T'); ?><br>
                PHP Session ID: <?php echo session_id(); ?>
            </p>
        </div>
    </div>
</body>
</html>

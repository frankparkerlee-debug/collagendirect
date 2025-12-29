<?php
/**
 * DIAGNOSTIC PAGE - Full System Check
 *
 * This page shows ALL relevant data to diagnose the UUID/INTEGER issue.
 * Access: /admin/diagnose.php?email=parker@senecawest.com
 */
declare(strict_types=1);

// Minimal bootstrap - just database, no auth
require_once __DIR__ . '/db.php';

$email = $_GET['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Diagnostic - CollagenDirect</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 20px; background: #1a1a2e; color: #eee; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #00d4ff; border-bottom: 2px solid #00d4ff; padding-bottom: 10px; }
        h2 { color: #ff6b6b; margin-top: 30px; font-size: 18px; }
        .card { background: #16213e; border-radius: 8px; padding: 20px; margin: 15px 0; border: 1px solid #0f3460; }
        .success { border-left: 4px solid #00ff88; }
        .error { border-left: 4px solid #ff4757; }
        .warning { border-left: 4px solid #ffa502; }
        .info { border-left: 4px solid #00d4ff; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid #0f3460; }
        th { background: #0f3460; color: #00d4ff; }
        tr:hover { background: #1a1a3e; }
        code { background: #0f3460; padding: 2px 8px; border-radius: 4px; font-family: 'Monaco', 'Consolas', monospace; font-size: 12px; color: #00ff88; }
        .uuid { color: #ff4757; }
        .integer { color: #00ff88; }
        pre { background: #0f3460; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 12px; color: #ccc; }
        input[type="text"] { padding: 10px 15px; font-size: 16px; border: 2px solid #0f3460; border-radius: 5px; background: #16213e; color: #fff; width: 300px; }
        input[type="text"]:focus { border-color: #00d4ff; outline: none; }
        button { padding: 10px 20px; font-size: 16px; background: #00d4ff; color: #1a1a2e; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; }
        button:hover { background: #00b8d4; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-success { background: #00ff88; color: #1a1a2e; }
        .badge-error { background: #ff4757; color: #fff; }
        .badge-warning { background: #ffa502; color: #1a1a2e; }
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 800px) { .two-col { grid-template-columns: 1fr; } }
        .highlight { background: #ff4757; color: #fff; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 System Diagnostic</h1>

    <div class="card info">
        <form method="GET">
            <label><strong>Email to diagnose:</strong></label><br><br>
            <input type="text" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="parker@senecawest.com">
            <button type="submit">Diagnose</button>
        </form>
    </div>

    <?php if ($email): ?>

    <h2>1. SESSION DATA</h2>
    <div class="card <?php echo isset($_SESSION['admin']) ? 'warning' : 'info'; ?>">
        <?php if (isset($_SESSION['admin'])): ?>
            <p><strong>Active session found:</strong></p>
            <table>
                <tr><th>Key</th><th>Value</th><th>Analysis</th></tr>
                <?php foreach ($_SESSION['admin'] as $key => $value): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($key); ?></code></td>
                    <td><code><?php echo htmlspecialchars(is_bool($value) ? ($value ? 'true' : 'false') : (string)$value); ?></code></td>
                    <td>
                        <?php if ($key === 'id'): ?>
                            <?php if (is_numeric($value) && strlen((string)$value) <= 10): ?>
                                <span class="badge badge-success">INTEGER ✓</span>
                            <?php else: ?>
                                <span class="badge badge-error">UUID - PROBLEM!</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <?php if (isset($_SESSION['user_id'])): ?>
            <p style="margin-top: 15px; color: #ffa502;"><strong>Also has $_SESSION['user_id']:</strong> <code><?php echo htmlspecialchars($_SESSION['user_id']); ?></code></p>
            <?php endif; ?>
        <?php else: ?>
            <p>No active admin session. <a href="/admin/login.php" style="color: #00d4ff;">Log in first</a> to see session data.</p>
        <?php endif; ?>
    </div>

    <div class="two-col">
        <div>
            <h2>2. admin_users TABLE</h2>
            <?php
            $stmt = $pdo->prepare("SELECT id, email, name, role, has_rep_view, password_hash FROM admin_users WHERE LOWER(email) = LOWER(?)");
            $stmt->execute([$email]);
            $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            <div class="card <?php echo $adminUser ? 'success' : 'error'; ?>">
                <?php if ($adminUser): ?>
                    <p><span class="badge badge-success">FOUND</span></p>
                    <table>
                        <tr><th>Column</th><th>Value</th></tr>
                        <tr><td>id</td><td><code class="integer"><?php echo $adminUser['id']; ?></code> <span class="badge badge-success">INTEGER</span></td></tr>
                        <tr><td>email</td><td><code><?php echo htmlspecialchars($adminUser['email']); ?></code></td></tr>
                        <tr><td>name</td><td><code><?php echo htmlspecialchars($adminUser['name'] ?? ''); ?></code></td></tr>
                        <tr><td>role</td><td><code><?php echo htmlspecialchars($adminUser['role'] ?? ''); ?></code></td></tr>
                        <tr><td>has_rep_view</td><td><code><?php echo $adminUser['has_rep_view'] ? 'true' : 'false'; ?></code></td></tr>
                        <tr><td>password_hash</td><td><code><?php echo $adminUser['password_hash'] ? substr($adminUser['password_hash'], 0, 20) . '...' : 'NULL'; ?></code></td></tr>
                    </table>
                <?php else: ?>
                    <p><span class="badge badge-error">NOT FOUND</span></p>
                    <p>No record exists in <code>admin_users</code> for <code><?php echo htmlspecialchars($email); ?></code></p>
                    <p style="color: #ff6b6b;"><strong>This is likely the problem!</strong> Login will fall through to <code>users</code> table.</p>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <h2>3. users TABLE</h2>
            <?php
            $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, role, password_hash FROM users WHERE LOWER(email) = LOWER(?)");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            ?>
            <div class="card <?php echo $user ? 'warning' : 'info'; ?>">
                <?php if ($user): ?>
                    <p><span class="badge badge-warning">FOUND</span></p>
                    <table>
                        <tr><th>Column</th><th>Value</th></tr>
                        <tr><td>id</td><td><code class="uuid"><?php echo $user['id']; ?></code> <span class="badge badge-error">UUID</span></td></tr>
                        <tr><td>email</td><td><code><?php echo htmlspecialchars($user['email']); ?></code></td></tr>
                        <tr><td>name</td><td><code><?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?></code></td></tr>
                        <tr><td>role</td><td><code><?php echo htmlspecialchars($user['role'] ?? ''); ?></code></td></tr>
                        <tr><td>password_hash</td><td><code><?php echo $user['password_hash'] ? substr($user['password_hash'], 0, 20) . '...' : 'NULL'; ?></code></td></tr>
                    </table>
                    <?php if ($adminUser): ?>
                    <p style="margin-top: 15px; color: #ffa502;"><strong>⚠️ User exists in BOTH tables!</strong></p>
                    <p>If passwords differ, login may use wrong table.</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><span class="badge badge-success">NOT FOUND</span> (This is fine)</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <h2>4. DIAGNOSIS</h2>
    <div class="card">
        <?php
        $issues = [];
        $solutions = [];

        // Check if admin_users record exists
        if (!$adminUser) {
            $issues[] = "❌ <code>$email</code> does NOT exist in <code>admin_users</code> table";
            $solutions[] = "Add the user to <code>admin_users</code> with <code>has_rep_view=true</code>";
        } else {
            if (empty($adminUser['has_rep_view'])) {
                $issues[] = "❌ User exists but <code>has_rep_view</code> is FALSE";
                $solutions[] = "Set <code>has_rep_view=true</code> in admin_users";
            }
            if (empty($adminUser['password_hash'])) {
                $issues[] = "❌ User has no password set";
                $solutions[] = "Set a password for this admin_users record";
            }
        }

        // Check if user exists in both tables
        if ($adminUser && $user) {
            $issues[] = "⚠️ User exists in BOTH tables - passwords may conflict";
            $solutions[] = "Ensure both records have the same password, OR delete the <code>users</code> table record if not needed";
        }

        // Check current session
        if (isset($_SESSION['admin']['id'])) {
            $sessionId = $_SESSION['admin']['id'];
            if (!is_numeric($sessionId) || strlen((string)$sessionId) > 10) {
                $issues[] = "❌ Current session has UUID: <code>$sessionId</code>";
                $solutions[] = "Log out and log back in after fixing the database";
            }
        }

        if (empty($issues)):
        ?>
            <p style="color: #00ff88; font-size: 18px;"><strong>✅ No issues detected!</strong></p>
            <p>The database appears correctly configured. If you're still having problems:</p>
            <ol>
                <li>Log out completely</li>
                <li>Clear browser cookies for this site</li>
                <li>Log back in</li>
            </ol>
        <?php else: ?>
            <p style="color: #ff4757; font-size: 18px;"><strong>Issues Found:</strong></p>
            <ul style="line-height: 2;">
                <?php foreach ($issues as $issue): ?>
                <li><?php echo $issue; ?></li>
                <?php endforeach; ?>
            </ul>

            <p style="color: #00d4ff; font-size: 18px; margin-top: 20px;"><strong>Solutions:</strong></p>
            <ol style="line-height: 2;">
                <?php foreach ($solutions as $solution): ?>
                <li><?php echo $solution; ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>

    <?php if (!$adminUser): ?>
    <h2>5. SQL TO FIX</h2>
    <div class="card info">
        <p><strong>Run this SQL to create the admin_users record:</strong></p>
        <pre>-- First, check if the user has a password in the users table we can copy
-- If so, use this:
INSERT INTO admin_users (email, name, role, has_rep_view, password_hash)
SELECT
    '<?php echo addslashes($email); ?>',
    CONCAT(first_name, ' ', last_name),
    'sales',
    true,
    password_hash
FROM users
WHERE email = '<?php echo addslashes($email); ?>';

-- OR if you need to set a new password, use this:
-- (Replace 'HASHED_PASSWORD' with actual bcrypt hash)
INSERT INTO admin_users (email, name, role, has_rep_view, password_hash)
VALUES (
    '<?php echo addslashes($email); ?>',
    'Parker Lee',  -- adjust name
    'sales',
    true,
    '$2y$10$...'  -- generate with password_hash('yourpassword', PASSWORD_DEFAULT)
);</pre>
    </div>
    <?php endif; ?>

    <h2>6. RAW DATA</h2>
    <div class="card">
        <details>
            <summary style="cursor: pointer; color: #00d4ff;">Click to see raw session data</summary>
            <pre><?php print_r($_SESSION ?? []); ?></pre>
        </details>
    </div>

    <?php endif; ?>

    <p style="margin-top: 40px; color: #666; font-size: 12px;">
        Generated: <?php echo date('Y-m-d H:i:s T'); ?> |
        Server: <?php echo $_SERVER['SERVER_NAME'] ?? 'local'; ?>
    </p>
</div>
</body>
</html>

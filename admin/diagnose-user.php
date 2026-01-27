<?php
/**
 * Diagnose User Login Issues
 * Shows which tables a user exists in and password hash status
 *
 * DELETE THIS FILE AFTER USE
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

// Require superadmin
$admin = current_admin();
if (!$admin || ($admin['role'] ?? '') !== 'superadmin') {
    http_response_code(403);
    die('Superadmin access required');
}

$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$results = [];

if ($email) {
    $emailLower = strtolower($email);

    // Check admin_users table
    $stmt = $pdo->prepare("SELECT id, email, name, role, password_hash, has_rep_view FROM admin_users WHERE LOWER(email) = ?");
    $stmt->execute([$emailLower]);
    $adminUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($adminUser) {
        $results['admin_users'] = [
            'found' => true,
            'id' => $adminUser['id'],
            'email' => $adminUser['email'],
            'name' => $adminUser['name'],
            'role' => $adminUser['role'],
            'has_rep_view' => $adminUser['has_rep_view'],
            'password_hash_length' => strlen($adminUser['password_hash'] ?? ''),
            'password_hash_prefix' => substr($adminUser['password_hash'] ?? '', 0, 10) . '...',
            'email_case_match' => $adminUser['email'] === $emailLower ? 'lowercase' : 'mixed case'
        ];
    } else {
        $results['admin_users'] = ['found' => false];
    }

    // Check users table
    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, role, password_hash FROM users WHERE LOWER(email) = ?");
    $stmt->execute([$emailLower]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $results['users'] = [
            'found' => true,
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'role' => $user['role'],
            'password_hash_length' => strlen($user['password_hash'] ?? ''),
            'password_hash_prefix' => substr($user['password_hash'] ?? '', 0, 10) . '...',
            'email_case_match' => $user['email'] === $emailLower ? 'lowercase' : 'mixed case'
        ];
    } else {
        $results['users'] = ['found' => false];
    }

    // Determine login behavior
    if ($results['admin_users']['found'] ?? false) {
        $results['login_behavior'] = 'Will authenticate against admin_users table (checked FIRST)';
        $results['warning'] = $results['users']['found'] ?? false
            ? 'USER EXISTS IN BOTH TABLES - password must be synced!'
            : null;
    } elseif ($results['users']['found'] ?? false) {
        $results['login_behavior'] = 'Will authenticate against users table';
    } else {
        $results['login_behavior'] = 'User not found in either table';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Diagnose User</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h1 { color: #333; }
        form { margin-bottom: 20px; }
        input[type="email"] { padding: 10px; width: 300px; font-size: 16px; }
        button { padding: 10px 20px; font-size: 16px; background: #5FA8A1; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .table-section { margin: 20px 0; padding: 15px; border-radius: 8px; }
        .found { background: #d1fae5; border: 1px solid #059669; }
        .not-found { background: #fee2e2; border: 1px solid #dc2626; }
        .warning { background: #fef3c7; border: 1px solid #d97706; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .info { background: #dbeafe; border: 1px solid #2563eb; padding: 15px; border-radius: 8px; margin: 20px 0; }
        pre { background: #f3f4f6; padding: 10px; border-radius: 6px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; }
    </style>
</head>
<body>
    <h1>Diagnose User Login</h1>

    <form method="get">
        <input type="email" name="email" placeholder="Enter email address" value="<?= htmlspecialchars($email) ?>" required>
        <button type="submit">Diagnose</button>
    </form>

    <?php if ($email && !empty($results)): ?>
        <div class="info">
            <strong>Login Behavior:</strong> <?= htmlspecialchars($results['login_behavior']) ?>
        </div>

        <?php if (!empty($results['warning'])): ?>
            <div class="warning">
                <strong>Warning:</strong> <?= htmlspecialchars($results['warning']) ?>
            </div>
        <?php endif; ?>

        <h2>admin_users Table</h2>
        <div class="table-section <?= ($results['admin_users']['found'] ?? false) ? 'found' : 'not-found' ?>">
            <?php if ($results['admin_users']['found'] ?? false): ?>
                <table>
                    <?php foreach ($results['admin_users'] as $key => $value): ?>
                        <?php if ($key !== 'found'): ?>
                        <tr>
                            <th><?= htmlspecialchars($key) ?></th>
                            <td><?= htmlspecialchars((string)$value) ?></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Not found in admin_users table</p>
            <?php endif; ?>
        </div>

        <h2>users Table</h2>
        <div class="table-section <?= ($results['users']['found'] ?? false) ? 'found' : 'not-found' ?>">
            <?php if ($results['users']['found'] ?? false): ?>
                <table>
                    <?php foreach ($results['users'] as $key => $value): ?>
                        <?php if ($key !== 'found'): ?>
                        <tr>
                            <th><?= htmlspecialchars($key) ?></th>
                            <td><?= htmlspecialchars((string)$value) ?></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p>Not found in users table</p>
            <?php endif; ?>
        </div>

        <h2>Raw Results</h2>
        <pre><?= htmlspecialchars(json_encode($results, JSON_PRETTY_PRINT)) ?></pre>
    <?php endif; ?>

    <p style="margin-top: 40px; color: #666;"><strong>Note:</strong> Delete this file after debugging: <code>/admin/diagnose-user.php</code></p>
</body>
</html>

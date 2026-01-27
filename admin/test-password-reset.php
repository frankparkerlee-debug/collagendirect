<?php
/**
 * Test Password Reset
 * Directly tests password_hash and password_verify
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

$result = null;
$testPassword = 'test123';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $newPassword = trim($_POST['password'] ?? '');

    if ($email && $newPassword) {
        // Generate hash
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Verify the hash works immediately
        $verifyResult = password_verify($newPassword, $hash);

        // Get current user data
        $stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Update password
            $updateStmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$hash, $user['id']]);
            $rowsAffected = $updateStmt->rowCount();

            // Re-fetch to confirm
            $stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE id = ?");
            $stmt->execute([$user['id']]);
            $userAfter = $stmt->fetch(PDO::FETCH_ASSOC);

            // Test the stored hash
            $storedHashVerify = password_verify($newPassword, $userAfter['password_hash']);

            $result = [
                'success' => true,
                'user_id' => $user['id'],
                'email' => $user['email'],
                'new_hash_generated' => $hash,
                'new_hash_length' => strlen($hash),
                'immediate_verify' => $verifyResult ? 'PASS' : 'FAIL',
                'rows_affected' => $rowsAffected,
                'stored_hash_prefix' => substr($userAfter['password_hash'], 0, 20),
                'stored_hash_length' => strlen($userAfter['password_hash']),
                'stored_hash_verify' => $storedHashVerify ? 'PASS' : 'FAIL',
                'hashes_match' => $hash === $userAfter['password_hash'] ? 'YES' : 'NO'
            ];
        } else {
            $result = ['success' => false, 'error' => 'User not found'];
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Password Reset</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        form { margin-bottom: 20px; padding: 20px; background: #f9fafb; border-radius: 8px; }
        input { padding: 10px; margin: 5px 0; width: 100%; font-size: 16px; box-sizing: border-box; }
        button { padding: 10px 20px; font-size: 16px; background: #5FA8A1; color: white; border: none; border-radius: 6px; cursor: pointer; margin-top: 10px; }
        .result { padding: 20px; background: #d1fae5; border-radius: 8px; margin-top: 20px; }
        .error { background: #fee2e2; }
        pre { background: #1f2937; color: #f9fafb; padding: 15px; border-radius: 6px; overflow-x: auto; }
        .warning { background: #fef3c7; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <h1>Test Password Reset</h1>

    <div class="warning">
        <strong>Warning:</strong> This will actually change the user's password!
    </div>

    <form method="post">
        <label>User Email:</label>
        <input type="email" name="email" required placeholder="user@example.com">

        <label>New Password:</label>
        <input type="text" name="password" required placeholder="Enter new password" value="<?= htmlspecialchars($testPassword) ?>">

        <button type="submit">Reset Password & Test</button>
    </form>

    <?php if ($result): ?>
        <div class="result <?= ($result['success'] ?? false) ? '' : 'error' ?>">
            <h2>Result</h2>
            <pre><?= htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT)) ?></pre>

            <?php if ($result['success'] ?? false): ?>
                <h3>Diagnosis:</h3>
                <ul>
                    <li>Hash generated: <?= $result['new_hash_length'] ?> chars (should be 60)</li>
                    <li>Immediate verify: <?= $result['immediate_verify'] ?></li>
                    <li>Rows updated: <?= $result['rows_affected'] ?></li>
                    <li>Stored hash verify: <?= $result['stored_hash_verify'] ?></li>
                    <li>Hashes match after storage: <?= $result['hashes_match'] ?></li>
                </ul>

                <?php if ($result['stored_hash_verify'] === 'PASS'): ?>
                    <p style="color: green; font-weight: bold;">Password reset successful! Try logging in with the new password.</p>
                <?php else: ?>
                    <p style="color: red; font-weight: bold;">Something went wrong - the stored hash doesn't verify!</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <p style="margin-top: 40px; color: #666;"><strong>Note:</strong> Delete this file after use: <code>/admin/test-password-reset.php</code></p>
</body>
</html>

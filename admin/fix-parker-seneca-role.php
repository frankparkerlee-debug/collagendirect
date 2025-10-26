<?php
require __DIR__ . '/../api/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Fixing parker@senecawest.com Role ===\n\n";

$email = 'parker@senecawest.com';

// First, check current status
echo "Current status:\n";
$stmt = $pdo->prepare("SELECT id, email, role, user_type, admin_type FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "✗ User not found: $email\n";
    exit;
}

echo "- Email: {$user['email']}\n";
echo "- Role: {$user['role']}\n";
echo "- User Type: {$user['user_type']}\n";
echo "- Admin Type: {$user['admin_type']}\n\n";

// Update to remove admin access
echo "Updating user...\n";
$stmt = $pdo->prepare("
    UPDATE users
    SET role = 'practice_admin',
        admin_type = NULL
    WHERE email = ?
");
$stmt->execute([$email]);

echo "✓ Updated successfully\n\n";

// Verify the change
echo "New status:\n";
$stmt = $pdo->prepare("SELECT id, email, role, user_type, admin_type FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

echo "- Email: {$user['email']}\n";
echo "- Role: {$user['role']}\n";
echo "- User Type: {$user['user_type']}\n";
echo "- Admin Type: " . ($user['admin_type'] ?: 'NULL') . "\n\n";

echo "✓ This user should now only have access to /portal, not /admin\n";

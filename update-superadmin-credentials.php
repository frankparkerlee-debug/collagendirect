<?php
// update-superadmin-credentials.php - Update super admin credentials
declare(strict_types=1);

// Secret protection (optional - remove if running locally)
$expectedSecret = getenv('MIGRATION_SECRET') ?: 'change-me-in-production';
if (!empty($_GET['secret']) && $_GET['secret'] !== $expectedSecret && $expectedSecret !== 'change-me-in-production') {
    http_response_code(403);
    die('Invalid secret');
}

require __DIR__ . '/api/db.php';

header('Content-Type: text/plain; charset=utf-8');

$newEmail = 'parker@collagendirect.health';
$newPassword = 'Password321!';
$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

echo "Updating superadmin credentials...\n\n";

try {
    // Find existing superadmin user(s) in admin_users table
    $stmt = $pdo->query("SELECT id, email, role FROM admin_users WHERE role IN ('superadmin', 'owner') ORDER BY id LIMIT 1");
    $superadmin = $stmt->fetch();

    if ($superadmin) {
        echo "Found existing superadmin:\n";
        echo "  ID: {$superadmin['id']}\n";
        echo "  Email: {$superadmin['email']}\n";
        echo "  Role: {$superadmin['role']}\n\n";

        // Update the credentials
        $updateStmt = $pdo->prepare("UPDATE admin_users SET email = ?, password_hash = ? WHERE id = ?");
        $updateStmt->execute([$newEmail, $passwordHash, $superadmin['id']]);

        echo "✓ Updated superadmin credentials\n\n";
    } else {
        echo "No superadmin found. Creating new superadmin...\n\n";

        // Create new superadmin
        $insertStmt = $pdo->prepare("INSERT INTO admin_users (name, email, role, password_hash, created_at) VALUES (?, ?, ?, ?, NOW())");
        $insertStmt->execute(['Parker Lee', $newEmail, 'superadmin', $passwordHash]);

        echo "✓ Created new superadmin\n\n";
    }

    // Verify the update
    $verifyStmt = $pdo->prepare("SELECT id, name, email, role FROM admin_users WHERE email = ?");
    $verifyStmt->execute([$newEmail]);
    $verified = $verifyStmt->fetch();

    if ($verified) {
        echo "✓ Verification successful:\n";
        echo "  ID: {$verified['id']}\n";
        echo "  Name: {$verified['name']}\n";
        echo "  Email: {$verified['email']}\n";
        echo "  Role: {$verified['role']}\n\n";
        echo "New credentials:\n";
        echo "  Email: $newEmail\n";
        echo "  Password: $newPassword\n";
    } else {
        echo "✗ Verification failed!\n";
        http_response_code(500);
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    http_response_code(500);
    exit(1);
}

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
    // Find existing superadmin user(s) in users table
    $stmt = $pdo->query("SELECT id, email, first_name, last_name, role FROM users WHERE role = 'superadmin' ORDER BY id LIMIT 1");
    $superadmin = $stmt->fetch();

    if ($superadmin) {
        echo "Found existing superadmin:\n";
        echo "  ID: {$superadmin['id']}\n";
        echo "  Name: {$superadmin['first_name']} {$superadmin['last_name']}\n";
        echo "  Email: {$superadmin['email']}\n";
        echo "  Role: {$superadmin['role']}\n\n";

        // Update the credentials
        $updateStmt = $pdo->prepare("UPDATE users SET email = ?, password_hash = ?, updated_at = NOW() WHERE id = ?");
        $updateStmt->execute([$newEmail, $passwordHash, $superadmin['id']]);

        echo "✓ Updated superadmin credentials\n\n";
    } else {
        echo "No superadmin found. Creating new superadmin...\n\n";

        // Create new superadmin user
        $userId = bin2hex(random_bytes(16));
        $insertStmt = $pdo->prepare("INSERT INTO users (id, email, password_hash, first_name, last_name, account_type, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'referral', 'superadmin', 'active', NOW(), NOW())");
        $insertStmt->execute([$userId, $newEmail, $passwordHash, 'Parker', 'Lee']);

        echo "✓ Created new superadmin\n\n";
    }

    // Verify the update
    $verifyStmt = $pdo->prepare("SELECT id, first_name, last_name, email, role FROM users WHERE email = ?");
    $verifyStmt->execute([$newEmail]);
    $verified = $verifyStmt->fetch();

    if ($verified) {
        echo "✓ Verification successful:\n";
        echo "  ID: {$verified['id']}\n";
        echo "  Name: {$verified['first_name']} {$verified['last_name']}\n";
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

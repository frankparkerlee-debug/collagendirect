<?php
// Test login functionality
declare(strict_types=1);

require __DIR__ . '/api/db.php';

$email = 'sparkingmatt@gmail.com';
$password = 'TempPassword123!';

try {
    // Get user from database
    $stmt = $pdo->prepare("SELECT id, email, password_hash, first_name, last_name, status FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "❌ User not found\n";
        exit(1);
    }

    // Verify password
    if (password_verify($password, $user['password_hash'])) {
        echo "✅ Login credentials are VALID!\n\n";
        echo "User Details:\n";
        echo "=============\n";
        echo "ID:         {$user['id']}\n";
        echo "Email:      {$user['email']}\n";
        echo "Name:       {$user['first_name']} {$user['last_name']}\n";
        echo "Status:     {$user['status']}\n\n";
        echo "🎉 You can now log in at: http://localhost:8000/portal\n";
    } else {
        echo "❌ Password verification failed\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

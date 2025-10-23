<?php
// Simple script to create a test user
require __DIR__ . '/api/db.php';

$email = 'Parker@senecawest.com';
$password = 'Password321';
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if user exists
    $check = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
    $check->execute([$email]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo "User already exists: {$existing['email']}\n";
        echo "Updating password...\n";

        $update = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?");
        $update->execute([$hash, $email]);

        echo "Password updated successfully!\n";
    } else {
        echo "Creating new user...\n";

        $insert = $pdo->prepare("INSERT INTO users (id, email, password_hash, first_name, last_name, verified, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $userId = rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=');
        $insert->execute([$userId, $email, $hash, 'Parker', 'West', 1]);

        echo "User created successfully!\n";
    }

    echo "\n=== Login Credentials ===\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "URL: https://collagendirect.onrender.com/login\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit(1);
}

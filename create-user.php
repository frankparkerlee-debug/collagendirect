<?php
// Script to create a new user account
declare(strict_types=1);

require __DIR__ . '/api/db.php';

// User details
$email = 'sparkingmatt@gmail.com';
$password = 'TempPassword123!'; // Change this after first login
$firstName = 'Matthew';
$lastName = 'User';
$practiceName = 'Medical Practice';
$npi = '1234567890'; // You should use a real NPI

// Generate unique user ID
$userId = bin2hex(random_bytes(16));

// Hash the password using bcrypt
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

try {
    // Check if user already exists
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);

    if ($check->fetch()) {
        echo "❌ User with email {$email} already exists!\n";
        exit(1);
    }

    // Insert new user
    $stmt = $pdo->prepare("
        INSERT INTO users (
            id, email, password_hash, first_name, last_name,
            practice_name, npi, status, account_type,
            agree_msa, agree_baa, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 'referral', 1, 1, NOW(), NOW())
    ");

    $stmt->execute([
        $userId,
        $email,
        $passwordHash,
        $firstName,
        $lastName,
        $practiceName,
        $npi
    ]);

    echo "✅ User created successfully!\n\n";
    echo "Login Credentials:\n";
    echo "==================\n";
    echo "Email:    {$email}\n";
    echo "Password: {$password}\n";
    echo "User ID:  {$userId}\n\n";
    echo "⚠️  IMPORTANT: Change this password after first login!\n\n";
    echo "Access the portal at: http://localhost:8000/portal\n";

} catch (Exception $e) {
    echo "❌ Error creating user: " . $e->getMessage() . "\n";
    exit(1);
}

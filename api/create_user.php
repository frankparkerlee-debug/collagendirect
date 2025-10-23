<?php
// API endpoint to create a user account
require __DIR__ . '/db.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(405, ['error' => 'Method not allowed']);
}

// Get JSON payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    json_out(400, ['error' => 'Invalid JSON']);
}

// Validate required fields
$required = ['email', 'password', 'first_name', 'last_name'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        json_out(400, ['error' => "Missing required field: $field"]);
    }
}

$email = trim($data['email']);
$password = $data['password'];
$firstName = trim($data['first_name']);
$lastName = trim($data['last_name']);

// Optional fields
$practiceName = $data['practice_name'] ?? 'Medical Practice';
$npi = $data['npi'] ?? '1234567890';
$accountType = $data['account_type'] ?? 'referral';

try {
    // Check if user already exists
    $check = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
    $check->execute([$email]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update password
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE email = ?");
        $update->execute([$hash, $email]);

        json_out(200, [
            'success' => true,
            'message' => 'User password updated',
            'user_id' => $existing['id'],
            'email' => $email
        ]);
    } else {
        // Create new user
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userId = rtrim(strtr(base64_encode(random_bytes(16)),'+/','-_'),'=');

        $insert = $pdo->prepare("
            INSERT INTO users (
                id, email, password_hash, first_name, last_name,
                practice_name, npi, status, account_type,
                agree_msa, agree_baa, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $insert->execute([
            $userId,
            $email,
            $hash,
            $firstName,
            $lastName,
            $practiceName,
            $npi,
            'active',
            $accountType,
            true,
            true
        ]);

        json_out(201, [
            'success' => true,
            'message' => 'User created successfully',
            'user_id' => $userId,
            'email' => $email
        ]);
    }

} catch (PDOException $e) {
    json_out(500, ['error' => 'Database error: ' . $e->getMessage()]);
}

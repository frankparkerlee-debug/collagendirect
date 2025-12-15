<?php
/**
 * Check if email exists in the system
 *
 * POST /api/check-email.php
 * Body: { "email": "user@example.com" }
 * Returns: { "exists": true/false }
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(405, ['error' => 'Method not allowed']);
}

require_csrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim($input['email'] ?? '');

if (!$email) {
  json_out(400, ['error' => 'Email is required']);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_out(400, ['error' => 'Invalid email format']);
}

// Check in users table
$stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) AND deleted_at IS NULL LIMIT 1");
$stmt->execute([$email]);
$userExists = (bool)$stmt->fetch();

// Also check admin_users table
if (!$userExists) {
  $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE LOWER(email) = LOWER(?) LIMIT 1");
  $stmt->execute([$email]);
  $userExists = (bool)$stmt->fetch();
}

json_out(200, ['exists' => $userExists]);

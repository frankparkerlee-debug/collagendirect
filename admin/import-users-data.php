<?php
/**
 * Import users data to new database
 * Usage: POST JSON data to this endpoint
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

try {
  $input = file_get_contents('php://input');
  $data = json_decode($input, true);

  if (!$data || !isset($data['users'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
  }

  $users = $data['users'];
  $imported = 0;
  $skipped = 0;
  $errors = [];

  $pdo->beginTransaction();

  foreach ($users as $user) {
    try {
      // Check if user already exists
      $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
      $check->execute([$user['email']]);

      if ($check->fetch()) {
        $skipped++;
        continue;
      }

      // Insert user with all their data
      $stmt = $pdo->prepare("
        INSERT INTO users (
          id, email, password_hash, first_name, last_name,
          practice_name, npi, role, status, account_type,
          address, city, state, zip, phone,
          agree_msa, agree_baa,
          created_at, updated_at
        ) VALUES (
          ?, ?, ?, ?, ?,
          ?, ?, ?, ?, ?,
          ?, ?, ?, ?, ?,
          ?, ?,
          ?, ?
        )
      ");

      $stmt->execute([
        $user['id'],
        $user['email'],
        $user['password_hash'],
        $user['first_name'] ?? null,
        $user['last_name'] ?? null,
        $user['practice_name'] ?? null,
        $user['npi'] ?? null,
        $user['role'] ?? 'physician',
        $user['status'] ?? 'active',
        $user['account_type'] ?? 'referral',
        $user['address'] ?? null,
        $user['city'] ?? null,
        $user['state'] ?? null,
        $user['zip'] ?? null,
        $user['phone'] ?? null,
        $user['agree_msa'] ?? false,
        $user['agree_baa'] ?? false,
        $user['created_at'] ?? date('Y-m-d H:i:s'),
        $user['updated_at'] ?? date('Y-m-d H:i:s')
      ]);

      $imported++;
    } catch (PDOException $e) {
      $errors[] = [
        'email' => $user['email'],
        'error' => $e->getMessage()
      ];
    }
  }

  $pdo->commit();

  echo json_encode([
    'success' => true,
    'imported' => $imported,
    'skipped' => $skipped,
    'errors' => $errors,
    'total' => count($users)
  ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}

<?php
/**
 * Export users data from old database
 * This script exports all users in JSON format for migration to new instance
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

try {
  // Export all users with their complete data
  $users = $pdo->query("
    SELECT
      id, email, password_hash, first_name, last_name,
      practice_name, npi, role, status, account_type,
      address, city, state, zip, phone,
      agree_msa, agree_baa,
      created_at, updated_at
    FROM users
    ORDER BY created_at ASC
  ")->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'success' => true,
    'count' => count($users),
    'users' => $users,
    'exported_at' => date('Y-m-d H:i:s')
  ], JSON_PRETTY_PRINT);

} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage()
  ]);
}

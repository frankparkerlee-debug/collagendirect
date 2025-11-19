<?php
/**
 * Patient Search API
 * Returns matching patients based on name search
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

// Get search query
$query = $_GET['q'] ?? '';
$userId = $_GET['user_id'] ?? '';

if (empty($query) || empty($userId)) {
  echo json_encode([]);
  exit;
}

try {
  global $pdo;

  // Search for patients by name (for this user only)
  $stmt = $pdo->prepare("
    SELECT DISTINCT
      p.id,
      p.first_name,
      p.last_name,
      p.phone,
      p.address,
      p.city,
      p.state,
      p.zip,
      MAX(o.created_at) as last_order_date
    FROM patients p
    LEFT JOIN orders o ON o.patient_id = p.id
    WHERE p.user_id = ?
      AND (
        LOWER(p.first_name || ' ' || p.last_name) LIKE LOWER(?)
        OR LOWER(p.last_name || ', ' || p.first_name) LIKE LOWER(?)
      )
    GROUP BY p.id, p.first_name, p.last_name, p.phone, p.address, p.city, p.state, p.zip
    ORDER BY MAX(o.created_at) DESC NULLS LAST
    LIMIT 10
  ");

  $searchPattern = '%' . $query . '%';
  $stmt->execute([$userId, $searchPattern, $searchPattern]);
  $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Format results
  $results = array_map(function($p) {
    $fullAddress = trim(($p['address'] ?? '') . ', ' . ($p['city'] ?? '') . ', ' . ($p['state'] ?? '') . ' ' . ($p['zip'] ?? ''));
    $fullAddress = preg_replace('/^,\s*/', '', $fullAddress); // Remove leading comma
    $fullAddress = preg_replace('/,\s*,/', ',', $fullAddress); // Remove double commas

    return [
      'id' => $p['id'],
      'first_name' => $p['first_name'],
      'last_name' => $p['last_name'],
      'phone' => $p['phone'],
      'address' => $fullAddress,
      'city' => $p['city'] ?? '',
      'state' => $p['state'] ?? '',
      'zip' => $p['zip'] ?? '',
      'last_order' => $p['last_order_date'] ? date('n/j/y', strtotime($p['last_order_date'])) : null,
      'display' => $p['first_name'] . ' ' . $p['last_name'] .
                  ($p['last_order_date'] ? ' - Previous order: ' . date('n/j/y', strtotime($p['last_order_date'])) : '')
    ];
  }, $patients);

  echo json_encode($results);

} catch (PDOException $e) {
  error_log("Patient search error: " . $e->getMessage());
  echo json_encode([]);
}

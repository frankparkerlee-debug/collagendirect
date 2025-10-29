<?php
declare(strict_types=1);

/**
 * AJAX endpoint for ICD-10 code search
 * Used by autocomplete UI in order creation forms
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/lib/icd10_api.php';

// Only allow authenticated users (physicians, admins)
session_start();
if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

// Get search term from query string
$searchTerm = trim((string)($_GET['term'] ?? $_GET['q'] ?? ''));

if (empty($searchTerm)) {
  echo json_encode(['success' => false, 'error' => 'Search term required', 'results' => []]);
  exit;
}

// Minimum 2 characters to search (reduce load)
if (mb_strlen($searchTerm) < 2) {
  echo json_encode(['success' => false, 'error' => 'Search term must be at least 2 characters', 'results' => []]);
  exit;
}

// Get max results (default 15 for autocomplete)
$maxResults = min(50, max(5, (int)($_GET['max'] ?? 15)));

// Search ICD-10 codes
$result = icd10_search($searchTerm, $maxResults);

// Return results
echo json_encode($result, JSON_PRETTY_PRINT);

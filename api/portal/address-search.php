<?php
/**
 * Address Search/Autocomplete API
 * Uses Google Places API for address suggestions
 *
 * Usage: GET /api/portal/address-search.php?query=123+Main+St
 */
declare(strict_types=1);
require __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'unauthorized']);
  exit;
}

$query = trim($_GET['query'] ?? '');

if (strlen($query) < 3) {
  echo json_encode(['ok' => true, 'suggestions' => []]);
  exit;
}

try {
  // Check for Google Places API key in environment
  $apiKey = getenv('GOOGLE_PLACES_API_KEY');

  if (!$apiKey) {
    // Fallback: Simple parsing without external API
    echo json_encode([
      'ok' => true,
      'suggestions' => [],
      'message' => 'Address autocomplete requires Google Places API key'
    ]);
    exit;
  }

  // Use Google Places Autocomplete API
  $url = 'https://maps.googleapis.com/maps/api/place/autocomplete/json?' . http_build_query([
    'input' => $query,
    'types' => 'address',
    'components' => 'country:us', // Restrict to US addresses
    'key' => $apiKey
  ]);

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 5);
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpCode !== 200 || !$response) {
    throw new Exception('Failed to fetch address suggestions');
  }

  $data = json_decode($response, true);

  if ($data['status'] !== 'OK' && $data['status'] !== 'ZERO_RESULTS') {
    throw new Exception('Google Places API error: ' . ($data['status'] ?? 'Unknown'));
  }

  $suggestions = [];
  foreach (($data['predictions'] ?? []) as $prediction) {
    $suggestions[] = [
      'description' => $prediction['description'],
      'place_id' => $prediction['place_id'],
      'main_text' => $prediction['structured_formatting']['main_text'] ?? '',
      'secondary_text' => $prediction['structured_formatting']['secondary_text'] ?? ''
    ];
  }

  echo json_encode([
    'ok' => true,
    'suggestions' => $suggestions
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'address_search_failed',
    'message' => $e->getMessage()
  ]);
}

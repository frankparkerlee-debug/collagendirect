<?php
/**
 * Address Details API
 * Fetches full address details from Google Place ID
 *
 * Usage: GET /api/portal/address-details.php?place_id=ChIJ...
 */
declare(strict_types=1);
require __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'unauthorized']);
  exit;
}

$placeId = trim($_GET['place_id'] ?? '');

if (!$placeId) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'missing_place_id']);
  exit;
}

try {
  $apiKey = getenv('GOOGLE_PLACES_API_KEY');

  if (!$apiKey) {
    http_response_code(400);
    echo json_encode([
      'ok' => false,
      'error' => 'api_key_missing',
      'message' => 'Google Places API key not configured'
    ]);
    exit;
  }

  // Use Google Places Details API
  $url = 'https://maps.googleapis.com/maps/api/place/details/json?' . http_build_query([
    'place_id' => $placeId,
    'fields' => 'address_components,formatted_address',
    'key' => $apiKey
  ]);

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 5);
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpCode !== 200 || !$response) {
    throw new Exception('Failed to fetch address details');
  }

  $data = json_decode($response, true);

  if ($data['status'] !== 'OK') {
    throw new Exception('Google Places API error: ' . ($data['status'] ?? 'Unknown'));
  }

  $result = $data['result'] ?? [];
  $components = $result['address_components'] ?? [];

  // Parse address components
  $parsed = [
    'street_number' => '',
    'route' => '',
    'city' => '',
    'state' => '',
    'state_short' => '',
    'zip' => '',
    'country' => ''
  ];

  foreach ($components as $component) {
    $types = $component['types'] ?? [];

    if (in_array('street_number', $types)) {
      $parsed['street_number'] = $component['long_name'] ?? '';
    }
    if (in_array('route', $types)) {
      $parsed['route'] = $component['long_name'] ?? '';
    }
    if (in_array('locality', $types)) {
      $parsed['city'] = $component['long_name'] ?? '';
    }
    if (in_array('administrative_area_level_1', $types)) {
      $parsed['state'] = $component['long_name'] ?? '';
      $parsed['state_short'] = $component['short_name'] ?? '';
    }
    if (in_array('postal_code', $types)) {
      $parsed['zip'] = $component['long_name'] ?? '';
    }
    if (in_array('country', $types)) {
      $parsed['country'] = $component['short_name'] ?? '';
    }
  }

  // Construct street address
  $street = trim($parsed['street_number'] . ' ' . $parsed['route']);

  echo json_encode([
    'ok' => true,
    'address' => [
      'formatted' => $result['formatted_address'] ?? '',
      'street' => $street,
      'city' => $parsed['city'],
      'state' => $parsed['state_short'], // Use short form (e.g., "TX" not "Texas")
      'zip' => $parsed['zip'],
      'country' => $parsed['country']
    ]
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'address_details_failed',
    'message' => $e->getMessage()
  ]);
}

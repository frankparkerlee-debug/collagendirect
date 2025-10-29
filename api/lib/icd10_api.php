<?php
declare(strict_types=1);

/**
 * NIH/NLM ICD-10-CM API Integration
 * Free public API for medical diagnosis code lookup
 * Documentation: https://clinicaltables.nlm.nih.gov/apidoc/icd10cm/v3/doc.html
 */

/**
 * Search ICD-10-CM codes via NIH NLM Clinical Tables API
 *
 * @param string $searchTerm Search query (diagnosis name, symptom, etc.)
 * @param int $maxResults Maximum number of results (default: 10, max: 500)
 * @return array ['success' => bool, 'results' => array, 'error' => string|null]
 *
 * Result format: [
 *   ['code' => 'A15.0', 'name' => 'Tuberculosis of lung', 'display' => 'A15.0 - Tuberculosis of lung'],
 *   ...
 * ]
 */
function icd10_search(string $searchTerm, int $maxResults = 10): array {
  if (empty(trim($searchTerm))) {
    return [
      'success' => false,
      'results' => [],
      'error' => 'Search term cannot be empty'
    ];
  }

  // Validate max results
  $maxResults = max(1, min(500, $maxResults));

  // Build API URL
  $baseUrl = 'https://clinicaltables.nlm.nih.gov/api/icd10cm/v3/search';
  $params = [
    'sf' => 'code,name', // Search both code and name fields
    'df' => 'code,name', // Return code and name in display
    'terms' => trim($searchTerm),
    'maxList' => $maxResults
  ];

  $url = $baseUrl . '?' . http_build_query($params);

  // Make request with timeout
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'CollagenDirect/1.0 (Medical Order System)'
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  // Handle cURL errors
  if ($curlError) {
    error_log("[icd10_api] cURL error: {$curlError}");
    return [
      'success' => false,
      'results' => [],
      'error' => "Network error: {$curlError}"
    ];
  }

  // Handle non-200 responses
  if ($httpCode !== 200) {
    error_log("[icd10_api] HTTP {$httpCode} error for term: {$searchTerm}");
    return [
      'success' => false,
      'results' => [],
      'error' => "API returned HTTP {$httpCode}"
    ];
  }

  // Parse JSON response
  $data = json_decode($response, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("[icd10_api] JSON decode error: " . json_last_error_msg());
    return [
      'success' => false,
      'results' => [],
      'error' => "Invalid JSON response"
    ];
  }

  // API returns array: [totalCount, codes[], extraData{}, displayStrings[], codeSystems[]]
  if (!is_array($data) || count($data) < 4) {
    error_log("[icd10_api] Unexpected response format");
    return [
      'success' => false,
      'results' => [],
      'error' => "Unexpected API response format"
    ];
  }

  $totalCount = $data[0] ?? 0;
  $codes = $data[1] ?? [];
  $displayStrings = $data[3] ?? [];

  // Format results
  $results = [];
  foreach ($codes as $index => $code) {
    $displayParts = $displayStrings[$index] ?? [];
    $codeValue = $displayParts[0] ?? $code;
    $name = $displayParts[1] ?? '';

    $results[] = [
      'code' => $codeValue,
      'name' => $name,
      'display' => "{$codeValue} - {$name}"
    ];
  }

  return [
    'success' => true,
    'results' => $results,
    'total_count' => $totalCount,
    'error' => null
  ];
}

/**
 * Get ICD-10-CM code details by exact code
 *
 * @param string $code ICD-10 code (e.g., 'A15.0')
 * @return array ['success' => bool, 'code' => string|null, 'name' => string|null, 'error' => string|null]
 */
function icd10_get_code_details(string $code): array {
  $code = strtoupper(trim($code));

  if (empty($code)) {
    return [
      'success' => false,
      'code' => null,
      'name' => null,
      'error' => 'Code cannot be empty'
    ];
  }

  // Search for exact code
  $result = icd10_search($code, 1);

  if (!$result['success']) {
    return [
      'success' => false,
      'code' => null,
      'name' => null,
      'error' => $result['error']
    ];
  }

  // Check if exact match found
  if (empty($result['results'])) {
    return [
      'success' => false,
      'code' => null,
      'name' => null,
      'error' => 'Code not found'
    ];
  }

  $firstResult = $result['results'][0];

  // Verify exact match (case-insensitive)
  if (strcasecmp($firstResult['code'], $code) !== 0) {
    return [
      'success' => false,
      'code' => null,
      'name' => null,
      'error' => 'Exact code not found'
    ];
  }

  return [
    'success' => true,
    'code' => $firstResult['code'],
    'name' => $firstResult['name'],
    'error' => null
  ];
}

/**
 * Search medical conditions API (includes ICD-10 codes)
 * Alternative endpoint with broader medical condition coverage
 *
 * @param string $searchTerm Search query
 * @param int $maxResults Maximum results
 * @return array Same format as icd10_search()
 */
function icd10_search_conditions(string $searchTerm, int $maxResults = 10): array {
  if (empty(trim($searchTerm))) {
    return [
      'success' => false,
      'results' => [],
      'error' => 'Search term cannot be empty'
    ];
  }

  $maxResults = max(1, min(500, $maxResults));

  $baseUrl = 'https://clinicaltables.nlm.nih.gov/api/conditions/v3/search';
  $params = [
    'sf' => 'consumer_name,name_icd10cm',
    'df' => 'consumer_name,icd10cm_codes',
    'terms' => trim($searchTerm),
    'maxList' => $maxResults
  ];

  $url = $baseUrl . '?' . http_build_query($params);

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'CollagenDirect/1.0 (Medical Order System)'
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($curlError) {
    error_log("[icd10_conditions] cURL error: {$curlError}");
    return [
      'success' => false,
      'results' => [],
      'error' => "Network error: {$curlError}"
    ];
  }

  if ($httpCode !== 200) {
    error_log("[icd10_conditions] HTTP {$httpCode} error");
    return [
      'success' => false,
      'results' => [],
      'error' => "API returned HTTP {$httpCode}"
    ];
  }

  $data = json_decode($response, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    return [
      'success' => false,
      'results' => [],
      'error' => "Invalid JSON response"
    ];
  }

  if (!is_array($data) || count($data) < 4) {
    return [
      'success' => false,
      'results' => [],
      'error' => "Unexpected API response format"
    ];
  }

  $totalCount = $data[0] ?? 0;
  $codes = $data[1] ?? [];
  $displayStrings = $data[3] ?? [];

  $results = [];
  foreach ($codes as $index => $code) {
    $displayParts = $displayStrings[$index] ?? [];
    $conditionName = $displayParts[0] ?? '';
    $icd10Codes = $displayParts[1] ?? [];

    // Conditions may have multiple ICD-10 codes
    if (is_array($icd10Codes) && !empty($icd10Codes)) {
      foreach ($icd10Codes as $icdCode) {
        $results[] = [
          'code' => $icdCode,
          'name' => $conditionName,
          'display' => "{$icdCode} - {$conditionName}"
        ];
      }
    }
  }

  return [
    'success' => true,
    'results' => $results,
    'total_count' => $totalCount,
    'error' => null
  ];
}

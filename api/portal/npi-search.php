<?php
/**
 * NPI Registry Search API
 * Uses CMS NPPES NPI Registry API (free, no API key required)
 *
 * Usage:
 * - Search by NPI: GET /api/portal/npi-search.php?npi=1234567890
 * - Search by name: GET /api/portal/npi-search.php?first_name=John&last_name=Smith
 * - Search by organization: GET /api/portal/npi-search.php?organization=Acme+Medical
 *
 * NPPES API Documentation: https://npiregistry.cms.hhs.gov/api-page
 */
declare(strict_types=1);
require __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'unauthorized']);
  exit;
}

// Build search parameters
$params = [];

// NPI number search (most specific)
if (!empty($_GET['npi'])) {
  $npi = preg_replace('/[^0-9]/', '', $_GET['npi']);
  if (strlen($npi) === 10) {
    $params['number'] = $npi;
  } else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_npi', 'message' => 'NPI must be 10 digits']);
    exit;
  }
}

// Name search (for individuals)
if (!empty($_GET['first_name'])) {
  $params['first_name'] = trim($_GET['first_name']);
}
if (!empty($_GET['last_name'])) {
  $params['last_name'] = trim($_GET['last_name']);
}

// Organization name search
if (!empty($_GET['organization'])) {
  $params['organization_name'] = trim($_GET['organization']);
}

// State filter (optional)
if (!empty($_GET['state'])) {
  $params['state'] = strtoupper(trim($_GET['state']));
}

// Taxonomy/specialty filter (optional)
if (!empty($_GET['taxonomy'])) {
  $params['taxonomy_description'] = trim($_GET['taxonomy']);
}

// Limit results
$params['limit'] = min((int)($_GET['limit'] ?? 20), 200);
$params['version'] = '2.1';

if (empty($params) || (count($params) <= 2)) { // version + limit don't count
  http_response_code(400);
  echo json_encode([
    'ok' => false,
    'error' => 'missing_search_criteria',
    'message' => 'Provide at least one search parameter: npi, first_name/last_name, or organization'
  ]);
  exit;
}

try {
  // NPPES NPI Registry API (free, public, no auth required)
  $url = 'https://npiregistry.cms.hhs.gov/api/?' . http_build_query($params);

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  curl_setopt($ch, CURLOPT_USERAGENT, 'CollagenDirect/1.0');
  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpCode !== 200 || !$response) {
    throw new Exception('Failed to fetch NPI data');
  }

  $data = json_decode($response, true);

  if (!isset($data['result_count'])) {
    throw new Exception('Invalid response from NPI registry');
  }

  $results = [];
  foreach (($data['results'] ?? []) as $npiData) {
    $basic = $npiData['basic'] ?? [];
    $addresses = $npiData['addresses'] ?? [];
    $taxonomies = $npiData['taxonomies'] ?? [];

    // Get primary practice location
    $practiceAddress = null;
    foreach ($addresses as $addr) {
      if ($addr['address_purpose'] === 'LOCATION') {
        $practiceAddress = $addr;
        break;
      }
    }
    // Fallback to mailing address
    if (!$practiceAddress) {
      foreach ($addresses as $addr) {
        if ($addr['address_purpose'] === 'MAILING') {
          $practiceAddress = $addr;
          break;
        }
      }
    }

    // Get primary taxonomy
    $primaryTaxonomy = null;
    foreach ($taxonomies as $tax) {
      if (($tax['primary'] ?? false) === true) {
        $primaryTaxonomy = $tax;
        break;
      }
    }
    if (!$primaryTaxonomy && count($taxonomies) > 0) {
      $primaryTaxonomy = $taxonomies[0];
    }

    // Determine entity type
    $entityType = $npiData['enumeration_type'] ?? '';
    $isOrganization = $entityType === 'NPI-2';

    // Build result
    $result = [
      'npi' => $npiData['number'] ?? '',
      'entity_type' => $isOrganization ? 'organization' : 'individual',
      'name' => '',
      'first_name' => '',
      'last_name' => '',
      'organization_name' => '',
      'credential' => $basic['credential'] ?? '',
      'specialty' => $primaryTaxonomy['desc'] ?? '',
      'taxonomy_code' => $primaryTaxonomy['code'] ?? '',
      'license_number' => $primaryTaxonomy['license'] ?? '',
      'license_state' => $primaryTaxonomy['state'] ?? '',
      'phone' => $basic['telephone_number'] ?? '',
      'address' => [
        'street1' => $practiceAddress['address_1'] ?? '',
        'street2' => $practiceAddress['address_2'] ?? '',
        'city' => $practiceAddress['city'] ?? '',
        'state' => $practiceAddress['state'] ?? '',
        'zip' => $practiceAddress['postal_code'] ?? '',
        'country' => $practiceAddress['country_code'] ?? 'US'
      ]
    ];

    if ($isOrganization) {
      $result['organization_name'] = $basic['organization_name'] ?? $basic['name'] ?? '';
      $result['name'] = $result['organization_name'];
    } else {
      $result['first_name'] = $basic['first_name'] ?? '';
      $result['last_name'] = $basic['last_name'] ?? '';
      $result['name'] = trim(($basic['first_name'] ?? '') . ' ' . ($basic['last_name'] ?? ''));
      if (!empty($basic['credential'])) {
        $result['name'] .= ', ' . $basic['credential'];
      }
    }

    $results[] = $result;
  }

  echo json_encode([
    'ok' => true,
    'count' => $data['result_count'],
    'results' => $results
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'npi_search_failed',
    'message' => $e->getMessage()
  ]);
}

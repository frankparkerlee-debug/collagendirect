<?php
/**
 * NPI Validation API
 *
 * Validates NPI numbers against the official NPPES registry
 * and verifies that provider name matches
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

header('Content-Type: application/json');

try {
  $data = json_decode(file_get_contents('php://input'), true) ?? [];
} catch (Throwable $e) {
  json_out(400, ['error' => 'Invalid JSON']);
}

$npi = preg_replace('/\D/', '', (string)($data['npi'] ?? ''));
$firstName = trim((string)($data['firstName'] ?? ''));
$lastName = trim((string)($data['lastName'] ?? ''));
$skipNameValidation = !empty($data['skipNameValidation']);

// Validate input
if (strlen($npi) !== 10) {
  json_out(400, ['error' => 'NPI must be 10 digits']);
}

// Name validation can be skipped for lookup-only requests
if (!$skipNameValidation && (empty($firstName) || empty($lastName))) {
  json_out(400, ['error' => 'First name and last name are required for validation']);
}

// Check for duplicate NPI in database
try {
  $dupeCheck = $pdo->prepare("SELECT email, first_name, last_name, status FROM users WHERE npi = ? LIMIT 1");
  $dupeCheck->execute([$npi]);
  $existingUser = $dupeCheck->fetch(PDO::FETCH_ASSOC);

  if ($existingUser) {
    json_out(200, [
      'valid' => false,
      'duplicate' => true,
      'reason' => 'NPI already registered',
      'npi' => $npi,
      'existingUser' => [
        'email' => $existingUser['email'],
        'name' => trim($existingUser['first_name'] . ' ' . $existingUser['last_name']),
        'status' => $existingUser['status']
      ]
    ]);
  }
} catch (Throwable $e) {
  error_log("Duplicate check error: " . $e->getMessage());
  // Continue even if duplicate check fails
}

// Query NPPES registry
$nppesUrl = "https://npiregistry.cms.hhs.gov/api/?number={$npi}&version=2.1";

try {
  $context = stream_context_create([
    'http' => [
      'method' => 'GET',
      'timeout' => 10,
      'user_agent' => 'CollagenDirect Registration Verification'
    ]
  ]);

  $response = @file_get_contents($nppesUrl, false, $context);

  if ($response === false) {
    json_out(503, [
      'error' => 'Unable to verify NPI with NPPES registry',
      'valid' => false,
      'reason' => 'Service unavailable'
    ]);
  }

  $nppesData = json_decode($response, true);

  // Check if NPI exists
  if (empty($nppesData['results']) || count($nppesData['results']) === 0) {
    json_out(200, [
      'valid' => false,
      'reason' => 'NPI not found in NPPES registry',
      'npi' => $npi
    ]);
  }

  $provider = $nppesData['results'][0];

  // Extract provider information
  $nppesFirstName = '';
  $nppesLastName = '';
  $providerType = '';
  $credentials = '';
  $taxonomy = '';
  $status = $provider['basic']['status'] ?? '';

  // Check if it's an individual (Type 1) or organization (Type 2)
  $enumType = $provider['enumeration_type'] ?? '';

  if ($enumType === 'NPI-1') {
    // Individual provider
    $providerType = 'Individual';
    $nppesFirstName = $provider['basic']['first_name'] ?? '';
    $nppesLastName = $provider['basic']['last_name'] ?? '';
    $credentials = $provider['basic']['credential'] ?? '';

    if (!empty($provider['taxonomies']) && is_array($provider['taxonomies'])) {
      $primaryTaxonomy = array_filter($provider['taxonomies'], fn($t) => ($t['primary'] ?? false) === true);
      if (!empty($primaryTaxonomy)) {
        $taxonomy = reset($primaryTaxonomy)['desc'] ?? '';
      } else {
        $taxonomy = $provider['taxonomies'][0]['desc'] ?? '';
      }
    }
  } else {
    // Organization - we'll be more lenient
    $providerType = 'Organization';
    $nppesLastName = $provider['basic']['organization_name'] ?? '';
  }

  // Verify NPI is active
  if (strtoupper($status) !== 'A') {
    json_out(200, [
      'valid' => false,
      'reason' => 'NPI is not active',
      'npi' => $npi,
      'status' => $status,
      'providerType' => $providerType
    ]);
  }

  // Name matching for individual providers (only if not skipping validation)
  if (!$skipNameValidation && $enumType === 'NPI-1') {
    // Normalize names for comparison (case-insensitive, remove extra spaces)
    $inputFirstNorm = strtolower(trim($firstName));
    $inputLastNorm = strtolower(trim($lastName));
    $nppesFirstNorm = strtolower(trim($nppesFirstName));
    $nppesLastNorm = strtolower(trim($nppesLastName));

    // Check for exact match or partial match (for names with middle initials, etc.)
    $firstNameMatch = ($inputFirstNorm === $nppesFirstNorm) ||
                      (strpos($nppesFirstNorm, $inputFirstNorm) === 0) ||
                      (strpos($inputFirstNorm, $nppesFirstNorm) === 0);

    $lastNameMatch = ($inputLastNorm === $nppesLastNorm) ||
                     (strpos($nppesLastNorm, $inputLastNorm) === 0) ||
                     (strpos($inputLastNorm, $nppesLastNorm) === 0);

    if (!$firstNameMatch || !$lastNameMatch) {
      json_out(200, [
        'valid' => false,
        'reason' => 'Name does not match NPPES registry',
        'npi' => $npi,
        'expected' => [
          'firstName' => $nppesFirstName,
          'lastName' => $nppesLastName,
          'credentials' => $credentials
        ],
        'provided' => [
          'firstName' => $firstName,
          'lastName' => $lastName
        ],
        'providerType' => $providerType,
        'taxonomy' => $taxonomy
      ]);
    }
  }

  // Extract address information
  $addresses = $provider['addresses'] ?? [];
  $locationAddress = null;
  $phone = '';

  // Find location address (practice address)
  foreach ($addresses as $addr) {
    if (($addr['address_purpose'] ?? '') === 'LOCATION') {
      $locationAddress = $addr;
      break;
    }
  }

  // Fallback to mailing address if no location found
  if (!$locationAddress && !empty($addresses)) {
    $locationAddress = $addresses[0];
  }

  // Extract address fields
  $addressLine1 = $locationAddress['address_1'] ?? '';
  $city = $locationAddress['city'] ?? '';
  $state = $locationAddress['state'] ?? '';
  $zip = $locationAddress['postal_code'] ?? '';
  $phone = $locationAddress['telephone_number'] ?? '';

  // Format zip code (NPPES returns 9 digits, we want 5)
  if (strlen($zip) > 5) {
    $zip = substr($zip, 0, 5);
  }

  // Success - NPI is valid and name matches
  json_out(200, [
    'valid' => true,
    'npi' => $npi,
    'providerInfo' => [
      'firstName' => $nppesFirstName,
      'lastName' => $nppesLastName,
      'credentials' => $credentials,
      'providerType' => $providerType,
      'taxonomy' => $taxonomy,
      'status' => $status,
      'address' => $addressLine1,
      'city' => $city,
      'state' => $state,
      'zip' => $zip,
      'phone' => $phone
    ],
    'message' => 'NPI verified successfully'
  ]);

} catch (Throwable $e) {
  error_log("NPI validation error: " . $e->getMessage());
  json_out(500, [
    'error' => 'Server error during NPI validation',
    'valid' => false
  ]);
}

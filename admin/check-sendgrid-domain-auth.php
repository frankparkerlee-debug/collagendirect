<?php
/**
 * SendGrid Domain Authentication Checker
 * Checks if collagendirect.health is authenticated with SendGrid
 */

require __DIR__ . '/../api/lib/env.php';

header('Content-Type: application/json');

$apiKey = env('SENDGRID_API_KEY') ?: getenv('SENDGRID_API_KEY');

if (!$apiKey) {
  echo json_encode(['error' => 'SENDGRID_API_KEY not configured'], JSON_PRETTY_PRINT);
  exit;
}

// Check authenticated domains
$ch = curl_init('https://api.sendgrid.com/v3/whitelabel/domains');
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
  ],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 10,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

$result = [
  'http_code' => $httpCode,
  'curl_error' => $error ?: null,
];

if ($httpCode === 200) {
  $domains = json_decode($response, true);
  $result['authenticated_domains'] = $domains;

  $collagenDirectDomain = null;
  foreach ($domains as $domain) {
    if (strpos($domain['domain'] ?? '', 'collagendirect') !== false) {
      $collagenDirectDomain = $domain;
      break;
    }
  }

  if ($collagenDirectDomain) {
    $result['collagendirect_status'] = [
      'domain' => $collagenDirectDomain['domain'],
      'valid' => $collagenDirectDomain['valid'] ?? false,
      'dns_valid' => [
        'mail_cname' => $collagenDirectDomain['dns']['mail_cname']['valid'] ?? false,
        'dkim1' => $collagenDirectDomain['dns']['dkim1']['valid'] ?? false,
        'dkim2' => $collagenDirectDomain['dns']['dkim2']['valid'] ?? false,
      ]
    ];
  } else {
    $result['collagendirect_status'] = 'NOT_AUTHENTICATED';
    $result['message'] = 'Domain collagendirect.health is not authenticated. This is why emails are not being delivered.';
    $result['fix_url'] = 'https://app.sendgrid.com/settings/sender_auth';
  }
} else {
  $result['raw_response'] = $response;
}

echo json_encode($result, JSON_PRETTY_PRINT);

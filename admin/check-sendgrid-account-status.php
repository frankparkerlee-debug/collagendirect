<?php
/**
 * Check SendGrid Account Status and Permissions
 */

require __DIR__ . '/../api/lib/env.php';

header('Content-Type: application/json');

$apiKey = env('SENDGRID_API_KEY') ?: getenv('SENDGRID_API_KEY');

if (!$apiKey) {
  echo json_encode(['error' => 'SENDGRID_API_KEY not configured'], JSON_PRETTY_PRINT);
  exit;
}

$result = [];

// 1. Check API key scopes/permissions
$ch = curl_init('https://api.sendgrid.com/v3/scopes');
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
  $scopes = json_decode($response, true);
  $result['api_key_scopes'] = $scopes['scopes'] ?? [];
  $result['has_mail_send'] = in_array('mail.send', $result['api_key_scopes']);
} else {
  $result['scopes_error'] = ['http_code' => $httpCode, 'response' => $response];
}

// 2. Check account details
$ch = curl_init('https://api.sendgrid.com/v3/user/account');
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
  $account = json_decode($response, true);
  $result['account'] = $account;
} else {
  $result['account_error'] = ['http_code' => $httpCode, 'response' => $response];
}

// 3. Check sender verification status
$ch = curl_init('https://api.sendgrid.com/v3/verified_senders');
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
  $senders = json_decode($response, true);
  $result['verified_senders'] = $senders['results'] ?? [];

  $hasVerifiedSender = false;
  foreach ($result['verified_senders'] as $sender) {
    if (($sender['verified'] ?? false) === true) {
      $hasVerifiedSender = true;
      break;
    }
  }
  $result['has_verified_sender'] = $hasVerifiedSender;
} else {
  $result['senders_error'] = ['http_code' => $httpCode, 'response' => $response];
}

// 4. Check recent email activity (last 24 hours)
$ch = curl_init('https://api.sendgrid.com/v3/messages?limit=10');
curl_setopt_array($ch, [
  CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
  $messages = json_decode($response, true);
  $result['recent_messages'] = $messages['messages'] ?? [];

  // Analyze status
  $statusCounts = [];
  foreach ($result['recent_messages'] as $msg) {
    $status = $msg['status'] ?? 'unknown';
    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
  }
  $result['message_status_counts'] = $statusCounts;
} else {
  $result['messages_error'] = ['http_code' => $httpCode, 'response' => $response];
}

// 5. Diagnosis
$issues = [];
if (!($result['has_mail_send'] ?? false)) {
  $issues[] = 'API key does not have mail.send permission';
}
if (!($result['has_verified_sender'] ?? false)) {
  $issues[] = 'No verified sender identity configured';
}
if (isset($result['message_status_counts']['pending']) && $result['message_status_counts']['pending'] > 0) {
  $issues[] = count($result['message_status_counts']['pending']) . ' emails stuck in pending status';
}

$result['diagnosis'] = [
  'issues_found' => $issues,
  'likely_cause' => empty($issues) ? 'No obvious issues found' : $issues[0],
  'action_required' => !empty($issues) ? 'See issues_found array' : 'Check SendGrid Activity Feed for more details'
];

echo json_encode($result, JSON_PRETTY_PRINT);

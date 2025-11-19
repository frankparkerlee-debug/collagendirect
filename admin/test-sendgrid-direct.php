<?php
/**
 * Direct SendGrid Test - Minimal Code
 */

// Get API key from environment
$apiKey = getenv('SENDGRID_API_KEY');

if (!$apiKey) {
  die("ERROR: SENDGRID_API_KEY not set in environment\n");
}

echo "API Key: " . substr($apiKey, 0, 10) . "... (" . strlen($apiKey) . " chars)\n\n";

// Prepare email
$data = [
  'personalizations' => [
    [
      'to' => [['email' => 'frank.parker.lee@gmail.com']],
      'subject' => 'Direct Test - ' . date('H:i:s')
    ]
  ],
  'from' => ['email' => 'no-reply@collagendirect.health', 'name' => 'CollagenDirect'],
  'content' => [
    ['type' => 'text/plain', 'value' => 'Direct test email sent at ' . date('Y-m-d H:i:s')]
  ]
];

echo "Sending email to: frank.parker.lee@gmail.com\n";
echo "From: no-reply@collagendirect.health\n";
echo "Payload: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

// Send via cURL
$ch = curl_init('https://api.sendgrid.com/v3/mail/send');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Authorization: Bearer ' . $apiKey,
  'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "=== RESULT ===\n";
echo "HTTP Code: $httpCode\n";
echo "cURL Error: " . ($error ?: "None") . "\n";
echo "Response: $response\n\n";

if ($httpCode === 202) {
  echo "✅ SUCCESS! Email sent to SendGrid\n";
  echo "Check: https://app.sendgrid.com/email_activity\n";
} else {
  echo "❌ FAILED! HTTP $httpCode\n";
  echo "Error: $response\n";
}

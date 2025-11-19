<?php
/**
 * Check Email Sending Logs and Test Actual SendGrid API Call
 */

require __DIR__ . '/../api/lib/env.php';
require __DIR__ . '/../api/lib/sg_curl.php';

header('Content-Type: text/plain');

echo "=== Email Diagnostic Test ===\n\n";

// 1. Check environment
$apiKey = env('SENDGRID_API_KEY') ?: getenv('SENDGRID_API_KEY');
$smtpFrom = env('SMTP_FROM') ?: getenv('SMTP_FROM');
$smtpFromName = env('SMTP_FROM_NAME') ?: getenv('SMTP_FROM_NAME');

echo "1. Environment Check:\n";
echo "   SENDGRID_API_KEY: " . ($apiKey ? "✅ SET (" . strlen($apiKey) . " chars)" : "❌ NOT SET") . "\n";
echo "   SMTP_FROM: " . ($smtpFrom ?: "❌ NOT SET") . "\n";
echo "   SMTP_FROM_NAME: " . ($smtpFromName ?: "❌ NOT SET") . "\n\n";

if (!$apiKey) {
  echo "ERROR: Cannot test - API key not configured\n";
  exit;
}

// 2. Test direct SendGrid API call with detailed logging
echo "2. Testing Direct SendGrid API Call:\n";

$testEmail = 'frank.parker.lee@gmail.com';
$testData = [
  'personalizations' => [
    [
      'to' => [['email' => $testEmail, 'name' => 'Frank Lee']],
      'subject' => 'Test Email - ' . date('Y-m-d H:i:s')
    ]
  ],
  'from' => ['email' => $smtpFrom, 'name' => $smtpFromName],
  'content' => [
    ['type' => 'text/plain', 'value' => 'This is a test email sent at ' . date('Y-m-d H:i:s') . ' to verify email delivery.']
  ]
];

echo "   Sending to: $testEmail\n";
echo "   From: $smtpFrom\n";
echo "   API Key prefix: " . substr($apiKey, 0, 10) . "...\n\n";

// Make the API call with detailed error reporting
$ch = curl_init('https://api.sendgrid.com/v3/mail/send');
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer ' . $apiKey,
    'Content-Type: application/json'
  ],
  CURLOPT_POSTFIELDS => json_encode($testData),
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_TIMEOUT => 20,
  CURLOPT_VERBOSE => false,
  CURLOPT_HEADER => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

echo "3. SendGrid Response:\n";
echo "   HTTP Code: $httpCode\n";
echo "   cURL Error: " . ($curlError ?: "None") . "\n\n";

if ($httpCode === 202) {
  echo "   ✅ SUCCESS! Email accepted by SendGrid\n";
  echo "   Status: Email sent successfully\n\n";

  // Extract message ID if present
  if (preg_match('/X-Message-Id: (.+)/', $headers, $matches)) {
    echo "   Message ID: " . trim($matches[1]) . "\n";
  }

  echo "\n   IMPORTANT: Check SendGrid Activity Feed:\n";
  echo "   https://app.sendgrid.com/email_activity\n";
  echo "   Search for: frank.parker.lee@gmail.com\n";
  echo "   The email should appear there within 1-2 minutes\n\n";

} else {
  echo "   ❌ FAILED! SendGrid rejected the email\n";
  echo "   Response Body:\n";
  echo "   " . $body . "\n\n";
}

echo "4. Response Headers:\n";
echo str_replace("\n", "\n   ", trim($headers)) . "\n\n";

// 3. Test via wrapper functions
echo "5. Testing via Wrapper Functions:\n";

try {
  require_once __DIR__ . '/../api/lib/registration_welcome.php';

  echo "   Attempting send_registration_welcome_email()...\n";
  $result = send_registration_welcome_email([
    'email' => $testEmail,
    'firstName' => 'Frank',
    'lastName' => 'Lee',
    'userType' => 'practice_admin',
    'practiceName' => 'Test Practice'
  ]);

  echo "   Result: " . ($result ? "✅ Success" : "❌ Failed") . "\n";

} catch (Throwable $e) {
  echo "   ❌ Exception: " . $e->getMessage() . "\n";
  echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== Diagnostic Complete ===\n";

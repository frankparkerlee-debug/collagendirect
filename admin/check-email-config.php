<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../api/lib/env.php';

echo "=== Email Configuration Check ===\n\n";

// Check SendGrid API Key
$apiKey = env('SENDGRID_API_KEY');
if ($apiKey) {
  echo "✓ SENDGRID_API_KEY: Configured (length: " . strlen($apiKey) . ")\n";
  echo "  First 10 chars: " . substr($apiKey, 0, 10) . "...\n";
} else {
  echo "✗ SENDGRID_API_KEY: NOT CONFIGURED\n";
}

// Check template IDs
$templates = [
  'SG_TMPL_PASSWORD_RESET',
  'SG_TMPL_ACCOUNT_CONFIRMATION',
  'SG_TMPL_PHYSACCOUNT_CONFIRMATION',
  'SG_TMPL_ORDER_RECEIVED',
  'SG_TMPL_ORDER_APPROVED',
  'SG_TMPL_ORDER_SHIPPED',
  'SG_TMPL_ORDER_DELIVERED'
];

echo "\nTemplate IDs:\n";
foreach ($templates as $key) {
  $val = env($key);
  if ($val) {
    echo "✓ $key: $val\n";
  } else {
    echo "✗ $key: NOT CONFIGURED\n";
  }
}

// Check SMTP settings
echo "\nSMTP Settings:\n";
$smtpFrom = env('SMTP_FROM', 'no-reply@collagendirect.health');
$smtpFromName = env('SMTP_FROM_NAME', 'CollagenDirect');
echo "  SMTP_FROM: $smtpFrom\n";
echo "  SMTP_FROM_NAME: $smtpFromName\n";

// Try to test SendGrid API connection
echo "\n=== Testing SendGrid API Connection ===\n";
if ($apiKey) {
  $ch = curl_init('https://api.sendgrid.com/v3/user/email');
  curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $apiKey],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
  ]);

  $response = curl_exec($ch);
  $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $error = curl_error($ch);
  curl_close($ch);

  if ($error) {
    echo "✗ cURL Error: $error\n";
  } elseif ($status === 200) {
    echo "✓ SendGrid API connection successful!\n";
    echo "  Response: $response\n";
  } else {
    echo "✗ SendGrid API returned status $status\n";
    echo "  Response: $response\n";
  }
} else {
  echo "⊗ Skipping API test (no API key configured)\n";
}

echo "\n=== Checking Error Logs ===\n";
$errorLogPath = __DIR__ . '/../api/auth/error_log';
if (file_exists($errorLogPath)) {
  echo "Recent password reset logs:\n";
  $lines = file($errorLogPath);
  $recentLines = array_slice($lines, -20);
  foreach ($recentLines as $line) {
    if (stripos($line, 'reset') !== false || stripos($line, 'SendGrid') !== false) {
      echo "  " . trim($line) . "\n";
    }
  }
} else {
  echo "No error_log file found at: $errorLogPath\n";
}

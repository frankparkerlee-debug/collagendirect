<?php
// /api/admin/test_ai_key.php — Test if ANTHROPIC_API_KEY is configured
session_start();
header('Content-Type: text/plain');

// Authentication check
if (isset($_SESSION['admin'])) {
  $adminRole = $_SESSION['admin']['role'];
} elseif (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin') {
  $adminRole = $_SESSION['role'];
} else {
  echo "ERROR: Not authenticated\n";
  exit;
}

// Only superadmin can see this
if ($adminRole !== 'superadmin') {
  echo "ERROR: Unauthorized - superadmin only\n";
  exit;
}

echo "=== AI Configuration Test ===\n\n";

// Check if API key is set
$apiKey = getenv('ANTHROPIC_API_KEY');

if (empty($apiKey)) {
  echo "❌ ANTHROPIC_API_KEY: NOT SET\n";
  echo "\nThe environment variable is not configured.\n";
  echo "Please add it to your Render environment and redeploy.\n";
} else {
  $keyPrefix = substr($apiKey, 0, 12);
  $keyLength = strlen($apiKey);
  echo "✅ ANTHROPIC_API_KEY: CONFIGURED\n";
  echo "   Prefix: {$keyPrefix}...\n";
  echo "   Length: {$keyLength} characters\n";

  // Test actual API call
  echo "\n=== Testing Claude API Connection ===\n";

  $data = [
    'model' => 'claude-3-5-sonnet-latest',
    'max_tokens' => 50,
    'messages' => [
      [
        'role' => 'user',
        'content' => 'Reply with exactly: API connection successful'
      ]
    ]
  ];

  $ch = curl_init('https://api.anthropic.com/v1/messages');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'x-api-key: ' . $apiKey,
      'anthropic-version: 2023-06-01'
    ],
    CURLOPT_TIMEOUT => 10
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $error = curl_error($ch);
  curl_close($ch);

  if ($error) {
    echo "❌ cURL Error: $error\n";
  } elseif ($httpCode === 200) {
    echo "✅ API Connection: SUCCESS (HTTP $httpCode)\n";
    $result = json_decode($response, true);
    if (isset($result['content'][0]['text'])) {
      echo "   Response: " . $result['content'][0]['text'] . "\n";
    }
  } else {
    echo "❌ API Connection: FAILED (HTTP $httpCode)\n";
    echo "   Response: " . substr($response, 0, 500) . "\n";
  }
}

echo "\n=== Test Complete ===\n";
echo "\nIf you see errors:\n";
echo "1. Check that ANTHROPIC_API_KEY is set in Render environment\n";
echo "2. Redeploy your service after adding the variable\n";
echo "3. Make sure the API key starts with 'sk-ant-'\n";
echo "4. Verify the key is valid at https://console.anthropic.com/\n";

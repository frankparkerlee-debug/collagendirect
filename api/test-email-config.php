<?php
/**
 * Email Configuration Diagnostic Script
 * Tests email sending functionality and configuration
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);

require __DIR__ . '/lib/env.php';
require __DIR__ . '/lib/email_notifications.php';

// Check environment variables
$config = [
  'SENDGRID_API_KEY' => getenv('SENDGRID_API_KEY'),
  'SMTP_FROM' => getenv('SMTP_FROM'),
  'SMTP_FROM_NAME' => getenv('SMTP_FROM_NAME'),
  'SG_TMPL_PASSWORD_RESET' => getenv('SG_TMPL_PASSWORD_RESET'),
];

$diagnostics = [
  'environment' => [
    'sendgrid_api_key_set' => !empty($config['SENDGRID_API_KEY']),
    'sendgrid_api_key_length' => $config['SENDGRID_API_KEY'] ? strlen($config['SENDGRID_API_KEY']) : 0,
    'sendgrid_api_key_prefix' => $config['SENDGRID_API_KEY'] ? substr($config['SENDGRID_API_KEY'], 0, 7) : 'NOT SET',
    'smtp_from' => $config['SMTP_FROM'] ?: 'NOT SET',
    'smtp_from_name' => $config['SMTP_FROM_NAME'] ?: 'NOT SET',
    'password_reset_template' => $config['SG_TMPL_PASSWORD_RESET'] ?: 'NOT SET (will use plain-text fallback)',
  ],
  'files' => [
    'email_notifications_exists' => file_exists(__DIR__ . '/lib/email_notifications.php'),
    'sg_curl_exists' => file_exists(__DIR__ . '/lib/sg_curl.php'),
    'env_exists' => file_exists(__DIR__ . '/lib/env.php'),
  ],
  'functions' => [
    'send_password_reset_email' => function_exists('send_password_reset_email'),
    'sg_curl_send' => function_exists('sg_curl_send'),
    'env' => function_exists('env'),
  ]
];

// Test email sending (only if ?test_email=1)
if (!empty($_GET['test_email'])) {
  $testEmail = $_GET['email'] ?? 'test@example.com';

  try {
    $result = send_password_reset_email(
      $testEmail,
      'Test User',
      'https://collagendirect.health/portal/reset/?selector=TEST&token=TEST123'
    );

    $diagnostics['test_send'] = [
      'attempted' => true,
      'success' => $result,
      'email' => $testEmail
    ];
  } catch (Throwable $e) {
    $diagnostics['test_send'] = [
      'attempted' => true,
      'success' => false,
      'error' => $e->getMessage(),
      'email' => $testEmail
    ];
  }
}

// Check SendGrid API connectivity (only if API key is set)
if (!empty($config['SENDGRID_API_KEY'])) {
  try {
    $ch = curl_init('https://api.sendgrid.com/v3/user/profile');
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $config['SENDGRID_API_KEY'],
        'Content-Type: application/json'
      ],
      CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $diagnostics['sendgrid_api'] = [
      'reachable' => $httpCode > 0,
      'http_code' => $httpCode,
      'authenticated' => $httpCode === 200,
      'response' => $httpCode === 200 ? json_decode($response, true) : 'Authentication failed or API error'
    ];
  } catch (Throwable $e) {
    $diagnostics['sendgrid_api'] = [
      'reachable' => false,
      'error' => $e->getMessage()
    ];
  }
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

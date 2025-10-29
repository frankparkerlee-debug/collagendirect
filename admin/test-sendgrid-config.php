<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/lib/env.php';
require_once __DIR__ . '/../api/lib/sg_curl.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== SendGrid Configuration Test ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

// Check environment variables
echo "1. Checking Environment Variables...\n";
echo "----------------------------------------\n";

$requiredEnvVars = [
  'SENDGRID_API_KEY',
  'SMTP_FROM',
  'SMTP_FROM_NAME',
  'SG_TMPL_PASSWORD_RESET',
  'SG_TMPL_ACCOUNT_CONFIRM',
  'SG_TMPL_ORDER_CONFIRM'
];

$allConfigured = true;
foreach ($requiredEnvVars as $var) {
  $value = env($var);
  if (empty($value)) {
    echo "  ✗ $var: NOT SET\n";
    $allConfigured = false;
  } else {
    // Mask API key
    if ($var === 'SENDGRID_API_KEY') {
      $masked = substr($value, 0, 10) . '...' . substr($value, -4);
      echo "  ✓ $var: $masked\n";
    } else {
      echo "  ✓ $var: $value\n";
    }
  }
}

echo "\n";

if (!$allConfigured) {
  echo "❌ ERROR: Some required environment variables are missing!\n";
  echo "\nPlease ensure your .env file is configured:\n";
  echo "Location: /api/.env\n\n";
  echo "Required variables:\n";
  echo "  SENDGRID_API_KEY=your_api_key_here\n";
  echo "  SMTP_FROM=no-reply@collagendirect.health\n";
  echo "  SMTP_FROM_NAME=CollagenDirect\n";
  echo "  SG_TMPL_PASSWORD_RESET=d-...\n";
  echo "  SG_TMPL_ACCOUNT_CONFIRM=d-...\n";
  echo "  SG_TMPL_ORDER_CONFIRM=d-...\n";
  exit(1);
}

echo "✓ All environment variables configured\n\n";

// Test SendGrid API connectivity
echo "2. Testing SendGrid API Connectivity...\n";
echo "----------------------------------------\n";

$testEmail = 'parker@collagendirect.health'; // Update this to your test email

echo "Attempting to send test email to: $testEmail\n\n";

$result = sg_send(
  $testEmail,
  'SendGrid Test Email - ' . date('Y-m-d H:i:s'),
  '<h2>SendGrid Configuration Test</h2>
   <p>This is a test email to verify SendGrid is working correctly.</p>
   <p><strong>Test Details:</strong></p>
   <ul>
     <li>Timestamp: ' . date('Y-m-d H:i:s') . '</li>
     <li>From: ' . env('SMTP_FROM') . '</li>
     <li>Sent via: sg_send() function</li>
   </ul>
   <p>If you received this email, SendGrid is configured correctly!</p>',
  ['text' => 'This is a test email from CollagenDirect SendGrid configuration test.']
);

if ($result) {
  echo "✅ SUCCESS: Test email sent successfully!\n";
  echo "\nCheck your inbox at: $testEmail\n";
  echo "Note: Email may take 1-2 minutes to arrive\n";
  echo "Check spam folder if not received within 5 minutes\n";
} else {
  echo "❌ FAILED: Could not send test email\n";
  echo "\nPossible issues:\n";
  echo "  1. Invalid SendGrid API key\n";
  echo "  2. SendGrid account not activated\n";
  echo "  3. Sender email not verified in SendGrid\n";
  echo "  4. Network/firewall blocking SendGrid API\n";
  echo "\nCheck error logs for more details\n";
}

echo "\n";

// Test template-based email
echo "3. Testing Template-Based Email...\n";
echo "----------------------------------------\n";

$templateId = env('SG_TMPL_PASSWORD_RESET');
echo "Using template: $templateId\n";
echo "Sending password reset email to: $testEmail\n\n";

$result2 = sg_send(
  ['email' => $testEmail, 'name' => 'Test User'],
  null,
  null,
  [
    'template_id' => $templateId,
    'dynamic_data' => [
      'first_name' => 'Test',
      'reset_url' => 'https://collagendirect.health/reset-password?token=test123',
      'support_email' => 'support@collagendirect.health',
      'year' => date('Y')
    ],
    'categories' => ['test', 'configuration']
  ]
);

if ($result2) {
  echo "✅ SUCCESS: Template-based email sent successfully!\n";
  echo "\nPassword reset template is working correctly\n";
} else {
  echo "❌ FAILED: Could not send template-based email\n";
  echo "\nPossible issues:\n";
  echo "  1. Invalid template ID\n";
  echo "  2. Template not published in SendGrid\n";
  echo "  3. Missing required template variables\n";
}

echo "\n";

// Summary
echo "========================================\n";
echo "Test Summary\n";
echo "========================================\n\n";

if ($result && $result2) {
  echo "✅ ALL TESTS PASSED\n\n";
  echo "SendGrid is configured correctly and emails are sending.\n";
  echo "You can now proceed with implementing/testing notification emails.\n";
} else {
  echo "❌ SOME TESTS FAILED\n\n";
  echo "Please fix the issues above before proceeding.\n";
  echo "Review the error logs for more detailed information.\n";
}

echo "\n=== End of Test ===\n";

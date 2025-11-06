<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== Environment Variable Debug ===\n\n";

echo "Using getenv():\n";
echo "---------------\n";
echo "TWILIO_ACCOUNT_SID: " . (getenv('TWILIO_ACCOUNT_SID') ?: '✗ NOT SET') . "\n";
echo "TWILIO_AUTH_TOKEN: " . (getenv('TWILIO_AUTH_TOKEN') ? '✓ SET' : '✗ NOT SET') . "\n";
echo "TWILIO_PHONE_NUMBER: " . (getenv('TWILIO_PHONE_NUMBER') ?: '✗ NOT SET') . "\n";
echo "TWILIO_FROM_PHONE: " . (getenv('TWILIO_FROM_PHONE') ?: '✗ NOT SET') . "\n\n";

echo "Using \$_ENV:\n";
echo "------------\n";
echo "TWILIO_ACCOUNT_SID: " . ($_ENV['TWILIO_ACCOUNT_SID'] ?? '✗ NOT SET') . "\n";
echo "TWILIO_AUTH_TOKEN: " . (isset($_ENV['TWILIO_AUTH_TOKEN']) ? '✓ SET' : '✗ NOT SET') . "\n";
echo "TWILIO_PHONE_NUMBER: " . ($_ENV['TWILIO_PHONE_NUMBER'] ?? '✗ NOT SET') . "\n";
echo "TWILIO_FROM_PHONE: " . ($_ENV['TWILIO_FROM_PHONE'] ?? '✗ NOT SET') . "\n\n";

echo "Using \$_SERVER:\n";
echo "---------------\n";
echo "TWILIO_ACCOUNT_SID: " . ($_SERVER['TWILIO_ACCOUNT_SID'] ?? '✗ NOT SET') . "\n";
echo "TWILIO_AUTH_TOKEN: " . (isset($_SERVER['TWILIO_AUTH_TOKEN']) ? '✓ SET' : '✗ NOT SET') . "\n";
echo "TWILIO_PHONE_NUMBER: " . ($_SERVER['TWILIO_PHONE_NUMBER'] ?? '✗ NOT SET') . "\n";
echo "TWILIO_FROM_PHONE: " . ($_SERVER['TWILIO_FROM_PHONE'] ?? '✗ NOT SET') . "\n\n";

require_once __DIR__ . '/../api/lib/env.php';

echo "Using env() function:\n";
echo "--------------------\n";
echo "TWILIO_ACCOUNT_SID: " . (env('TWILIO_ACCOUNT_SID') ?: '✗ NOT SET') . "\n";
echo "TWILIO_AUTH_TOKEN: " . (env('TWILIO_AUTH_TOKEN') ? '✓ SET' : '✗ NOT SET') . "\n";
echo "TWILIO_PHONE_NUMBER: " . (env('TWILIO_PHONE_NUMBER') ?: '✗ NOT SET') . "\n";
echo "TWILIO_FROM_PHONE: " . (env('TWILIO_FROM_PHONE') ?: '✗ NOT SET') . "\n\n";

echo "=== Debug Complete ===\n";

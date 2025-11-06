<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/lib/env.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== Twilio Environment Check ===\n\n";

$envPath = __DIR__ . '/../api/.env';
echo "Checking .env file at: {$envPath}\n";
echo "File exists: " . (file_exists($envPath) ? "YES" : "NO") . "\n";
echo "File readable: " . (is_readable($envPath) ? "YES" : "NO") . "\n\n";

echo "Environment Variables:\n";
echo "---------------------\n";

$vars = [
    'TWILIO_ACCOUNT_SID',
    'TWILIO_AUTH_TOKEN',
    'TWILIO_PHONE_NUMBER',
    'TWILIO_FROM_PHONE'  // Legacy check
];

foreach ($vars as $var) {
    $value = env($var);
    if ($value) {
        if ($var === 'TWILIO_AUTH_TOKEN') {
            echo "{$var}: " . str_repeat('*', 20) . "\n";
        } else if ($var === 'TWILIO_ACCOUNT_SID') {
            echo "{$var}: " . substr($value, 0, 10) . "...\n";
        } else {
            echo "{$var}: {$value}\n";
        }
    } else {
        echo "{$var}: ✗ NOT SET\n";
    }
}

echo "\n";

// Check if file has the variables
if (file_exists($envPath)) {
    echo "Contents of .env file (filtered):\n";
    echo "---------------------------------\n";
    $content = file_get_contents($envPath);
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        if (strpos($line, 'TWILIO_') === 0) {
            // Mask sensitive values
            if (strpos($line, 'TOKEN') !== false) {
                $parts = explode('=', $line, 2);
                echo $parts[0] . "=***\n";
            } else {
                echo $line . "\n";
            }
        }
    }
}

echo "\n=== Check Complete ===\n";

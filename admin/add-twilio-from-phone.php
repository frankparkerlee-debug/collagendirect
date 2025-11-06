<?php
/**
 * Add TWILIO_FROM_PHONE to .env file
 * This script adds the missing environment variable to enable SMS sending
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Adding TWILIO_FROM_PHONE to .env ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

$envFile = __DIR__ . '/../api/.env';

if (!file_exists($envFile)) {
    echo "✗ .env file not found at: {$envFile}\n";
    exit(1);
}

echo "Reading .env file...\n";
$content = file_get_contents($envFile);

// Check if TWILIO_FROM_PHONE already exists
if (strpos($content, 'TWILIO_FROM_PHONE') !== false) {
    echo "✓ TWILIO_FROM_PHONE already exists in .env\n";
    echo "\nCurrent Twilio config:\n";
    echo "----------------------------------------\n";
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        if (strpos($line, 'TWILIO_') === 0) {
            // Mask sensitive values
            if (strpos($line, 'TOKEN') !== false) {
                $parts = explode('=', $line, 2);
                echo $parts[0] . "=(masked)\n";
            } else {
                echo $line . "\n";
            }
        }
    }
    exit(0);
}

echo "Adding TWILIO_FROM_PHONE=+18884156880...\n";

// Find the end of Twilio section or create it
$twilioPhone = "\nTWILIO_FROM_PHONE=+18884156880\n";

// If TWILIO_AUTH_TOKEN exists, add after it
if (strpos($content, 'TWILIO_AUTH_TOKEN') !== false) {
    $content = preg_replace(
        '/(TWILIO_AUTH_TOKEN=.+)(\n)/',
        "$1\nTWILIO_FROM_PHONE=+18884156880$2",
        $content
    );
} elseif (strpos($content, 'TWILIO_ACCOUNT_SID') !== false) {
    // If only SID exists, add after it
    $content = preg_replace(
        '/(TWILIO_ACCOUNT_SID=.+)(\n)/',
        "$1\nTWILIO_FROM_PHONE=+18884156880$2",
        $content
    );
} else {
    // No Twilio config at all, add at the end
    $content .= "\n# Twilio SMS Configuration\nTWILIO_FROM_PHONE=+18884156880\n";
}

// Backup original file
$backup = $envFile . '.backup.' . date('YmdHis');
copy($envFile, $backup);
echo "✓ Backup created: {$backup}\n";

// Write updated content
file_put_contents($envFile, $content);
echo "✓ TWILIO_FROM_PHONE added to .env\n\n";

echo "Updated Twilio config:\n";
echo "----------------------------------------\n";
$lines = explode("\n", $content);
foreach ($lines as $line) {
    if (strpos($line, 'TWILIO_') === 0) {
        // Mask sensitive values
        if (strpos($line, 'TOKEN') !== false) {
            $parts = explode('=', $line, 2);
            echo $parts[0] . "=(masked)\n";
        } else {
            echo $line . "\n";
        }
    }
}

echo "\n✓ Complete! SMS should now work.\n";
echo "\nNext: Test SMS by marking an order as delivered.\n";
echo "=== Done ===\n";

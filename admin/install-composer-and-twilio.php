<?php
/**
 * Install Composer and Twilio SDK
 * Run via: https://collagendirect.health/admin/install-composer-and-twilio.php
 */

set_time_limit(300); // 5 minutes
header('Content-Type: text/plain; charset=utf-8');

echo "=== Composer & Twilio SDK Installation ===\n\n";

$baseDir = __DIR__ . '/..';
chdir($baseDir);

// Set HOME environment variable for Composer
putenv('HOME=' . $baseDir);
putenv('COMPOSER_HOME=' . $baseDir . '/.composer');

// Create .composer directory if it doesn't exist
if (!is_dir($baseDir . '/.composer')) {
    mkdir($baseDir . '/.composer', 0755, true);
}

echo "✓ Environment configured\n";
echo "  HOME: " . getenv('HOME') . "\n";
echo "  COMPOSER_HOME: " . getenv('COMPOSER_HOME') . "\n\n";

// Step 1: Download and install Composer
echo "Step 1: Installing Composer...\n";
echo str_repeat('-', 60) . "\n";

$composerSetup = file_get_contents('https://getcomposer.org/installer');
if (!$composerSetup) {
    echo "✗ Failed to download Composer installer\n";
    exit(1);
}

file_put_contents('composer-setup.php', $composerSetup);
echo "✓ Downloaded Composer installer\n";

// Run Composer installer with HOME set
$output = [];
$returnCode = 0;
exec('HOME=' . escapeshellarg($baseDir) . ' php composer-setup.php 2>&1', $output, $returnCode);

foreach ($output as $line) {
    echo $line . "\n";
}

// Clean up installer
unlink('composer-setup.php');

if ($returnCode !== 0) {
    echo "\n✗ Composer installation failed\n";
    exit(1);
}

if (!file_exists('composer.phar')) {
    echo "\n✗ composer.phar not found after installation\n";
    exit(1);
}

echo "\n✓ Composer installed successfully!\n\n";

// Step 2: Install dependencies with Composer
echo "Step 2: Installing Twilio SDK...\n";
echo str_repeat('-', 60) . "\n";

$output = [];
$returnCode = 0;

// Run composer install with HOME variable
$homeVar = 'HOME=' . escapeshellarg($baseDir);
$composerHomeVar = 'COMPOSER_HOME=' . escapeshellarg($baseDir . '/.composer');
passthru("$homeVar $composerHomeVar php composer.phar install --no-interaction 2>&1", $returnCode);

echo "\n" . str_repeat('-', 60) . "\n";

if ($returnCode !== 0) {
    echo "\n⚠ Composer install returned code: $returnCode\n";
    echo "This may be normal if dependencies were already satisfied.\n\n";
}

// Step 3: Verify installation
echo "\nStep 3: Verifying Twilio SDK...\n";
echo str_repeat('-', 60) . "\n";

if (!file_exists('vendor/autoload.php')) {
    echo "✗ vendor/autoload.php not found\n";
    echo "\nTrying alternative installation method...\n";

    // Try composer require directly with HOME variable
    passthru("$homeVar $composerHomeVar php composer.phar require twilio/sdk --no-interaction 2>&1", $returnCode);

    if (!file_exists('vendor/autoload.php')) {
        echo "\n✗ Installation failed\n";
        exit(1);
    }
}

echo "✓ vendor directory created\n";

// Test loading Twilio
try {
    require_once 'vendor/autoload.php';

    if (class_exists('Twilio\Rest\Client')) {
        echo "✓ Twilio SDK loaded successfully!\n";

        // Check version
        $reflection = new ReflectionClass('Twilio\Version');
        $filename = $reflection->getFileName();
        if (preg_match('/twilio\/sdk\/([^\/]+)\//', $filename, $matches)) {
            echo "✓ Twilio SDK version: " . $matches[1] . "\n";
        }

        echo "\n" . str_repeat('=', 60) . "\n";
        echo "=== Installation Complete! ===\n";
        echo str_repeat('=', 60) . "\n\n";

        echo "The Twilio SDK is now installed and ready to use.\n\n";

        echo "Next steps:\n";
        echo "1. Test sending SMS: Go to any patient page\n";
        echo "2. Click 'Request Photo' button\n";
        echo "3. Patient should receive SMS\n\n";

        echo "Webhook URL (verify in Twilio console):\n";
        echo "https://collagendirect.health/api/twilio/receive-mms.php\n\n";

        exit(0);
    } else {
        echo "✗ Twilio\Rest\Client class not found\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Error loading Twilio SDK: " . $e->getMessage() . "\n";
    exit(1);
}

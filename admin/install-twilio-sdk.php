<?php
/**
 * Install Twilio SDK via Composer
 * Run via: https://collagendirect.health/admin/install-twilio-sdk.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Twilio SDK Installation ===\n\n";

$baseDir = __DIR__ . '/..';
$vendorDir = $baseDir . '/vendor';
$composerJson = $baseDir . '/composer.json';
$composerLock = $baseDir . '/composer.lock';

// Check if composer.json exists
if (!file_exists($composerJson)) {
    echo "✗ composer.json not found\n";
    echo "Please ensure you've pulled the latest code from git.\n";
    exit(1);
}

echo "✓ composer.json found\n";

// Check if vendor directory already exists
if (is_dir($vendorDir)) {
    echo "✓ vendor directory exists\n";

    // Check if Twilio is installed
    $twilioDir = $vendorDir . '/twilio/sdk';
    if (is_dir($twilioDir)) {
        echo "✓ Twilio SDK already installed\n\n";

        // Test the installation
        echo "Testing Twilio SDK...\n";
        try {
            require_once $vendorDir . '/autoload.php';

            if (class_exists('Twilio\Rest\Client')) {
                echo "✓ Twilio SDK loaded successfully!\n\n";
                echo "=== Installation Complete ===\n";
                echo "The Twilio SDK is ready to use.\n";
                exit(0);
            } else {
                echo "⚠ Twilio SDK files exist but class not found\n";
            }
        } catch (Exception $e) {
            echo "⚠ Error loading Twilio SDK: " . $e->getMessage() . "\n";
        }
    } else {
        echo "⚠ Twilio SDK not found in vendor directory\n";
    }
} else {
    echo "✗ vendor directory not found\n";
}

echo "\nAttempting to install via Composer...\n\n";

// Check if composer is available
$composerCmd = null;
$composerPaths = ['composer', '/usr/local/bin/composer', '/usr/bin/composer'];

foreach ($composerPaths as $path) {
    exec("which $path 2>/dev/null", $output, $returnCode);
    if ($returnCode === 0 && !empty($output)) {
        $composerCmd = $path;
        break;
    }
}

if ($composerCmd) {
    echo "✓ Found Composer: $composerCmd\n\n";

    // Run composer install
    echo "Running: composer install\n";
    echo str_repeat('-', 60) . "\n";

    chdir($baseDir);
    passthru("$composerCmd install 2>&1", $returnCode);

    echo "\n" . str_repeat('-', 60) . "\n";

    if ($returnCode === 0) {
        echo "\n✓ Composer install completed successfully!\n\n";

        // Verify Twilio SDK
        if (file_exists($vendorDir . '/autoload.php')) {
            require_once $vendorDir . '/autoload.php';

            if (class_exists('Twilio\Rest\Client')) {
                echo "✓ Twilio SDK is now ready to use!\n\n";
                echo "=== Installation Complete ===\n";
                exit(0);
            }
        }

        echo "⚠ Installation completed but Twilio SDK not detected\n";
        exit(1);
    } else {
        echo "\n✗ Composer install failed with exit code: $returnCode\n";
        exit(1);
    }
} else {
    echo "✗ Composer not found on server\n\n";
    echo "Please install Composer manually:\n";
    echo "1. SSH to server: ssh collagendirect.health\n";
    echo "2. cd /var/www/html\n";
    echo "3. curl -sS https://getcomposer.org/installer | php\n";
    echo "4. php composer.phar install\n\n";
    exit(1);
}

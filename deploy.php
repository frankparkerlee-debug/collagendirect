<?php
/**
 * Simple git pull deployment script
 * Security: Only allow from GitHub IPs or with secret token
 */

$secret = 'your-secret-token-here'; // Change this!

// Check for secret token (PHP 5 compatible)
$token = '';
if (isset($_GET['token'])) {
    $token = $_GET['token'];
} elseif (isset($_POST['token'])) {
    $token = $_POST['token'];
}

if ($token !== $secret) {
    http_response_code(403);
    die('Forbidden');
}

// Try to find the correct web root directory
$possiblePaths = array(
    '/var/www/html',
    '/var/www/collagendirect.health/public_html',
    dirname(__FILE__)
);

$found = false;
foreach ($possiblePaths as $path) {
    if (is_dir($path . '/.git')) {
        chdir($path);
        $found = true;
        break;
    }
}

if (!$found) {
    // Just use current directory
    chdir(dirname(__FILE__));
}

// Pull latest changes
$output = array();
$return_var = 0;

header('Content-Type: text/plain');
echo "Current directory: " . getcwd() . "\n";
echo "Git directory exists: " . (is_dir('.git') ? 'YES' : 'NO') . "\n\n";

exec('git pull origin main 2>&1', $output, $return_var);

// Return results
echo "Deployment Status: " . ($return_var === 0 ? 'SUCCESS' : 'FAILED') . "\n";
echo "Output:\n";
echo implode("\n", $output) . "\n";

// Clear opcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "\nOPCache cleared.\n";
}

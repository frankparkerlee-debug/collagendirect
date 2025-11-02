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

// Change to web root directory
chdir('/var/www/html');

// Pull latest changes
$output = [];
$return_var = 0;
exec('git pull origin main 2>&1', $output, $return_var);

// Return results
header('Content-Type: text/plain');
echo "Deployment Status: " . ($return_var === 0 ? 'SUCCESS' : 'FAILED') . "\n";
echo "Output:\n";
echo implode("\n", $output);

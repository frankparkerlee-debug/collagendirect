<?php
/**
 * Simple git pull script for manual deployment
 * Access: https://collagendirect.health/admin/git-pull.php?pull=now
 */

// Simple security check
$key = '';
if (isset($_GET['pull'])) {
    $key = $_GET['pull'];
}

if ($key !== 'now') {
    http_response_code(403);
    die('Access denied. Use ?pull=now to execute.');
}

// Set the correct directory (adjust if needed)
$webRoot = dirname(__DIR__);
chdir($webRoot);

header('Content-Type: text/plain; charset=utf-8');
echo "Current directory: " . getcwd() . "\n\n";

// Check if it's a git repository
if (!is_dir('.git')) {
    echo "ERROR: Not a git repository!\n";
    echo "Looking for .git in: " . getcwd() . "\n";
    exit(1);
}

echo "Git repository found.\n\n";

// Execute git pull
echo "Executing: git pull origin main\n";
echo str_repeat('-', 60) . "\n";

$output = array();
$returnCode = 0;

exec('git pull origin main 2>&1', $output, $returnCode);

foreach ($output as $line) {
    echo $line . "\n";
}

echo str_repeat('-', 60) . "\n";
echo "\nReturn code: " . $returnCode . "\n";
echo "Status: " . ($returnCode === 0 ? 'SUCCESS' : 'FAILED') . "\n";

// Clear PHP opcache if available
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "\nOPCache cleared.\n";
}

echo "\nDeployment complete at " . date('Y-m-d H:i:s') . "\n";

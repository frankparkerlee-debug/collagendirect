<?php
// Quick test to check environment variables
header('Content-Type: text/plain');

echo "Environment Variables Test\n";
echo "==========================\n\n";

echo "SENDGRID_KEY: " . (getenv('SENDGRID_KEY') ? '✅ Set (' . substr(getenv('SENDGRID_KEY'), 0, 10) . '...)' : '❌ Not set') . "\n";
echo "GOOGLE_SEARCH_API_KEY: " . (getenv('GOOGLE_SEARCH_API_KEY') ? '✅ Set (' . substr(getenv('GOOGLE_SEARCH_API_KEY'), 0, 10) . '...)' : '❌ Not set') . "\n";
echo "GOOGLE_SEARCH_CX: " . (getenv('GOOGLE_SEARCH_CX') ? '✅ Set (' . getenv('GOOGLE_SEARCH_CX') . ')' : '❌ Not set') . "\n";

echo "\n";
echo "If any show '❌ Not set', Render hasn't picked up the environment variables yet.\n";
echo "Solution: Wait for Render to redeploy, or manually trigger a deploy.\n";

<?php
/**
 * Quick Twilio Test - Standalone (No Auth Required)
 * Use this to quickly test your newly approved Twilio number
 *
 * Usage: php admin/quick-twilio-test.php [phone_number]
 * Example: php admin/quick-twilio-test.php 5551234567
 */

// Load environment
require_once __DIR__ . '/../api/lib/env.php';
require_once __DIR__ . '/../api/lib/twilio_sms.php';

// CLI colors for better output
function color_text($text, $color = 'green') {
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

echo "\n";
echo color_text("╔═══════════════════════════════════════╗", 'blue') . "\n";
echo color_text("║   Twilio SMS Test - Quick Version    ║", 'blue') . "\n";
echo color_text("╚═══════════════════════════════════════╝", 'blue') . "\n\n";

// Check environment variables
echo color_text("📋 Checking Configuration...", 'yellow') . "\n";
echo str_repeat("─", 50) . "\n";

$accountSid = env('TWILIO_ACCOUNT_SID');
$authToken = env('TWILIO_AUTH_TOKEN');
$fromPhone = env('TWILIO_PHONE_NUMBER');

if (empty($accountSid)) {
    echo color_text("✗ TWILIO_ACCOUNT_SID: NOT SET", 'red') . "\n";
    $hasError = true;
} else {
    echo color_text("✓ TWILIO_ACCOUNT_SID: ", 'green') . substr($accountSid, 0, 10) . "..." . substr($accountSid, -4) . "\n";
}

if (empty($authToken)) {
    echo color_text("✗ TWILIO_AUTH_TOKEN: NOT SET", 'red') . "\n";
    $hasError = true;
} else {
    echo color_text("✓ TWILIO_AUTH_TOKEN: ", 'green') . str_repeat('*', 28) . substr($authToken, -4) . "\n";
}

if (empty($fromPhone)) {
    echo color_text("✗ TWILIO_PHONE_NUMBER: NOT SET", 'red') . "\n";
    $hasError = true;
} else {
    echo color_text("✓ TWILIO_PHONE_NUMBER: {$fromPhone}", 'green') . "\n";
}

echo "\n";

if (isset($hasError)) {
    echo color_text("❌ ERROR: Missing Twilio credentials", 'red') . "\n";
    echo "\nPlease set these environment variables:\n";
    echo "- TWILIO_ACCOUNT_SID\n";
    echo "- TWILIO_AUTH_TOKEN\n";
    echo "- TWILIO_PHONE_NUMBER\n";
    exit(1);
}

// Get phone number from command line
$testPhone = $argv[1] ?? '';

if (empty($testPhone)) {
    echo color_text("ℹ️  Usage Instructions:", 'blue') . "\n";
    echo str_repeat("─", 50) . "\n";
    echo "To send a test SMS, run:\n";
    echo color_text("  php admin/quick-twilio-test.php YOUR_PHONE_NUMBER", 'yellow') . "\n\n";
    echo "Examples:\n";
    echo "  php admin/quick-twilio-test.php 5551234567\n";
    echo "  php admin/quick-twilio-test.php +15551234567\n";
    echo "  php admin/quick-twilio-test.php \"(555) 123-4567\"\n\n";
    echo color_text("Note:", 'yellow') . " Twilio trial accounts can only send to verified numbers.\n";
    echo "      If your account is approved, you can send to any US number.\n";
    exit(0);
}

// Send test SMS
echo color_text("📤 Sending Test SMS...", 'yellow') . "\n";
echo str_repeat("─", 50) . "\n";
echo "To: {$testPhone}\n";

$normalizedPhone = normalize_phone_number($testPhone);
if (!$normalizedPhone) {
    echo color_text("✗ Invalid phone number format", 'red') . "\n";
    exit(1);
}

echo "Normalized: {$normalizedPhone}\n";
echo "From: {$fromPhone}\n\n";

$timestamp = date('g:i A');
$message = "✅ Test message from CollagenDirect\n\n"
    . "If you receive this, your Twilio number is working correctly!\n\n"
    . "Sent at: {$timestamp}";

echo "Sending...\n";
$result = twilio_send_sms($normalizedPhone, $message);

echo "\n";

if ($result['success']) {
    echo color_text("✅ SUCCESS! SMS SENT", 'green') . "\n";
    echo str_repeat("─", 50) . "\n";
    echo color_text("Message SID: ", 'green') . $result['sid'] . "\n";
    echo color_text("Status: ", 'green') . $result['status'] . "\n\n";

    echo color_text("📱 Check your phone for the test message!", 'blue') . "\n\n";

    echo "You can also view delivery status in Twilio Console:\n";
    echo color_text("https://console.twilio.com/us1/monitor/logs/sms", 'blue') . "\n\n";

    echo color_text("Status meanings:", 'yellow') . "\n";
    echo "  • queued    - Message queued for sending\n";
    echo "  • sent      - Sent to carrier\n";
    echo "  • delivered - Delivered to recipient\n";
    echo "  • failed    - Failed to send\n";

} else {
    echo color_text("❌ FAILED TO SEND SMS", 'red') . "\n";
    echo str_repeat("─", 50) . "\n";
    echo color_text("Error: ", 'red') . $result['error'] . "\n\n";

    echo color_text("Possible issues:", 'yellow') . "\n";
    echo "  • Twilio credentials are incorrect\n";
    echo "  • Phone number is not verified (trial account only)\n";
    echo "  • Twilio account has insufficient balance\n";
    echo "  • From phone number is not active in Twilio\n";
    echo "  • Network connectivity issues\n\n";

    echo "Check Twilio Console for more details:\n";
    echo color_text("https://console.twilio.com/us1/monitor/logs/sms", 'blue') . "\n";

    exit(1);
}

echo "\n";
echo color_text("✅ Test Complete", 'green') . "\n\n";

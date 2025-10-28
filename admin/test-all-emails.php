<?php
// Comprehensive Email Testing Script
// Tests all email functionalities in the CollagenDirect system

declare(strict_types=1);

require_once __DIR__ . '/../api/lib/env.php';
require_once __DIR__ . '/../api/lib/sg_curl.php';
require_once __DIR__ . '/../api/lib/provider_welcome.php';
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');

echo "===================================\n";
echo "EMAIL SYSTEM TESTING\n";
echo "===================================\n\n";

$testEmail = 'parker@collagendirect.health'; // Change this to your test email
$testResults = [];
$errors = [];

// Check environment variables
echo "Step 1: Checking Email Configuration\n";
echo str_repeat("-", 50) . "\n";

$sendgridKey = env('SENDGRID_API_KEY');
$smtpFrom = env('SMTP_FROM', 'no-reply@collagendirect.health');
$smtpFromName = env('SMTP_FROM_NAME', 'CollagenDirect');

if ($sendgridKey) {
    echo "✅ SENDGRID_API_KEY: Configured (" . substr($sendgridKey, 0, 10) . "...)\n";
} else {
    echo "❌ SENDGRID_API_KEY: MISSING\n";
    $errors['config'] = 'SendGrid API key not configured';
}

echo "✅ SMTP_FROM: $smtpFrom\n";
echo "✅ SMTP_FROM_NAME: $smtpFromName\n";

// Check template IDs
$passwordResetTmpl = env('SG_TMPL_PASSWORD_RESET');
$orderConfirmTmpl = env('SG_TMPL_ORDER_CONFIRM');
$accountConfirmTmpl = env('SG_TMPL_ACCOUNT_CONFIRM');

echo "\nSendGrid Templates:\n";
echo "  Password Reset: " . ($passwordResetTmpl ?: 'NOT CONFIGURED') . "\n";
echo "  Order Confirm: " . ($orderConfirmTmpl ?: 'NOT CONFIGURED') . "\n";
echo "  Account Confirm: " . ($accountConfirmTmpl ?: 'NOT CONFIGURED') . "\n";
echo "\n";

if (!$sendgridKey) {
    echo "\n❌ Cannot proceed with tests - SendGrid not configured\n";
    echo "   Configure SENDGRID_API_KEY in .env file\n";
    exit(1);
}

// Test 1: Simple Plain Text Email
echo "\nTest 1: Simple Plain Text Email\n";
echo str_repeat("-", 50) . "\n";
echo "Sending to: $testEmail\n";

try {
    $result = sg_send(
        $testEmail,
        'Test Email #1: Plain Text',
        '<h2>This is a test email</h2><p>If you received this, plain text emails are working!</p>',
        ['text' => 'This is a test email. If you received this, plain text emails are working!']
    );

    if ($result) {
        echo "✅ PASS: Plain text email sent successfully\n";
        $testResults['plain_text'] = 'PASS';
    } else {
        echo "❌ FAIL: Failed to send plain text email\n";
        $testResults['plain_text'] = 'FAIL';
        $errors['plain_text'] = 'SendGrid returned false';
    }
} catch (Throwable $e) {
    echo "❌ FAIL: Exception: " . $e->getMessage() . "\n";
    $testResults['plain_text'] = 'FAIL';
    $errors['plain_text'] = $e->getMessage();
}
echo "\n";

// Test 2: Password Reset Email Template
echo "Test 2: Password Reset Email (SendGrid Template)\n";
echo str_repeat("-", 50) . "\n";

if ($passwordResetTmpl) {
    echo "Template ID: $passwordResetTmpl\n";
    echo "Sending to: $testEmail\n";

    try {
        $result = sg_send(
            ['email' => $testEmail, 'name' => 'Test User'],
            null,
            null,
            [
                'template_id' => $passwordResetTmpl,
                'dynamic_data' => [
                    'first_name' => 'Test',
                    'reset_url' => 'https://collagendirect.health/portal/reset?token=test123456',
                    'support_email' => 'support@collagendirect.health',
                    'year' => date('Y'),
                ],
                'categories' => ['test', 'password_reset']
            ]
        );

        if ($result) {
            echo "✅ PASS: Password reset template email sent\n";
            $testResults['password_reset'] = 'PASS';
        } else {
            echo "❌ FAIL: Failed to send password reset email\n";
            $testResults['password_reset'] = 'FAIL';
            $errors['password_reset'] = 'SendGrid returned false';
        }
    } catch (Throwable $e) {
        echo "❌ FAIL: Exception: " . $e->getMessage() . "\n";
        $testResults['password_reset'] = 'FAIL';
        $errors['password_reset'] = $e->getMessage();
    }
} else {
    echo "⚠️  SKIP: Password reset template not configured\n";
    $testResults['password_reset'] = 'SKIP';
}
echo "\n";

// Test 3: Welcome Email (New Account)
echo "Test 3: New Account Welcome Email\n";
echo str_repeat("-", 50) . "\n";
echo "Sending to: $testEmail\n";

try {
    $result = send_provider_welcome_email(
        $testEmail,
        'Test User',
        'Physician',
        'TestPassword123!'
    );

    if ($result) {
        echo "✅ PASS: Welcome email sent successfully\n";
        $testResults['welcome_email'] = 'PASS';
    } else {
        echo "❌ FAIL: Failed to send welcome email\n";
        $testResults['welcome_email'] = 'FAIL';
        $errors['welcome_email'] = 'Function returned false';
    }
} catch (Throwable $e) {
    echo "❌ FAIL: Exception: " . $e->getMessage() . "\n";
    $testResults['welcome_email'] = 'FAIL';
    $errors['welcome_email'] = $e->getMessage();
}
echo "\n";

// Test 4: Manufacturer Notification (if we have an order)
echo "Test 4: Manufacturer Order Notification\n";
echo str_repeat("-", 50) . "\n";

try {
    // Check if we have any recent orders
    $stmt = $pdo->query("SELECT id FROM orders ORDER BY created_at DESC LIMIT 1");
    $order = $stmt->fetch();

    if ($order) {
        echo "Found order ID: {$order['id']}\n";

        // Check if manufacturer notification function exists
        if (file_exists(__DIR__ . '/../api/lib/order_manufacturer_notification.php')) {
            require_once __DIR__ . '/../api/lib/order_manufacturer_notification.php';

            if (function_exists('notify_manufacturer_of_order')) {
                // Test notification (this will send to actual manufacturer email)
                echo "⚠️  This will send email to actual manufacturer\n";
                echo "   Would you like to proceed? (Skipping for safety)\n";
                echo "ℹ️  SKIP: Skipping actual send to avoid spamming manufacturer\n";
                $testResults['manufacturer_notification'] = 'SKIP';
            } else {
                echo "❌ FAIL: notify_manufacturer_of_order function not found\n";
                $testResults['manufacturer_notification'] = 'FAIL';
                $errors['manufacturer_notification'] = 'Function not found';
            }
        } else {
            echo "❌ FAIL: order_manufacturer_notification.php not found\n";
            $testResults['manufacturer_notification'] = 'FAIL';
            $errors['manufacturer_notification'] = 'File not found';
        }
    } else {
        echo "⚠️  SKIP: No orders in database to test with\n";
        $testResults['manufacturer_notification'] = 'SKIP';
    }
} catch (Throwable $e) {
    echo "❌ FAIL: Exception: " . $e->getMessage() . "\n";
    $testResults['manufacturer_notification'] = 'FAIL';
    $errors['manufacturer_notification'] = $e->getMessage();
}
echo "\n";

// Test 5: Email with BCC
echo "Test 5: Email with BCC\n";
echo str_repeat("-", 50) . "\n";
echo "To: $testEmail\n";
echo "BCC: ops@collagendirect.health (if configured)\n";

try {
    $result = sg_send(
        $testEmail,
        'Test Email #5: BCC Test',
        '<h2>BCC Test</h2><p>This email has a BCC recipient (ops@collagendirect.health)</p>',
        [
            'text' => 'BCC Test',
            'bcc' => [['email' => 'ops@collagendirect.health', 'name' => 'Operations']],
            'categories' => ['test', 'bcc']
        ]
    );

    if ($result) {
        echo "✅ PASS: Email with BCC sent successfully\n";
        $testResults['bcc'] = 'PASS';
    } else {
        echo "❌ FAIL: Failed to send email with BCC\n";
        $testResults['bcc'] = 'FAIL';
        $errors['bcc'] = 'SendGrid returned false';
    }
} catch (Throwable $e) {
    echo "❌ FAIL: Exception: " . $e->getMessage() . "\n";
    $testResults['bcc'] = 'FAIL';
    $errors['bcc'] = $e->getMessage();
}
echo "\n";

// Test 6: Email with Reply-To
echo "Test 6: Email with Reply-To\n";
echo str_repeat("-", 50) . "\n";
echo "To: $testEmail\n";
echo "Reply-To: support@collagendirect.health\n";

try {
    $result = sg_send(
        $testEmail,
        'Test Email #6: Reply-To Test',
        '<h2>Reply-To Test</h2><p>Reply to this email should go to support@collagendirect.health</p>',
        [
            'text' => 'Reply-To Test',
            'reply_to' => ['email' => 'support@collagendirect.health', 'name' => 'CollagenDirect Support'],
            'categories' => ['test', 'reply_to']
        ]
    );

    if ($result) {
        echo "✅ PASS: Email with Reply-To sent successfully\n";
        $testResults['reply_to'] = 'PASS';
    } else {
        echo "❌ FAIL: Failed to send email with Reply-To\n";
        $testResults['reply_to'] = 'FAIL';
        $errors['reply_to'] = 'SendGrid returned false';
    }
} catch (Throwable $e) {
    echo "❌ FAIL: Exception: " . $e->getMessage() . "\n";
    $testResults['reply_to'] = 'FAIL';
    $errors['reply_to'] = $e->getMessage();
}
echo "\n";

// Test 7: Multiple Recipients
echo "Test 7: Email to Multiple Recipients\n";
echo str_repeat("-", 50) . "\n";
echo "To: $testEmail, ops@collagendirect.health\n";

try {
    $result = sg_send(
        [
            ['email' => $testEmail, 'name' => 'Test User'],
            ['email' => 'ops@collagendirect.health', 'name' => 'Operations']
        ],
        'Test Email #7: Multiple Recipients',
        '<h2>Multiple Recipients Test</h2><p>This email was sent to multiple recipients</p>',
        ['text' => 'Multiple Recipients Test']
    );

    if ($result) {
        echo "✅ PASS: Email to multiple recipients sent successfully\n";
        $testResults['multiple_recipients'] = 'PASS';
    } else {
        echo "❌ FAIL: Failed to send to multiple recipients\n";
        $testResults['multiple_recipients'] = 'FAIL';
        $errors['multiple_recipients'] = 'SendGrid returned false';
    }
} catch (Throwable $e) {
    echo "❌ FAIL: Exception: " . $e->getMessage() . "\n";
    $testResults['multiple_recipients'] = 'FAIL';
    $errors['multiple_recipients'] = $e->getMessage();
}
echo "\n";

// Check for patient delivery confirmation system
echo "Test 8: Patient Delivery Confirmation System\n";
echo str_repeat("-", 50) . "\n";

if (file_exists(__DIR__ . '/../api/lib/patient_delivery_notification.php')) {
    echo "✅ File exists: patient_delivery_notification.php\n";
    $testResults['delivery_confirmation'] = 'EXISTS';
} else {
    echo "❌ NOT IMPLEMENTED: patient_delivery_notification.php\n";
    echo "   This is required for insurance compliance\n";
    $testResults['delivery_confirmation'] = 'MISSING';
    $errors['delivery_confirmation'] = 'Not implemented';
}
echo "\n";

// Check for physician status notification system
echo "Test 9: Physician Status Notification System\n";
echo str_repeat("-", 50) . "\n";

if (file_exists(__DIR__ . '/../api/lib/physician_status_notification.php')) {
    echo "✅ File exists: physician_status_notification.php\n";
    $testResults['status_notification'] = 'EXISTS';
} else {
    echo "❌ NOT IMPLEMENTED: physician_status_notification.php\n";
    echo "   This improves physician experience\n";
    $testResults['status_notification'] = 'MISSING';
    $errors['status_notification'] = 'Not implemented';
}
echo "\n";

// Summary
echo "===================================\n";
echo "TEST SUMMARY\n";
echo "===================================\n\n";

$passCount = 0;
$failCount = 0;
$skipCount = 0;
$missingCount = 0;

foreach ($testResults as $testName => $result) {
    $icon = match($result) {
        'PASS' => '✅',
        'FAIL' => '❌',
        'SKIP' => '⚠️ ',
        'MISSING' => '❌',
        'EXISTS' => '✅',
        default => 'ℹ️ '
    };

    echo "$icon " . str_replace('_', ' ', ucwords($testName, '_')) . ": $result\n";

    if ($result === 'PASS' || $result === 'EXISTS') $passCount++;
    elseif ($result === 'FAIL' || $result === 'MISSING') $failCount++;
    elseif ($result === 'SKIP') $skipCount++;

    if (isset($errors[$testName])) {
        echo "   Error: {$errors[$testName]}\n";
    }
}

echo "\nTotal Tests: " . count($testResults) . "\n";
echo "Passed: $passCount\n";
echo "Failed: $failCount\n";
echo "Skipped: $skipCount\n";

if ($failCount === 0) {
    echo "\n🎉 All active email tests passed!\n";
} else {
    echo "\n⚠️  Some tests failed. Review errors above.\n";
}

echo "\n===================================\n";
echo "RECOMMENDATIONS\n";
echo "===================================\n\n";

echo "1. Check your email inbox ($testEmail) for test emails\n";
echo "2. Check spam folder if emails not received\n";
echo "3. Verify SendGrid dashboard for delivery status\n";
echo "4. Implement missing notification systems:\n";
echo "   - Patient delivery confirmation (insurance compliance)\n";
echo "   - Physician status notifications (operational)\n";
echo "\n";

echo "===================================\n";
echo "NEXT STEPS\n";
echo "===================================\n\n";

if ($failCount > 0) {
    echo "❌ Fix failed tests before proceeding\n";
    echo "   Review error messages above\n";
    echo "   Check SendGrid dashboard for details\n";
} else {
    echo "✅ Basic email system is working\n";
    echo "\nTo implement missing features:\n";
    echo "1. Create /api/lib/patient_delivery_notification.php\n";
    echo "2. Create /api/cron/send-delivery-confirmations.php\n";
    echo "3. Create /api/lib/physician_status_notification.php\n";
    echo "4. Create /api/cron/send-physician-status-updates.php\n";
    echo "5. Add cron jobs to render.yaml\n";
}

echo "\n===================================\n";

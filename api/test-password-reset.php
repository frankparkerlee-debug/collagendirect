<?php
/**
 * Password Reset Email Diagnostic
 * Tests the complete flow of sending a password reset email
 */

header('Content-Type: application/json');

require_once __DIR__ . '/lib/email_notifications.php';

// Test email configuration
$apiKey = getenv('SENDGRID_API_KEY');
$testEmail = $_GET['email'] ?? 'frank.parker.lee@gmail.com';

$diagnostics = [
    'sendgrid_key_set' => !empty($apiKey),
    'sendgrid_key_length' => $apiKey ? strlen($apiKey) : 0,
    'function_exists' => function_exists('send_password_reset_email'),
    'sg_curl_send_exists' => function_exists('sg_curl_send'),
    'test_email' => $testEmail
];

// Test sending
if ($apiKey) {
    $resetUrl = 'https://collagendirect.health/portal/reset/?selector=TEST&token=TEST123';

    try {
        $result = send_password_reset_email($testEmail, 'Test User', $resetUrl);
        $diagnostics['send_result'] = $result;
        $diagnostics['send_error'] = $result ? null : 'Function returned false';
    } catch (Throwable $e) {
        $diagnostics['send_result'] = false;
        $diagnostics['send_error'] = $e->getMessage();
    }
} else {
    $diagnostics['send_result'] = false;
    $diagnostics['send_error'] = 'No SendGrid API key configured';
}

// Try direct API call
if ($apiKey) {
    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $testEmail, 'name' => 'Test User']],
                'subject' => 'Password Reset Test'
            ]
        ],
        'from' => ['email' => 'no-reply@collagendirect.health', 'name' => 'CollagenDirect'],
        'content' => [
            ['type' => 'text/plain', 'value' => 'This is a test email from the password reset diagnostic script.']
        ],
        'tracking_settings' => [
            'click_tracking' => ['enable' => false, 'enable_text' => false],
            'open_tracking' => ['enable' => false]
        ]
    ];

    try {
        $directResult = sg_curl_send($apiKey, $data);
        $diagnostics['direct_send'] = $directResult;
    } catch (Throwable $e) {
        $diagnostics['direct_send'] = ['success' => false, 'error' => $e->getMessage()];
    }
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT);

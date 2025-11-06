<?php
/**
 * Simple Twilio Test - Web Interface
 * No authentication required (for initial testing only - remove after testing!)
 */

require_once __DIR__ . '/../api/lib/env.php';
require_once __DIR__ . '/../api/lib/twilio_sms.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Twilio SMS Test</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            padding: 2rem;
        }
        h1 {
            color: #333;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .subtitle {
            color: #666;
            font-size: 0.875rem;
            margin-bottom: 2rem;
        }
        .status-box {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }
        .status-box.error {
            background: #fff5f5;
            border-color: #dc3545;
        }
        .status-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        .status-item:last-child {
            margin-bottom: 0;
        }
        .icon-success { color: #28a745; }
        .icon-error { color: #dc3545; }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }
        input[type="tel"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        input[type="tel"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .btn:active {
            transform: translateY(0);
        }
        .result {
            margin-top: 1.5rem;
            padding: 1rem;
            border-radius: 8px;
            display: none;
        }
        .result.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            display: block;
        }
        .result.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            display: block;
        }
        .help-text {
            font-size: 0.875rem;
            color: #666;
            margin-top: 0.5rem;
        }
        .code {
            background: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.875rem;
        }
        .links {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e0e0e0;
            font-size: 0.875rem;
            color: #666;
        }
        .links a {
            color: #667eea;
            text-decoration: none;
        }
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <span>📱</span>
            Twilio SMS Test
        </h1>
        <p class="subtitle">Test your newly approved Twilio phone number</p>

        <?php
        // Check configuration
        $accountSid = env('TWILIO_ACCOUNT_SID');
        $authToken = env('TWILIO_AUTH_TOKEN');
        $fromPhone = env('TWILIO_PHONE_NUMBER');

        $configOk = !empty($accountSid) && !empty($authToken) && !empty($fromPhone);

        if ($configOk) {
            echo '<div class="status-box">';
            echo '<div class="status-item"><span class="icon-success">✓</span> Twilio Account SID configured</div>';
            echo '<div class="status-item"><span class="icon-success">✓</span> Twilio Auth Token configured</div>';
            echo '<div class="status-item"><span class="icon-success">✓</span> Twilio Phone: ' . htmlspecialchars($fromPhone) . '</div>';
            echo '</div>';
        } else {
            echo '<div class="status-box error">';
            echo '<div class="status-item"><span class="icon-error">✗</span> Twilio credentials not configured</div>';
            echo '<p style="margin-top: 1rem; font-size: 0.875rem;">Please add TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, and TWILIO_PHONE_NUMBER to your environment variables.</p>';
            echo '</div>';
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $configOk) {
            $testPhone = $_POST['phone'] ?? '';

            if (!empty($testPhone)) {
                $normalizedPhone = normalize_phone_number($testPhone);

                if ($normalizedPhone) {
                    $timestamp = date('g:i A');
                    $message = "✅ Test from CollagenDirect\n\n"
                        . "Your Twilio number is working!\n\n"
                        . "Sent at: {$timestamp}";

                    $result = twilio_send_sms($normalizedPhone, $message);

                    if ($result['success']) {
                        echo '<div class="result success">';
                        echo '<strong>✅ Success!</strong><br>';
                        echo 'SMS sent successfully to ' . htmlspecialchars($normalizedPhone) . '<br>';
                        echo '<small>Message SID: ' . htmlspecialchars($result['sid']) . '<br>';
                        echo 'Status: ' . htmlspecialchars($result['status']) . '</small>';
                        echo '</div>';
                    } else {
                        echo '<div class="result error">';
                        echo '<strong>❌ Failed</strong><br>';
                        echo 'Error: ' . htmlspecialchars($result['error']);
                        echo '</div>';
                    }
                } else {
                    echo '<div class="result error">';
                    echo '<strong>❌ Invalid Phone Number</strong><br>';
                    echo 'Please enter a valid phone number.';
                    echo '</div>';
                }
            }
        }
        ?>

        <?php if ($configOk): ?>
        <form method="POST">
            <div class="form-group">
                <label for="phone">Phone Number to Test</label>
                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    placeholder="(555) 123-4567"
                    required
                    value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                >
                <p class="help-text">
                    Enter your phone number in any format: <span class="code">5551234567</span>, <span class="code">+15551234567</span>, or <span class="code">(555) 123-4567</span>
                </p>
            </div>

            <button type="submit" class="btn">
                Send Test SMS
            </button>
        </form>

        <div class="links">
            <p><strong>📊 Monitor Messages:</strong></p>
            <p><a href="https://console.twilio.com/us1/monitor/logs/sms" target="_blank">View SMS logs in Twilio Console →</a></p>
            <p style="margin-top: 1rem;"><strong>⚠️ Security Note:</strong> Delete this file after testing!</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
/**
 * Twilio Webhook Setup Instructions
 * Shows how to configure Twilio to receive MMS photo replies
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Twilio Webhook Setup</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 900px;
            margin: 0 auto;
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
        h2 {
            color: #444;
            font-size: 1.25rem;
            margin: 2rem 0 1rem 0;
            padding-top: 1.5rem;
            border-top: 2px solid #e0e0e0;
        }
        h2:first-of-type {
            margin-top: 1.5rem;
            padding-top: 0;
            border-top: none;
        }
        .subtitle {
            color: #666;
            font-size: 0.875rem;
            margin-bottom: 2rem;
        }
        .step {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }
        .step-number {
            display: inline-block;
            background: #667eea;
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            text-align: center;
            line-height: 28px;
            font-weight: bold;
            margin-right: 0.75rem;
            font-size: 0.875rem;
        }
        .step-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
            color: #333;
        }
        .step-content {
            color: #555;
            line-height: 1.6;
            margin-left: 2.5rem;
        }
        .code-box {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 1rem;
            border-radius: 6px;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 0.9rem;
            margin: 0.75rem 0;
            overflow-x: auto;
            position: relative;
        }
        .copy-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: #667eea;
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            font-size: 0.75rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .copy-btn:hover {
            background: #5568d3;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
            color: #856404;
        }
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 4px;
            color: #155724;
        }
        .link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        .link:hover {
            text-decoration: underline;
        }
        ol {
            margin-left: 2.5rem;
            margin-top: 0.5rem;
        }
        ol li {
            margin-bottom: 0.5rem;
            color: #555;
            line-height: 1.6;
        }
        .screenshot {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            margin: 1rem 0;
            max-width: 100%;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 Twilio MMS Webhook Setup</h1>
        <p class="subtitle">Configure Twilio to receive patient photo replies</p>

        <div class="warning">
            <strong>⚠️ Important:</strong> Without this configuration, patient photo replies via SMS will not be received by the portal.
        </div>

        <h2>Current Configuration Status</h2>

        <?php
        // Check if webhook is configured by looking for recent MMS receipts
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM wound_photos
            WHERE created_at > NOW() - INTERVAL '24 hours'
        ");
        $recentPhotos = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($recentPhotos > 0) {
            echo '<div class="success">✓ Webhook appears to be working - received ' . $recentPhotos . ' photo(s) in last 24 hours</div>';
        } else {
            echo '<div class="warning">⚠️ No photos received in last 24 hours - webhook may not be configured</div>';
        }
        ?>

        <h2>Webhook URL</h2>
        <div class="step">
            <div class="step-title">
                Use this URL in your Twilio configuration:
            </div>
            <div class="code-box">
                <button class="copy-btn" onclick="copyToClipboard('https://collagendirect.health/api/twilio/receive-mms.php')">Copy</button>
                https://collagendirect.health/api/twilio/receive-mms.php
            </div>
        </div>

        <h2>Setup Instructions</h2>

        <div class="step">
            <div class="step-title">
                <span class="step-number">1</span>
                Log in to Twilio Console
            </div>
            <div class="step-content">
                Go to <a href="https://console.twilio.com/" target="_blank" class="link">console.twilio.com</a> and log in with your Twilio account.
            </div>
        </div>

        <div class="step">
            <div class="step-title">
                <span class="step-number">2</span>
                Navigate to Phone Numbers
            </div>
            <div class="step-content">
                <ol>
                    <li>In the left sidebar, click <strong>"Phone Numbers"</strong></li>
                    <li>Click <strong>"Manage"</strong></li>
                    <li>Click <strong>"Active numbers"</strong></li>
                    <li>Click on your number: <strong>+1 (888) 415-6880</strong></li>
                </ol>
            </div>
        </div>

        <div class="step">
            <div class="step-title">
                <span class="step-number">3</span>
                Configure Messaging Webhook
            </div>
            <div class="step-content">
                Scroll down to the <strong>"Messaging Configuration"</strong> section and configure:
                <div style="margin-top: 1rem;">
                    <strong>"A MESSAGE COMES IN"</strong> section:
                    <ul style="list-style: none; margin-left: 0; margin-top: 0.5rem;">
                        <li style="margin-bottom: 0.5rem;">
                            <strong>Webhook URL:</strong>
                            <div class="code-box" style="display: inline-block; padding: 0.5rem; margin-top: 0.25rem;">
                                https://collagendirect.health/api/twilio/receive-mms.php
                            </div>
                        </li>
                        <li style="margin-bottom: 0.5rem;">
                            <strong>HTTP Method:</strong> <code style="background: #f0f0f0; padding: 0.25rem 0.5rem; border-radius: 3px;">POST</code>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="step">
            <div class="step-title">
                <span class="step-number">4</span>
                Save Configuration
            </div>
            <div class="step-content">
                Click the <strong>"Save"</strong> button at the bottom of the page to apply your changes.
            </div>
        </div>

        <div class="step">
            <div class="step-title">
                <span class="step-number">5</span>
                Test the Configuration
            </div>
            <div class="step-content">
                <ol>
                    <li>Use the "Request Photo" button in the portal to send a test SMS to a patient</li>
                    <li>Reply to that SMS with a photo from your phone</li>
                    <li>Check the "Photo Reviews" section in the portal to see if the photo appears</li>
                    <li>If it doesn't appear, check the Twilio logs at <a href="https://console.twilio.com/us1/monitor/logs/sms" target="_blank" class="link">Twilio SMS Logs</a></li>
                </ol>
            </div>
        </div>

        <h2>Troubleshooting</h2>

        <div class="step">
            <div class="step-title">Photos not appearing in portal?</div>
            <div class="step-content">
                <ol>
                    <li>Check Twilio webhook logs: <a href="https://console.twilio.com/us1/monitor/logs/webhooks" target="_blank" class="link">Webhook Error Logs</a></li>
                    <li>Verify the webhook URL is exactly: <code style="background: #f0f0f0; padding: 0.25rem 0.5rem; border-radius: 3px;">https://collagendirect.health/api/twilio/receive-mms.php</code></li>
                    <li>Make sure HTTP method is set to <strong>POST</strong></li>
                    <li>Check if patient's phone number in portal matches the number they're texting from</li>
                </ol>
            </div>
        </div>

        <div class="success" style="margin-top: 2rem;">
            <strong>✓ Quick Test:</strong> Send a photo reply to your last photo request SMS. It should appear in Photo Reviews within seconds.
        </div>

        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e0e0e0; font-size: 0.875rem; color: #666;">
            <strong>Need help?</strong> Check the <a href="https://www.twilio.com/docs/sms/tutorials/how-to-receive-and-reply/php" target="_blank" class="link">Twilio MMS Documentation</a>
        </div>
    </div>

    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                event.target.textContent = 'Copied!';
                setTimeout(() => {
                    event.target.textContent = 'Copy';
                }, 2000);
            });
        }
    </script>
</body>
</html>

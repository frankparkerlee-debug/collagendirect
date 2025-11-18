<?php
/**
 * Email Diagnostic Tool - Debug why emails aren't being delivered
 *
 * This script checks:
 * 1. Environment variable configuration (local .env vs system env vars)
 * 2. SendGrid API key validation
 * 3. Email sending logs from database/error logs
 * 4. Test email sending with detailed error output
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../api/db.php';
require __DIR__ . '/../api/lib/env.php';
require __DIR__ . '/../api/lib/sg_curl.php';
require __DIR__ . '/../api/lib/registration_welcome.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
  <title>Email Diagnostic Tool</title>
  <style>
    body { font-family: monospace; padding: 20px; background: #f5f5f5; }
    .section { background: white; margin: 20px 0; padding: 15px; border-radius: 5px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    h2 { border-bottom: 2px solid #333; padding-bottom: 5px; }
    pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
    .test-form { margin: 20px 0; }
    input[type="email"] { width: 300px; padding: 8px; }
    button { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
    button:hover { background: #0056b3; }
  </style>
</head>
<body>
  <h1>📧 Email Diagnostic Tool</h1>

  <div class="section">
    <h2>1. Environment Configuration</h2>
    <?php
    echo "<h3>Environment Variables Check:</h3>";

    // Check .env file
    $envFilePath = __DIR__ . '/../api/.env';
    $envFileExists = file_exists($envFilePath);
    echo "<p><strong>.env file exists:</strong> " . ($envFileExists ? "✅ Yes ($envFilePath)" : "❌ No") . "</p>";

    if ($envFileExists) {
      $envContents = file_get_contents($envFilePath);
      $hasSendGridKey = strpos($envContents, 'SENDGRID_API_KEY') !== false;
      echo "<p><strong>.env has SENDGRID_API_KEY:</strong> " . ($hasSendGridKey ? "✅ Yes" : "❌ No") . "</p>";
    }

    // Check via env() function
    $apiKeyViaEnv = env('SENDGRID_API_KEY');
    $smtpFrom = env('SMTP_FROM');
    $smtpFromName = env('SMTP_FROM_NAME');

    echo "<h3>Loaded Configuration:</h3>";
    echo "<pre>";
    echo "SENDGRID_API_KEY: " . ($apiKeyViaEnv ? "✅ Set (" . strlen($apiKeyViaEnv) . " chars, prefix: " . substr($apiKeyViaEnv, 0, 7) . "...)" : "❌ NOT SET") . "\n";
    echo "SMTP_FROM:        " . ($smtpFrom ?: "❌ NOT SET") . "\n";
    echo "SMTP_FROM_NAME:   " . ($smtpFromName ?: "❌ NOT SET") . "\n";
    echo "</pre>";

    // Check system environment variables (Render sets these)
    echo "<h3>System Environment Variables:</h3>";
    $systemApiKey = getenv('SENDGRID_API_KEY');
    echo "<pre>";
    echo "getenv('SENDGRID_API_KEY'): " . ($systemApiKey ? "✅ Set (" . strlen($systemApiKey) . " chars)" : "❌ NOT SET") . "\n";
    echo "getenv('SMTP_FROM'):        " . (getenv('SMTP_FROM') ?: "❌ NOT SET") . "\n";
    echo "getenv('SMTP_FROM_NAME'):   " . (getenv('SMTP_FROM_NAME') ?: "❌ NOT SET") . "\n";
    echo "</pre>";

    if (!$apiKeyViaEnv && !$systemApiKey) {
      echo '<p class="error">⚠️ CRITICAL: SENDGRID_API_KEY is not set in either .env file or system environment!</p>';
      echo '<p>For Render deployment, set environment variable: <code>SENDGRID_API_KEY</code></p>';
    }
    ?>
  </div>

  <div class="section">
    <h2>2. SendGrid API Connection Test</h2>
    <?php
    if ($apiKeyViaEnv || $systemApiKey) {
      $testKey = $apiKeyViaEnv ?: $systemApiKey;

      echo "<p>Testing SendGrid API connection with API key...</p>";

      // Test API key validity by calling SendGrid API
      $ch = curl_init('https://api.sendgrid.com/v3/user/profile');
      curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $testKey],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
      ]);

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);
      curl_close($ch);

      if ($error) {
        echo '<p class="error">❌ cURL Error: ' . htmlspecialchars($error) . '</p>';
      } elseif ($httpCode === 200) {
        echo '<p class="success">✅ SendGrid API is reachable and API key is valid!</p>';
        $profile = json_decode($response, true);
        if ($profile) {
          echo '<pre>Account: ' . htmlspecialchars(json_encode($profile, JSON_PRETTY_PRINT)) . '</pre>';
        }
      } elseif ($httpCode === 401) {
        echo '<p class="error">❌ SendGrid API Key is INVALID (401 Unauthorized)</p>';
        echo '<p>Response: ' . htmlspecialchars($response) . '</p>';
      } else {
        echo '<p class="error">❌ SendGrid API returned HTTP ' . $httpCode . '</p>';
        echo '<pre>' . htmlspecialchars($response) . '</pre>';
      }
    } else {
      echo '<p class="error">❌ Cannot test - no API key configured</p>';
    }
    ?>
  </div>

  <div class="section">
    <h2>3. Recent Email Attempts (from error logs)</h2>
    <?php
    // Try to read PHP error log for email-related messages
    $errorLogPath = ini_get('error_log');
    echo "<p><strong>Error log path:</strong> " . ($errorLogPath ?: "php://stderr (default)") . "</p>";

    if ($errorLogPath && file_exists($errorLogPath)) {
      $logLines = file($errorLogPath);
      $emailLogs = array_filter($logLines, function($line) {
        return stripos($line, 'email') !== false ||
               stripos($line, 'sendgrid') !== false ||
               stripos($line, 'registration') !== false;
      });

      if (!empty($emailLogs)) {
        echo "<h3>Last 20 email-related log entries:</h3>";
        echo "<pre>";
        echo htmlspecialchars(implode('', array_slice($emailLogs, -20)));
        echo "</pre>";
      } else {
        echo '<p class="warning">⚠️ No email-related entries found in error log</p>';
      }
    } else {
      echo '<p class="warning">⚠️ Cannot read error log file</p>';
    }

    // Check recent registrations
    try {
      $stmt = $pdo->prepare("
        SELECT email, first_name, last_name, user_type, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 10
      ");
      $stmt->execute();
      $recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

      echo "<h3>Last 10 registrations:</h3>";
      if (!empty($recentUsers)) {
        echo "<pre>";
        foreach ($recentUsers as $user) {
          echo sprintf(
            "%s | %s %s | %s | %s\n",
            $user['created_at'],
            $user['first_name'],
            $user['last_name'],
            $user['email'],
            $user['user_type']
          );
        }
        echo "</pre>";
      } else {
        echo "<p>No recent registrations found</p>";
      }
    } catch (Throwable $e) {
      echo '<p class="error">❌ Database error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    ?>
  </div>

  <div class="section">
    <h2>4. Test Email Sending</h2>

    <?php
    if (!empty($_POST['test_email'])) {
      $testEmail = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);

      if (!$testEmail) {
        echo '<p class="error">❌ Invalid email address</p>';
      } else {
        echo "<h3>Sending test registration welcome email to: " . htmlspecialchars($testEmail) . "</h3>";

        $testData = [
          'email' => $testEmail,
          'firstName' => 'Test',
          'lastName' => 'User',
          'userType' => 'practice_admin',
          'practiceName' => 'Test Practice',
        ];

        try {
          ob_start();
          $result = send_registration_welcome_email($testData);
          $output = ob_get_clean();

          if ($result) {
            echo '<p class="success">✅ Email sent successfully!</p>';
            echo '<p>Check the inbox (and spam folder) for: ' . htmlspecialchars($testEmail) . '</p>';
          } else {
            echo '<p class="error">❌ Email sending failed</p>';
            echo '<p>Check error logs for details</p>';
          }

          if ($output) {
            echo '<pre>Output: ' . htmlspecialchars($output) . '</pre>';
          }

          // Also try to send via sg_curl_send directly
          echo "<h3>Direct SendGrid API Test:</h3>";
          $apiKey = env('SENDGRID_API_KEY') ?: getenv('SENDGRID_API_KEY');
          if ($apiKey) {
            $data = [
              'personalizations' => [
                [
                  'to' => [['email' => $testEmail]],
                  'subject' => 'Test Email from Diagnostic Tool'
                ]
              ],
              'from' => ['email' => 'no-reply@collagendirect.health', 'name' => 'CollagenDirect Test'],
              'content' => [
                ['type' => 'text/plain', 'value' => 'This is a test email from the diagnostic tool. If you receive this, email sending is working correctly.']
              ]
            ];

            $directResult = sg_curl_send($apiKey, $data);

            if ($directResult['success']) {
              echo '<p class="success">✅ Direct SendGrid API call succeeded!</p>';
            } else {
              echo '<p class="error">❌ Direct SendGrid API call failed: ' . htmlspecialchars($directResult['error'] ?? 'Unknown error') . '</p>';
            }
          }

        } catch (Throwable $e) {
          echo '<p class="error">❌ Exception: ' . htmlspecialchars($e->getMessage()) . '</p>';
          echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        }
      }
    }
    ?>

    <div class="test-form">
      <form method="POST">
        <label for="test_email">Enter email address to test:</label><br>
        <input type="email" name="test_email" id="test_email" placeholder="your.email@example.com" required>
        <button type="submit">Send Test Email</button>
      </form>
    </div>
  </div>

  <div class="section">
    <h2>5. Common Issues & Solutions</h2>
    <ul>
      <li><strong>API Key not set:</strong> Set SENDGRID_API_KEY in Render environment variables</li>
      <li><strong>Invalid API Key:</strong> Generate new API key in SendGrid dashboard</li>
      <li><strong>Emails sent but not received:</strong>
        <ul>
          <li>Check spam/junk folder</li>
          <li>Verify sender domain authentication in SendGrid</li>
          <li>Check SendGrid Activity Feed for delivery status</li>
          <li>Ensure sender domain (collagendirect.health) is verified</li>
        </ul>
      </li>
      <li><strong>Template not found:</strong> Verify template IDs in SendGrid dashboard match .env file</li>
    </ul>

    <h3>SendGrid Dashboard Links:</h3>
    <ul>
      <li><a href="https://app.sendgrid.com/settings/api_keys" target="_blank">API Keys</a></li>
      <li><a href="https://app.sendgrid.com/settings/sender_auth" target="_blank">Sender Authentication</a></li>
      <li><a href="https://app.sendgrid.com/email_activity" target="_blank">Email Activity Feed</a></li>
      <li><a href="https://app.sendgrid.com/templates" target="_blank">Dynamic Templates</a></li>
    </ul>
  </div>

  <div class="section">
    <h2>6. Next Steps</h2>
    <ol>
      <li>If SENDGRID_API_KEY is not set, add it to Render environment variables</li>
      <li>Send a test email using the form above</li>
      <li>Check SendGrid Activity Feed to see if email was delivered</li>
      <li>If delivered but not received, check spam folder and domain authentication</li>
      <li>If not delivered, check SendGrid Activity Feed for bounce/error details</li>
    </ol>
  </div>

</body>
</html>

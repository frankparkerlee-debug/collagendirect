<?php
// Test email sending functionality
declare(strict_types=1);

require __DIR__ . '/../api/db.php';
require __DIR__ . '/../api/lib/sg_curl.php';

// Check if logged in
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$userId = (string)$_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  http_response_code(404);
  echo json_encode(['ok' => false, 'error' => 'User not found']);
  exit;
}

$toEmail = $user['email'];
$toName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

echo "<h1>Email Test</h1>";
echo "<p>Testing email send to: <strong>$toEmail</strong> ($toName)</p>";

$subject = "Test Email from CollagenDirect";
$html = "
  <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
    <h2 style='color: #2563eb;'>Test Email</h2>
    <p>Hello $toName,</p>
    <p>This is a test email from the CollagenDirect platform to verify that email sending is working correctly.</p>
    <p>If you received this email, SendGrid is configured properly!</p>
    <hr>
    <p style='font-size: 12px; color: #9ca3af;'>
      Sent at: " . date('Y-m-d H:i:s') . "<br>
      From: " . (getenv('SMTP_FROM') ?: 'no-reply@collagendirect.health') . "
    </p>
  </div>
";

echo "<h3>Email Configuration:</h3>";
echo "<ul>";
echo "<li>SENDGRID_API_KEY: " . (getenv('SENDGRID_API_KEY') ? 'Set (✓)' : 'NOT SET (✗)') . "</li>";
echo "<li>SMTP_FROM: " . (getenv('SMTP_FROM') ?: 'no-reply@collagendirect.health (default)') . "</li>";
echo "<li>SMTP_FROM_NAME: " . (getenv('SMTP_FROM_NAME') ?: 'CollagenDirect (default)') . "</li>";
echo "</ul>";

echo "<h3>Sending test email...</h3>";

try {
  $result = sg_send(
    ['email' => $toEmail, 'name' => $toName],
    $subject,
    $html,
    ['categories' => ['test', 'diagnostic']]
  );

  if ($result) {
    echo "<p style='color: green; font-weight: bold;'>✓ Email sent successfully!</p>";
    echo "<p>Check your inbox at <strong>$toEmail</strong></p>";
  } else {
    echo "<p style='color: red; font-weight: bold;'>✗ Email failed to send (sg_send returned false)</p>";
    echo "<p>Check the error logs for more details.</p>";
  }
} catch (Exception $e) {
  echo "<p style='color: red; font-weight: bold;'>✗ Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='/portal/'>← Back to Portal</a></p>";

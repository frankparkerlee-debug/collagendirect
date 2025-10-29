<?php
/**
 * Portal Screenshot Generator
 * Authenticates and captures portal pages for documentation
 */

require_once __DIR__ . '/../api/lib/db.php';
require_once __DIR__ . '/../api/lib/auth.php';

// Configuration
$BASE_URL = 'https://collagendirect.health';
$CREDENTIALS = [
    'email' => 'parker@senecawest.com',
    'password' => 'Password321'
];

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portal Screenshots - CollagenDirect</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
  <div class="max-w-6xl mx-auto">
    <h1 class="text-3xl font-bold mb-6">Portal Screenshot Capture</h1>

    <?php
    // Step 1: Authenticate and get session
    echo "<div class='bg-white rounded-lg shadow p-6 mb-6'>";
    echo "<h2 class='text-xl font-semibold mb-4'>Step 1: Authentication</h2>";

    // Initialize cURL session for login
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $BASE_URL . '/api/index.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'action' => 'auth.login',
        'email' => $CREDENTIALS['email'],
        'password' => $CREDENTIALS['password']
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, '/tmp/portal_cookies.txt');
    curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/portal_cookies.txt');

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode === 200) {
        echo "<p class='text-green-600'>✓ Successfully authenticated as {$CREDENTIALS['email']}</p>";

        // Extract header and body
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $loginData = json_decode($body, true);

        if (isset($loginData['success']) && $loginData['success']) {
            echo "<p class='text-sm text-gray-600 mt-2'>User: {$loginData['user']['first_name']} {$loginData['user']['last_name']}</p>";
            echo "<p class='text-sm text-gray-600'>Role: {$loginData['user']['role']}</p>";
        }
    } else {
        echo "<p class='text-red-600'>✗ Authentication failed (HTTP {$httpCode})</p>";
        echo "<pre class='text-xs bg-gray-100 p-2 mt-2'>" . htmlspecialchars($response) . "</pre>";
    }

    curl_close($ch);
    echo "</div>";

    // Step 2: Fetch portal pages with session
    $pages = [
        'Dashboard' => '/portal/index.php',
        'Patients List' => '/portal/index.php?page=patients',
        'Orders List' => '/portal/index.php?page=orders',
        'Documents' => '/portal/index.php?page=documents',
    ];

    // Get a patient ID for detail pages
    $pdo = db();
    $patientStmt = $pdo->query("SELECT id, first_name, last_name FROM patients LIMIT 1");
    $samplePatient = $patientStmt->fetch(PDO::FETCH_ASSOC);

    if ($samplePatient) {
        $pages['Patient Detail'] = '/portal/index.php?page=patient-detail&id=' . $samplePatient['id'];
        $pages['Add Order'] = '/portal/index.php?page=order-add&patient_id=' . $samplePatient['id'];
        $pages['Edit Patient'] = '/portal/index.php?page=patient-edit&id=' . $samplePatient['id'];
    }

    echo "<div class='bg-white rounded-lg shadow p-6 mb-6'>";
    echo "<h2 class='text-xl font-semibold mb-4'>Step 2: Capture Portal Pages</h2>";

    foreach ($pages as $pageName => $pageUrl) {
        echo "<div class='border-t pt-4 mt-4 first:border-t-0 first:pt-0 first:mt-0'>";
        echo "<h3 class='font-semibold text-lg mb-2'>{$pageName}</h3>";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $BASE_URL . $pageUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, '/tmp/portal_cookies.txt');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $html) {
            // Create iframe to display the page
            $safeHtml = base64_encode($html);
            echo "<p class='text-green-600 text-sm mb-2'>✓ Page loaded successfully</p>";
            echo "<div class='border rounded-lg overflow-hidden' style='height: 500px;'>";
            echo "<iframe srcdoc='" . htmlspecialchars($html) . "' class='w-full h-full' sandbox='allow-same-origin'></iframe>";
            echo "</div>";

            // Instructions
            echo "<div class='mt-2 bg-blue-50 p-3 rounded text-sm'>";
            echo "<strong>To capture:</strong> Right-click the preview above and use browser DevTools or take a screenshot";
            echo "</div>";
        } else {
            echo "<p class='text-red-600 text-sm'>✗ Failed to load (HTTP {$httpCode})</p>";
        }

        echo "</div>";
    }

    echo "</div>";
    ?>

    <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4">
      <h3 class="font-semibold mb-2">Alternative: Open Portal Directly</h3>
      <p class="text-sm mb-3">The previews above may not render correctly due to iframe restrictions. Instead:</p>
      <ol class="list-decimal ml-6 text-sm space-y-1">
        <li>Log into the portal manually with the credentials</li>
        <li>Use browser DevTools to capture full-page screenshots</li>
        <li>Chrome: F12 → Cmd+Shift+P → "Capture full size screenshot"</li>
      </ol>
      <a href="<?= $BASE_URL ?>/portal/" target="_blank" class="inline-block mt-3 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        Open Portal in New Tab →
      </a>
    </div>

  </div>
</body>
</html>

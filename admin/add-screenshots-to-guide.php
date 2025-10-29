<?php
/**
 * Screenshot Upload & Guide Updater
 * Upload screenshots and automatically update portal-guide.php
 */

require_once __DIR__ . '/../api/db.php';

// Define screenshot directory
$SCREENSHOT_DIR = is_dir('/var/data/uploads')
  ? '/var/data/uploads/portal-screenshots'
  : __DIR__ . '/../assets/screenshots';

// Create directory if it doesn't exist
if (!is_dir($SCREENSHOT_DIR)) {
  mkdir($SCREENSHOT_DIR, 0755, true);
}

// Handle file upload
$uploadMessage = '';
$uploadError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['screenshot'])) {
  $file = $_FILES['screenshot'];
  $filename = $_POST['filename'] ?? basename($file['name']);

  // Sanitize filename
  $filename = preg_replace('/[^a-z0-9\-_\.]/i', '', $filename);

  if ($file['error'] === UPLOAD_ERR_OK) {
    $targetPath = $SCREENSHOT_DIR . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
      $uploadMessage = "✓ Screenshot uploaded successfully: {$filename}";
    } else {
      $uploadError = "✗ Failed to move uploaded file";
    }
  } else {
    $uploadError = "✗ Upload error: " . $file['error'];
  }
}

// Scan for existing screenshots
$existingScreenshots = [];
if (is_dir($SCREENSHOT_DIR)) {
  $files = scandir($SCREENSHOT_DIR);
  foreach ($files as $file) {
    if (preg_match('/\.(png|jpg|jpeg|gif|webp)$/i', $file)) {
      $existingScreenshots[] = [
        'filename' => $file,
        'path' => $SCREENSHOT_DIR . '/' . $file,
        'url' => '/uploads/portal-screenshots/' . $file,
        'size' => filesize($SCREENSHOT_DIR . '/' . $file),
        'modified' => filemtime($SCREENSHOT_DIR . '/' . $file)
      ];
    }
  }
}

// Sort by modified time (newest first)
usort($existingScreenshots, function($a, $b) {
  return $b['modified'] - $a['modified'];
});

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Screenshots to Guide - CollagenDirect</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
  <div class="max-w-6xl mx-auto">
    <div class="mb-6">
      <a href="/admin/" class="text-blue-600 hover:underline">← Back to Admin</a>
    </div>

    <h1 class="text-3xl font-bold mb-6">Portal Guide Screenshot Manager</h1>

    <?php if ($uploadMessage): ?>
      <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
        <p class="text-green-900"><?= htmlspecialchars($uploadMessage) ?></p>
      </div>
    <?php endif; ?>

    <?php if ($uploadError): ?>
      <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
        <p class="text-red-900"><?= htmlspecialchars($uploadError) ?></p>
      </div>
    <?php endif; ?>

    <!-- Upload Form -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
      <h2 class="text-xl font-bold mb-4">Upload New Screenshot</h2>

      <form method="post" enctype="multipart/form-data" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Select Screenshot</label>
          <input type="file" name="screenshot" accept="image/*" required
                 class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none p-2">
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Filename (optional - will use original if not specified)</label>
          <select name="filename" class="block w-full p-2 border border-gray-300 rounded-lg">
            <option value="">Use original filename</option>
            <option value="dashboard.png">dashboard.png - Dashboard Overview</option>
            <option value="icd10-autocomplete.png">icd10-autocomplete.png - ICD-10 Autocomplete ⭐</option>
            <option value="patients-list.png">patients-list.png - Patient List</option>
            <option value="patient-detail.png">patient-detail.png - Patient Detail Page</option>
            <option value="patient-add.png">patient-add.png - Add Patient Form</option>
            <option value="order-create.png">order-create.png - Create Order Form</option>
            <option value="orders-list.png">orders-list.png - Orders List</option>
            <option value="documents.png">documents.png - Documents Page</option>
            <option value="mobile-view.png">mobile-view.png - Mobile View</option>
          </select>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
          Upload Screenshot
        </button>
      </form>

      <div class="mt-4 bg-yellow-50 border-l-4 border-yellow-500 p-4">
        <p class="text-sm text-yellow-900">
          <strong>Tip:</strong> Use Chrome DevTools (Cmd+Shift+P → "Capture full size screenshot") for best results
        </p>
      </div>
    </div>

    <!-- Existing Screenshots -->
    <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
      <h2 class="text-xl font-bold mb-4">Uploaded Screenshots (<?= count($existingScreenshots) ?>)</h2>

      <?php if (empty($existingScreenshots)): ?>
        <p class="text-gray-600 italic">No screenshots uploaded yet</p>
      <?php else: ?>
        <div class="grid md:grid-cols-2 gap-6">
          <?php foreach ($existingScreenshots as $ss): ?>
            <div class="border rounded-lg overflow-hidden">
              <div class="bg-gray-100 p-4">
                <img src="<?= htmlspecialchars($ss['url']) ?>" alt="<?= htmlspecialchars($ss['filename']) ?>" class="w-full h-auto rounded">
              </div>
              <div class="p-4">
                <p class="font-semibold text-sm mb-1"><?= htmlspecialchars($ss['filename']) ?></p>
                <p class="text-xs text-gray-600">
                  <?= number_format($ss['size'] / 1024, 1) ?> KB |
                  <?= date('M j, Y g:i A', $ss['modified']) ?>
                </p>
                <div class="mt-2">
                  <code class="text-xs bg-gray-100 px-2 py-1 rounded block overflow-x-auto">
                    &lt;img src="<?= htmlspecialchars($ss['url']) ?>" alt="..." class="w-full rounded-lg shadow-md"&gt;
                  </code>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Integration Guide -->
    <div class="bg-white rounded-lg shadow-lg p-6">
      <h2 class="text-xl font-bold mb-4">How to Add Screenshots to Guide</h2>

      <div class="prose max-w-none">
        <p class="mb-4">Once screenshots are uploaded, add them to <code class="bg-gray-100 px-2 py-1 rounded">/portal-guide.php</code>:</p>

        <ol class="list-decimal ml-6 space-y-3 text-sm">
          <li>
            <strong>Find the screenshot placeholder:</strong>
            <pre class="bg-gray-100 p-3 rounded mt-2 overflow-x-auto text-xs"><code>&lt;div class="screenshot-placeholder rounded-lg mb-6"&gt;
  Dashboard Screenshot - Revenue Analytics, Recent Patients
&lt;/div&gt;</code></pre>
          </li>

          <li>
            <strong>Replace with actual image:</strong>
            <pre class="bg-gray-100 p-3 rounded mt-2 overflow-x-auto text-xs"><code>&lt;div class="mb-6"&gt;
  &lt;img src="/uploads/portal-screenshots/dashboard.png"
       alt="Portal Dashboard showing revenue analytics and patient metrics"
       class="w-full rounded-lg shadow-md border"&gt;
&lt;/div&gt;</code></pre>
          </li>

          <li>
            <strong>For the ICD-10 screenshot (most important):</strong>
            <pre class="bg-gray-100 p-3 rounded mt-2 overflow-x-auto text-xs"><code>&lt;div class="bg-yellow-50 border-2 border-yellow-300 rounded-lg p-4 mb-6"&gt;
  &lt;p class="text-sm font-semibold text-yellow-900 mb-3"&gt;
    ⭐ NEW FEATURE: Watch the autocomplete in action
  &lt;/p&gt;
  &lt;img src="/uploads/portal-screenshots/icd10-autocomplete.png"
       alt="ICD-10 autocomplete showing search results for wound"
       class="w-full rounded-lg shadow-md border"&gt;
&lt;/div&gt;</code></pre>
          </li>
        </ol>

        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mt-6">
          <p class="text-sm text-blue-900">
            <strong>Pro Tip:</strong> Always include descriptive alt text for accessibility and SEO
          </p>
        </div>
      </div>
    </div>

    <div class="mt-8 flex gap-4">
      <a href="/portal-guide.php" target="_blank" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition">
        View Portal Guide
      </a>
      <a href="capture-screenshots.php" class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition">
        Screenshot Capture Instructions
      </a>
    </div>

  </div>
</body>
</html>

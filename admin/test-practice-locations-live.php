<?php
/**
 * Test what version of practice-locations.php is actually being served
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_admin();

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Practice Locations Version Test</h1>";
echo "<p>This will fetch the actual live page and check what version is being served.</p>";

// Fetch the actual page content
$url = "https://collagendirect.health/portal/practice-locations.php";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$content = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h2>HTTP Status: $httpCode</h2>";

if ($content) {
  // Check for inline editing
  $hasInlineEditing = (strpos($content, 'id="add-row"') !== false);

  // Check for modals
  $hasModals = (strpos($content, 'class="modal"') !== false ||
                strpos($content, 'openAddModal') !== false ||
                strpos($content, 'id="addLocationModal"') !== false);

  echo "<h2>Version Analysis:</h2>";
  echo "<ul style='font-size: 1.2em; line-height: 2;'>";

  if ($hasInlineEditing && !$hasModals) {
    echo "<li style='color: green;'><strong>✓ CORRECT VERSION:</strong> Inline editing, no modals</li>";
    echo "<li><strong>This means Render HAS deployed the new code correctly.</strong></li>";
    echo "<li style='color: red;'><strong>The issue is BROWSER CACHE on your end.</strong></li>";
    echo "<li><strong>Solution:</strong> Hard refresh (Cmd+Shift+R on Mac, Ctrl+Shift+R on Windows)</li>";
    echo "<li><strong>Or:</strong> Clear browser cache and cookies for collagendirect.health</li>";
  } elseif ($hasModals) {
    echo "<li style='color: red;'><strong>✗ OLD VERSION:</strong> Still has modal code</li>";
    echo "<li><strong>This means Render has NOT deployed 0b7b5cc correctly.</strong></li>";
    echo "<li><strong>Check Render dashboard Events tab to verify deployment status.</strong></li>";
  } else {
    echo "<li style='color: orange;'><strong>? UNKNOWN:</strong> Neither inline editing nor modals found</li>";
    echo "<li>The page may have errored or is showing a different view</li>";
  }

  echo "</ul>";

  echo "<h2>Code Snippets Found:</h2>";

  // Show snippets
  if (preg_match('/id="add-row"/', $content)) {
    echo "<p><strong>✓ Found:</strong> <code>id=\"add-row\"</code> (inline editing)</p>";
  }

  if (preg_match('/class="modal"/', $content)) {
    echo "<p><strong>✗ Found:</strong> <code>class=\"modal\"</code> (modal dialogs)</p>";
  }

  if (preg_match('/openAddModal/', $content)) {
    echo "<p><strong>✗ Found:</strong> <code>openAddModal</code> function (modal dialogs)</p>";
  }

  if (preg_match('/id="addLocationModal"/', $content)) {
    echo "<p><strong>✗ Found:</strong> <code>id=\"addLocationModal\"</code> (modal dialogs)</p>";
  }

  // Check for table existence warning
  if (strpos($content, 'Database Setup Required') !== false) {
    echo "<p style='color: orange;'><strong>Note:</strong> Page is showing 'Database Setup Required' message (table doesn't exist yet)</p>";
  }

} else {
  echo "<p style='color: red;'>Failed to fetch page content</p>";
}

echo "<hr>";
echo "<h2>Local File Check:</h2>";
$localFile = __DIR__ . '/../portal/practice-locations.php';
$localContent = file_get_contents($localFile);

$hasInlineLocal = (strpos($localContent, 'id="add-row"') !== false);
$hasModalsLocal = (strpos($localContent, 'class="modal"') !== false);

echo "<p><strong>Local file has inline editing:</strong> " . ($hasInlineLocal ? '✓ Yes' : '✗ No') . "</p>";
echo "<p><strong>Local file has modals:</strong> " . ($hasModalsLocal ? '✗ Yes' : '✓ No') . "</p>";

if ($hasInlineLocal && !$hasModalsLocal) {
  echo "<p style='color: green; font-weight: bold;'>Local file is CORRECT (inline editing, no modals)</p>";
} else {
  echo "<p style='color: red; font-weight: bold;'>Local file still has OLD code</p>";
}

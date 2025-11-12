<?php
/**
 * ONE-TIME FIX: Update Google Maps API script to use async loading and proper callback
 */

$portalFile = __DIR__ . '/../portal/index.php';

if (!file_exists($portalFile)) {
    die(json_encode(['ok' => false, 'error' => 'Portal file not found']));
}

$content = file_get_contents($portalFile);

// Find the old Google Maps script tag
$oldPattern = '<script src="https://maps.googleapis.com/maps/api/js?key=' . getenv('GOOGLE_PLACES_API_KEY') . '&libraries=places&callback=Function.prototype" defer></script>';

if (strpos($content, $oldPattern) === false) {
    die(json_encode(['ok' => false, 'error' => 'Old script tag not found', 'pattern' => $oldPattern]));
}

// Replace with async version and proper callback
$apiKey = getenv('GOOGLE_PLACES_API_KEY');
$newScript = <<<HTML
<script>
// Initialize Google Maps callback
window.initGoogleMaps = function() {
  console.log('Google Maps API loaded, initializing address fields...');
  if (typeof initAddressFields === 'function') {
    initAddressFields();
  }
};
</script>
<script src="https://maps.googleapis.com/maps/api/js?key={$apiKey}&libraries=places&callback=initGoogleMaps" async defer></script>
HTML;

$newContent = str_replace($oldPattern, $newScript, $content);

if ($newContent === $content) {
    die(json_encode(['ok' => false, 'error' => 'No replacement made']));
}

if (file_put_contents($portalFile, $newContent) === false) {
    die(json_encode(['ok' => false, 'error' => 'Failed to write file']));
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'message' => 'Google Maps API script updated with proper async loading',
    'file_size' => filesize($portalFile)
]);

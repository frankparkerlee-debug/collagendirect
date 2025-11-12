<?php
/**
 * ONE-TIME SCRIPT: Inject Google Maps Places API into portal/index.php
 * This is required for address autocomplete to work
 */

$portalFile = __DIR__ . '/../portal/index.php';
$apiKey = getenv('GOOGLE_PLACES_API_KEY');

if (!$apiKey) {
    die(json_encode(['ok' => false, 'error' => 'Google Places API key not set']));
}

if (!file_exists($portalFile)) {
    die(json_encode(['ok' => false, 'error' => 'Portal file not found']));
}

$content = file_get_contents($portalFile);

// Check if already injected
if (strpos($content, 'maps.googleapis.com/maps/api/js') !== false) {
    die(json_encode(['ok' => true, 'message' => 'Google Maps API already injected', 'already_present' => true]));
}

$googleMapsScript = '<script src="https://maps.googleapis.com/maps/api/js?key=' . $apiKey . '&libraries=places&callback=Function.prototype" defer></script>';

// Find the search-helpers.js script and add Google Maps API before it
$searchPattern = '<script src="portal/search-helpers.js"></script>';

if (strpos($content, $searchPattern) === false) {
    // Try alternative: add before </head>
    $searchPattern = '</head>';
    $replacement = $googleMapsScript . "\n" . $searchPattern;
} else {
    $replacement = $googleMapsScript . "\n" . $searchPattern;
}

if (strpos($content, $searchPattern) === false) {
    die(json_encode(['ok' => false, 'error' => 'Could not find insertion point']));
}

$newContent = str_replace($searchPattern, $replacement, $content);

if (file_put_contents($portalFile, $newContent) === false) {
    die(json_encode(['ok' => false, 'error' => 'Failed to write file']));
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'message' => 'Google Maps API injected successfully',
    'script' => $googleMapsScript,
    'file_size' => filesize($portalFile)
]);

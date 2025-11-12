<?php
/**
 * ONE-TIME FIX: Remove incorrect script tag and add correct one
 * The injection added portal/address-autocomplete-init.js but it should be address-autocomplete-init.js
 */

$portalFile = __DIR__ . '/../portal/index.php';

if (!file_exists($portalFile)) {
    die(json_encode(['ok' => false, 'error' => 'Portal file not found']));
}

$content = file_get_contents($portalFile);

// Remove the incorrect script tag (with portal/ prefix)
$oldScriptTag = '<script src="portal/address-autocomplete-init.js"></script>';
if (strpos($content, $oldScriptTag) !== false) {
    $content = str_replace($oldScriptTag, '', $content);
    error_log('Removed old script tag with incorrect path');
}

// Check if correct script tag already exists
$correctScriptTag = '<script src="address-autocomplete-init.js"></script>';
if (strpos($content, $correctScriptTag) !== false) {
    die(json_encode(['ok' => true, 'message' => 'Correct script already present', 'already_fixed' => true]));
}

// Add correct script tag before </head>
$searchPattern = '</head>';
$replacement = $correctScriptTag . "\n" . $searchPattern;

if (strpos($content, $searchPattern) === false) {
    die(json_encode(['ok' => false, 'error' => '</head> tag not found in portal file']));
}

$newContent = str_replace($searchPattern, $replacement, $content);

if (file_put_contents($portalFile, $newContent) === false) {
    die(json_encode(['ok' => false, 'error' => 'Failed to write file']));
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'message' => 'Script path fixed successfully',
    'old_tag' => $oldScriptTag,
    'new_tag' => $correctScriptTag,
    'file_size' => filesize($portalFile)
]);

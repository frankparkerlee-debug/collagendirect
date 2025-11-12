<?php
/**
 * ONE-TIME SCRIPT: Inject address autocomplete script into portal/index.php
 * This bypasses Git blob corruption issue
 */

$portalFile = __DIR__ . '/../portal/index.php';
$scriptTag = '<script src="portal/address-autocomplete-init.js"></script>';

if (!file_exists($portalFile)) {
    die(json_encode(['ok' => false, 'error' => 'Portal file not found']));
}

$content = file_get_contents($portalFile);

// Check if already injected
if (strpos($content, 'address-autocomplete-init.js') !== false) {
    die(json_encode(['ok' => true, 'message' => 'Script already injected', 'already_present' => true]));
}

// Find the </head> tag and add script before it
$searchPattern = '</head>';
$replacement = $scriptTag . "\n" . $searchPattern;

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
    'message' => 'Script tag injected successfully',
    'script_tag' => $scriptTag,
    'file_size' => filesize($portalFile)
]);

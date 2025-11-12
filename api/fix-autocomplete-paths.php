<?php
/**
 * ONE-TIME FIX: Fix autocomplete script paths in portal/index.php
 * Changes portal/search-helpers.js to search-helpers.js
 */

$portalFile = __DIR__ . '/../portal/index.php';

if (!file_exists($portalFile)) {
    die(json_encode(['ok' => false, 'error' => 'Portal file not found']));
}

$content = file_get_contents($portalFile);

// Fix the incorrect path
$oldTag = '<script src="portal/search-helpers.js"></script>';
$newTag = '<script src="search-helpers.js"></script>';

if (strpos($content, $oldTag) === false) {
    // Check if already fixed
    if (strpos($content, $newTag) !== false) {
        die(json_encode(['ok' => true, 'message' => 'Paths already fixed', 'already_fixed' => true]));
    }
    die(json_encode(['ok' => false, 'error' => 'Old script tag not found', 'looking_for' => $oldTag]));
}

$newContent = str_replace($oldTag, $newTag, $content);

if (file_put_contents($portalFile, $newContent) === false) {
    die(json_encode(['ok' => false, 'error' => 'Failed to write file']));
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'message' => 'Autocomplete script paths fixed',
    'changed' => 'portal/search-helpers.js → search-helpers.js',
    'file_size' => filesize($portalFile)
]);

<?php
/**
 * ONE-TIME SCRIPT: Inject address autocomplete scripts into portal/index.php
 */

$portalFile = __DIR__ . '/../portal/index.php';

if (!file_exists($portalFile)) {
    die(json_encode(['ok' => false, 'error' => 'Portal file not found']));
}

$content = file_get_contents($portalFile);

// Check if already injected
if (strpos($content, 'search-helpers.js') !== false) {
    die(json_encode(['ok' => true, 'message' => 'Autocomplete scripts already injected', 'already_present' => true]));
}

// Script tags to inject (before </head>)
$scriptTags = <<<HTML
<script src="portal/search-helpers.js"></script>
<script src="address-autocomplete-init.js"></script>
HTML;

// Find </head> and inject before it
$searchPattern = '</head>';

if (strpos($content, $searchPattern) === false) {
    die(json_encode(['ok' => false, 'error' => 'Could not find </head> tag']));
}

$newContent = str_replace($searchPattern, $scriptTags . "\n" . $searchPattern, $content);

if (file_put_contents($portalFile, $newContent) === false) {
    die(json_encode(['ok' => false, 'error' => 'Failed to write file']));
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'message' => 'Address autocomplete scripts injected successfully',
    'scripts' => [
        'portal/search-helpers.js',
        'address-autocomplete-init.js'
    ],
    'file_size' => filesize($portalFile)
]);

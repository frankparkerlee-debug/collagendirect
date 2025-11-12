<?php
/**
 * Debug script to check what's actually in portal/index.php
 */

$portalFile = __DIR__ . '/../portal/index.php';

if (!file_exists($portalFile)) {
    die(json_encode(['ok' => false, 'error' => 'Portal file not found']));
}

$content = file_get_contents($portalFile);

// Check for various script patterns
$checks = [
    'search-helpers.js found' => strpos($content, 'search-helpers.js') !== false,
    'address-autocomplete-init.js found' => strpos($content, 'address-autocomplete-init.js') !== false,
    'initAddressAutocomplete function found' => strpos($content, 'function initAddressAutocomplete') !== false,
    'np-address field found' => strpos($content, 'id="np-address"') !== false,
    'np-address initialization found' => strpos($content, "getElementById('np-address')") !== false,
];

// Find the actual script tags
$scriptMatches = [];
if (preg_match_all('/<script[^>]*src=["\']([^"\']*(?:search-helpers|address-autocomplete)[^"\']*)["\']/i', $content, $matches)) {
    $scriptMatches = $matches[1];
}

// Get a snippet around search-helpers if found
$searchHelpersContext = '';
if (strpos($content, 'search-helpers.js') !== false) {
    $pos = strpos($content, 'search-helpers.js');
    $start = max(0, $pos - 100);
    $searchHelpersContext = substr($content, $start, 250);
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'checks' => $checks,
    'script_tags_found' => $scriptMatches,
    'search_helpers_context' => $searchHelpersContext,
    'file_size' => filesize($portalFile),
    'file_modified' => date('Y-m-d H:i:s', filemtime($portalFile))
], JSON_PRETTY_PRINT);

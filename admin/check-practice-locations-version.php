<?php
/**
 * Diagnostic: Check which version of practice-locations.php is deployed
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

require_admin();

header('Content-Type: text/plain');

echo "=== PRACTICE LOCATIONS VERSION CHECK ===\n\n";

$portalFile = __DIR__ . '/../portal/practice-locations.php';

if (!file_exists($portalFile)) {
  echo "ERROR: File not found at $portalFile\n";
  exit;
}

echo "File Path: $portalFile\n";
echo "File Size: " . filesize($portalFile) . " bytes\n";
echo "Last Modified: " . date('Y-m-d H:i:s', filemtime($portalFile)) . "\n\n";

// Read file and check for key indicators
$content = file_get_contents($portalFile);

echo "=== VERSION INDICATORS ===\n\n";

// Check for inline editing
if (strpos($content, 'id="add-row"') !== false) {
  echo "✓ INLINE EDITING: id=\"add-row\" found (NEW VERSION)\n";
} else {
  echo "✗ INLINE EDITING: id=\"add-row\" NOT found (OLD VERSION)\n";
}

// Check for modals
if (strpos($content, 'class="modal"') !== false || strpos($content, 'openAddModal') !== false) {
  echo "✗ MODALS: Modal code found (OLD VERSION)\n";
} else {
  echo "✓ MODALS: No modal code found (NEW VERSION)\n";
}

// Check for table existence check
if (strpos($content, 'practice_locations') !== false && strpos($content, 'information_schema.tables') !== false) {
  echo "✓ TABLE CHECK: Table existence check found (NEW VERSION)\n";
} else {
  echo "✗ TABLE CHECK: No table existence check (OLD VERSION)\n";
}

// Check for Google Places API
if (strpos($content, 'google.maps.places.Autocomplete') !== false) {
  echo "✓ GOOGLE PLACES: Autocomplete code found (NEW VERSION)\n";
} else {
  echo "✗ GOOGLE PLACES: No autocomplete code (OLD VERSION)\n";
}

echo "\n=== FILE HEADER ===\n\n";
echo substr($content, 0, 500) . "\n...\n";

echo "\n=== DEPLOYMENT RECOMMENDATION ===\n\n";

$hasInlineEditing = strpos($content, 'id="add-row"') !== false;
$hasModals = strpos($content, 'class="modal"') !== false || strpos($content, 'openAddModal') !== false;

if ($hasInlineEditing && !$hasModals) {
  echo "✓ File contains CORRECT version (inline editing, no modals)\n";
  echo "  If users still see modals, this is a BROWSER CACHE issue.\n";
  echo "  Ask users to hard refresh: Cmd+Shift+R (Mac) or Ctrl+Shift+R (Windows)\n";
} elseif ($hasModals) {
  echo "✗ File contains OLD version (has modals)\n";
  echo "  Render may not have deployed commit 0b7b5cc correctly.\n";
  echo "  Check Render Events tab to verify deployment status.\n";
} else {
  echo "? File version unclear - needs manual inspection\n";
}

<?php
/**
 * ONE-TIME SCRIPT: Embed initAddressAutocomplete function directly into portal/index.php
 * This allows it to be committed to Git and survive deployments
 */

$portalFile = __DIR__ . '/index.php';

if (!file_exists($portalFile)) {
    die("ERROR: Portal file not found\n");
}

$content = file_get_contents($portalFile);

// Check if already embedded
if (strpos($content, 'function initAddressAutocomplete(') !== false) {
    die("SUCCESS: initAddressAutocomplete function already embedded\n");
}

// The function to embed
$functionCode = <<<'JAVASCRIPT'

/* ===== ADDRESS AUTOCOMPLETE FUNCTION ===== */
function initAddressAutocomplete(inputId, onSelect, options = {}) {
  const input = document.getElementById(inputId);
  if (!input) {
    console.error(`Address input #${inputId} not found`);
    return;
  }

  const minChars = options.minChars || 3;
  const debounceMs = options.debounceMs || 300;
  const containerClass = options.containerClass || 'address-autocomplete-container';
  const suggestionClass = options.suggestionClass || 'address-suggestion';

  let debounceTimer;
  let suggestionsDiv;

  function createSuggestionsContainer() {
    if (suggestionsDiv) return suggestionsDiv;
    suggestionsDiv = document.createElement('div');
    suggestionsDiv.className = containerClass;
    suggestionsDiv.style.cssText = `position: absolute; z-index: 9999; background: white; border: 1px solid #e2e8f0; border-radius: 6px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-height: 300px; overflow-y: auto; display: none;`;
    const rect = input.getBoundingClientRect();
    suggestionsDiv.style.top = (rect.bottom + window.scrollY) + 'px';
    suggestionsDiv.style.left = rect.left + 'px';
    suggestionsDiv.style.width = rect.width + 'px';
    document.body.appendChild(suggestionsDiv);
    return suggestionsDiv;
  }

  async function fetchSuggestions(query) {
    try {
      const response = await fetch(`/api/portal/address-search.php?query=${encodeURIComponent(query)}`);
      const data = await response.json();
      if (!data.ok) { console.error('Address search error:', data.error); return []; }
      return data.suggestions || [];
    } catch (error) { console.error('Address search failed:', error); return []; }
  }

  async function fetchAddressDetails(placeId) {
    try {
      const response = await fetch(`/api/portal/address-details.php?place_id=${encodeURIComponent(placeId)}`);
      const data = await response.json();
      if (!data.ok) { console.error('Address details error:', data.error); return null; }
      return data.address;
    } catch (error) { console.error('Address details fetch failed:', error); return null; }
  }

  function showSuggestions(suggestions) {
    const container = createSuggestionsContainer();
    container.innerHTML = '';
    if (suggestions.length === 0) { container.style.display = 'none'; return; }
    suggestions.forEach(suggestion => {
      const div = document.createElement('div');
      div.className = suggestionClass;
      div.style.cssText = `padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #f1f5f9;`;
      div.innerHTML = `<div style="font-weight: 500; color: #1e293b;">${suggestion.main_text}</div><div style="font-size: 0.875rem; color: #64748b;">${suggestion.secondary_text}</div>`;
      div.addEventListener('mouseenter', () => { div.style.background = '#f8fafc'; });
      div.addEventListener('mouseleave', () => { div.style.background = 'white'; });
      div.addEventListener('click', async () => {
        input.value = suggestion.description;
        container.style.display = 'none';
        const address = await fetchAddressDetails(suggestion.place_id);
        if (address && onSelect) { onSelect(address); }
      });
      container.appendChild(div);
    });
    container.style.display = 'block';
  }

  function hideSuggestions() {
    if (suggestionsDiv) { suggestionsDiv.style.display = 'none'; }
  }

  input.addEventListener('input', (e) => {
    const query = e.target.value.trim();
    clearTimeout(debounceTimer);
    if (query.length < minChars) { hideSuggestions(); return; }
    debounceTimer = setTimeout(async () => {
      const suggestions = await fetchSuggestions(query);
      showSuggestions(suggestions);
    }, debounceMs);
  });

  document.addEventListener('click', (e) => {
    if (e.target !== input && suggestionsDiv && !suggestionsDiv.contains(e.target)) {
      hideSuggestions();
    }
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { hideSuggestions(); }
  });
}
/* ===== END ADDRESS AUTOCOMPLETE FUNCTION ===== */

JAVASCRIPT;

// Find the DOMContentLoaded section and inject before it
$searchPattern = "/* Dropdown functionality */\ndocument.addEventListener('DOMContentLoaded', () => {";

if (strpos($content, $searchPattern) === false) {
    die("ERROR: Could not find DOMContentLoaded section\n");
}

$newContent = str_replace($searchPattern, $functionCode . "\n" . $searchPattern, $content);

if ($newContent === $content) {
    die("ERROR: No replacement made\n");
}

if (file_put_contents($portalFile, $newContent) === false) {
    die("ERROR: Failed to write file\n");
}

echo "SUCCESS: initAddressAutocomplete function embedded into portal/index.php\n";
echo "File size: " . filesize($portalFile) . " bytes\n";
echo "You can now commit this change to Git\n";

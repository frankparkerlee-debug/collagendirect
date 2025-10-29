/**
 * ICD-10 Autocomplete Component
 * Integrates with NIH/NLM ICD-10-CM API via local proxy endpoint
 *
 * Usage:
 * <input type="text" class="icd10-autocomplete" placeholder="Type to search ICD-10 codes...">
 * <script src="/assets/icd10-autocomplete.js"></script>
 * <script>initICD10Autocomplete();</script>
 */

(function() {
  'use strict';

  const ICD10_API_ENDPOINT = '/api/icd10_search.php';
  const MIN_SEARCH_LENGTH = 2;
  const DEBOUNCE_MS = 300;
  const MAX_RESULTS = 15;

  /**
   * Initialize autocomplete on all elements with class 'icd10-autocomplete'
   */
  window.initICD10Autocomplete = function() {
    const inputs = document.querySelectorAll('.icd10-autocomplete');
    inputs.forEach(input => {
      if (input._icd10AutocompleteInitialized) return;
      input._icd10AutocompleteInitialized = true;
      attachAutocomplete(input);
    });
  };

  /**
   * Attach autocomplete functionality to a specific input element
   * @param {HTMLInputElement} input
   */
  function attachAutocomplete(input) {
    let debounceTimer = null;
    let currentResults = [];
    let selectedIndex = -1;

    // Create dropdown container
    const dropdown = document.createElement('div');
    dropdown.className = 'icd10-dropdown';
    dropdown.style.cssText = `
      position: absolute;
      z-index: 1000;
      background: white;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.1);
      max-height: 320px;
      overflow-y: auto;
      display: none;
      min-width: 400px;
      margin-top: 4px;
    `;

    // Position dropdown relative to input
    input.style.position = 'relative';
    const parent = input.parentElement;
    if (parent && window.getComputedStyle(parent).position === 'static') {
      parent.style.position = 'relative';
    }
    parent.appendChild(dropdown);

    // Search handler with debounce
    input.addEventListener('input', (e) => {
      const query = e.target.value.trim();

      clearTimeout(debounceTimer);

      if (query.length < MIN_SEARCH_LENGTH) {
        hideDropdown();
        return;
      }

      // Show loading state
      showLoading();

      debounceTimer = setTimeout(() => {
        searchICD10(query);
      }, DEBOUNCE_MS);
    });

    // Keyboard navigation
    input.addEventListener('keydown', (e) => {
      if (!dropdown.style.display || dropdown.style.display === 'none') return;

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        selectedIndex = Math.min(selectedIndex + 1, currentResults.length - 1);
        updateSelection();
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        selectedIndex = Math.max(selectedIndex - 1, -1);
        updateSelection();
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (selectedIndex >= 0 && currentResults[selectedIndex]) {
          selectResult(currentResults[selectedIndex]);
        }
      } else if (e.key === 'Escape') {
        hideDropdown();
      }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!input.contains(e.target) && !dropdown.contains(e.target)) {
        hideDropdown();
      }
    });

    /**
     * Search ICD-10 codes via API
     */
    async function searchICD10(query) {
      try {
        const url = `${ICD10_API_ENDPOINT}?term=${encodeURIComponent(query)}&max=${MAX_RESULTS}`;
        const response = await fetch(url, {
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        if (data.success && data.results) {
          currentResults = data.results;
          selectedIndex = -1;
          showResults(data.results);
        } else {
          showError(data.error || 'No results found');
        }
      } catch (error) {
        console.error('[ICD10 Autocomplete] Search error:', error);
        showError('Search failed. Please try again.');
      }
    }

    /**
     * Display search results in dropdown
     */
    function showResults(results) {
      if (!results || results.length === 0) {
        dropdown.innerHTML = '<div style="padding: 12px; text-align: center; color: #6b7280; font-size: 14px;">No results found</div>';
        dropdown.style.display = 'block';
        return;
      }

      dropdown.innerHTML = '';

      results.forEach((result, index) => {
        const item = document.createElement('div');
        item.className = 'icd10-result-item';
        item.dataset.index = index;
        item.style.cssText = `
          padding: 10px 14px;
          cursor: pointer;
          border-bottom: 1px solid #f3f4f6;
          transition: background 0.15s;
        `;

        item.innerHTML = `
          <div style="display: flex; align-items: center; gap: 10px;">
            <div style="font-weight: 600; color: #047857; font-size: 13px; min-width: 70px;">${escapeHtml(result.code)}</div>
            <div style="color: #374151; font-size: 14px; flex: 1;">${escapeHtml(result.name)}</div>
          </div>
        `;

        item.addEventListener('mouseenter', () => {
          selectedIndex = index;
          updateSelection();
        });

        item.addEventListener('click', () => {
          selectResult(result);
        });

        dropdown.appendChild(item);
      });

      dropdown.style.display = 'block';
    }

    /**
     * Show loading state
     */
    function showLoading() {
      dropdown.innerHTML = '<div style="padding: 12px; text-align: center; color: #6b7280; font-size: 14px;">Searching...</div>';
      dropdown.style.display = 'block';
    }

    /**
     * Show error message
     */
    function showError(message) {
      dropdown.innerHTML = `<div style="padding: 12px; text-align: center; color: #dc2626; font-size: 14px;">${escapeHtml(message)}</div>`;
      dropdown.style.display = 'block';
    }

    /**
     * Update visual selection highlight
     */
    function updateSelection() {
      const items = dropdown.querySelectorAll('.icd10-result-item');
      items.forEach((item, index) => {
        if (index === selectedIndex) {
          item.style.background = '#f0fdf4';
        } else {
          item.style.background = 'white';
        }
      });
    }

    /**
     * Select a result and fill input
     */
    function selectResult(result) {
      // Store both code and name as data attributes
      input.value = result.code;
      input.dataset.icd10Code = result.code;
      input.dataset.icd10Name = result.name;

      // Trigger change event for form handling
      input.dispatchEvent(new Event('change', { bubbles: true }));

      // Optional: Show tooltip with full name
      input.title = result.display;

      hideDropdown();
    }

    /**
     * Hide dropdown
     */
    function hideDropdown() {
      dropdown.style.display = 'none';
      currentResults = [];
      selectedIndex = -1;
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(str) {
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    }
  }

  // Auto-initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.initICD10Autocomplete);
  } else {
    window.initICD10Autocomplete();
  }
})();

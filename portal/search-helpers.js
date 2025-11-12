/**
 * Search Helpers - Address Autocomplete and NPI Lookup
 * Provides reusable components for address and NPI search
 */

/* ===============================================
   ADDRESS AUTOCOMPLETE
   =============================================== */

/**
 * Initialize address autocomplete for an input field
 *
 * @param {string} inputId - ID of the input element
 * @param {Function} onSelect - Callback when address is selected
 * @param {Object} options - Configuration options
 *
 * Usage:
 *   initAddressAutocomplete('shipping_address', (address) => {
 *     document.getElementById('shipping_city').value = address.city;
 *     document.getElementById('shipping_state').value = address.state;
 *     document.getElementById('shipping_zip').value = address.zip;
 *   });
 */
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

  // Create suggestions container
  function createSuggestionsContainer() {
    if (suggestionsDiv) return suggestionsDiv;

    suggestionsDiv = document.createElement('div');
    suggestionsDiv.className = containerClass;
    suggestionsDiv.style.cssText = `
      position: absolute;
      z-index: 9999;
      background: white;
      border: 1px solid #e2e8f0;
      border-radius: 6px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      max-height: 300px;
      overflow-y: auto;
      display: none;
    `;

    // Position below input
    const rect = input.getBoundingClientRect();
    suggestionsDiv.style.top = (rect.bottom + window.scrollY) + 'px';
    suggestionsDiv.style.left = rect.left + 'px';
    suggestionsDiv.style.width = rect.width + 'px';

    document.body.appendChild(suggestionsDiv);
    return suggestionsDiv;
  }

  // Fetch address suggestions
  async function fetchSuggestions(query) {
    try {
      const response = await fetch(`/api/portal/address-search.php?query=${encodeURIComponent(query)}`);
      const data = await response.json();

      if (!data.ok) {
        console.error('Address search error:', data.error);
        return [];
      }

      return data.suggestions || [];
    } catch (error) {
      console.error('Address search failed:', error);
      return [];
    }
  }

  // Fetch full address details from place_id
  async function fetchAddressDetails(placeId) {
    try {
      const response = await fetch(`/api/portal/address-details.php?place_id=${encodeURIComponent(placeId)}`);
      const data = await response.json();

      if (!data.ok) {
        console.error('Address details error:', data.error);
        return null;
      }

      return data.address;
    } catch (error) {
      console.error('Address details fetch failed:', error);
      return null;
    }
  }

  // Display suggestions
  function showSuggestions(suggestions) {
    const container = createSuggestionsContainer();
    container.innerHTML = '';

    if (suggestions.length === 0) {
      container.style.display = 'none';
      return;
    }

    suggestions.forEach(suggestion => {
      const div = document.createElement('div');
      div.className = suggestionClass;
      div.style.cssText = `
        padding: 0.75rem 1rem;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
      `;
      div.innerHTML = `
        <div style="font-weight: 500; color: #1e293b;">${suggestion.main_text}</div>
        <div style="font-size: 0.875rem; color: #64748b;">${suggestion.secondary_text}</div>
      `;

      // Hover effect
      div.addEventListener('mouseenter', () => {
        div.style.background = '#f8fafc';
      });
      div.addEventListener('mouseleave', () => {
        div.style.background = 'white';
      });

      // Click handler
      div.addEventListener('click', async () => {
        input.value = suggestion.description;
        container.style.display = 'none';

        // Fetch full address details
        const address = await fetchAddressDetails(suggestion.place_id);
        if (address && onSelect) {
          onSelect(address);
        }
      });

      container.appendChild(div);
    });

    container.style.display = 'block';
  }

  // Hide suggestions
  function hideSuggestions() {
    if (suggestionsDiv) {
      suggestionsDiv.style.display = 'none';
    }
  }

  // Input event handler
  input.addEventListener('input', (e) => {
    const query = e.target.value.trim();

    clearTimeout(debounceTimer);

    if (query.length < minChars) {
      hideSuggestions();
      return;
    }

    debounceTimer = setTimeout(async () => {
      const suggestions = await fetchSuggestions(query);
      showSuggestions(suggestions);
    }, debounceMs);
  });

  // Close suggestions when clicking outside
  document.addEventListener('click', (e) => {
    if (e.target !== input && suggestionsDiv && !suggestionsDiv.contains(e.target)) {
      hideSuggestions();
    }
  });

  // Handle escape key
  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      hideSuggestions();
    }
  });
}

/* ===============================================
   NPI SEARCH
   =============================================== */

/**
 * Search NPI registry
 *
 * @param {Object} criteria - Search criteria
 * @returns {Promise<Array>} Array of NPI results
 *
 * Usage:
 *   const results = await searchNPI({ npi: '1234567890' });
 *   const results = await searchNPI({ first_name: 'John', last_name: 'Smith', state: 'TX' });
 */
async function searchNPI(criteria) {
  try {
    const params = new URLSearchParams(criteria);
    const response = await fetch(`/api/portal/npi-search.php?${params}`);
    const data = await response.json();

    if (!data.ok) {
      throw new Error(data.message || 'NPI search failed');
    }

    return data.results || [];
  } catch (error) {
    console.error('NPI search error:', error);
    throw error;
  }
}

/**
 * Create an NPI search modal
 *
 * @param {Function} onSelect - Callback when NPI is selected
 *
 * Usage:
 *   showNPISearchModal((npiData) => {
 *     document.getElementById('npi').value = npiData.npi;
 *     document.getElementById('provider_name').value = npiData.name;
 *   });
 */
function showNPISearchModal(onSelect) {
  // Create modal overlay
  const overlay = document.createElement('div');
  overlay.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
  `;

  // Create modal content
  const modal = document.createElement('div');
  modal.style.cssText = `
    background: white;
    border-radius: 12px;
    padding: 2rem;
    width: 90%;
    max-width: 700px;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);
  `;

  modal.innerHTML = `
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h2 style="margin: 0; font-size: 1.5rem; font-weight: 600;">NPI Registry Search</h2>
      <button id="npi-modal-close" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b;">&times;</button>
    </div>

    <div style="margin-bottom: 1.5rem;">
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
        <input type="text" id="npi-search-number" placeholder="NPI Number (10 digits)" style="padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px;">
        <input type="text" id="npi-search-state" placeholder="State (e.g., TX)" maxlength="2" style="padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px;">
      </div>
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
        <input type="text" id="npi-search-first" placeholder="First Name" style="padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px;">
        <input type="text" id="npi-search-last" placeholder="Last Name" style="padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px;">
      </div>
      <input type="text" id="npi-search-org" placeholder="Organization Name" style="width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 1rem;">
      <button id="npi-search-btn" style="width: 100%; padding: 0.75rem; background: #3b82f6; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;">
        Search NPI Registry
      </button>
    </div>

    <div id="npi-search-results" style="margin-top: 1.5rem;"></div>
  `;

  overlay.appendChild(modal);
  document.body.appendChild(overlay);

  // Close modal
  function closeModal() {
    document.body.removeChild(overlay);
  }

  document.getElementById('npi-modal-close').addEventListener('click', closeModal);
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) closeModal();
  });

  // Search handler
  async function performSearch() {
    const resultsDiv = document.getElementById('npi-search-results');
    const npiNumber = document.getElementById('npi-search-number').value.trim();
    const state = document.getElementById('npi-search-state').value.trim().toUpperCase();
    const firstName = document.getElementById('npi-search-first').value.trim();
    const lastName = document.getElementById('npi-search-last').value.trim();
    const organization = document.getElementById('npi-search-org').value.trim();

    // Build search criteria
    const criteria = {};
    if (npiNumber) criteria.npi = npiNumber;
    if (state) criteria.state = state;
    if (firstName) criteria.first_name = firstName;
    if (lastName) criteria.last_name = lastName;
    if (organization) criteria.organization = organization;

    if (Object.keys(criteria).length === 0) {
      resultsDiv.innerHTML = '<p style="color: #ef4444;">Please enter at least one search criterion</p>';
      return;
    }

    resultsDiv.innerHTML = '<p style="color: #64748b;">Searching NPI registry...</p>';

    try {
      const results = await searchNPI(criteria);

      if (results.length === 0) {
        resultsDiv.innerHTML = '<p style="color: #64748b;">No results found</p>';
        return;
      }

      resultsDiv.innerHTML = `<p style="color: #64748b; margin-bottom: 1rem;">Found ${results.length} result(s)</p>`;

      results.forEach(result => {
        const resultDiv = document.createElement('div');
        resultDiv.style.cssText = `
          padding: 1rem;
          border: 1px solid #e2e8f0;
          border-radius: 6px;
          margin-bottom: 0.75rem;
          cursor: pointer;
          transition: all 0.2s;
        `;

        resultDiv.innerHTML = `
          <div style="font-weight: 600; margin-bottom: 0.5rem;">${result.name}</div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.875rem; color: #64748b;">
            <div><strong>NPI:</strong> ${result.npi}</div>
            <div><strong>Specialty:</strong> ${result.specialty || 'N/A'}</div>
            <div><strong>State:</strong> ${result.license_state || result.address.state || 'N/A'}</div>
            <div><strong>License:</strong> ${result.license_number || 'N/A'}</div>
          </div>
          <div style="margin-top: 0.5rem; font-size: 0.875rem; color: #64748b;">
            ${result.address.street1}<br>
            ${result.address.city}, ${result.address.state} ${result.address.zip}
          </div>
        `;

        resultDiv.addEventListener('mouseenter', () => {
          resultDiv.style.background = '#f8fafc';
          resultDiv.style.borderColor = '#3b82f6';
        });
        resultDiv.addEventListener('mouseleave', () => {
          resultDiv.style.background = 'white';
          resultDiv.style.borderColor = '#e2e8f0';
        });

        resultDiv.addEventListener('click', () => {
          if (onSelect) onSelect(result);
          closeModal();
        });

        resultsDiv.appendChild(resultDiv);
      });

    } catch (error) {
      resultsDiv.innerHTML = `<p style="color: #ef4444;">Error: ${error.message}</p>`;
    }
  }

  document.getElementById('npi-search-btn').addEventListener('click', performSearch);

  // Allow Enter key to search
  ['npi-search-number', 'npi-search-state', 'npi-search-first', 'npi-search-last', 'npi-search-org'].forEach(id => {
    document.getElementById(id).addEventListener('keypress', (e) => {
      if (e.key === 'Enter') performSearch();
    });
  });
}

/**
 * Address Autocomplete Initialization
 * Initializes Google Places autocomplete for all address fields across the portal
 */

// Track initialized fields to prevent duplicates
window.addressAutocompleteInitialized = window.addressAutocompleteInitialized || {};

function initAddressFields() {
  if (typeof initAddressAutocomplete !== 'function') {
    console.log('initAddressAutocomplete function not loaded yet');
    return;
  }

  // Patient address (modal)
  if (document.getElementById('patient-address') && !window.addressAutocompleteInitialized['patient-address']) {
    window.addressAutocompleteInitialized['patient-address'] = true;
    initAddressAutocomplete('patient-address', (address) => {
      document.getElementById('patient-address').value = address.formatted || address.street || '';
      document.getElementById('patient-city').value = address.city || '';
      document.getElementById('patient-state').value = address.state || '';
      document.getElementById('patient-zip').value = address.zip || '';
    });
  }

  // Practice address in settings
  if (document.getElementById('practice-address') && !window.addressAutocompleteInitialized['practice-address']) {
    window.addressAutocompleteInitialized['practice-address'] = true;
    initAddressAutocomplete('practice-address', (address) => {
      document.getElementById('practice-address').value = address.formatted || address.street || '';
      document.getElementById('practice-city').value = address.city || '';
      document.getElementById('practice-state').value = address.state || '';
      document.getElementById('practice-zip').value = address.zip || '';
    });
  }

  // Shipping address in order forms
  if (document.getElementById('ship-addr') && !window.addressAutocompleteInitialized['ship-addr']) {
    window.addressAutocompleteInitialized['ship-addr'] = true;
    initAddressAutocomplete('ship-addr', (address) => {
      if (document.getElementById('ship-addr')) document.getElementById('ship-addr').value = address.formatted || address.street || '';
      if (document.getElementById('ship-city')) document.getElementById('ship-city').value = address.city || '';
      if (document.getElementById('ship-state')) document.getElementById('ship-state').value = address.state || '';
      if (document.getElementById('ship-zip')) document.getElementById('ship-zip').value = address.zip || '';
    });
  }

  // New patient address in order creation flow (THIS IS THE ONE NOT WORKING)
  if (document.getElementById('np-address') && !window.addressAutocompleteInitialized['np-address']) {
    window.addressAutocompleteInitialized['np-address'] = true;
    console.log('Initializing np-address autocomplete');
    initAddressAutocomplete('np-address', (address) => {
      document.getElementById('np-address').value = address.formatted || address.street || '';
      document.getElementById('np-city').value = address.city || '';
      document.getElementById('np-state').value = address.state || '';
      document.getElementById('np-zip').value = address.zip || '';
      console.log('np-address autocomplete callback fired:', address);
    });
  }

  // Full page "Add Patient" form address
  if (document.getElementById('new-address') && !window.addressAutocompleteInitialized['new-address']) {
    window.addressAutocompleteInitialized['new-address'] = true;
    initAddressAutocomplete('new-address', (address) => {
      document.getElementById('new-address').value = address.formatted || address.street || '';
      document.getElementById('new-city').value = address.city || '';
      document.getElementById('new-state').value = address.state || '';
      document.getElementById('new-zip').value = address.zip || '';
    });
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  console.log('Address autocomplete init script loaded');
  initAddressFields();
});

// Re-initialize when modals/forms appear (for dynamic content)
setInterval(() => {
  initAddressFields();
}, 1000);

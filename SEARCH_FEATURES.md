# Address and NPI Search Features

## Overview

This system provides two powerful search features to streamline data entry:

1. **Address Autocomplete** - Google Places integration for accurate address entry
2. **NPI Registry Search** - Direct integration with CMS NPPES for provider lookups

---

## Address Autocomplete

### Setup

#### 1. Add Google Places API Key (Optional but Recommended)

Add to Render environment variables:
```
GOOGLE_PLACES_API_KEY=your_api_key_here
```

Get your API key: https://developers.google.com/maps/documentation/places/web-service/get-api-key

**Note:** The system works without the API key (fallback mode), but autocomplete will be disabled.

#### 2. Include JavaScript Library

```html
<script src="/portal/search-helpers.js"></script>
```

### Usage

#### Basic Address Autocomplete

```javascript
// Initialize autocomplete for a single field
initAddressAutocomplete('patient_address', (address) => {
  // Auto-fill related fields when address is selected
  document.getElementById('patient_city').value = address.city;
  document.getElementById('patient_state').value = address.state;
  document.getElementById('patient_zip').value = address.zip;
});
```

#### Multiple Address Fields

```javascript
// Patient address
initAddressAutocomplete('patient_address', (address) => {
  document.getElementById('patient_city').value = address.city;
  document.getElementById('patient_state').value = address.state;
  document.getElementById('patient_zip').value = address.zip;
});

// Shipping address
initAddressAutocomplete('shipping_address', (address) => {
  document.getElementById('shipping_city').value = address.city;
  document.getElementById('shipping_state').value = address.state;
  document.getElementById('shipping_zip').value = address.zip;
});
```

### API Endpoints

#### GET /api/portal/address-search.php

Search for address suggestions.

**Parameters:**
- `query` (required) - Address search string (min 3 characters)

**Response:**
```json
{
  "ok": true,
  "suggestions": [
    {
      "description": "123 Main St, Austin, TX, USA",
      "place_id": "ChIJ...",
      "main_text": "123 Main St",
      "secondary_text": "Austin, TX, USA"
    }
  ]
}
```

#### GET /api/portal/address-details.php

Get full address details from Place ID.

**Parameters:**
- `place_id` (required) - Google Place ID

**Response:**
```json
{
  "ok": true,
  "address": {
    "formatted": "123 Main St, Austin, TX 78701, USA",
    "street": "123 Main St",
    "city": "Austin",
    "state": "TX",
    "zip": "78701",
    "country": "US"
  }
}
```

---

## NPI Registry Search

### Features

- Search by NPI number (10 digits)
- Search by provider name (first/last)
- Search by organization name
- Filter by state
- No API key required (uses public CMS NPPES API)

### Usage

#### Programmatic Search

```javascript
// Search by NPI number
const results = await searchNPI({ npi: '1234567890' });

// Search by provider name
const results = await searchNPI({
  first_name: 'John',
  last_name: 'Smith',
  state: 'TX'
});

// Search by organization
const results = await searchNPI({
  organization: 'Acme Medical Group',
  state: 'CA'
});

// Process results
results.forEach(provider => {
  console.log(`${provider.name} - NPI: ${provider.npi}`);
  console.log(`Specialty: ${provider.specialty}`);
  console.log(`License: ${provider.license_number} (${provider.license_state})`);
});
```

#### Interactive Search Modal

```javascript
// Show NPI search modal with callback
showNPISearchModal((npiData) => {
  // Auto-fill form fields
  document.getElementById('provider_npi').value = npiData.npi;
  document.getElementById('provider_name').value = npiData.name;
  document.getElementById('provider_specialty').value = npiData.specialty;
  document.getElementById('provider_license').value = npiData.license_number;
  document.getElementById('provider_state').value = npiData.license_state;

  // Fill address fields
  document.getElementById('practice_address').value = npiData.address.street1;
  document.getElementById('practice_city').value = npiData.address.city;
  document.getElementById('practice_state').value = npiData.address.state;
  document.getElementById('practice_zip').value = npiData.address.zip;
});
```

#### Add Search Button to Form

```html
<div class="form-group">
  <label>Provider NPI</label>
  <div style="display: flex; gap: 0.5rem;">
    <input type="text" id="provider_npi" name="npi" placeholder="1234567890">
    <button type="button" onclick="showNPISearchModal(handleNPISelect)">
      Search NPI Registry
    </button>
  </div>
</div>

<script>
function handleNPISelect(npiData) {
  document.getElementById('provider_npi').value = npiData.npi;
  document.getElementById('provider_name').value = npiData.name;
  // ... fill other fields
}
</script>
```

### API Endpoint

#### GET /api/portal/npi-search.php

Search the NPI registry.

**Parameters:**
- `npi` - 10-digit NPI number
- `first_name` - Provider first name
- `last_name` - Provider last name
- `organization` - Organization name
- `state` - State filter (2-letter code)
- `taxonomy` - Specialty/taxonomy description
- `limit` - Max results (default: 20, max: 200)

**Response:**
```json
{
  "ok": true,
  "count": 1,
  "results": [
    {
      "npi": "1234567890",
      "entity_type": "individual",
      "name": "John Smith, MD",
      "first_name": "John",
      "last_name": "Smith",
      "credential": "MD",
      "specialty": "Internal Medicine",
      "taxonomy_code": "207R00000X",
      "license_number": "A12345",
      "license_state": "TX",
      "phone": "5125551234",
      "address": {
        "street1": "123 Medical Plaza",
        "street2": "Suite 100",
        "city": "Austin",
        "state": "TX",
        "zip": "78701",
        "country": "US"
      }
    }
  ]
}
```

---

## Implementation Examples

### Order Form Integration

```html
<!DOCTYPE html>
<html>
<head>
  <title>Create Order</title>
  <script src="/portal/search-helpers.js"></script>
</head>
<body>
  <form id="order-form">
    <!-- Patient Address with Autocomplete -->
    <div class="form-group">
      <label>Patient Address</label>
      <input type="text" id="patient_address" name="address" placeholder="Start typing address...">
    </div>
    <div class="form-row">
      <input type="text" id="patient_city" name="city" placeholder="City">
      <input type="text" id="patient_state" name="state" placeholder="State" maxlength="2">
      <input type="text" id="patient_zip" name="zip" placeholder="ZIP">
    </div>

    <!-- NPI Search -->
    <div class="form-group">
      <label>Ordering Provider</label>
      <div style="display: flex; gap: 0.5rem;">
        <input type="text" id="provider_npi" name="npi" placeholder="NPI Number">
        <button type="button" onclick="searchProvider()">Search NPI</button>
      </div>
    </div>
    <div class="form-row">
      <input type="text" id="provider_name" name="provider_name" placeholder="Provider Name" readonly>
      <input type="text" id="provider_specialty" name="specialty" placeholder="Specialty" readonly>
    </div>
  </form>

  <script>
    // Initialize address autocomplete
    initAddressAutocomplete('patient_address', (address) => {
      document.getElementById('patient_city').value = address.city;
      document.getElementById('patient_state').value = address.state;
      document.getElementById('patient_zip').value = address.zip;
    });

    // NPI search handler
    function searchProvider() {
      showNPISearchModal((npiData) => {
        document.getElementById('provider_npi').value = npiData.npi;
        document.getElementById('provider_name').value = npiData.name;
        document.getElementById('provider_specialty').value = npiData.specialty;
      });
    }
  </script>
</body>
</html>
```

### Registration Form Integration

```javascript
// On registration form page
document.addEventListener('DOMContentLoaded', () => {
  // Practice address autocomplete
  initAddressAutocomplete('practice_address', (address) => {
    document.getElementById('practice_city').value = address.city;
    document.getElementById('practice_state').value = address.state;
    document.getElementById('practice_zip').value = address.zip;
  });

  // Pre-fill NPI data when user enters NPI manually
  document.getElementById('npi').addEventListener('blur', async (e) => {
    const npi = e.target.value.trim();
    if (npi.length === 10) {
      try {
        const results = await searchNPI({ npi });
        if (results.length > 0) {
          const provider = results[0];
          if (confirm(`Found: ${provider.name}. Auto-fill details?`)) {
            document.getElementById('first_name').value = provider.first_name;
            document.getElementById('last_name').value = provider.last_name;
            document.getElementById('specialty').value = provider.specialty;
            document.getElementById('license').value = provider.license_number;
            document.getElementById('license_state').value = provider.license_state;
          }
        }
      } catch (error) {
        console.error('NPI lookup failed:', error);
      }
    }
  });
});
```

---

## Error Handling

### Address Search Errors

```javascript
initAddressAutocomplete('address_field', (address) => {
  // Success callback
}, {
  onError: (error) => {
    console.error('Address autocomplete error:', error);
    // Show user-friendly message
    alert('Address search is temporarily unavailable. Please enter manually.');
  }
});
```

### NPI Search Errors

```javascript
try {
  const results = await searchNPI({ npi: '1234567890' });
  if (results.length === 0) {
    alert('No provider found with that NPI');
  }
} catch (error) {
  alert('NPI search failed: ' + error.message);
}
```

---

## Performance Considerations

### Address Autocomplete
- Debounced (300ms default) to reduce API calls
- Requires minimum 3 characters before searching
- Results cached by browser
- Graceful fallback if API key not configured

### NPI Search
- Results limited to 20 by default (configurable up to 200)
- Direct connection to CMS servers (no local caching)
- Typical response time: 500-2000ms
- No rate limiting from CMS (public API)

---

## Costs

### Google Places API
- **Autocomplete**: $2.83 per 1,000 requests
- **Place Details**: $17 per 1,000 requests
- **Free tier**: $200/month credit (~70 autocomplete + details per day)

Recommendation: Enable for production if budget allows, or restrict to admin users only.

### NPI Registry API
- **FREE** - No API key or billing required
- Provided by CMS as public service
- No rate limits

---

## Testing

### Test Address Autocomplete

```javascript
// Should show suggestions
initAddressAutocomplete('test_address', (address) => {
  console.log('Selected address:', address);
  assert(address.city !== '');
  assert(address.state !== '');
  assert(address.zip !== '');
});

// Type "123 main st" and verify suggestions appear
```

### Test NPI Search

```javascript
// Known good NPI (example)
const results = await searchNPI({ npi: '1234567890' });
assert(results.length > 0);
assert(results[0].npi === '1234567890');

// Name search
const results2 = await searchNPI({
  last_name: 'Smith',
  state: 'TX',
  limit: 5
});
assert(results2.length <= 5);
```

---

## Security

- ✅ Session authentication required for all endpoints
- ✅ Input validation (NPI must be 10 digits, state must be 2 letters)
- ✅ Rate limiting handled by Google (address) and CMS (NPI)
- ✅ No sensitive data stored or logged
- ✅ HTTPS required for Google Places API

---

## Browser Compatibility

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

Requires:
- Fetch API
- Async/await
- ES6+ features

---

## Troubleshooting

### Address autocomplete not working
1. Check if `GOOGLE_PLACES_API_KEY` is set in environment
2. Verify API key has Places API enabled in Google Cloud Console
3. Check browser console for errors
4. Ensure HTTPS (Places API requires secure connection)

### NPI search returns no results
1. Verify NPI is exactly 10 digits
2. Check spelling of provider name
3. Try broader search (e.g., just last name instead of full name)
4. Verify internet connectivity (CMS API is external)

### Performance issues
1. Address autocomplete: Increase debounce delay in options
2. NPI search: Reduce result limit
3. Check network tab for slow API responses

---

## Future Enhancements

- [ ] Cache NPI lookups in local database
- [ ] Add fuzzy name matching for NPI search
- [ ] Support international addresses (currently US-only)
- [ ] Add batch NPI validation
- [ ] Implement offline mode with cached data

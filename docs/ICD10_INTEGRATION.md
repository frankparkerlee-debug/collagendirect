# ICD-10 Code Prepopulation Feature

## Overview
The ICD-10 prepopulation feature provides real-time autocomplete functionality for medical diagnosis codes using the free NIH/NLM Clinical Tables API. This helps physicians quickly find and enter accurate ICD-10-CM codes when creating orders, reducing errors and improving workflow efficiency.

## Features
- **Real-time Search**: As physicians type, the system queries the NIH/NLM ICD-10-CM database
- **Autocomplete Dropdown**: Shows up to 15 relevant diagnosis codes with descriptions
- **Keyboard Navigation**: Use arrow keys to navigate results, Enter to select, Escape to close
- **Responsive Design**: Works on desktop and mobile devices
- **Session-Based Security**: Only authenticated users can access the search API
- **Zero Configuration**: No API keys or external accounts required

## Technical Components

### 1. Backend Integration (`api/lib/icd10_api.php`)
PHP wrapper for NIH/NLM Clinical Tables API:
- `icd10_search($searchTerm, $maxResults)` - Search ICD-10-CM codes
- `icd10_get_code_details($code)` - Get details for specific code
- `icd10_search_conditions($searchTerm, $maxResults)` - Alternative broader search

**API Endpoint**: `https://clinicaltables.nlm.nih.gov/api/icd10cm/v3/search`

### 2. AJAX Endpoint (`api/icd10_search.php`)
- Handles autocomplete requests from frontend
- Requires user authentication (session validation)
- Minimum 2-character search term
- Returns JSON: `{success: true, results: [{code, name, display}], error: null}`

### 3. Frontend Component (`assets/icd10-autocomplete.js`)
- Vanilla JavaScript (no dependencies)
- Attaches to inputs with class `icd10-autocomplete`
- 300ms debounce to reduce API calls
- Stores selected code and name in `data-icd10-code` and `data-icd10-name` attributes
- Auto-initializes on page load

### 4. Integration in Order Form (`portal/index.php`)
- Primary and Secondary ICD-10 input fields have `icd10-autocomplete` class
- Script included in `<head>`: `<script src="/assets/icd10-autocomplete.js"></script>`
- Re-initializes when new wounds are dynamically added

## Usage

### For Developers

#### Adding autocomplete to any input field:
```html
<input type="text" class="icd10-autocomplete" placeholder="Type to search ICD-10 codes..." autocomplete="off">
<script src="/assets/icd10-autocomplete.js"></script>
```

#### Manual initialization after dynamic DOM changes:
```javascript
// After adding new inputs dynamically
if (typeof initICD10Autocomplete === 'function') {
  initICD10Autocomplete();
}
```

#### Retrieving selected code in JavaScript:
```javascript
const input = document.querySelector('.icd10-autocomplete');
const code = input.dataset.icd10Code;
const name = input.dataset.icd10Name;
console.log(`Selected: ${code} - ${name}`);
```

#### Retrieving selected code in PHP:
```php
$icd10Code = $_POST['icd10_primary'] ?? '';
// Validate if needed:
$result = icd10_get_code_details($icd10Code);
if ($result['success']) {
  echo "Valid code: {$result['code']} - {$result['name']}";
}
```

### For Physicians

1. **Creating a new order**:
   - Navigate to order creation form
   - Click on "Primary ICD-10" or "Secondary ICD-10" field
   - Type at least 2 characters (e.g., "diab" for diabetes)
   - Wait ~300ms for results to appear
   - Use arrow keys (↑↓) to navigate results
   - Press Enter or click to select
   - Code is automatically filled into the field

2. **Search examples**:
   - `"L97.4"` → Finds pressure ulcer codes by code number
   - `"pressure"` → Finds all pressure ulcer diagnoses
   - `"diabetes"` → Finds diabetes-related codes
   - `"wound"` → Finds wound-related codes

## API Rate Limiting
The NIH/NLM API does not publish specific rate limits, but the system includes:
- 300ms debounce (max ~3 requests/second per user)
- Session-based authentication (prevents anonymous abuse)
- Maximum 500 results per request (default: 15)
- Maximum offset + count: 7,500 results

## Error Handling
- **Network errors**: Shows "Search failed. Please try again."
- **No results**: Shows "No results found"
- **Unauthorized**: Returns 401 if user session expired
- **Short query**: Requires minimum 2 characters
- All errors logged to PHP error_log

## Browser Compatibility
- Modern browsers (Chrome, Firefox, Safari, Edge)
- IE11+ with polyfills
- Mobile browsers (iOS Safari, Chrome Mobile)

## Performance
- Average search latency: 200-500ms
- Dropdown renders in <50ms
- No impact on page load (async initialization)
- Lightweight: ~7KB JavaScript (uncompressed)

## Future Enhancements
- [ ] Local caching of common ICD-10 codes (reduce API calls)
- [ ] Prefetch top 100 codes on page load
- [ ] Support for ICD-10-PCS (procedure codes)
- [ ] Integration with diagnosis history (suggest previously used codes)
- [ ] Batch validation endpoint for imported data

## Troubleshooting

### Autocomplete not working
1. Check browser console for JavaScript errors
2. Verify `/api/icd10_search.php` returns 200 OK
3. Ensure user is authenticated (session active)
4. Check network tab for API calls to NIH/NLM
5. Verify input has class `icd10-autocomplete`

### Dropdown not positioning correctly
- Parent element must have `position: relative` or `position: absolute`
- Check z-index conflicts with other overlays
- Verify dropdown has `position: absolute` and `z-index: 1000`

### No results for valid codes
- Try searching by diagnosis name instead of code
- NIH API may have outdated data (updates quarterly)
- Check if code is ICD-10-CM vs ICD-10-PCS (we only support CM)

## References
- [NIH/NLM ICD-10-CM API Documentation](https://clinicaltables.nlm.nih.gov/apidoc/icd10cm/v3/doc.html)
- [ICD-10-CM Official Guidelines](https://www.cms.gov/medicare/coding-billing/icd-10-codes)
- [NIH Clinical Tables Demo](https://clinicaltables.nlm.nih.gov/demo.html?db=icd10cm)

## Support
For issues or feature requests, contact the development team or open a GitHub issue.

# Order Form Enhancement Implementation Plan

**Created**: 2025-11-07
**Status**: Planning Phase
**Target**: `/portal/index.php` (lines 5490-5717 - Order Form Dialog)

## Overview

This document provides a comprehensive implementation plan for 13 requested order form enhancements. Each change is tracked with specific file locations, code snippets, database requirements, and testing checkpoints.

---

## Database Schema Requirements

### New Columns Needed

```sql
-- Run this migration before starting implementation

-- 1. Exudate level field
ALTER TABLE orders ADD COLUMN IF NOT EXISTS exudate_level VARCHAR(20);
COMMENT ON COLUMN orders.exudate_level IS 'Wound exudate level: minimal, moderate, heavy';

-- 2. Wound photo path (non-billable baseline photo)
ALTER TABLE orders ADD COLUMN IF NOT EXISTS baseline_wound_photo_path TEXT;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS baseline_wound_photo_mime VARCHAR(100);

-- 3. Duration days (already may exist from WOUND_PHOTO_ENHANCEMENTS.md)
-- Check if exists first
ALTER TABLE orders ADD COLUMN IF NOT EXISTS duration_days INT;

-- 4. Remove refills column check (we'll hide in UI but keep in DB for backwards compatibility)
-- No DB change needed - just hide from UI

-- Note: tracking_number, secondary_dressing, wounds_data, frequency already exist per migration files
```

### Existing Columns (Confirmed)

- ✅ `tracking_number` VARCHAR(100) - Added in admin/run-tracking-migration.php
- ✅ `secondary_dressing` VARCHAR(255) - Added in portal/add-secondary-dressing-column.php
- ✅ `wounds_data` JSONB - Added in portal/add-wounds-data-column.php
- ✅ `frequency` (likely VARCHAR or TEXT)
- ✅ `duration_days` INT - Added in WOUND_PHOTO_ENHANCEMENTS.md

---

## Implementation Sequence

Changes are ordered by dependency and complexity to minimize risk of breaks.

### Phase 1: Simple UI Changes (Low Risk)

**Order**: 3, 4, 6
**Time Estimate**: 15 minutes
**Can Break**: Unlikely - simple field removals/renames

---

## Change #3: Remove Refill Count Field

**File**: `/portal/index.php`
**Line**: 5629-5631
**Priority**: HIGH (simple, low risk)

### Current Code (lines 5629-5631)

```html
<div>
  <label class="text-sm">Refills Authorized</label>
  <input id="refills" type="number" min="0" value="0" class="w-full">
</div>
```

### Action

**REMOVE** the entire `<div>` block containing the refills field.

### Code Change

```diff
-        <div>
-          <label class="text-sm">Refills Authorized</label>
-          <input id="refills" type="number" min="0" value="0" class="w-full">
-        </div>
```

### Database Impact

**NONE** - Keep `refills` column in database for backwards compatibility with existing orders.

### JavaScript Impact

Search for `refills` in order submission code and remove from FormData:

```bash
# Find all references to refills in portal/index.php
grep -n "refills" /Users/parkerlee/CollageDirect2.1/portal/index.php
```

Likely in `submitOrder()` or similar function - remove this line:
```javascript
fd.append('refills', document.getElementById('refills').value);
```

### Testing Checkpoint

- [ ] Order form loads without refills field
- [ ] Can submit order without JavaScript errors
- [ ] Existing orders with refills still display correctly in patient detail

---

## Change #4: Rename "Additional Instructions" to "Patient Instructions"

**File**: `/portal/index.php`
**Line**: 5634
**Priority**: HIGH (simple, low risk)

### Current Code (line 5634)

```html
<label class="text-sm">Additional Instructions</label>
```

### Action

Change label text only.

### Code Change

```diff
-          <label class="text-sm">Additional Instructions</label>
+          <label class="text-sm">Patient Instructions</label>
```

### Database Impact

**NONE** - Field name remains same in backend.

### Testing Checkpoint

- [ ] Label displays as "Patient Instructions"
- [ ] Field still saves correctly
- [ ] Order detail shows instructions

---

## Change #6: Add Secondary Dressing Options

**File**: `/portal/index.php`
**Lines**: 5638-5652
**Priority**: HIGH (simple addition)

### Current Code (lines 5640-5651)

```html
<select id="secondary-dressing" class="w-full">
  <option value="">None</option>
  <option value="Gauze - 2x2">Gauze - 2x2</option>
  <option value="Gauze - 4x4">Gauze - 4x4</option>
  <option value="Gauze - 6x6">Gauze - 6x6</option>
  <option value="Non-adherent pad">Non-adherent pad</option>
  <option value="Foam dressing">Foam dressing</option>
  <option value="Transparent film">Transparent film</option>
  <option value="Compression wrap">Compression wrap</option>
  <option value="Tubular bandage">Tubular bandage</option>
  <option value="Other">Other (specify in instructions)</option>
</select>
```

### Action

Add three new options:
1. Dermal Wound Cleaner 8oz bottle
2. Sterile Gauze 4x4
3. Sterile Gauze roll

### Code Change

```diff
 <select id="secondary-dressing" class="w-full">
   <option value="">None</option>
+  <option value="Dermal Wound Cleaner 8oz">Dermal Wound Cleaner 8oz bottle</option>
   <option value="Gauze - 2x2">Gauze - 2x2</option>
   <option value="Gauze - 4x4">Gauze - 4x4</option>
+  <option value="Sterile Gauze 4x4">Sterile Gauze 4x4</option>
   <option value="Gauze - 6x6">Gauze - 6x6</option>
+  <option value="Sterile Gauze roll">Sterile Gauze roll</option>
   <option value="Non-adherent pad">Non-adherent pad</option>
   <option value="Foam dressing">Foam dressing</option>
   <option value="Transparent film">Transparent film</option>
   <option value="Compression wrap">Compression wrap</option>
   <option value="Tubular bandage">Tubular bandage</option>
   <option value="Other">Other (specify in instructions)</option>
 </select>
```

### Database Impact

**NONE** - `secondary_dressing` column already exists (VARCHAR(255)).

### Testing Checkpoint

- [ ] All three new options appear in dropdown
- [ ] Can select and save each option
- [ ] Order detail displays selected secondary dressing

---

### Phase 2: Product Filtering (Medium Risk)

**Order**: 1
**Time Estimate**: 20 minutes
**Can Break**: Product selection if query is wrong

---

## Change #1: Remove 15-Day Kits from Order Form

**File**: `/portal/index.php`
**Line**: ~5585 (product dropdown population in JavaScript)
**Priority**: HIGH (business requirement - discontinued as of 11/6/2025)

### Background

Three products to hide:
- 15-Day Collagen Kit (KIT-COL-15)
- 15-Day Alginate Kit (KIT-ALG-15)
- 15-Day Silver Alginate Kit (KIT-AG-15)

### Current Approach

Need to find where products are loaded into `#ord-product` dropdown. Search for:

```bash
grep -n "ord-product" /Users/parkerlee/CollageDirect2.1/portal/index.php | head -20
```

Likely pattern:
```javascript
// Populate product dropdown
const products = await fetch('/api/products').then(r => r.json());
const select = document.getElementById('ord-product');
products.forEach(p => {
  const opt = document.createElement('option');
  opt.value = p.id;
  opt.textContent = p.name;
  select.appendChild(opt);
});
```

### Action

Add filter to exclude 15-day products:

```javascript
// Filter out discontinued 15-day kits (as of 11/6/2025)
products
  .filter(p => !p.name.includes('15-Day'))
  .forEach(p => {
    const opt = document.createElement('option');
    opt.value = p.id;
    opt.textContent = p.name;
    select.appendChild(opt);
  });
```

### Alternative: Database-Level Deactivation

More robust approach - mark products as inactive:

```sql
-- Mark 15-day kits as inactive
UPDATE products
SET active = FALSE, updated_at = NOW()
WHERE sku IN ('KIT-COL-15', 'KIT-ALG-15', 'KIT-AG-15');
```

**Benefit**: Automatically filtered by existing query `WHERE active=TRUE` in API.

**Recommendation**: Use database deactivation - cleaner and affects all UIs.

### Testing Checkpoint

- [ ] 15-day products do NOT appear in dropdown
- [ ] 30-day and 60-day products still appear
- [ ] Existing orders with 15-day kits still display correctly
- [ ] Can still create orders with other products

---

### Phase 3: Add New Fields with Validation (Medium Risk)

**Order**: 2, 8
**Time Estimate**: 45 minutes
**Can Break**: Form validation logic

---

## Change #2: Add Exudate Dropdown with Collagen Restriction

**File**: `/portal/index.php`
**Location**: Insert after line 5605 (after wounds-container)
**Priority**: MEDIUM (new field with validation logic)

### Database Preparation

```sql
ALTER TABLE orders ADD COLUMN IF NOT EXISTS exudate_level VARCHAR(20);
```

### Code to Insert

Insert new field after `#wounds-container` div (line 5605):

```html
<!-- After wounds-container, before last-eval -->
<div class="md:col-span-2">
  <label class="text-sm">Exudate Level <span class="text-red-600">*</span></label>
  <select id="exudate-level" class="w-full" onchange="validateCollagenRestriction()">
    <option value="">Select exudate level</option>
    <option value="minimal">Minimal</option>
    <option value="moderate">Moderate</option>
    <option value="heavy">Heavy (collagen contraindicated)</option>
  </select>
  <div id="exudate-warning" class="text-xs mt-1 p-2 bg-yellow-50 border border-yellow-300 rounded hidden">
    ⚠️ <strong>Heavy exudate:</strong> Collagen products are contraindicated. Please select alginate or foam-based products.
  </div>
</div>
```

### JavaScript Validation Function

Add this function to the page's `<script>` section:

```javascript
function validateCollagenRestriction() {
  const exudateLevel = document.getElementById('exudate-level').value;
  const productSelect = document.getElementById('ord-product');
  const warning = document.getElementById('exudate-warning');

  if (exudateLevel === 'heavy') {
    // Show warning
    warning.classList.remove('hidden');

    // Get selected product name
    const selectedOption = productSelect.options[productSelect.selectedIndex];
    const productName = selectedOption ? selectedOption.textContent : '';

    // Check if collagen product is selected
    if (productName.toLowerCase().includes('collagen')) {
      alert('⚠️ Collagen products cannot be used with heavy exudate. Please select an alginate or foam-based product.');
      productSelect.value = ''; // Clear selection
    }
  } else {
    warning.classList.add('hidden');
  }
}

// Also add validation on product change
document.addEventListener('DOMContentLoaded', function() {
  const productSelect = document.getElementById('ord-product');
  productSelect.addEventListener('change', function() {
    validateCollagenRestriction();
  });
});
```

### Form Submission Validation

In the order submission function, add validation:

```javascript
// In submitOrder() function, before submit
const exudateLevel = document.getElementById('exudate-level').value;
const productName = document.getElementById('ord-product').selectedOptions[0].textContent;

if (!exudateLevel) {
  alert('Please select exudate level');
  return;
}

if (exudateLevel === 'heavy' && productName.toLowerCase().includes('collagen')) {
  alert('Cannot submit: Collagen products are contraindicated for heavy exudate');
  return;
}

// Add to FormData
fd.append('exudate_level', exudateLevel);
```

### API Update

**File**: `/api/portal/orders.create.php`
**Line**: After line 156 (after `$prior_auth`)

```php
$exudate_level = safe($_POST['exudate_level'] ?? null);
```

Update INSERT statement to include exudate_level:

```php
// In the INSERT statement (line 175)
// Add to column list:
exudate_level,

// Add to VALUES list (after line 202):
?,

// Add to execute array (after line 202):
$exudate_level,
```

### Testing Checkpoint

- [ ] Exudate dropdown appears and is required
- [ ] Selecting "Heavy" shows warning message
- [ ] Cannot select collagen product when exudate is heavy
- [ ] Can select alginate/foam products with heavy exudate
- [ ] Exudate level saves to database
- [ ] Order detail displays exudate level

---

## Change #8: Add Optional Wound Photo Upload

**File**: `/portal/index.php`
**Location**: After line 5697 (after file-rx upload)
**Priority**: MEDIUM (file upload with non-billable flag)

### Database Preparation

```sql
ALTER TABLE orders ADD COLUMN IF NOT EXISTS baseline_wound_photo_path TEXT;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS baseline_wound_photo_mime VARCHAR(100);
```

### Code to Insert

Insert new upload field after Visit Notes upload (line 5697):

```html
<div class="md:col-span-1">
  <label class="text-sm">Baseline Wound Photo (Optional)</label>
  <input type="file" id="file-wound-photo" accept="image/*" class="w-full">
  <div class="text-xs text-slate-500 mt-1">
    Non-billable baseline documentation<br>
    <em>For photo review billing, use Photo Reviews page</em>
  </div>
</div>
```

### JavaScript Update

In order submission function, add file to FormData:

```javascript
// In submitOrder() function, with other file appends
const woundPhoto = document.getElementById('file-wound-photo').files[0];
if (woundPhoto) {
  fd.append('baseline_wound_photo', woundPhoto);
}
```

### API Update

**File**: `/api/portal/orders.create.php`
**Line**: After line 222 (in save_upload section)

```php
// Add baseline wound photo upload
[$wound_photo_path, $wound_photo_mime] = save_upload('baseline_wound_photo', '/uploads/wounds');
```

Update the file update section (after line 234):

```php
if ($rx_path || $ins_path || $id_path || $wound_photo_path) {
  $sets=[]; $params=[];
  if ($rx_path)  { $sets[]='rx_note_path=?';  $params[]=$rx_path;  $sets[]='rx_note_mime=?';  $params[]=$rx_mime; }
  if ($ins_path) { $sets[]='ins_card_path=?'; $params[]=$ins_path; $sets[]='ins_card_mime=?'; $params[]=$ins_mime; }
  if ($id_path)  { $sets[]='id_card_path=?';  $params[]=$id_path;  $sets[]='id_card_mime=?';  $params[]=$id_mime; }
  if ($wound_photo_path) { $sets[]='baseline_wound_photo_path=?'; $params[]=$wound_photo_path; $sets[]='baseline_wound_photo_mime=?'; $params[]=$wound_photo_mime; }
  $params[] = $order_id; $params[] = $uid;
  $pdo->prepare("UPDATE orders SET ".implode(', ',$sets).", updated_at=NOW() WHERE id=? AND user_id=?")->execute($params);
}
```

### Ensure Upload Directory Exists

```bash
# On deployment, ensure directory exists:
mkdir -p /var/data/uploads/wounds
chmod 775 /var/data/uploads/wounds
```

### Testing Checkpoint

- [ ] Wound photo upload field appears
- [ ] Can select image file
- [ ] File uploads successfully
- [ ] Photo saves to /uploads/wounds directory
- [ ] Order record contains baseline_wound_photo_path
- [ ] Photo does NOT create billable encounter

---

### Phase 4: AOB Removal (Medium Risk)

**Order**: 7
**Time Estimate**: 30 minutes
**Can Break**: Order submission if AOB check is enforced

---

## Change #7: Remove AOB from Order Form and Gate

**Files**:
- `/portal/index.php` (lines 5700-5708)
- `/api/portal/orders.create.php` (check for AOB validation)

**Priority**: MEDIUM (business logic change)

### Current Code (lines 5700-5708)

```html
<div class="md:col-span-2">
  <label class="text-sm block mb-2">Insurance Requirements</label>
  <div class="text-xs text-slate-600">
    Patient ID & Insurance Card must be on file with the patient. An AOB is also required (only needs to be signed once per patient).
  </div>
  <div class="mt-2">
    <button type="button" id="btn-aob" class="btn">Generate & Sign AOB</button>
    <span id="aob-hint" class="text-xs text-slate-500 ml-2"></span>
  </div>
</div>
```

### Action

**REMOVE** entire insurance requirements section from order form.

### Code Change

```diff
-        <div class="md:col-span-2">
-          <label class="text-sm block mb-2">Insurance Requirements</label>
-          <div class="text-xs text-slate-600">
-            Patient ID & Insurance Card must be on file with the patient. An AOB is also required (only needs to be signed once per patient).
-          </div>
-          <div class="mt-2">
-            <button type="button" id="btn-aob" class="btn">Generate & Sign AOB</button>
-            <span id="aob-hint" class="text-xs text-slate-500 ml-2"></span>
-          </div>
-        </div>
```

### JavaScript Cleanup

Search for AOB validation in order submission:

```bash
grep -n "aob\|AOB" /Users/parkerlee/CollageDirect2.1/portal/index.php
```

Remove any checks like:
```javascript
// REMOVE THIS:
if (paymentType === 'insurance' && !patientHasAOB) {
  alert('Patient must sign AOB before submitting insurance order');
  return;
}
```

### API Validation Removal

**File**: `/api/portal/orders.create.php`

Search for AOB validation:

```bash
grep -n "aob\|AOB" /Users/parkerlee/CollageDirect2.1/api/portal/orders.create.php
```

If found, remove validation but keep AOB field in database for existing data.

### Keep AOB on Patient Profile

**IMPORTANT**: Do NOT remove AOB from patient profile pages. Only remove from:
1. Order creation form
2. Order submission validation

### Testing Checkpoint

- [ ] AOB section removed from order form
- [ ] Can submit insurance orders without AOB
- [ ] AOB still visible on patient profile
- [ ] Can still generate/sign AOB from patient profile
- [ ] Existing orders with AOB still display correctly

---

### Phase 5: Multi-Wound Support (High Risk)

**Order**: 5, 13
**Time Estimate**: 1.5 hours
**Can Break**: Wound data submission and storage

---

## Change #5 & #13: Multi-Wound Support ("Add Another Wound")

**File**: `/portal/index.php`
**Lines**: 5596-5605 (wounds container)
**Reference**: `/portal/order-edit-dialog.html` (existing multi-wound pattern)
**Priority**: HIGH (complex - multiple wounds per visit)

### Current Implementation

Lines 5596-5605 already have infrastructure:
- `#wounds-container` div
- "Add Wound" button exists (`btn-add-wound`)
- `addWound()` function likely exists

### Investigation Needed

```bash
# Find addWound() function
grep -n "function addWound\|addWound()" /Users/parkerlee/CollageDirect2.1/portal/index.php
```

### Expected Pattern (from order-edit-dialog.html)

```javascript
let woundCounter = 0;

function addWound(woundData = null) {
  const container = document.getElementById('wounds-container');
  const woundIndex = woundCounter++;

  const woundHtml = `
    <div class="p-4 border rounded bg-slate-50 space-y-3" data-wound-index="${woundIndex}">
      <div class="flex items-center justify-between">
        <h5 class="font-medium">Wound #${woundIndex + 1}</h5>
        ${woundIndex > 0 ? `<button type="button" class="text-sm text-red-600" onclick="removeWound(${woundIndex})">Remove</button>` : ''}
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="text-sm">Location <span class="text-red-600">*</span></label>
          <select class="w-full wound-location" data-wound="${woundIndex}" required>
            <option value="">Select location</option>
            <option value="Foot">Foot</option>
            <option value="Ankle">Ankle</option>
            <option value="Lower leg">Lower leg</option>
            <option value="Knee">Knee</option>
            <option value="Upper leg/thigh">Upper leg/thigh</option>
            <option value="Sacrum">Sacrum</option>
            <option value="Buttock">Buttock</option>
            <option value="Hip">Hip</option>
            <option value="Other">Other</option>
          </select>
        </div>

        <div>
          <label class="text-sm">Laterality</label>
          <select class="w-full wound-laterality" data-wound="${woundIndex}">
            <option value="">N/A</option>
            <option value="Left">Left</option>
            <option value="Right">Right</option>
            <option value="Bilateral">Bilateral</option>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="text-sm">Size (cm)</label>
          <div class="grid grid-cols-3 gap-2">
            <input type="number" step="0.1" min="0" class="wound-length" data-wound="${woundIndex}" placeholder="Length">
            <input type="number" step="0.1" min="0" class="wound-width" data-wound="${woundIndex}" placeholder="Width">
            <input type="number" step="0.1" min="0" class="wound-depth" data-wound="${woundIndex}" placeholder="Depth">
          </div>
        </div>

        <div class="md:col-span-2">
          <label class="text-sm">Additional Notes</label>
          <textarea class="w-full wound-notes" data-wound="${woundIndex}" rows="2" placeholder="Wound appearance, drainage, etc."></textarea>
        </div>
      </div>
    </div>
  `;

  container.insertAdjacentHTML('beforeend', woundHtml);

  // Pre-populate if editing
  if (woundData) {
    document.querySelector(`.wound-location[data-wound="${woundIndex}"]`).value = woundData.location || '';
    document.querySelector(`.wound-laterality[data-wound="${woundIndex}"]`).value = woundData.laterality || '';
    document.querySelector(`.wound-length[data-wound="${woundIndex}"]`).value = woundData.length || '';
    document.querySelector(`.wound-width[data-wound="${woundIndex}"]`).value = woundData.width || '';
    document.querySelector(`.wound-depth[data-wound="${woundIndex}"]`).value = woundData.depth || '';
    document.querySelector(`.wound-notes[data-wound="${woundIndex}"]`).value = woundData.notes || '';
  }
}

function removeWound(index) {
  const wound = document.querySelector(`[data-wound-index="${index}"]`);
  if (wound) wound.remove();
}

// Initialize with one wound on form open
document.getElementById('dlg-order').addEventListener('open', function() {
  woundCounter = 0;
  document.getElementById('wounds-container').innerHTML = '';
  addWound(); // Add first wound
});
```

### Data Collection on Submit

```javascript
function collectWoundsData() {
  const wounds = [];
  const woundDivs = document.querySelectorAll('#wounds-container > div');

  woundDivs.forEach((div, idx) => {
    const woundIndex = div.dataset.woundIndex;
    wounds.push({
      location: document.querySelector(`.wound-location[data-wound="${woundIndex}"]`).value,
      laterality: document.querySelector(`.wound-laterality[data-wound="${woundIndex}"]`).value,
      length: document.querySelector(`.wound-length[data-wound="${woundIndex}"]`).value,
      width: document.querySelector(`.wound-width[data-wound="${woundIndex}"]`).value,
      depth: document.querySelector(`.wound-depth[data-wound="${woundIndex}"]`).value,
      notes: document.querySelector(`.wound-notes[data-wound="${woundIndex}"]`).value
    });
  });

  return wounds;
}

// In submitOrder():
const woundsData = collectWoundsData();
fd.append('wounds_data', JSON.stringify(woundsData));
```

### Database Storage

**Column**: `wounds_data JSONB` (already exists per add-wounds-data-column.php)

**API Update** - `/api/portal/orders.create.php`:

```php
// After line 154
$wounds_data_json = safe($_POST['wounds_data'] ?? null);

// Parse and validate
$wounds_data = null;
if ($wounds_data_json) {
  $wounds_data = json_decode($wounds_data_json, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'invalid_wounds_data']);
    exit;
  }
}

// Add to INSERT statement (line 175):
// Columns:
wounds_data,

// Values (line 186):
?,

// Execute array (line 210):
json_encode($wounds_data),
```

### Backwards Compatibility

Existing orders may have:
- `wound_location` VARCHAR
- `wound_laterality` VARCHAR
- `wound_notes` TEXT

New multi-wound orders use:
- `wounds_data` JSONB array

**Display Logic**:
```php
// When displaying order
if ($order['wounds_data']) {
  $wounds = json_decode($order['wounds_data'], true);
  foreach ($wounds as $idx => $wound) {
    echo "Wound #" . ($idx + 1) . ": " . $wound['location'];
  }
} else {
  // Legacy single wound
  echo $order['wound_location'];
}
```

### Testing Checkpoint

- [ ] Order form opens with 1 wound by default
- [ ] "Add Wound" button adds additional wound fields
- [ ] Can remove wounds (except first one)
- [ ] All wound data collects correctly
- [ ] wounds_data saves as JSON array
- [ ] Order detail displays all wounds
- [ ] Legacy orders (single wound) still display correctly

---

### Phase 6: Display Enhancements (Low Risk)

**Order**: 9, 10, 11, 12
**Time Estimate**: 1 hour
**Can Break**: Navigation and UI display

---

## Change #10: Add Tracking Link in Patient Profile

**File**: `/portal/index.php`
**Location**: Patient detail order list (around line 7757)
**Priority**: MEDIUM (display enhancement)

### Current Implementation

Orders display in patient detail with "View" button (recently updated).

### Investigation Needed

```bash
# Find patient order display
grep -n "View Order\|order detail" /Users/parkerlee/CollageDirect2.1/portal/index.php | grep -A5 -B5 7757
```

### Expected Current Code

```javascript
// In patient detail order list rendering
orders.forEach(order => {
  html += `
    <tr>
      <td>${order.created_at}</td>
      <td>${order.product}</td>
      <td>${order.status}</td>
      <td>
        <button onclick="viewOrderDetails(order)">View Order</button>
      </td>
    </tr>
  `;
});
```

### Enhancement

Add tracking link column:

```javascript
orders.forEach(order => {
  // Build tracking link if available
  let trackingHtml = '-';
  if (order.tracking_number && order.carrier) {
    const trackingUrl = getTrackingUrl(order.carrier, order.tracking_number);
    trackingHtml = `<a href="${trackingUrl}" target="_blank" class="text-blue-600 hover:underline">Track Package</a>`;
  } else if (order.tracking_number) {
    trackingHtml = order.tracking_number; // Just show number if no carrier
  }

  html += `
    <tr>
      <td>${order.created_at}</td>
      <td>${order.product}</td>
      <td>${order.status}</td>
      <td>${trackingHtml}</td>
      <td>
        <button onclick="viewOrderDetails(order)">View Order</button>
      </td>
    </tr>
  `;
});

// Helper function for carrier URLs
function getTrackingUrl(carrier, trackingNumber) {
  const carriers = {
    'USPS': `https://tools.usps.com/go/TrackConfirmAction?tLabels=${trackingNumber}`,
    'UPS': `https://www.ups.com/track?tracknum=${trackingNumber}`,
    'FedEx': `https://www.fedex.com/fedextrack/?trknbr=${trackingNumber}`,
    'DHL': `https://www.dhl.com/en/express/tracking.html?AWB=${trackingNumber}`
  };
  return carriers[carrier] || `https://google.com/search?q=${trackingNumber}`;
}
```

### API Update

Ensure tracking_number and carrier are returned in order queries:

```php
// In patient detail order query
SELECT
  o.id,
  o.created_at,
  o.product,
  o.status,
  o.tracking_number,
  o.carrier,
  o.frequency,
  o.duration_days,
  o.secondary_dressing
FROM orders o
WHERE o.patient_id = ?
ORDER BY o.created_at DESC
```

### Testing Checkpoint

- [ ] Tracking column appears in order list
- [ ] Shows "-" when no tracking available
- [ ] Shows tracking number when set by admin
- [ ] Shows clickable link when carrier + tracking available
- [ ] Link opens correct carrier tracking page

---

## Change #11: Show Duration, Frequency, Secondary Dressing in Order Detail

**File**: `/portal/index.php`
**Location**: `viewOrderDetails()` function (around line 7937)
**Priority**: LOW (display enhancement)

### Current Implementation (lines 7937-7948)

```javascript
function viewOrderDetails(order) {
  const modal = document.getElementById('dlg-order-detail');
  const content = document.getElementById('order-detail-content');

  content.innerHTML = `
    <div>
      <h5>Order Document</h5>
      <a href="/admin/order.pdf.php?id=${order.id}&csrf=${CSRF_TOKEN}">
        Download Order PDF
      </a>
    </div>
  `;

  modal.showModal();
}
```

### Enhancement

Add comprehensive order details:

```javascript
function viewOrderDetails(order) {
  const modal = document.getElementById('dlg-order-detail');
  const content = document.getElementById('order-detail-content');

  // Format wounds data
  let woundsHtml = '';
  if (order.wounds_data) {
    try {
      const wounds = JSON.parse(order.wounds_data);
      woundsHtml = wounds.map((w, idx) => `
        <div class="p-3 bg-slate-50 rounded mb-2">
          <strong>Wound #${idx + 1}:</strong> ${w.location || 'Not specified'}
          ${w.laterality ? ` (${w.laterality})` : ''}
          ${w.length && w.width ? `<br>Size: ${w.length} x ${w.width}` : ''}
          ${w.depth ? ` x ${w.depth} cm` : ' cm'}
          ${w.notes ? `<br><em>${w.notes}</em>` : ''}
        </div>
      `).join('');
    } catch (e) {
      woundsHtml = '<em>Unable to parse wound data</em>';
    }
  } else if (order.wound_location) {
    // Legacy single wound
    woundsHtml = `
      <div class="p-3 bg-slate-50 rounded">
        <strong>Location:</strong> ${order.wound_location}
        ${order.wound_laterality ? ` (${order.wound_laterality})` : ''}
        ${order.wound_notes ? `<br>${order.wound_notes}` : ''}
      </div>
    `;
  }

  content.innerHTML = `
    <div class="space-y-4">
      <!-- Order Info -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <h5 class="font-medium text-sm text-slate-600">Order ID</h5>
          <p>${order.id}</p>
        </div>
        <div>
          <h5 class="font-medium text-sm text-slate-600">Date</h5>
          <p>${new Date(order.created_at).toLocaleDateString()}</p>
        </div>
        <div>
          <h5 class="font-medium text-sm text-slate-600">Product</h5>
          <p>${order.product || 'N/A'}</p>
        </div>
        <div>
          <h5 class="font-medium text-sm text-slate-600">Status</h5>
          <p>${order.status || 'N/A'}</p>
        </div>
      </div>

      <!-- Treatment Details -->
      <div class="border-t pt-4">
        <h5 class="font-medium mb-3">Treatment Details</h5>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <h6 class="text-sm text-slate-600">Frequency</h6>
            <p>${order.frequency || 'Not specified'}</p>
          </div>
          <div>
            <h6 class="text-sm text-slate-600">Duration</h6>
            <p>${order.duration_days ? order.duration_days + ' days' : 'Not specified'}</p>
          </div>
          <div class="col-span-2">
            <h6 class="text-sm text-slate-600">Secondary Dressing</h6>
            <p>${order.secondary_dressing || 'None'}</p>
          </div>
          ${order.exudate_level ? `
          <div class="col-span-2">
            <h6 class="text-sm text-slate-600">Exudate Level</h6>
            <p>${order.exudate_level}</p>
          </div>
          ` : ''}
        </div>
      </div>

      <!-- Wounds -->
      ${woundsHtml ? `
      <div class="border-t pt-4">
        <h5 class="font-medium mb-3">Wound Information</h5>
        ${woundsHtml}
      </div>
      ` : ''}

      <!-- Tracking -->
      ${order.tracking_number ? `
      <div class="border-t pt-4">
        <h5 class="font-medium mb-3">Shipping</h5>
        <p>
          <strong>Tracking:</strong> ${order.tracking_number}
          ${order.carrier ? `<br><strong>Carrier:</strong> ${order.carrier}` : ''}
          ${order.carrier ? `<br><a href="${getTrackingUrl(order.carrier, order.tracking_number)}" target="_blank" class="text-blue-600 hover:underline">Track Package →</a>` : ''}
        </p>
      </div>
      ` : ''}

      <!-- Order Document -->
      <div class="border-t pt-4">
        <h5 class="font-medium mb-2">Order Document</h5>
        <a href="/admin/order.pdf.php?id=${order.id}&csrf=${CSRF_TOKEN}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
           target="_blank">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
          </svg>
          Download Order PDF
        </a>
      </div>

      <!-- Baseline Photo -->
      ${order.baseline_wound_photo_path ? `
      <div class="border-t pt-4">
        <h5 class="font-medium mb-2">Baseline Wound Photo</h5>
        <img src="${order.baseline_wound_photo_path}" alt="Baseline wound photo" class="max-w-md rounded border">
      </div>
      ` : ''}
    </div>
  `;

  modal.showModal();
}
```

### Testing Checkpoint

- [ ] Order detail shows frequency
- [ ] Order detail shows duration in days
- [ ] Order detail shows secondary dressing
- [ ] Order detail shows exudate level (if set)
- [ ] Order detail shows all wounds with details
- [ ] Order detail shows tracking info (if available)
- [ ] Order detail shows baseline photo (if uploaded)
- [ ] PDF download link works

---

## Change #9: Show Order Detail After Completing Order

**File**: `/portal/index.php`
**Location**: Order submission success callback
**Priority**: MEDIUM (UX enhancement)

### Current Implementation

After successful order submission, likely:
```javascript
// In submitOrder() success handler
fetch('/api/portal/orders.create.php', {
  method: 'POST',
  body: formData
})
.then(r => r.json())
.then(data => {
  if (data.ok) {
    alert('Order submitted successfully!');
    document.getElementById('dlg-order').close();
    loadPatients(); // Refresh patient list
  }
});
```

### Enhancement

Redirect to order detail after successful submission:

```javascript
fetch('/api/portal/orders.create.php', {
  method: 'POST',
  body: formData
})
.then(r => r.json())
.then(data => {
  if (data.ok) {
    const orderId = data.data.order_id;
    const patientId = data.data.patient_id;

    // Close order form
    document.getElementById('dlg-order').close();

    // Fetch the newly created order details
    fetch(`/api/portal/order.get.php?id=${orderId}`)
      .then(r => r.json())
      .then(orderData => {
        if (orderData.ok) {
          // Show order detail modal
          viewOrderDetails(orderData.data);

          // Update UI
          loadPatients(); // Refresh patient list

          // Show success message
          setTimeout(() => {
            alert('✅ Order submitted successfully!');
          }, 500);
        }
      });
  } else {
    alert('Error: ' + (data.error || 'Unknown error'));
  }
});
```

### API Endpoint Check

Verify `/api/portal/order.get.php` exists and returns order with all needed fields:

```bash
ls -la /Users/parkerlee/CollageDirect2.1/api/portal/order.get.php
```

If it doesn't exist or doesn't return enough data, enhance it:

```php
<?php
// /api/portal/order.get.php
declare(strict_types=1);
require __DIR__ . '/../db.php';
header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']);
  exit;
}

$orderId = $_GET['id'] ?? '';
$userId = $_SESSION['user_id'];

if (!$orderId) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'missing_order_id']);
  exit;
}

// Get order with all details
$stmt = $pdo->prepare("
  SELECT
    o.*,
    p.first_name as patient_first_name,
    p.last_name as patient_last_name
  FROM orders o
  LEFT JOIN patients p ON p.id = o.patient_id
  WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
  http_response_code(404);
  echo json_encode(['ok'=>false,'error'=>'order_not_found']);
  exit;
}

echo json_encode(['ok'=>true,'data'=>$order]);
```

### Testing Checkpoint

- [ ] After submitting order, order detail modal opens automatically
- [ ] Order detail shows all correct information
- [ ] Success message appears
- [ ] Patient list refreshes
- [ ] Can close modal and continue working

---

## Testing Strategy

### Unit Testing Sequence

1. **Phase 1 Changes (3, 4, 6)** - Test each field change independently
2. **Phase 2 Change (1)** - Test product filtering in isolation
3. **Phase 3 Changes (2, 8)** - Test new fields and validation
4. **Phase 4 Change (7)** - Test AOB removal and order submission
5. **Phase 5 Changes (5, 13)** - Test multi-wound thoroughly
6. **Phase 6 Changes (9, 10, 11)** - Test display enhancements

### Integration Testing

After all changes:

1. **Create New Order Flow**
   - [ ] Open order form
   - [ ] Select patient
   - [ ] Select product (verify no 15-day kits)
   - [ ] Add wound #1 with all details
   - [ ] Select exudate level
   - [ ] Add wound #2
   - [ ] Select secondary dressing (verify new options)
   - [ ] Upload baseline photo
   - [ ] Submit order (verify no AOB gate)
   - [ ] Verify order detail opens automatically

2. **Patient Profile View**
   - [ ] Open patient with new order
   - [ ] Verify tracking link appears (if set by admin)
   - [ ] Click "View" on order
   - [ ] Verify all details display correctly

3. **Edit Existing Order**
   - [ ] Open order from patient profile
   - [ ] Verify multi-wound support in edit dialog
   - [ ] Update and save

4. **Backwards Compatibility**
   - [ ] View old order (pre-changes)
   - [ ] Verify it still displays correctly
   - [ ] Verify legacy single wound shows properly

---

## Rollback Plan

### If Critical Error Occurs

1. **Identify Breaking Change**
   ```bash
   git log --oneline -10
   ```

2. **Revert Specific File**
   ```bash
   git checkout HEAD~1 -- portal/index.php
   git checkout HEAD~1 -- api/portal/orders.create.php
   ```

3. **Database Rollback** (if migration applied)
   ```sql
   -- Remove new columns (data will be lost!)
   ALTER TABLE orders DROP COLUMN IF EXISTS exudate_level;
   ALTER TABLE orders DROP COLUMN IF EXISTS baseline_wound_photo_path;
   ALTER TABLE orders DROP COLUMN IF EXISTS baseline_wound_photo_mime;
   ```

### Partial Rollback

If only one feature breaks, comment out that specific section:

```html
<!-- TEMPORARILY DISABLED: Exudate field causing validation errors
<div class="md:col-span-2">
  <label class="text-sm">Exudate Level</label>
  ...
</div>
-->
```

---

## Database Migration Script

Run this BEFORE starting implementation:

```sql
-- File: portal/add-order-form-enhancements.php
<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';

echo "Running order form enhancements migration...\n";

try {
  // 1. Exudate level
  echo "Adding exudate_level column...\n";
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS exudate_level VARCHAR(20)");
  echo "✓ exudate_level added\n";

  // 2. Baseline wound photo
  echo "Adding baseline wound photo columns...\n";
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS baseline_wound_photo_path TEXT");
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS baseline_wound_photo_mime VARCHAR(100)");
  echo "✓ Baseline wound photo columns added\n";

  // 3. Duration days (may already exist)
  echo "Adding duration_days column...\n";
  $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS duration_days INT");
  echo "✓ duration_days added\n";

  // 4. Verify existing columns
  echo "\nVerifying existing columns...\n";
  $stmt = $pdo->query("
    SELECT column_name
    FROM information_schema.columns
    WHERE table_name = 'orders'
    AND column_name IN ('tracking_number', 'secondary_dressing', 'wounds_data', 'frequency')
    ORDER BY column_name
  ");
  $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

  foreach (['tracking_number', 'secondary_dressing', 'wounds_data', 'frequency'] as $col) {
    if (in_array($col, $existing)) {
      echo "✓ $col exists\n";
    } else {
      echo "⚠ WARNING: $col does NOT exist - may need manual migration\n";
    }
  }

  echo "\n✅ Migration complete!\n";

} catch (PDOException $e) {
  echo "❌ Migration failed: " . $e->getMessage() . "\n";
  exit(1);
}
```

Run migration:
```bash
php portal/add-order-form-enhancements.php
```

---

## Deactivate 15-Day Products Script

```sql
-- File: admin/deactivate-15day-kits.php
<?php
declare(strict_types=1);
require __DIR__ . '/../api/db.php';

echo "Deactivating 15-day product kits (discontinued 11/6/2025)...\n";

try {
  $stmt = $pdo->prepare("
    UPDATE products
    SET active = FALSE, updated_at = NOW()
    WHERE sku IN ('KIT-COL-15', 'KIT-ALG-15', 'KIT-AG-15')
    RETURNING id, name, sku
  ");
  $stmt->execute();
  $deactivated = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (empty($deactivated)) {
    echo "⚠ No products found with those SKUs\n";
  } else {
    echo "✅ Deactivated " . count($deactivated) . " products:\n";
    foreach ($deactivated as $p) {
      echo "  - {$p['name']} ({$p['sku']})\n";
    }
  }

} catch (PDOException $e) {
  echo "❌ Failed: " . $e->getMessage() . "\n";
  exit(1);
}
```

Run script:
```bash
php admin/deactivate-15day-kits.php
```

---

## Change Summary Table

| # | Change Description | Files Modified | DB Changes | Risk Level | Est. Time |
|---|-------------------|----------------|------------|------------|-----------|
| 1 | Remove 15-day kits | admin script | `products.active` | Low | 10 min |
| 2 | Add exudate dropdown + validation | index.php, orders.create.php | `exudate_level` | Medium | 45 min |
| 3 | Remove refill count | index.php | None | Low | 5 min |
| 4 | Rename "Additional Instructions" | index.php | None | Low | 2 min |
| 5 | Add "Add Another Wound" | index.php | None | High | 1 hour |
| 6 | Add secondary dressing options | index.php | None | Low | 5 min |
| 7 | Remove AOB gate | index.php, orders.create.php | None | Medium | 30 min |
| 8 | Add wound photo upload | index.php, orders.create.php | `baseline_wound_photo_*` | Medium | 30 min |
| 9 | Show order detail after submit | index.php, order.get.php | None | Medium | 20 min |
| 10 | Add tracking links | index.php | None | Low | 15 min |
| 11 | Enhance order detail display | index.php | None | Low | 30 min |
| 12 | Replace clinical note with PDF | index.php | None | Low | DONE ✅ |
| 13 | Multi-wound support | index.php, orders.create.php | None | High | Same as #5 |

**Total Estimated Time**: ~4.5 hours
**Total Risk Assessment**: Medium-High (due to multi-wound complexity)

---

## Implementation Checklist

- [ ] **Pre-Implementation**
  - [ ] Run database migration script
  - [ ] Deactivate 15-day products
  - [ ] Backup current index.php and orders.create.php
  - [ ] Create feature branch in git

- [ ] **Phase 1: Simple UI Changes**
  - [ ] Remove refill count field (#3)
  - [ ] Rename "Additional Instructions" (#4)
  - [ ] Add secondary dressing options (#6)
  - [ ] Test Phase 1

- [ ] **Phase 2: Product Filtering**
  - [ ] Verify 15-day products deactivated (#1)
  - [ ] Test product dropdown
  - [ ] Test Phase 2

- [ ] **Phase 3: New Fields with Validation**
  - [ ] Add exudate dropdown (#2)
  - [ ] Add exudate validation logic
  - [ ] Add baseline wound photo upload (#8)
  - [ ] Test Phase 3

- [ ] **Phase 4: AOB Removal**
  - [ ] Remove AOB section from form (#7)
  - [ ] Remove AOB validation from JS
  - [ ] Remove AOB validation from API
  - [ ] Test Phase 4

- [ ] **Phase 5: Multi-Wound Support**
  - [ ] Implement addWound() function (#5, #13)
  - [ ] Implement removeWound() function
  - [ ] Implement collectWoundsData() function
  - [ ] Update API to save wounds_data
  - [ ] Test multi-wound creation
  - [ ] Test backwards compatibility

- [ ] **Phase 6: Display Enhancements**
  - [ ] Add tracking links in patient profile (#10)
  - [ ] Enhance order detail modal (#11)
  - [ ] Auto-show order detail after submit (#9)
  - [ ] Test Phase 6

- [ ] **Integration Testing**
  - [ ] Full order creation flow
  - [ ] Patient profile view
  - [ ] Edit existing order
  - [ ] Backwards compatibility

- [ ] **Deployment**
  - [ ] Create git commit with detailed message
  - [ ] Push to production
  - [ ] Monitor for errors
  - [ ] Document any issues

---

## Contact & Support

If errors occur during implementation:

1. Check browser console for JavaScript errors
2. Check server logs for PHP errors
3. Verify database migration completed
4. Test with simple order first (1 wound, minimal fields)
5. Use rollback plan if critical error

**Generated**: 2025-11-07
**Next Step**: Review plan with user, then begin Phase 1 implementation


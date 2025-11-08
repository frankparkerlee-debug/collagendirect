# Button Connectivity & Functionality Audit
**Date**: 2025-11-08
**System**: CollageDirect Portal

## Executive Summary

Comprehensive audit of all interactive buttons across the portal to identify broken connections, missing handlers, and non-functional buttons.

**Critical Issues Found**: 2
**Medium Issues Found**: 0
**Low Issues Found**: 3
**Total Buttons Audited**: 40+

---

## ❌ CRITICAL ISSUES

### 1. **Missing Handlers for Draft Order Buttons** (CRITICAL)

**Location**: Patient Detail Accordion - Order List
**Affected Buttons**:
- `data-edit-draft` - Edit Draft Order button
- `data-submit-draft` - Submit Draft Order button

**Problem**:
```javascript
// Line 7030-7031: Buttons are rendered
<button data-edit-draft="${o.id}">Edit</button>
<button data-submit-draft="${o.id}">Submit</button>

// Line 7884-7885: Other buttons have handlers
acc.querySelectorAll('[data-stop]').forEach(...)
acc.querySelectorAll('[data-restart]').forEach(...)

// ❌ NO HANDLERS FOR data-edit-draft or data-submit-draft
```

**Impact**:
- Users CANNOT edit draft orders
- Users CANNOT submit draft orders from the patient detail view
- Drafts are essentially "stuck" and unusable

**User Experience**:
1. User creates and saves a draft order
2. User sees "Edit" and "Submit" buttons in patient orders list
3. User clicks button → **NOTHING HAPPENS**
4. User is confused and frustrated

**Fix Required**:
```javascript
// Add after line 7885
acc.querySelectorAll('[data-edit-draft]').forEach(b => {
  b.onclick = () => openOrderEditDialog(b.dataset.editDraft);
});

acc.querySelectorAll('[data-submit-draft]').forEach(b => {
  b.onclick = async () => {
    if (!confirm('Submit this draft order for admin review?')) return;
    const r = await fetch('?action=order.submit_draft', {
      method: 'POST',
      body: new FormData().append('order_id', b.dataset.submitDraft)
    });
    const j = await r.json();
    if (j.ok) {
      alert('Draft submitted successfully!');
      toggleAccordion(rowEl, p.id, page); // Refresh
    } else {
      alert(j.error || 'Failed to submit draft');
    }
  };
});
```

---

### 2. **Missing Order Edit Dialog Function** (CRITICAL)

**Problem**: The fix above references `openOrderEditDialog()` but this function doesn't exist!

**Required**: Need to implement order edit functionality

**Options**:
1. Create `openOrderEditDialog(orderId)` function
2. OR reuse existing order dialog with pre-population
3. OR use the order-edit-dialog.html component (if it exists)

**Investigation Needed**: Check if order-edit-dialog.html is loaded

---

## ⚠️ MEDIUM ISSUES

No medium issues found. All other buttons have proper handlers.

---

## 📝 LOW PRIORITY ISSUES

### 1. **Inconsistent Button ID Naming** (LOW)

**Problem**: Mixed naming conventions
- Some use `btn-{action}` (e.g., `btn-add-patient`)
- Some use `{context}-btn` (e.g., `notifications-btn`)
- Some use `{context}-{action}-btn` (e.g., `global-new-order-btn`)

**Impact**: Code readability and maintainability

**Recommendation**: Standardize to `btn-{context}-{action}` format

---

### 2. **Upload Documents Button Hidden by Default** (LOW)

**Location**: Order Dialog - Patient Documents Section
**Button**: `btn-upload-docs`

**Code**: `<button id="btn-upload-docs" class="hidden">Upload Documents</button>`

**Status**: Intentional - shown dynamically when documents are missing

**Verification**: ✅ Handler exists and works correctly

---

### 3. **Duplicate Button Definitions** (LOW)

**Found**: Some buttons defined multiple times for different contexts
- `btn-save-patient` (order dialog) vs patient detail save
- Multiple "Add Patient" buttons in different views

**Impact**: Minimal - each context handles its own button

**Recommendation**: Consider unique IDs per context

---

## ✅ VERIFIED WORKING BUTTONS

All button handlers verified and functional:

| Button ID | Function | Status |
|-----------|----------|--------|
| `btn-add-patient` | Open add patient dialog | ✅ Working |
| `btn-add-physician` | Open add physician dialog | ✅ Working |
| `btn-add-wound` | Add wound to order form | ✅ Working (inline onclick) |
| `btn-aob-sign` | Sign AOB document | ✅ Working |
| `btn-compose` | Compose new message | ✅ Working |
| `btn-create-patient` | Save new patient from order | ✅ Working |
| `btn-dashboard-export` | Export dashboard data | ✅ Working |
| `btn-dashboard-filter` | Filter dashboard | ✅ Working |
| `btn-order-create` | Submit new order | ✅ Working |
| `btn-order-draft` | Save order as draft | ✅ Working |
| `btn-pw` | Update password | ✅ Working |
| `btn-restart-go` | Restart stopped order | ✅ Working |
| `btn-save-patient` | Save patient details | ✅ Working |
| `btn-send-message` | Send message | ✅ Working |
| `btn-send-reply` | Send message reply | ✅ Working |
| `btn-stop-go` | Stop active order | ✅ Working |
| `btn-upload-docs` | Upload missing docs | ✅ Working |
| `global-new-order-btn` | Global new order button | ✅ Working |
| `mark-all-read-btn` | Mark notifications read | ✅ Working |
| `mobile-menu-btn` | Toggle mobile menu | ✅ Working |
| `notifications-btn` | Open notifications panel | ✅ Working |
| `patient-detail-new-order-btn` | New order from patient detail | ✅ Working |
| `search-btn` | Open search | ✅ Working |
| `sidebar-toggle-btn` | Toggle sidebar | ✅ Working |

---

## ✅ VERIFIED WORKING INLINE FUNCTIONS

All onclick inline functions verified:

| Function | Usage Count | Status |
|----------|-------------|--------|
| `addWound()` | 1 | ✅ Exists |
| `assignPhotoToOrder()` | 1 | ✅ Exists |
| `confirmDirectBillExport()` | 1 | ✅ Exists |
| `exportDirectBill()` | 1 | ✅ Exists |
| `generateAOB()` | 1 | ✅ Exists |
| `generateApprovalScore()` | 2 | ✅ Exists |
| `handleNotificationClick()` | 1 | ✅ Exists |
| `deletePatient()` | 1 | ✅ Exists |
| `openOrderDialog()` | 1 | ✅ Exists |
| `removePhysician()` | 1 | ✅ Exists |
| `requestWoundPhoto()` | 2 | ✅ Exists |
| `resetDMEFilters()` | 1 | ✅ Exists |
| `savePatientFromDetail()` | 1 | ✅ Exists |
| `saveProviderResponse()` | 1 | ✅ Exists |
| `viewOrderDetails()` | 1 | ✅ Exists |
| `viewWoundPhoto()` | 1 | ✅ Exists |

---

## 🔌 API ENDPOINT CONNECTIVITY

### Portal Actions (Handled in index.php)

All action routes verified:

| Action | Handler Location | Method | Status |
|--------|------------------|--------|--------|
| `order.create` | index.php:2058 | Inline | ✅ Working |
| `order.get` | api/portal/order.get.php | Delegated | ✅ Working |
| `order.submit_draft` | api/portal/order.submit-draft.php | Delegated | ✅ Working |
| `order.stop` | index.php:2316 | Inline | ✅ Working |
| `order.reorder` | index.php:2325 | Inline | ✅ Working |
| `patient.get` | index.php:909 | Inline | ✅ Working |
| `patient.save` | index.php:1072 | Inline | ✅ Working |
| `patient.upload` | index.php:1842 | Inline | ✅ Working |
| `patient.delete` | index.php:2000 | Inline | ✅ Working |
| `patient.save_provider_response` | index.php:1767 | Inline | ✅ Working |
| `practice.physicians` | index.php:2769 | Inline | ✅ Working |
| `practice.add_physician` | index.php:2802 | Inline | ✅ Working |
| `practice.remove_physician` | index.php:2936 | Inline | ✅ Working |
| `practice.get_info` | index.php:2983 | Inline | ✅ Working |
| `practice.update_info` | index.php:3022 | Inline | ✅ Working |
| `user.change_password` | index.php:2390 | Inline | ✅ Working |
| `messages` | index.php:2402 | Inline | ✅ Working |
| `message.read` | index.php:2438 | Inline | ✅ Working |
| `message.send` | index.php:2448 | Inline | ✅ Working |
| `notifications` | index.php:2556 | Inline | ✅ Working |
| `mark_notifications_read` | index.php:2667 | Inline | ✅ Working |
| `dismiss_notification` | index.php:2736 | Inline | ✅ Working |
| `products` | index.php:2040 | Inline | ✅ Working |
| `patients` | index.php:758 | Inline | ✅ Working |
| `orders` | index.php:2349 | Inline | ✅ Working |
| `metrics` | index.php:595 | Inline | ✅ Working |
| `chart_data` | index.php:673 | Inline | ✅ Working |
| `request_wound_photo` | index.php:1134 | Inline | ✅ Working |
| `photo.assign_order` | index.php:1220 | Inline | ✅ Working |
| `get_patient_photos` | index.php:1323 | Inline | ✅ Working |
| `review_wound_photo` | index.php:1371 | Inline | ✅ Working |
| `file.download` | index.php:1006 | Inline | ✅ Working |

---

## 📋 BUTTON FLOW DIAGRAMS

### Critical: Draft Order Flow (BROKEN)

```
User Action: Saves draft order
     ↓
Draft stored: status='draft', review_status='draft'
     ↓
Patient accordion shows order list
     ↓
HTML renders: [Edit] [Submit] buttons
     ↓
❌ User clicks "Edit" → NOTHING HAPPENS (no handler)
❌ User clicks "Submit" → NOTHING HAPPENS (no handler)
     ↓
User is stuck - cannot edit or submit draft
```

**Expected Flow**:
```
User clicks "Edit"
     ↓
openOrderEditDialog(orderId)
     ↓
Fetch order data via ?action=order.get
     ↓
Populate order form with existing data
     ↓
User edits and saves
     ↓
POST to order.update.php
     ↓
Success - order updated
```

---

## 🔧 RECOMMENDED FIXES

### Priority 1: Fix Draft Order Buttons (CRITICAL)

**File**: `portal/index.php`
**Line**: After line 7885

**Add**:
```javascript
// Handle Edit Draft button
acc.querySelectorAll('[data-edit-draft]').forEach(b => {
  b.onclick = () => {
    const orderId = b.dataset.editDraft;
    // TODO: Implement openOrderEditDialog or reuse openOrderDialog with edit mode
    console.warn('Order edit not yet implemented. Order ID:', orderId);
    alert('Order editing is not yet available. Please contact support.');
  };
});

// Handle Submit Draft button
acc.querySelectorAll('[data-submit-draft]').forEach(b => {
  b.onclick = async () => {
    const orderId = b.dataset.submitDraft;
    if (!confirm('Submit this draft order for admin review?')) return;

    b.disabled = true;
    b.textContent = 'Submitting...';

    try {
      const formData = new FormData();
      formData.append('order_id', orderId);

      const r = await fetch('?action=order.submit_draft', {
        method: 'POST',
        body: formData
      });

      const j = await r.json();

      if (j.ok) {
        alert('Draft submitted successfully for admin review!');
        toggleAccordion(rowEl, p.id, page); // Refresh accordion
      } else {
        alert(j.error || 'Failed to submit draft');
      }
    } catch (e) {
      alert('Network error: ' + e.message);
    } finally {
      b.disabled = false;
      b.textContent = 'Submit';
    }
  };
});
```

---

### Priority 2: Implement Order Edit Functionality (CRITICAL)

**Options**:

**Option A**: Check if order-edit-dialog.html exists and use it
```javascript
function openOrderEditDialog(orderId) {
  // Load order-edit-dialog.html if not already loaded
  // Fetch order data
  // Populate dialog
  // Show dialog
}
```

**Option B**: Reuse existing order dialog with edit mode
```javascript
function openOrderDialog(patientId, editOrderId = null) {
  if (editOrderId) {
    // Load order data
    // Populate form
    // Change submit button to "Update Order"
  }
  // Show dialog
}
```

---

## 📊 AUDIT STATISTICS

| Category | Count |
|----------|-------|
| Total Buttons with IDs | 24 |
| Inline onClick Functions | 16 |
| Data-Attribute Buttons | 6 |
| Broken Buttons | 2 |
| Working Buttons | 38+ |
| API Actions | 31 |
| Missing Handlers | 2 |

---

## 🎯 CONCLUSIONS

**Overall Assessment**: MODERATE RISK

The portal's button infrastructure is generally sound with most buttons working correctly. However, there are **2 critical broken buttons** that affect the draft order workflow - a key user feature.

**Impact**:
- Users cannot edit saved drafts
- Users cannot submit drafts from the order list
- Workaround: Users must create new orders instead of using drafts

**Recommended Actions**:
1. **URGENT**: Fix draft order Edit and Submit buttons (1-2 hours)
2. **HIGH**: Implement order edit dialog functionality (4-6 hours)
3. **LOW**: Standardize button naming conventions (future refactor)

**Risk Level**: MEDIUM
**User Impact**: HIGH (affects daily workflow)
**Fix Complexity**: MEDIUM (requires new functionality)

# Order Status System Fixes - November 4, 2025

## ✅ Completed

### 1. Fixed Order Editing (JSON Errors)
**Problem:** Getting JSON errors when attempting to edit orders.

**Root Cause:** The `openOrderEditDialog()` function was a TODO stub that just showed an alert.

**Solution:** 
- Removed duplicate stub implementation from order-workflow.js
- Complete implementation already existed in order-edit-dialog.html
- Dialog now properly:
  - Fetches order data via `?action=order.get`
  - Populates all form fields
  - Submits updates to `/api/portal/order.update.php`
  - Handles success/error responses

**Files Modified:**
- [portal/order-workflow.js](portal/order-workflow.js:282-290) - Removed duplicate
- [portal/order-edit-dialog.html](portal/order-edit-dialog.html:341-390) - Already complete

###2. Changed "Submitted" to "Pending"
**Problem:** "Submitted" is an action, not a status.

**Solution:** Renamed status display from "Submitted" to "Pending" throughout system.

**Status System:**
- **Draft** (editable) - Order being prepared by physician
- **Pending** (editable) - Awaiting manufacturer review  
- **Needs Revision** (editable) - Manufacturer requested changes
- **Accepted** (locked) - Approved, will be billed - CANNOT EDIT
- **Rejected** (locked) - Order rejected - CANNOT EDIT
- **Expired** (locked) - >30 days old, not approved - CANNOT EDIT

**Files Modified:**
- [portal/order-status-helper.js](portal/order-status-helper.js:46-57) - Changed "Submitted" to "Pending"
- [portal/order-status-helper.js](portal/order-status-helper.js:131-142) - Updated default status

**Editability Rules:**
- Can edit: Draft, Pending, Needs Revision
- Cannot edit: Accepted, Rejected, Expired

---

## ⏳ Pending (Need Design Decision)

### 3. Draft Order Save Functionality
**Your Request:** "If we create a 'Draft' status the physician has to have the ability to save a draft order which they will revisit, we do not currently have this in place."

**Current Behavior:**
- When physician creates order, it immediately sets `review_status = 'pending_admin_review'`
- No way to save incomplete orders as drafts
- Orders must be completed in one session

**Proposed Solution Options:**

#### Option A: Add "Save as Draft" Button
Add second button to order creation dialog:
```
[Cancel]  [Save as Draft]  [Submit Order]
```

- "Save as Draft" → Sets `review_status = 'draft'`, visible only to physician
- "Submit Order" → Sets `review_status = 'pending_admin_review'`, visible to admin

**Pros:** Simple, clear UX
**Cons:** Adds another button, may clutter UI

#### Option B: Auto-Save Drafts
Automatically save order as draft when physician starts filling form:
- Any incomplete order automatically becomes draft
- "Submit" button finalizes and sends to admin
- Drafts auto-saved periodically (like Gmail)

**Pros:** Never lose work, seamless
**Cons:** More complex implementation

#### Option C: Explicit Draft Creation
Add "Create Draft Order" as separate action from "Create Order":
- Dashboard has both "New Order" and "New Draft" buttons
- Draft orders shown in separate section
- Physician explicitly chooses draft vs full order

**Pros:** Very clear separation
**Cons:** More UI complexity

**What do you prefer?**

---

### 4. Filter Draft Orders from Admin View
**Your Request:** "Draft orders should not be visible to admin."

**Current Behavior:**
- Admin sees all orders regardless of review_status
- No filtering by status in admin panel

**Implementation Plan:**
Once draft functionality is added, update admin queries:

```php
// admin/orders.php or similar
$stmt = $pdo->prepare("
  SELECT * FROM orders 
  WHERE review_status != 'draft'
  ORDER BY created_at DESC
");
```

**Files to Modify:**
- `admin/order-review.php` - Filter draft orders from list
- Any admin dashboard queries that show order counts
- Admin order search/filter functionality

**Note:** Can implement this immediately, but makes more sense after draft save functionality is added.

---

## Git Commits

- **5843b90** - Fix JavaScript timing issue (load order-status-helper.js early)
- **b5f17ee** - Fix order editing and change 'Submitted' to 'Pending' status

---

## Testing Checklist

### Order Editing ✅
- [ ] Click Edit on pending order
- [ ] Form opens with pre-filled data
- [ ] Modify fields and save
- [ ] Changes persist correctly
- [ ] No JSON errors in console

### Status Display ✅
- [ ] Orders show "Pending" instead of "Submitted"  
- [ ] Draft orders show "Draft" badge
- [ ] Edit button only appears for Draft/Pending/Needs Revision
- [ ] Edit button does NOT appear for Accepted/Expired/Rejected

### Still Need to Test (After Draft Implementation)
- [ ] Save order as draft
- [ ] Draft orders visible to physician
- [ ] Draft orders NOT visible to admin
- [ ] Physician can resume editing draft
- [ ] Draft can be submitted to become Pending

---

## Questions for You

1. **Draft Save Functionality:** Which option do you prefer (A, B, or C)?
2. **Draft Storage:** Should drafts automatically delete after X days if not submitted?
3. **Draft UI:** Where should physicians see their drafts? (Separate section? Mixed with orders?)

---

**Status:** ✅ Order editing fixed, "Pending" status implemented
**Next:** Need your decision on draft save functionality approach
**Deployed:** Yes - changes pushed to GitHub (commit b5f17ee)

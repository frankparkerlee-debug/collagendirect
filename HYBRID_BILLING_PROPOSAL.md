# Hybrid Billing Solution Proposal
**Date:** November 4, 2025
**Status:** Awaiting User Decision

## Problem Statement

**Hybrid Practice Scenario:**
- Practice has a DME license
- Some patients: Practice bills directly (using their own DME license)
- Other patients: CollagenDirect bills (MD-DME model)
- Need an easy, intuitive way to indicate which billing model applies

**Full Referral-Only Scenario:**
- Practice does NOT have DME license
- All orders go through CollagenDirect billing
- Already handled by `users.is_referral_only = TRUE` flag

---

## Recommended Solution: **Per-Order Selection with Smart Defaults**

### Why This Approach?

**Flexibility:** Same patient might have different orders with different billing models (insurance changes, practice decisions, etc.)

**Accuracy:** Revenue tracking is precise per-order, not assumed

**Simplicity:** One decision point at order creation - no scattered settings

**Audit Trail:** Clear record of who billed what, when

---

## Implementation Details

### 1. Database Schema

Add `billed_by` column to `orders` table:

```sql
ALTER TABLE orders
ADD COLUMN billed_by VARCHAR(50) DEFAULT 'collagen_direct';
-- Values: 'collagen_direct' or 'practice_dme'
```

**Why not reuse `payment_type`?**
- `payment_type` = How patient pays (insurance/self_pay)
- `billed_by` = Who submits the claim/invoice

These are separate concepts:
- Insurance order → Could be billed by CollagenDirect OR practice DME
- Self-pay order → Could be billed by CollagenDirect OR practice DME

### 2. User Interface

#### Option A: Radio Buttons (Recommended)
```
┌─────────────────────────────────────────────────────────────┐
│ Create Order - Step 3: Billing                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Who will bill insurance/patient for this order?            │
│                                                             │
│ ⚪ CollagenDirect (MD-DME Model)                           │
│    → Order processed by CollagenDirect                     │
│    → CollagenDirect handles insurance/billing             │
│    → Product shipped from CollagenDirect                   │
│                                                             │
│ 🔵 My Practice (Direct Bill)                               │
│    → I will bill insurance/patient directly                │
│    → I will use my DME license                             │
│    → Product shipped to my practice                        │
│                                                             │
│ [ Previous ]  [ Submit Order ]                             │
└─────────────────────────────────────────────────────────────┘
```

**Benefits:**
- Crystal clear what each option means
- Visual distinction with icons
- Explanation text prevents confusion
- Default can be set based on practice type

#### Option B: Toggle Switch (Alternative)
```
┌─────────────────────────────────────────────────────────────┐
│ Order Billing:                                              │
│                                                             │
│  CollagenDirect  [    ●─────]  My Practice                 │
│                                                             │
│  ℹ️ This order will be billed by My Practice using your    │
│     DME license. Product will ship to your practice.       │
└─────────────────────────────────────────────────────────────┘
```

**Benefits:**
- Modern, clean interface
- Less visual clutter
- Quick to toggle

**My Recommendation: Option A (Radio Buttons)**
- More explicit (harder to make mistakes)
- Better for users who create orders infrequently
- Clear labeling prevents confusion

### 3. Smart Defaults

Set default based on practice type:

```javascript
// When order form loads
const userType = '<?php echo $user['user_type'] ?? 'practice_admin'; ?>';
const isDmeHybrid = userType === 'dme_hybrid';
const isDmeWholesale = userType === 'dme_wholesale';

// Default selection
let defaultBilledBy = 'collagen_direct';
if (isDmeWholesale) {
  defaultBilledBy = 'practice_dme'; // Wholesale always bills themselves
}
// dme_hybrid defaults to collagen_direct but can choose either
```

**Practice Type Defaults:**
| Practice Type | Default | Can Override? |
|--------------|---------|---------------|
| `practice_admin` (referral-only) | `collagen_direct` | ❌ No (locked) |
| `dme_hybrid` | `collagen_direct` | ✅ Yes |
| `dme_wholesale` | `practice_dme` | ✅ Yes (but usually practice_dme) |

### 4. Order Display

Show billing indicator clearly on order cards:

```
┌─────────────────────────────────────────────────────────────┐
│ Order #1234 - CollagenMatrix Classic                        │
├─────────────────────────────────────────────────────────────┤
│ Status: ✓ Approved                                          │
│ Patient: John Smith                                         │
│ Frequency: 2x per week                                      │
│                                                             │
│ 💳 Billed By: CollagenDirect                                │
│ 💵 Payment: Insurance (Aetna)                               │
│                                                             │
│ [ View Details ]                                            │
└─────────────────────────────────────────────────────────────┘
```

With badge styling:
```html
<div class="billing-badge collagen-direct">
  💼 Billed by CollagenDirect
</div>

<div class="billing-badge practice-dme">
  🏥 Billed by Practice DME
</div>
```

### 5. Revenue Dashboard Filtering

Add ability to filter/segment revenue:

```
┌─────────────────────────────────────────────────────────────┐
│ Revenue Summary - November 2025                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Show: [All Orders ▼]  [CollagenDirect Only]  [Practice Only] │
│                                                             │
│  Total Revenue: $45,250                                     │
│  ├─ CollagenDirect: $32,100 (71%)                          │
│  └─ Practice DME: $13,150 (29%)                            │
│                                                             │
│  Orders This Month: 156                                     │
│  ├─ CollagenDirect: 112 orders                             │
│  └─ Practice DME: 44 orders                                │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### 6. Workflow Implications

**Different paths based on `billed_by`:**

| Aspect | `collagen_direct` | `practice_dme` |
|--------|-------------------|----------------|
| Admin Review | ✅ Required | ⚠️ Optional (practice decision) |
| Shipping | CollagenDirect warehouse | Practice address or patient |
| Documentation | Full insurance docs required | Practice handles |
| Pricing | MD-DME pricing model | Wholesale pricing |
| Revenue Tracking | CollagenDirect revenue | Practice revenue |

**Suggested workflow difference:**

```php
// In order creation/review logic
if ($order['billed_by'] === 'practice_dme') {
  // Practice is handling everything - minimal admin review
  $requiresAdminReview = false;
  $pricing = 'wholesale';
  $shipFrom = 'practice_warehouse';
} else {
  // CollagenDirect billing - full review process
  $requiresAdminReview = true;
  $pricing = 'md_dme';
  $shipFrom = 'collagen_direct_warehouse';
}
```

---

## Alternative Solutions (Not Recommended)

### ❌ Per-Patient Billing Model
**Why not:**
- Patient's insurance might change
- Practice might want flexibility per-order
- Creates confusion: "Why can't I bill this order myself?"
- Less flexible than per-order

### ❌ Practice-Level Default Only
**Why not:**
- Hybrid practices need per-order flexibility
- Can't track revenue accurately if all orders assumed same billing
- No way to override for special cases

### ❌ Automatic Detection Based on Insurance
**Why not:**
- Practice might have contracts with some insurers, not others
- Creates "magic" behavior that's hard to understand
- Harder to audit/debug when billing is wrong

---

## Migration Strategy

### Step 1: Add Database Column
```sql
ALTER TABLE orders
ADD COLUMN billed_by VARCHAR(50) DEFAULT 'collagen_direct';

-- Backfill existing orders
UPDATE orders
SET billed_by = 'collagen_direct'
WHERE billed_by IS NULL;
```

### Step 2: Update Order Creation UI
- Add radio button selection
- Set smart default based on practice type
- Add validation

### Step 3: Update Order Display
- Show billing indicator on order cards
- Add to order details view
- Include in order exports

### Step 4: Update Revenue Dashboard
- Add filtering by `billed_by`
- Show breakdown in summary stats
- Update CSV exports to include column

### Step 5: Backfill Logic (Optional)
If we want to guess existing orders:
```sql
-- Referral-only practices → collagen_direct
UPDATE orders o
JOIN users u ON u.id = o.user_id
SET o.billed_by = 'collagen_direct'
WHERE u.is_referral_only = TRUE;

-- DME wholesale practices → practice_dme
UPDATE orders o
JOIN users u ON u.id = o.user_id
SET o.billed_by = 'practice_dme'
WHERE u.user_type = 'dme_wholesale';

-- Hybrid practices → collagen_direct (safer default)
UPDATE orders o
JOIN users u ON u.id = o.user_id
SET o.billed_by = 'collagen_direct'
WHERE u.user_type = 'dme_hybrid';
```

---

## UI Mockups

### Order Creation Form (Radio Button Version)

```html
<div class="form-section">
  <h3>Billing Information</h3>
  <p class="help-text">Select who will bill the insurance company or patient for this order.</p>

  <div class="radio-group">
    <label class="radio-card">
      <input type="radio" name="billed_by" value="collagen_direct" checked>
      <div class="radio-content">
        <div class="radio-icon">💼</div>
        <div class="radio-label">CollagenDirect (MD-DME)</div>
        <div class="radio-description">
          CollagenDirect will process this order, handle billing, and ship the product.
          Recommended for most orders.
        </div>
      </div>
    </label>

    <label class="radio-card">
      <input type="radio" name="billed_by" value="practice_dme">
      <div class="radio-content">
        <div class="radio-icon">🏥</div>
        <div class="radio-label">My Practice (Direct Bill)</div>
        <div class="radio-description">
          You will bill insurance/patient directly using your DME license.
          Product ships to your practice or patient per your instructions.
        </div>
      </div>
    </label>
  </div>
</div>
```

### CSS Styling

```css
.radio-card {
  display: block;
  padding: 1rem;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  margin-bottom: 1rem;
  cursor: pointer;
  transition: all 0.2s;
}

.radio-card:hover {
  border-color: #cbd5e0;
  background: #f7fafc;
}

.radio-card input:checked + .radio-content {
  border-color: #4299e1;
  background: #ebf8ff;
}

.radio-icon {
  font-size: 2rem;
  margin-bottom: 0.5rem;
}

.radio-label {
  font-weight: 600;
  font-size: 1.1rem;
  margin-bottom: 0.25rem;
}

.radio-description {
  font-size: 0.875rem;
  color: #64748b;
  line-height: 1.4;
}
```

---

## Questions for User

1. **Do you prefer radio buttons or toggle switch?**
   - My recommendation: Radio buttons (clearer, less error-prone)

2. **Should practice_dme orders skip admin review entirely?**
   - Or should there be a lightweight review for compliance?

3. **Pricing difference between billing models?**
   - Should wholesale pricing apply to `practice_dme` orders?
   - Should this be automatic or configurable?

4. **Documentation requirements:**
   - Do practice_dme orders need same level of documentation?
   - Or can practices handle their own compliance?

5. **Shipping destination:**
   - `collagen_direct` orders → Patient address (default)
   - `practice_dme` orders → Practice address or patient?

6. **Export/Reporting:**
   - Need separate CSV exports for each billing model?
   - Separate revenue dashboards?

---

## Recommendation Summary

**Implement: Per-Order Selection with Smart Defaults**

**Why:**
- ✅ Maximum flexibility for hybrid practices
- ✅ Accurate revenue tracking
- ✅ Clear audit trail
- ✅ Easy to understand and use
- ✅ Handles edge cases naturally

**Implementation Priority:**
1. Add `billed_by` column to orders
2. Update order creation form with radio buttons
3. Update order display to show billing indicator
4. Add revenue filtering by billing model
5. Adjust workflow (skip review for practice_dme if desired)

**Estimated Implementation Time:** 2-3 hours

---

## Next Steps

1. **User Decision:** Choose preferred UI approach (radio vs toggle)
2. **User Input:** Answer questions above
3. **Implementation:** I'll build the chosen solution
4. **Testing:** Verify with hybrid practice workflow
5. **Documentation:** Update user guides

---

**Ready to proceed when you provide feedback!**

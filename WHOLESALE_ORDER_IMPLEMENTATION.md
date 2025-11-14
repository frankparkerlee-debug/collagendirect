# Wholesale Order Implementation Plan

## Problem Statement
Currently, all orders use the same workflow regardless of billing type. This creates unnecessary administrative burden for wholesale orders where the physician bills their own DME license.

## Solution: Dual Order Workflows

### 1. Insurance Orders (billed_by = 'collagen_direct')
**Full Documentation Required:**
- Patient insurance cards
- AOB (Assignment of Benefits)
- Photo ID
- Detailed wound documentation
- Clinical photos
- Full ICD-10 coding

**Pricing:** Medicare rates (price_admin)
**Admin View:** Standard orders view

### 2. Wholesale Orders (billed_by = 'practice_dme')
**Simplified Requirements:**
- Product selection
- Quantity/duration
- Shipping address
- Basic patient info (name, DOB)
- Physician signature

**Pricing:** Wholesale rates (price_wholesale)
**Admin View:** Dedicated wholesale orders view at `/admin/wholesale-orders`

## Code Changes Required

### A. Order Creation Logic (portal/index.php lines 2323-2476)

#### Current Issues:
1. Line 2323: Only fetches `price_admin`
2. Lines 2426-2432: Calculates pieces, not boxes
3. Line 2467: Always uses `price_admin`
4. No differentiation in requirements based on billing type

#### Required Changes:

```php
// Line 2323 - Fetch both pricing models
$pr=$pdo->prepare("
  SELECT id, name, size, price_admin, price_wholesale, pieces_per_box,
         {$hcpcsCol} as hcpcs_code{$categorySelect}
  FROM products
  WHERE id=? AND active=TRUE
");
$pr->execute([$product_id]);
$prod=$pr->fetch(PDO::FETCH_ASSOC);
if(!$prod){ $pdo->rollBack(); jerr('Product not found'); }

// After line 2295 - Relax requirements for wholesale orders
$isWholesale = ($billed_by === 'practice_dme');

// Lines 2327-2333 - Make insurance docs optional for wholesale
if (!$isWholesale) {
  // Full insurance order requirements
  if(empty($p['id_card_path'])) jerr('Patient Photo ID required');
  if(empty($p['ins_card_path'])) jerr('Insurance card required');
  if(empty($p['aob_path'])) jerr('AOB required');
} else {
  // Wholesale orders only need basic info
  if(empty($sign_name)) jerr('Physician signature required');
}

// Lines 2426-2432 - Calculate boxes, not pieces
$pieces_per_box = (int)($prod['pieces_per_box'] ?? 10);
$changes_per_day = $freq_per_week / 7.0;
$total_changes = $changes_per_day * $duration_days;
$pieces_needed = $total_changes * $qty_per_change;
$total_pieces_with_refills = $pieces_needed * (1 + $refills_allowed);

// Calculate boxes needed (round up)
$boxes_needed = (int)ceil($total_pieces_with_refills / $pieces_per_box);

// Choose pricing based on billing type
$unit_price = $isWholesale ? $prod['price_wholesale'] : $prod['price_admin'];
$box_price = $unit_price * $pieces_per_box;
$total_price = $boxes_needed * $box_price;

// Line 2467 - Use calculated price
$ins->execute([
  // ... other fields ...
  $prod['id'],
  $unit_price,  // Changed from $prod['price_admin']
  // ... rest of fields ...
]);
```

### B. Orders Table Schema Addition

Add columns to track box-based ordering:

```sql
ALTER TABLE orders ADD COLUMN IF NOT EXISTS pieces_per_box INTEGER DEFAULT 10;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS boxes_ordered INTEGER;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS pieces_needed INTEGER;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS box_price DECIMAL(10,2);
ALTER TABLE orders ADD COLUMN IF NOT EXISTS total_order_value DECIMAL(10,2);
```

### C. Wholesale Orders Admin Page

**File:** `/admin/wholesale-orders.php`

**Features:**
1. **Filter by billing type** - Show only practice_dme orders
2. **Simplified columns:**
   - Order Date
   - Physician/Practice
   - Patient Name
   - Product
   - Boxes Ordered
   - Unit Price (wholesale)
   - Total Value
   - Status
   - Shipping Address

3. **Export functionality:**
   - CSV export for accounting
   - Include: Order #, Date, Practice, Product, Boxes, Unit Price, Total

4. **Bulk actions:**
   - Mark as Shipped
   - Mark as Paid
   - Generate packing slip

**Sample SQL Query:**
```sql
SELECT
  o.id,
  o.created_at,
  u.practice_name,
  u.first_name || ' ' || u.last_name as physician_name,
  p.first_name || ' ' || p.last_name as patient_name,
  o.product,
  o.boxes_ordered,
  o.product_price as unit_price_wholesale,
  o.total_order_value,
  o.status,
  o.shipping_address
FROM orders o
JOIN users u ON o.user_id = u.id
JOIN patients p ON o.patient_id = p.id
WHERE o.billed_by = 'practice_dme'
ORDER BY o.created_at DESC
```

### D. Order Display Updates

Update order views to show:
- **Boxes ordered** (not just pieces)
- **Price per box**
- **Total order value**
- **Billing type** (Insurance vs Wholesale)

## Implementation Steps

### Phase 1: Backend (Priority)
1. ✅ Add price_wholesale and pieces_per_box columns to products
2. ✅ Populate wholesale pricing for all 20 products
3. ⏳ Update order.create logic:
   - Fetch both pricing models
   - Calculate boxes needed
   - Use appropriate pricing
   - Relax requirements for wholesale
4. ⏳ Add tracking columns to orders table

### Phase 2: Admin Interface
1. Create `/admin/wholesale-orders.php`
2. Add navigation link in admin portal
3. Implement filtering and export
4. Add packing slip generation

### Phase 3: Reporting
1. Wholesale revenue report
2. Product popularity by billing type
3. Practice-level wholesale volume

## Example Calculations

### Insurance Order (collagen_direct):
```
Product: AlgiHeal Calcium Alginate 2x2
Frequency: 1 change/day (7/week)
Duration: 30 days
Medicare Rate: $6.28/piece
Pieces per box: 10

Pieces needed: 1/day × 30 days = 30 pieces
Boxes needed: CEIL(30 / 10) = 3 boxes
Charged to insurance: 30 pieces × $6.28 = $188.40
```

### Wholesale Order (practice_dme):
```
Product: AlgiHeal Calcium Alginate 2x2
Frequency: 1 change/day (7/week)
Duration: 30 days
Wholesale Rate: $2.50/piece
Pieces per box: 10

Pieces needed: 1/day × 30 days = 30 pieces
Boxes needed: CEIL(30 / 10) = 3 boxes
Cost to practice: 3 boxes × ($2.50 × 10) = $75.00
Practice bills patient/insurance at Medicare rate: $188.40
Practice profit: $188.40 - $75.00 = $113.40
```

## Benefits

1. **Reduced Administrative Burden** - Wholesale orders don't need full insurance documentation
2. **Clear Pricing** - Wholesale customers see their actual cost
3. **Better Tracking** - Separate view for wholesale vs insurance orders
4. **Accurate Inventory** - Track boxes, not just pieces
5. **Financial Clarity** - Revenue split by business model

## Next Actions

1. Implement order.create logic updates
2. Create wholesale orders admin page
3. Add order schema columns
4. Test both workflows thoroughly
5. Document for practice staff

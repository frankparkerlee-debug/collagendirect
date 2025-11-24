# Product Architecture Analysis

## Current State

### Database: Single `products` Table ✓
**Location**: PostgreSQL database
**Purpose**: Single source of truth for all products
**Managed via**: https://collagendirect.health/admin/products.php

**Key Fields**:
- `id` - Primary key
- `name` - Full product name (e.g., "Calcium Alginate 2x2 (A6196)")
- `sku` - Manufacturer SKU
- `size` - Product size (e.g., "2x2")
- `category` - Product category
- `hcpcs_code` - Medicare billing code (for referral orders)
- `price_admin` - Medicare reimbursement rate per PIECE (for referral orders)
- `price_wholesale` - Wholesale price per BOX (for practice DME orders)
- `pieces_per_box` - Number of pieces in each box
- `cost_per_box` - Our cost per box (for profit calculation)
- `active` - Whether product is available

### Order Types

#### 1. Referral Orders (Billed by Collagen Direct)
**Flow**: portal/index.php → `action=order.create`
**Selection Method**: Users select EXACT product by ID
**Code Reference**: Line 2892-2896, 2908-2910

```php
// Get product_id from wounds_data
$product_id = (int)$wounds_array[0]['product_id'];

// Fetch specific product
$pr = $pdo->prepare("SELECT id, name, size, price_admin, price_wholesale,
                      pieces_per_box, hcpcs_code FROM products
                      WHERE id=? AND active=TRUE");
$pr->execute([$product_id]);
```

**Revenue Calculation**:
```
Total Pieces = (duration_days / 7) × frequency_per_week × qty_per_change × (1 + refills)
Boxes Needed = CEIL(Total Pieces / pieces_per_box)
Revenue = Boxes × pieces_per_box × price_admin (per piece)
```

**Example**: Calcium Alginate 2x2 (A6196)
- User selects specific product ID from dropdown
- Product: $102.80 per piece (price_admin)
- 10 pieces per box
- Revenue per box: 10 × $102.80 = $1,028.00

#### 2. Wholesale Orders (Practice DME)
**Flow**: portal/wholesale-order-form.js → portal/index.php → `action=order.create.wholesale`
**Selection Method**: Users select EXACT product from grid display
**Code Reference**: wholesale-order-form.js lines 299-333

```javascript
// Product grid shows ALL products
wholesaleState.products.forEach(product => {
  const pricePerBox = parseFloat(product.price_wholesale || 0);
  const piecesPerBox = parseInt(product.pieces_per_box || 10);

  // Display: "$30.00 per box" and "10 pieces per box"
});
```

**Revenue Calculation**:
```
Revenue = boxes_ordered × price_wholesale (per box)
```

**Example**: Calcium Alginate 2x2 (A6196)
- User selects product, enters number of boxes
- Price: $25.00 per box (price_wholesale)
- Revenue: boxes × $25.00

## Current Problems

### Problem 1: 51 Products vs Expected 29 ❌
**Issue**: Database has 22 duplicate products
**Root Cause**: Old products without HCPCS codes + newer versions with HCPCS codes

**Duplicates**:
- Old: "Calcium Alginate 2x2" (ID: 1, no HCPCS)
- New: "Calcium Alginate 2x2 (A6196)" (ID: 40, has HCPCS) ✓

**Impact**:
- Confusing product selection in both referral and wholesale
- Revenue report may pull wrong pricing
- Users can select deprecated products

**Solution**: Deactivate 19 old products (keep HCPCS-coded versions)

### Problem 2: No Category-Based Selection ⚠️
**User's Concern**: "users pick the product category then the sizing"

**Current Reality**:
- **Referral Orders**: Select exact product by name (e.g., "Calcium Alginate 2x2 (A6196)")
- **Wholesale Orders**: Select exact product from grid

**This is CORRECT** because:
1. Each size has different HCPCS codes and pricing
2. Revenue calculations require exact product_id
3. Medicare billing requires specific HCPCS code per product

**If we changed to category→size**:
- Would need to map category + size → product_id
- Risk of selecting wrong HCPCS code
- More complex logic, more prone to errors

**Recommendation**: Keep current exact-product selection ✓

### Problem 3: Missing Product Fields
**Current Schema**: Has all required fields ✓
```
- name, size, sku, category ✓
- hcpcs_code, cpt_code ✓
- price_admin (referral per piece) ✓
- price_wholesale (wholesale per box) ✓
- pieces_per_box ✓
- cost_per_box (needs population) ⚠️
- active ✓
```

**Issue**: `cost_per_box` is NULL for all products
**Impact**: Profit calculations show $0.00
**Solution**: Populate cost_per_box with actual costs

## Revenue Report Integration

### Current Implementation ✓
**File**: admin/revenue-report.php lines 100, 242-256

```php
// Query pulls from products table
pr.name AS product_name,
pr.hcpcs_code AS cpt_code,
pr.pieces_per_box,
pr.price_wholesale,
COALESCE(pp.cost_per_box, pr.cost_per_box, 0) AS cost_per_box

// Referral revenue calculation
if ($hasRates && $cpt && isset($rates[$cpt])) {
  $cpt_rate_per_piece = $rates[$cpt];  // From reimbursement_rates table
} else {
  // Fallback to product_price or $150 default
  $cpt_rate_per_piece = $price_per_box / $pieces_per_box;
}
$revenue = $billable_pieces × $cpt_rate_per_piece;

// Wholesale revenue calculation
$price_per_box = (float)($order['price_wholesale'] ?? 150.0);
$revenue = $totalBoxes × $price_per_box;
```

**Issue**: Line 253 uses `150.0 / $pieces_per_box` as fallback
**Impact**: Products without HCPCS codes (e.g., Disposable Tubing Set) show $150/piece revenue
**Solution**: Use `$0` or pull from product.price_admin instead

## Correct Architecture ✓

```
Single Products Table (Source of Truth)
         ↓
    [admin/products.php] ← Edit/manage all products
         ↓
    ┌────────────────┴────────────────┐
    ↓                                  ↓
Referral Orders                 Wholesale Orders
(Collagen Direct bills)         (Practice DME bills)
         ↓                                  ↓
Select exact product            Select exact product from grid
by product_id                   by product_id
         ↓                                  ↓
Revenue = boxes × pieces ×      Revenue = boxes × price_wholesale
          price_admin
         ↓                                  ↓
    ┌────────────────┴────────────────┐
    ↓                                  ↓
    Revenue Report
    (Pulls from products table + reimbursement_rates)
```

## Required Actions

### Immediate (Before Cleanup)
1. ✅ Fix products.php boolean error (DONE - commit 7f01175)
2. ⚠️ DO NOT run clean-duplicate-products.php yet
3. ✅ Verify product selection works in both order types

### Phase 1: Verification
- [ ] Test referral order creation with current products
- [ ] Test wholesale order creation with current products
- [ ] Verify revenue report pulls correct pricing
- [ ] Check which products are actually used in existing orders

### Phase 2: Product Cleanup
- [ ] Identify which 29 products should remain active
- [ ] Verify no active orders use products being deactivated
- [ ] Run clean-duplicate-products.php (modified if needed)
- [ ] Verify revenue report still works after cleanup

### Phase 3: Data Population
- [ ] Populate cost_per_box for all active products
- [ ] Fix $150 fallback in revenue-report.php line 253
- [ ] Update reimbursement_rates table with current Medicare rates

## Questions for User

1. **Product Selection**: Are users confused by having to select "Calcium Alginate 2x2 (A6196)"
   instead of Category→Size? This is the correct way, but we can improve UI.

2. **29 Products**: Which exact 29 products should be active?
   - 23 wound care products (Calcium Alginate, Silver Alginate, Silicone Foam, etc.)
   - 6 Hydrapad products?
   - Any accessories (Tubing, Canisters, Kits)?

3. **Disposable Tubing Set**: This has $0.00 price_admin. Should it:
   - Be excluded from referral orders?
   - Be wholesale-only?
   - Have a set price_admin value?

4. **Practice-Specific Pricing**: Do some practices have custom wholesale pricing?
   - This exists in practice_pricing table
   - Should we migrate to products table?

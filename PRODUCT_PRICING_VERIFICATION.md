# Product Pricing Verification - All Selections and Discounts Based on admin/products.php

## ✓ CONFIRMED: All Product Data Comes From Products Table

**Single Source of Truth**: [admin/products.php](https://collagendirect.health/admin/products.php)

---

## 1. Product Selection - Order Creation

### Referral Orders
**File**: [portal/index.php](portal/index.php:2389)

```sql
-- Line 2389: Query products table for active products
SELECT p.id, p.name, p.size, p.price_admin, p.price_wholesale, p.pieces_per_box, p.hcpcs_code
FROM products p
LEFT JOIN practice_pricing pp ON pp.product_id = p.id AND pp.user_id = ?
WHERE p.active = TRUE
  AND (p.name NOT ILIKE '%deprecated%' OR p.name IS NULL)
  AND (p.category NOT ILIKE '%deprecated%' OR p.category IS NULL)
```

**Result**:
- ✓ Queries `products` table directly
- ✓ Gets default pricing from `products.price_wholesale` and `products.price_admin`
- ✓ Joins with `practice_pricing` for custom pricing (if exists)

### Wholesale Orders
**File**: [portal/wholesale-order-form.js](portal/wholesale-order-form.js:51)

```javascript
// Fetches same endpoint as referral orders
const response = await fetch('/portal/index.php?action=products');
```

**Result**:
- ✓ Uses same products query as referral orders
- ✓ All product data comes from `products` table

---

## 2. Pricing Logic - Order Creation

### Wholesale Order Pricing
**File**: [api/portal/wholesale-order.create.php](api/portal/wholesale-order.create.php:361-384)

```php
// Check for practice-specific pricing
SELECT custom_price, discount_percentage
FROM practice_pricing
WHERE user_id = ? AND product_id = ?

// Pricing priority:
if (custom_price exists) {
  // 1. Use practice-specific custom price
  $pricePerBox = custom_price × pieces_per_box;

} elseif (discount_percentage exists) {
  // 2. Apply discount to products.price_wholesale
  $pricePerBox = products.price_wholesale × (1 - discount_percentage/100);

} else {
  // 3. Use default pricing from products table
  $pricePerBox = products.price_wholesale;
}
```

**Result**:
- ✓ Custom pricing references `product_id` from products table
- ✓ Discount applies to `products.price_wholesale`
- ✓ Default falls back to `products.price_wholesale`
- ✓ **All paths reference products table**

### Referral Order Pricing
**File**: [portal/index.php](portal/index.php:2497, 2727, 2908)

```php
// Validate selected product against products table
SELECT id, name, size, price_admin, price_wholesale, pieces_per_box
FROM products
WHERE id = ? AND active = TRUE
```

**Result**:
- ✓ Uses `products.price_admin` for Medicare billing rate
- ✓ All pricing comes from products table

---

## 3. Practice-Specific Pricing Management

### Admin Interface
**File**: [admin/practice-pricing.php](admin/practice-pricing.php:34)

```php
// Get all products from products table
SELECT id, price_wholesale, pieces_per_box
FROM products
WHERE active = TRUE

// Calculate custom pricing based on products.price_wholesale
foreach ($products as $product) {
  $defaultPricePerBox = $product['price_wholesale'];
  $piecesPerBox = $product['pieces_per_box'];
  $defaultPricePerPiece = $defaultPricePerBox / $piecesPerBox;

  // Apply catalog discount to default price
  $customPricePerPiece = $defaultPricePerPiece × (1 - discount/100);

  // Store in practice_pricing table
  INSERT INTO practice_pricing (user_id, product_id, custom_price, discount_percentage)
  VALUES (?, ?, ?, ?);
}
```

**Result**:
- ✓ Queries `products` table for default pricing
- ✓ Custom prices calculated from `products.price_wholesale`
- ✓ Stores `product_id` reference to products table
- ✓ **practice_pricing is derived from products table**

---

## 4. Revenue Report

### Revenue Calculation
**File**: [admin/revenue-report.php](admin/revenue-report.php:100)

```sql
-- Join orders with products table
SELECT o.*,
       pr.name AS product_name,
       pr.hcpcs_code AS cpt_code,
       pr.pieces_per_box,
       pr.price_wholesale,
       COALESCE(pp.cost_per_box, pr.cost_per_box, 0) AS cost_per_box
FROM orders o
LEFT JOIN products pr ON pr.id = o.product_id
LEFT JOIN practice_pricing pp ON pp.user_id = o.user_id AND pp.product_id = o.product_id
```

**Pricing Fallback Chain** (lines 250-264):
```php
// 1. Try reimbursement_rates table (Medicare rates)
if (hasRates && cpt_code exists) {
  $rate = reimbursement_rates[cpt_code];

// 2. Try order's stored price
} elseif (order.product_price > 0) {
  $rate = order.product_price / pieces_per_box;

// 3. Fall back to products.price_wholesale (FIXED)
} else {
  $rate = products.price_wholesale / pieces_per_box;
}
```

**Result**:
- ✓ Joins with `products` table for current product data
- ✓ Uses `products.price_wholesale` as fallback
- ✓ No hardcoded $150 fallback (removed in commit 3f5fbb8)

---

## 5. Data Flow Diagram

```
┌─────────────────────────────────────────┐
│  admin/products.php                     │
│  (Single Source of Truth)               │
│                                         │
│  • price_wholesale (per box)            │
│  • price_admin (Medicare rate/piece)    │
│  • pieces_per_box                       │
│  • hcpcs_code                           │
│  • active status                        │
└────────────┬────────────────────────────┘
             │
             ├──────────────────────────────────────────┐
             │                                          │
             ↓                                          ↓
┌────────────────────────┐              ┌───────────────────────────┐
│  practice_pricing      │              │  Order Creation           │
│  (Optional Overrides)  │              │  (portal/index.php)       │
│                        │              │                           │
│  • product_id ────┐    │              │  SELECT FROM products     │
│  • custom_price   │    │              │  WHERE active = TRUE      │
│  • discount_%     │    │              │                           │
└───────────────────┼────┘              └─────────────┬─────────────┘
                    │                                  │
                    │  References                      │
                    │  products table                  │
                    │                                  │
                    └──────────────┬───────────────────┘
                                   │
                                   ↓
                    ┌──────────────────────────────┐
                    │  Wholesale Order Creation    │
                    │  (wholesale-order.create)    │
                    │                              │
                    │  Pricing Priority:           │
                    │  1. practice_pricing.custom  │
                    │  2. products × discount_%    │
                    │  3. products.price_wholesale │
                    └──────────────┬───────────────┘
                                   │
                                   ↓
                    ┌──────────────────────────────┐
                    │  Revenue Report              │
                    │  (admin/revenue-report.php)  │
                    │                              │
                    │  JOIN products               │
                    │  JOIN practice_pricing       │
                    │                              │
                    │  Fallback: products table    │
                    └──────────────────────────────┘
```

---

## 6. Database Schema Relationships

```sql
-- Products table (master)
CREATE TABLE products (
  id SERIAL PRIMARY KEY,
  sku VARCHAR(100),
  name VARCHAR(255),
  price_wholesale DECIMAL(10,2),  -- Default price per box
  price_admin DECIMAL(10,2),      -- Medicare rate per piece
  pieces_per_box INTEGER,
  hcpcs_code VARCHAR(20),
  active BOOLEAN DEFAULT TRUE
);

-- Practice-specific pricing (overrides)
CREATE TABLE practice_pricing (
  id SERIAL PRIMARY KEY,
  user_id TEXT REFERENCES users(id),
  product_id INTEGER REFERENCES products(id),  -- ← References products table
  custom_price DECIMAL(10,2),                   -- Per piece price
  discount_percentage DECIMAL(5,2),
  UNIQUE(user_id, product_id)
);

-- Orders (historical records)
CREATE TABLE orders (
  id TEXT PRIMARY KEY,
  user_id TEXT REFERENCES users(id),
  product_id INTEGER REFERENCES products(id),  -- ← References products table
  product TEXT,                                -- Snapshot of name
  product_price DECIMAL(10,2),                 -- Snapshot of price
  -- ... other fields
);
```

**Foreign Key Relationships**:
- ✓ `practice_pricing.product_id` → `products.id`
- ✓ `orders.product_id` → `products.id`
- ✓ All pricing references products table via `product_id`

---

## 7. Verification Checklist

- [x] Product list queries products table (`portal/index.php:2389`)
- [x] Wholesale pricing uses products table (`wholesale-order.create.php:382`)
- [x] Practice discounts reference products table (`practice-pricing.php:34`)
- [x] Custom pricing stored with product_id reference
- [x] Revenue report joins with products table (`revenue-report.php:100`)
- [x] No hardcoded fallback prices (removed $150 fallback)
- [x] All active products come from `products.active = TRUE`
- [x] Deactivating products in admin affects all queries

---

## 8. Impact of Changes in admin/products.php

When you update products at [admin/products.php](https://collagendirect.health/admin/products.php):

### Immediate Effects:
- ✓ Product list in order forms updates
- ✓ Default wholesale pricing changes
- ✓ Medicare rates change (if you update price_admin)
- ✓ Deactivating products removes them from selection

### Existing Orders:
- ✗ Historical order prices don't change (snapshot stored)
- ✓ Revenue report still shows product name/HCPCS (via JOIN)
- ✓ Can update pieces_per_box and affects revenue calculations

### Practice-Specific Pricing:
- ⚠️ Catalog discounts recalculate from new products.price_wholesale
- ⚠️ Custom prices remain unchanged (need manual update)
- ✓ Percentage discounts auto-apply to new prices

---

## Conclusion

✓ **VERIFIED**: All product selections and discounts are based on the products table managed at [admin/products.php](https://collagendirect.health/admin/products.php).

**No alternative product sources exist**. The products table is the single authoritative source for:
- Product availability (active status)
- Default pricing (price_wholesale, price_admin)
- Product metadata (name, size, HCPCS, pieces_per_box)
- Practice-specific pricing calculations
- Revenue report data

Any changes made in the admin products interface immediately affect new orders and pricing calculations.

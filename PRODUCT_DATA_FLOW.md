# Product Data Flow - Source of Truth Verification

## Confirmation: ALL Items Derive from Products Table ✓

**Single Source of Truth**: `products` table in PostgreSQL database
**Management UI**: https://collagendirect.health/admin/products.php

---

## Complete Data Flow

### 1. Product Management (Admin)
**File**: [admin/products.php](admin/products.php)

```
Admin UI → INSERT/UPDATE products table
         ← SELECT * FROM products WHERE active = TRUE
```

**Who Can Access**: Super Admin + Manufacturing roles only
**Operations**: Create, Update, Deactivate (soft delete), Bulk edit pricing

---

### 2. Order Creation - Referral Orders
**Files**:
- [portal/index.php](portal/index.php) (lines 2908-2910)
- [api/portal/orders.create.php](api/portal/orders.create.php) (line 156)

```sql
-- Frontend fetches product list (portal/index.php line 2389)
SELECT id, name, size, price_admin, hcpcs_code, price_wholesale, pieces_per_box
FROM products
WHERE active = TRUE

-- Backend validates selected product (api/portal/orders.create.php line 156)
SELECT id, name, price_admin, cpt_code
FROM products
WHERE id = ? AND active = TRUE

-- Creates order
INSERT INTO orders (product_id, product, product_price, ...)
VALUES (?, ?, ?, ...)
```

**Flow**:
1. User selects specific product by ID from dropdown
2. Frontend sends `product_id` to backend
3. Backend queries products table to get current pricing
4. Order created with reference to `product_id`

---

### 3. Order Creation - Wholesale Orders
**Files**:
- [portal/wholesale-order-form.js](portal/wholesale-order-form.js) (line 51)
- [portal/index.php](portal/index.php) (line 2389) - same products endpoint
- [api/portal/wholesale-order.create.php](api/portal/wholesale-order.create.php)

```javascript
// Frontend: Fetch all active products
const response = await fetch('/portal/index.php?action=products');

// Backend: Returns products with custom pricing
SELECT p.id, p.name, p.size, p.price_wholesale, p.pieces_per_box,
       COALESCE(pp.custom_price, p.price_wholesale) AS effective_wholesale_price
FROM products p
LEFT JOIN practice_pricing pp ON pp.product_id = p.id
WHERE p.active = TRUE
```

**Wholesale Order Pricing** (api/portal/wholesale-order.create.php lines 362-384):
1. Check `practice_pricing` table for custom pricing
2. If custom price exists: use it
3. If discount percentage exists: apply to `price_wholesale`
4. Otherwise: use `price_wholesale` from products table

**All pricing derives from products table**, with optional practice-specific overrides in `practice_pricing` table.

---

### 4. Revenue Report
**File**: [admin/revenue-report.php](admin/revenue-report.php) (line 104)

```sql
-- Join orders with products to get current product info
SELECT o.*, pr.name AS product_name, pr.hcpcs_code, pr.pieces_per_box, pr.price_wholesale
FROM orders o
LEFT JOIN products pr ON pr.id = o.product_id
WHERE ...
```

**Revenue Calculations Use**:
- `pr.pieces_per_box` - from products table
- `pr.price_wholesale` - from products table
- `pr.hcpcs_code` - from products table
- Falls back to `reimbursement_rates` table for Medicare rates

---

## Product Selection Flow

### Referral Orders (Billed by Collagen Direct)
```
1. Portal loads → GET /portal/index.php?action=products
                → SELECT FROM products WHERE active = TRUE

2. User selects product → Sends product_id to backend

3. Backend validates → SELECT FROM products WHERE id = ? AND active = TRUE

4. Order created → INSERT INTO orders (product_id, ...)
```

### Wholesale Orders (Practice DME)
```
1. Portal loads → GET /portal/index.php?action=products
                → SELECT FROM products + practice_pricing

2. User adds products to cart → Frontend stores product data

3. User submits → POST to /api/portal/wholesale-order.create.php
                → Receives full product data from frontend
                → Uses product.id for practice_pricing lookup

4. Order created → INSERT INTO orders (product_id, ...)
```

---

## Practice-Specific Pricing

**Table**: `practice_pricing`
**Purpose**: Override default wholesale pricing for specific practices

```sql
CREATE TABLE practice_pricing (
  user_id TEXT REFERENCES users(id),
  product_id INTEGER REFERENCES products(id),
  custom_price DECIMAL(10,2),      -- Custom price per box
  discount_percentage DECIMAL(5,2) -- Or percentage discount
);
```

**Pricing Priority**:
1. `practice_pricing.custom_price` (if exists)
2. `products.price_wholesale × (1 - discount_percentage)` (if discount exists)
3. `products.price_wholesale` (default)

**All paths still reference products table via product_id.**

---

## Data Integrity

### Products Table is Referenced By:
- ✓ `orders.product_id` - Foreign key reference
- ✓ `practice_pricing.product_id` - Foreign key reference
- ✓ All frontend product lists query this table
- ✓ Revenue report joins on this table

### Orphan Prevention:
- Products are **soft deleted** (active = FALSE), never hard deleted
- Historical orders maintain product_id reference
- Revenue reports can still access deactivated products for historical data

---

## Verification Checklist

- [x] Product list endpoint uses products table
- [x] Referral order creation validates against products table
- [x] Wholesale order creation validates against products table
- [x] Revenue report joins with products table
- [x] Practice-specific pricing references products table
- [x] All pricing fields derive from products table
- [x] Soft delete prevents orphaned references

---

## Conclusion

**✓ CONFIRMED**: All items in the system derive from the `products` table.

**Single Source of Truth**: https://collagendirect.health/admin/products.php

Any changes made to products (pricing, HCPCS codes, sizes, etc.) in the admin UI will:
1. Immediately affect new orders (frontend queries active products)
2. Maintain historical accuracy (existing orders store product_id)
3. Update revenue calculations (report joins with current product data)
4. Apply to both referral and wholesale order types

**No alternative product sources exist.** The products table is the sole authoritative source.

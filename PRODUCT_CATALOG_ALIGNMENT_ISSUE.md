# Product Catalog Alignment Issue

## Problem Statement

You are correct - the product catalogs used for **Referral Orders** and **Wholesale Orders** are **NOT identical**. They use different filtering logic, which means physicians may see different products depending on which order type they're creating.

## Current State - Three Different Queries

### 1. Referral Ordering (`portal/index.php` action=`products`)

**File:** [portal/index.php:2364-2381](portal/index.php#L2364-L2381)

```sql
SELECT p.id, p.name, p.size, p.price_admin, p.hcpcs_code, ...
FROM products p
LEFT JOIN practice_pricing pp ON pp.product_id = p.id AND pp.user_id = ?
WHERE p.active = TRUE  -- ✓ Active products only
ORDER BY p.name ASC
```

**Filters:**
- ✅ `active = TRUE`
- ❌ **NO** deprecated filter
- ❌ **NO** deduplication
- **Result:** Shows ALL active products, including duplicates and deprecated items

---

### 2. Wholesale Ordering (`portal/wholesale-new.php`)

**File:** [portal/wholesale-new.php:38-68](portal/wholesale-new.php#L38-L68)

```sql
SELECT DISTINCT ON (
  CASE
    WHEN p.hcpcs_code IS NOT NULL THEN p.hcpcs_code || '|' || p.size
    ELSE 'NO_HCPCS|' || p.name || '|' || p.size
  END
)
  p.*, pp.custom_price, pp.discount_percentage, ...
FROM products p
LEFT JOIN practice_pricing pp ON pp.product_id = p.id AND pp.user_id = ?
WHERE p.active = true                                    -- ✓ Active products
  AND (p.name NOT ILIKE '%deprecated%' OR p.name IS NULL)     -- ✓ Exclude deprecated
  AND (p.category NOT ILIKE '%deprecated%' OR p.category IS NULL)  -- ✓ Exclude deprecated
ORDER BY [deduplication key], ...
```

**Filters:**
- ✅ `active = true`
- ✅ **YES** deprecated filter (excludes products with "deprecated" in name/category)
- ✅ **YES** deduplication (DISTINCT ON by HCPCS+size or name+size)
- **Result:** Shows deduplicated, non-deprecated products only

---

### 3. Practice Pricing (`admin/practice-pricing.php`)

**File:** [admin/practice-pricing.php:124-145](admin/practice-pricing.php#L124-L145)

```sql
SELECT DISTINCT ON (
  CASE
    WHEN hcpcs_code IS NOT NULL THEN hcpcs_code || '|' || size
    ELSE 'NO_HCPCS|' || name || '|' || size
  END
)
  id, name, size, price_wholesale, pieces_per_box, ...
FROM products
WHERE active = TRUE                                    -- ✓ Active products
  AND (name NOT ILIKE '%deprecated%' OR name IS NULL)        -- ✓ Exclude deprecated
  AND (category NOT ILIKE '%deprecated%' OR category IS NULL)  -- ✓ Exclude deprecated
ORDER BY [deduplication key], ...
```

**Filters:**
- ✅ `active = TRUE`
- ✅ **YES** deprecated filter
- ✅ **YES** deduplication
- **Result:** Shows deduplicated, non-deprecated products (same as wholesale)

---

## Impact

### Scenario: Physician creates orders

1. **Creating referral patient order** → Sees 50 products (includes duplicates + deprecated)
2. **Creating wholesale bulk order** → Sees 35 products (deduplicated, no deprecated)
3. **Viewing practice pricing** → Sees 35 products (deduplicated, no deprecated)

### Problems This Causes:

❌ **Inconsistent UX:** Same physician sees different product lists depending on context
❌ **Duplicate products:** Referral orders show "Collagen Dressing 7x7" AND "Collagen Drx 7x7" (same HCPCS A6023)
❌ **Deprecated products:** Referral orders may show old/discontinued items
❌ **Confusion:** Physician thinks "Why can't I find product X in wholesale when I saw it in referral orders?"

---

## Solution: Standardize All Three Queries

All three queries should use **IDENTICAL** filtering and deduplication:

```sql
-- Standard product query for ALL order types
SELECT DISTINCT ON (
  CASE
    WHEN p.hcpcs_code IS NOT NULL AND p.hcpcs_code != ''
      THEN p.hcpcs_code || '|' || LOWER(TRIM(COALESCE(p.size, '')))
    ELSE 'NO_HCPCS|' || LOWER(TRIM(p.name)) || '|' || LOWER(TRIM(COALESCE(p.size, '')))
  END
)
  p.id,
  p.name,
  p.size,
  p.price_admin,
  p.price_wholesale,
  p.pieces_per_box,
  p.hcpcs_code,
  p.category,
  p.can_be_primary,
  p.can_be_secondary,
  p.can_be_additional,
  -- Practice-specific pricing (if needed)
  pp.custom_price,
  pp.discount_percentage
FROM products p
LEFT JOIN practice_pricing pp ON pp.product_id = p.id AND pp.user_id = ?
WHERE p.active = TRUE
  AND (p.name NOT ILIKE '%deprecated%' OR p.name IS NULL)
  AND (p.category NOT ILIKE '%deprecated%' OR p.category IS NULL)
ORDER BY
  CASE
    WHEN p.hcpcs_code IS NOT NULL AND p.hcpcs_code != ''
      THEN p.hcpcs_code || '|' || LOWER(TRIM(COALESCE(p.size, '')))
    ELSE 'NO_HCPCS|' || LOWER(TRIM(p.name)) || '|' || LOWER(TRIM(COALESCE(p.size, '')))
  END,
  CASE WHEN p.hcpcs_code IS NOT NULL AND p.hcpcs_code != '' THEN 0 ELSE 1 END,
  CASE WHEN p.price_wholesale > 0 THEN 0 ELSE 1 END,
  LENGTH(p.name) DESC,
  p.id ASC
```

### Filters Applied:
1. ✅ `active = TRUE` - Only active products
2. ✅ Exclude deprecated products (name/category NOT ILIKE '%deprecated%')
3. ✅ Deduplicate by HCPCS+size (products with same HCPCS and size are duplicates)
4. ✅ Deduplicate by name+size (for products without HCPCS codes like Calcium Alginate)

---

## Files That Need Updates

### 1. `portal/index.php` (Referral Ordering)

**Location:** Lines 2329-2384 (action=`products`)

**Current issue:** No deprecated filter, no deduplication

**Fix:** Apply the same DISTINCT ON and deprecated filters as wholesale

---

### 2. `portal/index.php` (Individual Product Queries)

**Locations:**
- Line 2442: `order.create.wholesale` product fetch
- Line 2672: Another product fetch
- Line 2853: Product fetch with HCPCS

**Current issue:** These individual product fetches don't filter deprecated

**Fix:** Add deprecated filter to WHERE clause:
```sql
WHERE id = ? AND active = TRUE
  AND (name NOT ILIKE '%deprecated%' OR name IS NULL)
  AND (category NOT ILIKE '%deprecated%' OR category IS NULL)
```

---

## Expected Outcome After Fix

✅ **Referral orders** show same 35 products as wholesale
✅ **Wholesale orders** show same 35 products as referral
✅ **Practice pricing** shows same 35 products as both
✅ No duplicates like "Collagen Dressing" vs "Collagen Drx"
✅ No deprecated products visible in any order type
✅ Consistent UX - physician sees identical catalog everywhere

---

## Pricing Model Differences (CORRECT - Keep As-Is)

The **products table is shared**, but pricing differs based on order type:

| Order Type | Pricing Model | Field Used |
|------------|---------------|------------|
| **Referral** | CPT/HCPCS billing | `price_admin` (per piece) |
| **Wholesale** | Direct billing | `price_wholesale` (per box) |

This is **correct** - same products, different pricing based on billing model.

What's **incorrect** is showing different product LISTS to the same physician.

---

## Diagnostic Tool

Run this to verify the issue on production:

```bash
curl -s https://collagendirect.health/admin/compare-product-queries.php
```

This will show:
- How many products each query returns
- Which products are exclusive to each query
- Whether catalogs are aligned

---

## Priority

**HIGH** - This affects data integrity and user experience. Physicians should see the same product catalog regardless of order type. The only difference should be the pricing model applied.

# Product Schema Redesign

## Current Schema Problems

### Existing Columns (from admin/products.php and portal/index.php)
```sql
id                      SERIAL PRIMARY KEY
sku                     VARCHAR(100)      -- Keep as reference_number
name                    VARCHAR(255)      -- Rename to product_name
description             TEXT              -- Remove (not used)
size                    VARCHAR(50)       -- Keep
category                VARCHAR(100)      -- Remove (use boolean flags instead)
hcpcs_code              VARCHAR(20)       -- Keep
cpt_code                VARCHAR(20)       -- Remove (duplicate of hcpcs_code)
price_admin             DECIMAL(10,2)     -- Rename to medicare_allowable
price_wholesale         DECIMAL(10,2)     -- Rename to price_per_box
price_referral          DECIMAL(10,2)     -- Remove (same as price_admin)
pieces_per_box          INTEGER           -- Keep
cost_per_box            DECIMAL(10,2)     -- Keep
can_be_primary          BOOLEAN           -- Rename to primary_dressing
can_be_secondary        BOOLEAN           -- Rename to secondary_dressing
can_be_additional       BOOLEAN           -- Rename to additional_supplies
active                  BOOLEAN           -- Keep
created_at              TIMESTAMP         -- Keep
```

### Missing Columns (from your requirements)
- `brand` - Product manufacturer/brand
- `product` - Base product name without size
- `exudate_minimal` - Can handle minimal exudate (Yes/No)
- `exudate_moderate` - Can handle moderate exudate (Yes/No)
- `exudate_heavy` - Can handle heavy exudate (Yes/No)
- `price_per_piece` - Calculate as price_per_box / pieces_per_box

## Proposed New Schema

```sql
CREATE TABLE products (
  -- Identity
  id                    SERIAL PRIMARY KEY,
  reference_number      VARCHAR(100) NOT NULL UNIQUE,  -- Was: sku
  brand                 VARCHAR(100),                   -- NEW: Brand/manufacturer
  product               VARCHAR(255) NOT NULL,          -- NEW: Base product type (e.g., "Calcium Alginate")
  size                  VARCHAR(50) NOT NULL,           -- KEEP SEPARATE: Product dimensions (e.g., "2x2")
  hcpcs_code            VARCHAR(20),                    -- Keep

  -- REMOVED: product_name (display as: product + " " + size + " (" + hcpcs_code + ")")

  -- Pricing (all per box unless specified)
  price_per_box         DECIMAL(10,2),                  -- Was: price_wholesale
  pieces_per_box        INTEGER NOT NULL DEFAULT 10,    -- Keep
  price_per_piece       DECIMAL(10,2),                  -- NEW: Calculated field
  medicare_allowable    DECIMAL(10,2),                  -- Was: price_admin
  cost_per_box          DECIMAL(10,2),                  -- Keep

  -- Product Classification
  primary_dressing      BOOLEAN DEFAULT FALSE,          -- Was: can_be_primary
  secondary_dressing    BOOLEAN DEFAULT FALSE,          -- Was: can_be_secondary
  additional_supplies   BOOLEAN DEFAULT FALSE,          -- Was: can_be_additional

  -- Clinical Characteristics (NEW)
  exudate_minimal       BOOLEAN DEFAULT FALSE,          -- Can handle minimal exudate
  exudate_moderate      BOOLEAN DEFAULT FALSE,          -- Can handle moderate exudate
  exudate_heavy         BOOLEAN DEFAULT FALSE,          -- Can handle heavy exudate

  -- System
  active                BOOLEAN DEFAULT TRUE,           -- Keep
  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_products_reference ON products(reference_number);
CREATE INDEX idx_products_active ON products(active);
CREATE INDEX idx_products_hcpcs ON products(hcpcs_code);
CREATE INDEX idx_products_primary ON products(primary_dressing) WHERE primary_dressing = TRUE;
CREATE INDEX idx_products_exudate ON products(exudate_minimal, exudate_moderate, exudate_heavy);

-- Calculated field trigger
CREATE OR REPLACE FUNCTION calculate_price_per_piece()
RETURNS TRIGGER AS $$
BEGIN
  IF NEW.pieces_per_box > 0 AND NEW.price_per_box IS NOT NULL THEN
    NEW.price_per_piece = NEW.price_per_box / NEW.pieces_per_box;
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_price_per_piece
  BEFORE INSERT OR UPDATE ON products
  FOR EACH ROW
  EXECUTE FUNCTION calculate_price_per_piece();
```

## Field Definitions

### Core Fields
- **reference_number**: Manufacturer SKU/reference (e.g., "MD0202CS")
- **brand**: Manufacturer name (e.g., "Medline", "McKesson", "Hydrapad")
- **product**: Base product type (e.g., "Calcium Alginate", "Silicone Foam")
- **size**: Product dimensions - STORED SEPARATELY (e.g., "2x2", "4x4", "6x6")
- **hcpcs_code**: Medicare billing code (e.g., "A6196", "A6212")

**Display Name**: Constructed dynamically as: `product + " " + size + " (" + hcpcs_code + ")"`
Example: "Calcium Alginate" + "2x2" + "(A6196)" = "Calcium Alginate 2x2 (A6196)"

**Benefits of Separate Fields**:
- Easy filtering by size (all 2x2 products, all 4x4 products)
- Easy sorting by product type or size
- Can search products independent of size
- Avoids duplication in storage

### Pricing Fields
- **price_per_box**: Wholesale price charged to practice (for Practice DME orders)
- **pieces_per_box**: Number of individual pieces in each box
- **price_per_piece**: Auto-calculated (price_per_box ÷ pieces_per_box)
- **medicare_allowable**: Medicare reimbursement rate per piece (for Referral orders)
- **cost_per_box**: Our cost to procure/produce

### Classification Fields (Boolean - can be multiple)
- **primary_dressing**: Can be used as primary wound dressing
- **secondary_dressing**: Can be used as secondary dressing
- **additional_supplies**: Supplies/accessories (tubing, canisters, etc.)

### Clinical Characteristics (Boolean - can be multiple)
- **exudate_minimal**: Suitable for wounds with minimal drainage
- **exudate_moderate**: Suitable for moderate drainage
- **exudate_heavy**: Suitable for heavy drainage

## Migration Strategy

### Phase 1: Add New Columns (Non-Breaking)
```sql
-- Add new columns without dropping old ones
ALTER TABLE products ADD COLUMN IF NOT EXISTS brand VARCHAR(100);
ALTER TABLE products ADD COLUMN IF NOT EXISTS product VARCHAR(255);
ALTER TABLE products ADD COLUMN IF NOT EXISTS price_per_piece DECIMAL(10,2);
ALTER TABLE products ADD COLUMN IF NOT EXISTS exudate_minimal BOOLEAN DEFAULT FALSE;
ALTER TABLE products ADD COLUMN IF NOT EXISTS exudate_moderate BOOLEAN DEFAULT FALSE;
ALTER TABLE products ADD COLUMN IF NOT EXISTS exudate_heavy BOOLEAN DEFAULT FALSE;

-- Rename columns via aliases (keep old names working)
ALTER TABLE products RENAME COLUMN sku TO reference_number;
ALTER TABLE products RENAME COLUMN name TO product_name;
ALTER TABLE products RENAME COLUMN price_admin TO medicare_allowable;
ALTER TABLE products RENAME COLUMN price_wholesale TO price_per_box;
ALTER TABLE products RENAME COLUMN can_be_primary TO primary_dressing;
ALTER TABLE products RENAME COLUMN can_be_secondary TO secondary_dressing;
ALTER TABLE products RENAME COLUMN can_be_additional TO additional_supplies;
```

### Phase 2: Populate New Fields
```sql
-- Extract brand from reference number
UPDATE products SET brand = CASE
  WHEN reference_number LIKE 'HYDRA%' THEN 'Hydrapad'
  WHEN reference_number LIKE 'MD%' THEN 'Medline'
  WHEN reference_number LIKE 'GEN%' THEN 'Generic'
  WHEN reference_number LIKE 'NP%' THEN 'NisusOne'
  WHEN reference_number LIKE 'AR%' THEN 'Alliance'
  ELSE 'Unknown'
END;

-- Extract product type from name (remove size and HCPCS)
-- Example: "Calcium Alginate 2x2 (A6196)" → "Calcium Alginate"
UPDATE products SET product = TRIM(
  REGEXP_REPLACE(
    REGEXP_REPLACE(name, '\s*\d+\.?\d*\s*[xX×]\s*\d+\.?\d*\s*', ' '),  -- Remove size
    '\s*\([A-Z]\d{4}\)\s*', ''                                           -- Remove HCPCS
  )
);

-- NOTE: Size is already in separate 'size' column - no need to extract

-- Calculate price per piece
UPDATE products
SET price_per_piece = price_per_box / NULLIF(pieces_per_box, 0)
WHERE price_per_box IS NOT NULL AND pieces_per_box > 0;

-- Set exudate levels based on product type (needs clinical review)
UPDATE products SET
  exudate_minimal = TRUE,
  exudate_moderate = TRUE
WHERE product LIKE '%Calcium Alginate%' OR product LIKE '%Silver Alginate%';

UPDATE products SET
  exudate_moderate = TRUE,
  exudate_heavy = TRUE
WHERE product LIKE '%Foam%';

UPDATE products SET
  exudate_heavy = TRUE
WHERE product LIKE '%Super Absorb%';
```

### Phase 3: Update Code References
Files needing updates:
- `admin/products.php` - Update admin UI
- `portal/index.php` - Update order creation (line 2908)
- `admin/revenue-report.php` - Update queries (line 100)
- `portal/wholesale-order-form.js` - Update display (line 310)

### Phase 4: Remove Old Columns (Breaking Change)
```sql
-- Only after all code is updated
ALTER TABLE products DROP COLUMN IF EXISTS description;
ALTER TABLE products DROP COLUMN IF EXISTS cpt_code;
ALTER TABLE products DROP COLUMN IF EXISTS price_referral;
ALTER TABLE products DROP COLUMN IF EXISTS category;
```

## Code Impact Analysis

### Files Using Products Table

#### 1. admin/products.php (Product Management)
**Changes Needed**:
- Update INSERT/UPDATE queries to use new column names
- Add brand, product, exudate fields to form
- Remove description, category fields
- Update display table

#### 2. portal/index.php (Order Creation)
**Line 2908-2910**:
```php
// OLD
$pr = $pdo->prepare("SELECT id, name, size, price_admin, price_wholesale,
                      pieces_per_box, hcpcs_code FROM products...");

// NEW - Construct display name dynamically
$pr = $pdo->prepare("SELECT id,
                      CONCAT(product, ' ', size,
                        CASE WHEN hcpcs_code IS NOT NULL
                          THEN CONCAT(' (', hcpcs_code, ')')
                          ELSE '' END
                      ) AS display_name,
                      product, size, medicare_allowable, price_per_box,
                      pieces_per_box, hcpcs_code
                      FROM products...");
```

#### 3. admin/revenue-report.php (Revenue Calculations)
**Line 100**:
```php
// OLD
pr.name AS product_name, pr.hcpcs_code AS cpt_code, pr.pieces_per_box, pr.price_wholesale

// NEW
pr.product_name, pr.hcpcs_code AS cpt_code, pr.pieces_per_box, pr.price_per_box
```

**Line 242-256** (Pricing logic):
```php
// Use medicare_allowable instead of price_admin
$cpt_rate_per_piece = $product['medicare_allowable'] ?? 0;
```

#### 4. portal/wholesale-order-form.js (Wholesale Orders)
**Line 310**:
```javascript
// Display updated fields
const pricePerBox = parseFloat(product.price_per_box || 0);
```

## Data Migration Example

### Example Product: Calcium Alginate 2x2 (A6196)

**Before**:
```
id: 40
sku: MD0202CA
name: Calcium Alginate 2x2 (A6196)     ← Combined name
category: wound_care
size: 2x2
hcpcs_code: A6196
price_admin: 102.80       (per piece)
price_wholesale: 25.00    (per box)
pieces_per_box: 10
can_be_primary: true
can_be_secondary: false
can_be_additional: false
```

**After**:
```
id: 40
reference_number: MD0202CA
brand: Medline
product: Calcium Alginate              ← SEPARATE: Base product
size: 2x2                              ← SEPARATE: Size
hcpcs_code: A6196
price_per_box: 25.00      (wholesale)
pieces_per_box: 10
price_per_piece: 2.50     (auto-calculated: 25.00/10)
medicare_allowable: 102.80 (per piece for referral)
cost_per_box: [TBD]
primary_dressing: true
secondary_dressing: false
additional_supplies: false
exudate_minimal: true
exudate_moderate: true
exudate_heavy: false

DISPLAY NAME (generated):
"Calcium Alginate 2x2 (A6196)"  ← product + " " + size + " (" + hcpcs_code + ")"
```

## Benefits

1. **Clearer Naming**:
   - `medicare_allowable` vs `price_admin` - explicit purpose
   - `price_per_box` vs `price_wholesale` - explicit unit
   - `primary_dressing` vs `can_be_primary` - clearer intent

2. **Clinical Filtering**:
   - Can filter products by exudate level during order creation
   - Helps physicians select appropriate products

3. **Calculated Fields**:
   - `price_per_piece` auto-calculated from `price_per_box / pieces_per_box`
   - Eliminates calculation errors

4. **Better Organization**:
   - Brand tracking for inventory management
   - Product type extraction for reporting

5. **Removes Confusion**:
   - No more `price_admin` vs `price_referral` (they were the same)
   - No more `cpt_code` vs `hcpcs_code` (they were the same)
   - Category replaced with explicit boolean flags

## Rollout Plan

1. **Week 1**: Create migration script, test on staging
2. **Week 2**: Update admin/products.php UI
3. **Week 3**: Update order creation code
4. **Week 4**: Update revenue report
5. **Week 5**: Remove old columns after verification

## Open Questions

1. Should we populate exudate levels automatically or require manual entry?
2. Do we need additional clinical characteristics (antimicrobial, non-adherent, etc.)?
3. Should brand be required or optional?
4. How should we handle products without HCPCS codes (accessories)?

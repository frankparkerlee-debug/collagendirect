# Dual Pricing Implementation Plan

## Overview
Implement two pricing models for CollagenDirect:
1. **Wholesale Model** - Practice bills insurance, purchases at wholesale rates
2. **Referral/CPT Model** - CollagenDirect bills insurance, practice receives CPT revenue

## Database Changes

### Products Table
```sql
ALTER TABLE products ADD COLUMN price_wholesale DECIMAL(10,2);
ALTER TABLE products ADD COLUMN pieces_per_box INTEGER DEFAULT 10;
```

- `price_admin` → CPT/HCPCS billing rate (what insurance pays CollagenDirect)
- `price_wholesale` → Wholesale cost (what practice pays for direct bill)
- `pieces_per_box` → Number of pieces in a box (for quantity calculation)

## Pricing Logic

### When to Use Wholesale Pricing
```php
if ($billed_by === 'practice_dme') {
  // Use price_wholesale
  // Practice bills insurance directly
  // Practice pays wholesale cost
} else {
  // Use price_admin (CPT/HCPCS rate)
  // CollagenDirect bills insurance
  // Practice receives CPT revenue share
}
```

## Order Quantity Calculation

### Current Problem
Orders use generic "quantity" without considering:
- Frequency (changes per day/week)
- Duration (days of treatment)
- Pieces per box

### Required Logic
```
Example: 1 change per day × 14 days = 14 pieces needed
Product has 10 pieces per box
Boxes needed = CEIL(14 / 10) = 2 boxes

Example: 2 changes per day × 30 days = 60 pieces needed
Product has 10 pieces per box
Boxes needed = CEIL(60 / 10) = 6 boxes
```

### Implementation
```php
// In order.create handler
$pieces_needed = $frequency_per_week * ($duration_days / 7) * $qty_per_change;
$boxes_needed = ceil($pieces_needed / $product['pieces_per_box']);
$unit_price = ($billed_by === 'practice_dme')
  ? $product['price_wholesale']
  : $product['price_admin'];
$total_price = $boxes_needed * ($unit_price * $product['pieces_per_box']);
```

## Wholesale Pricing Data

From MD-DME Bulk Order Form:

### AlgiHeal - Calcium Alginate
| Product | REF # | Price/Piece | Pieces/Box | Price/Box |
|---------|-------|-------------|------------|-----------|
| 2"x2" | MD0202CA | $2.50 | 10 | $25.00 |
| 4.33"x4.33" | MD0404CA | $4.00 | 10 | $40.00 |
| 6"x6" | MD0606CA | $5.75 | 10 | $57.50 |
| 12" Rope | MDCAR | $5.00 | 5 | $25.00 |

### AlgiHeal AG - Silver Alginate
| Product | REF # | Price/Piece | Pieces/Box | Price/Box |
|---------|-------|-------------|------------|-----------|
| 2"x2" | MD0202SA | $2.75 | 10 | $27.50 |
| 4.33"x4.33" | MD0404SA | $4.75 | 10 | $47.50 |
| 6"x6" | MD0606SA | $6.25 | 10 | $62.50 |

### CuraFoam - Silicone Foam
| Product | REF # | Price/Piece | Pieces/Box | Price/Box |
|---------|-------|-------------|------------|-----------|
| 2"x2" | MD0202SFB | $2.00 | 10 | $20.00 |
| 4.13"x4.13" | MD0404SFB | $3.00 | 10 | $30.00 |
| 6"x6" | MD0606SFB | $4.50 | 10 | $45.00 |

### HydraPad - Super Absorbent
| Product | REF # | Price/Piece | Pieces/Box | Price/Box |
|---------|-------|-------------|------------|-----------|
| Non-adherent 2"x2" | MD0202SAN | $1.50 | 10 | $15.00 |
| Non-adherent 4.13"x4.13" | MD0404SAN | $2.50 | 10 | $25.00 |
| Non-adherent 8"x8" | MD0808SAN | $4.50 | 10 | $45.00 |
| Adherent 2"x2" | MD0202SAA | $1.75 | 10 | $17.50 |
| Adherent 4.13"x4.13" | MD0404SAA | $3.75 | 10 | $37.50 |
| Adherent 8"x8" | MD0808SAA | $5.00 | 10 | $50.00 |

### CollaHeal - Collagen
| Product | REF # | Price/Piece | Pieces/Box | Price/Box |
|---------|-------|-------------|------------|-----------|
| Pad 2"x2" | MD0202CS | $12.00 | 10 | $120.00 |
| Pad 7"x7" | MD0707CS | $90.00 | 10 | $900.00 |
| Particles 1g | MD001000 | $16.50 | 10 | $165.00 |

### Other Products
| Product | REF # | Price/Piece | Pieces/Box | Price/Box |
|---------|-------|-------------|------------|-----------|
| Wound Cleanser 8oz | MD08WCS | $4.00 | 6 | $24.00 |
| Bordered Gauze 4"x4" | GEN-15410 | $0.60 | 25 | $15.00 |
| Bordered Gauze 6"x6" | GEN-15610 | $1.23 | 25 | $30.80 |
| Bordered Gauze 8"x8" | GEN-15810 | $2.65 | 10 | $26.48 |
| Silicone Foam-Sacral 9"x9" | GEN-14700 | $6.50 | 10 | $65.00 |

## HCPCS Billing Rates (TODO)

**ACTION REQUIRED:** Extract data from "Dressing Rule Matrix.xlsx"
- HCPCS codes for each product
- Medicare billing rates (Texas MAC)
- Update `price_admin` column with these rates

## Implementation Steps

### Phase 1: Database Migration ✅
- [x] Add `price_wholesale` column
- [x] Add `pieces_per_box` column
- [x] Populate wholesale pricing from MD-DME form
- [ ] Populate HCPCS rates from Dressing Rule Matrix

### Phase 2: Order Creation Logic
- [ ] Update product selection to show both prices
- [ ] Calculate boxes needed based on frequency/duration
- [ ] Apply correct pricing based on `billed_by`
- [ ] Store order with:
  - `product_price` (unit price used)
  - `quantity` (boxes ordered)
  - `pieces_needed` (calculated pieces)
  - `pieces_per_box`

### Phase 3: Billing Route Configuration
- [ ] Ensure all users can access Billing Settings
- [ ] Add cash pay option
- [ ] Default route selection (wholesale vs referral)

### Phase 4: Reporting & Invoicing
- [ ] Wholesale orders show wholesale pricing
- [ ] Referral orders show CPT revenue
- [ ] Export includes pricing model indicator

## Files to Modify

1. `/portal/index.php` (order.create handler)
   - Line ~2323: Product pricing query
   - Line ~2467: Order INSERT with pricing

2. `/portal/add-wholesale-pricing.php` (migration script)
   - Run this to add columns and populate wholesale data

3. Product management UI
   - Show both pricing columns
   - Allow editing of pieces_per_box

## Testing Scenarios

1. **Wholesale Order (practice_dme)**
   - Create patient without insurance
   - Set default billing route to practice_dme
   - Create order: 1/day × 14 days
   - Verify: 2 boxes @ wholesale price

2. **Referral Order (collagen_direct)**
   - Create patient with insurance
   - Billing route = collagen_direct
   - Create order: same frequency/duration
   - Verify: Uses HCPCS pricing

3. **Multi-wound Order**
   - Different products per wound
   - Different frequencies
   - Verify: Correct box calculation per wound

## Revenue Models Explained

### Model 1: Wholesale (practice_dme)
```
Practice → Bills Insurance → Receives Payment
Practice → Pays CollagenDirect → Wholesale Price
Practice Revenue = Insurance Payment - Wholesale Cost
```

### Model 2: Referral (collagen_direct)
```
CollagenDirect → Bills Insurance → Receives HCPCS Rate
Practice → Receives CPT Code → CPT Revenue
CollagenDirect Revenue = HCPCS Rate - Product Cost - CPT Revenue Share
```

## Next Steps

1. Can you export the "Dressing Rule Matrix.xlsx" as CSV so I can extract the HCPCS codes and Medicare rates?
2. Should CPT revenue sharing be configurable per practice, or is it a fixed percentage?
3. Do we need to track CPT revenue separately in the orders table?

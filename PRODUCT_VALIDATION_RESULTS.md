# Product Validation Results

## Summary
- **Total products in database:** 17
- **Wholesale pricing populated:** 9 products
- **Products legitimately ON matrix:** 13 products
- **Products NOT on matrix (exclude from primary):** 4 products

## Wholesale Pricing Status

### Successfully Matched (9 products)
✅ AlgiHeal Alginate Dressing (AH-ALG-2X2) - $2.50/piece, 10/box
✅ AlgiHeal Alginate Dressing (AH-ALG-4X4) - $4.00/piece, 10/box
✅ AlgiHeal Alginate Dressing (AH-ALG-6X6) - $5.75/piece, 10/box
✅ AlgiHeal AG Silver Alginate (AH-AG-2X2) - $2.75/piece, 10/box
✅ CollaHeal Collagen Powder (CH-POW-1G) - $16.50/piece, 10/box
✅ CuraFoam Silicone Foam (CF-FOAM-2X2) - $2.00/piece, 10/box
✅ CuraFoam Silicone Foam (CF-FOAM-6X6) - $4.50/piece, 10/box
✅ 15-Day Alginate Kit (KIT-ALG-15) - $2.50/piece, 10/box
✅ 15-Day Silver Alginate Kit (KIT-AG-15) - $2.75/piece, 10/box

### Need Wholesale Pricing (8 products)
⚠️ CollaHeal Collagen Wound Dressing (CH-COL-2X2) - Need price
⚠️ CollaHeal Collagen Wound Dressing (CH-COL-7X7) - Need price
⚠️ HydraPad Super Absorbent (HP-SA-2X2) - Need price
⚠️ HydraPad Super Absorbent (HP-SA-8X8) - Need price
⚠️ HydraCare AG Silver Hydrogel (HC-AG-0.9OZ) - Need price
⚠️ HydraCare Amorphous Hydrogel (HC-GEL-0.9OZ) - Need price
⚠️ 15-Day Collagen Kit (KIT-COL-15) - Need price
⚠️ 30-Day Collagen Kit (KIT-COL-30) - Need price

## Dressing Rule Matrix Validation

### Products ON Matrix (13 products - OK for primary dressing)
✅ AlgiHeal Alginate Dressing 2x2 - Matches "Calcium Alginate 2x2" (A6196)
✅ AlgiHeal Alginate Dressing 4x4 - Matches "Calcium Alginate 4.33x4.33" (A6197)
✅ AlgiHeal Alginate Dressing 6x6 - Matches "Calcium Alginate 6x6" (A6197)
✅ AlgiHeal AG Silver Alginate 2x2 - Matches "Silver Alginate Dressing 2x2" (A6196)
✅ CollaHeal Collagen Powder 1G - Matches "Collagen Powder 1g" (A6010)
✅ CollaHeal Collagen Wound Dressing 2x2 - Matches "Collagen Dressing 2x2" (A6021)
✅ CollaHeal Collagen Wound Dressing 7x7 - Matches "Collagen Dressing 7x7" (A6023)
✅ CuraFoam Silicone Foam 2x2 - Matches "Silicone Foam Dressing (border) 2x2" (A6212)
✅ CuraFoam Silicone Foam 6x6 - Matches "Silicone Foam Dressing (border) 6x6" (A6213)
✅ HydraPad Super Absorbent 2x2 - Matches "Super Absorbent (Non-adherent) 2x2" (A6251)
✅ HydraPad Super Absorbent 8x8 - Matches "Super Absorbent (Non-adherent) 8x8" (A6253)

**Note:** Need to clarify if HydraPad products are Adherent or Non-adherent versions

### Products NOT ON Matrix (4 products - EXCLUDE from primary dressing)
❌ HydraCare AG Silver Hydrogel (HC-AG-0.9OZ) - Hydrogel not on matrix
❌ HydraCare Amorphous Hydrogel (HC-GEL-0.9OZ) - Hydrogel not on matrix
❌ 15-Day Collagen Kit (KIT-COL-15) - Kit bundle, not individual dressing
❌ 30-Day Collagen Kit (KIT-COL-30) - Kit bundle, not individual dressing

**Note:** Kits (15-Day Alginate, 15-Day Silver Alginate) contain products that ARE on the matrix, but the kits themselves are not listed as primary dressings

## Next Steps

1. **Add Wholesale Pricing for Missing Products**
   - Map CollaHeal 2x2 and 7x7 to MD-DME pricing
   - Map HydraPad 2x2 and 8x8 to MD-DME pricing
   - Determine if kits have wholesale pricing or if they're calculated

2. **Update System to Exclude Non-Matrix Products**
   - Remove HydraCare products from primary dressing selection
   - Consider if kits should be in a separate category

3. **Clarify Product Specifications**
   - Confirm if HydraPad products are Adherent or Non-adherent
   - Verify if we need 4x4 sizes for AlgiHeal and CuraFoam (matrix shows 4.13x4.13)

4. **Implement Order Pricing Logic**
   - Use `price_wholesale` when `billed_by='practice_dme'`
   - Use `price_admin` when `billed_by='collagen_direct'`
   - Calculate boxes needed: `CEIL(frequency × duration / pieces_per_box)`

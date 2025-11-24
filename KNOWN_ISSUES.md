# Known Issues

## Revenue Report: Multi-Product Orders Displayed Separately

**Issue**: When a referral order contains multiple products (order group), the revenue report shows each product as a separate line item instead of grouping them together.

**Example**: John Smith's order (d01b7c6a5a3641794062d49ec53a8baf) contains 3 products:
- Collagen Dressing 2×2
- Silicone Foam Dressing
- Disposable tubing set

All 3 products share the same:
- Frequency: 7×/week
- Duration: 30 days
- Patient/physician info

But they appear as 3 separate orders in the revenue report.

**Root Cause**: The revenue report doesn't implement order grouping logic. Orders table has an `order_group_id` field, but the report processes each order independently.

**Impact**:
- Revenue calculations are CORRECT (each product calculated properly)
- Display is confusing - looks like 3 separate orders
- Cannot see at a glance that products are part of one order

**Proper Fix Needed**:
1. Group orders by `order_group_id` in the query
2. Display grouped orders as a single row with:
   - Shared info (patient, date, frequency, duration) shown once
   - Product list shown together
   - Combined totals (boxes, cost, revenue)
3. Update CSV export to maintain grouping

**Workaround**: Look at the `order_id` or creation date to identify related orders.

---

## Missing Cost Data

**Issue**: Revenue report shows $0.00 for cost and profit because `cost_per_box` is not populated.

**Fix**: Run SQL to populate costs:
```sql
-- Set default product costs
UPDATE products SET cost_per_box = 75.00 WHERE hcpcs_code = 'A6021';
UPDATE products SET cost_per_box = 50.00 WHERE hcpcs_code = 'A6212';

-- Set practice-specific costs (if different)
UPDATE practice_pricing
SET cost_per_box = 70.00
WHERE user_id = 'PRACTICE_ID' AND product_id = 'PRODUCT_ID';
```

---

## AI Approval Scoring

**Current Status**: ✅ FIXED in commit 83d821b
- Document extraction working
- File paths resolved correctly
- Database schema errors fixed

**Testing Needed**: Create a new test patient to verify scoring works end-to-end.

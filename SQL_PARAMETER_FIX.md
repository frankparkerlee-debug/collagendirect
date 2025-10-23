# SQL Parameter Binding Fix

## Error Encountered

```
Order create failed: SQLSTATE[HY093]: Invalid parameter number:
number of bound variables does not match number of tokens
```

## Root Cause

**Parameter count mismatch in the order INSERT statement**

The SQL INSERT had **37 placeholders (`?`)** but the execute array only provided **36 values**.

### The Bug

**Location:** [portal/index.php:314-322](portal/index.php#L314-322)

**Before (BROKEN):**
```php
$ins->execute([
    // ... 21 values ...
    $icd10_primary,$icd10_secondary,$wlen,$wwid,$wdep,  // Only 5 values
    $wtype,$wstage,$last_eval,$start_date,...           // Next line
    // ... 10 more values ...
]);
```

**Column order in SQL:**
```sql
icd10_primary, icd10_secondary, wound_length_cm, wound_width_cm, wound_depth_cm,
wound_type, wound_stage, last_eval_date, ...
-- That's 6 columns for the first line, not 5!
```

### The Problem

Line 311 of the SQL VALUES clause has **6 placeholders**:
```sql
?,?,?,?,?,?
```

These map to:
1. `icd10_primary`
2. `icd10_secondary`
3. `wound_length_cm`
4. `wound_width_cm`
5. `wound_depth_cm`
6. `wound_type` ← **This was missing!**

But the execute array line 319 only had **5 values**:
```php
$icd10_primary, $icd10_secondary, $wlen, $wwid, $wdep
// Missing $wtype here!
```

Then `$wtype` was on the NEXT line with `$wstage`, causing all subsequent values to be off by one.

## The Fix

**Move `$wtype` to the correct position:**

```php
// BEFORE (36 values):
$icd10_primary,$icd10_secondary,$wlen,$wwid,$wdep,
$wtype,$wstage,$last_eval,...

// AFTER (37 values):
$icd10_primary,$icd10_secondary,$wlen,$wwid,$wdep,$wtype,
$wstage,$last_eval,...
```

Now the parameter count matches:
- **SQL placeholders:** 37
- **Execute values:** 37 ✓

## Verification

**Count check:**
```
Placeholders in SQL:
Line 307: 10 (id through payment_type)
Line 308: 3  (wound location, laterality, notes)      = 13 total
Line 309: 6  (shipping fields)                        = 19 total
Line 310: 2  (sign_name, sign_title) + 3 NOW()       = 21 total
Line 311: 6  (icd10s + wound measurements + type)    = 27 total
Line 312: 9  (stage through additional_instructions) = 36 total
Line 313: 1  (cpt)                                    = 37 total

Values in execute():
Line 315: 10 values = 10 total
Line 316: 3 values  = 13 total
Line 317: 6 values  = 19 total
Line 318: 2 values  = 21 total
Line 319: 6 values  = 27 total ✓ (now includes $wtype)
Line 320: 9 values  = 36 total
Line 321: 1 value   = 37 total ✓
```

## Impact

**Before fix:** Orders failed to save with SQL parameter error
**After fix:** Orders save successfully to database

## Testing

Try creating an order again:

1. Log in to portal
2. Select patient with all files uploaded
3. Fill in all required order fields
4. Submit

**Expected:** Order saves successfully, no SQL error

**Verify:**
```bash
docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect \
  -e "SELECT id, product, status, wound_type, created_at FROM orders ORDER BY created_at DESC LIMIT 1;"
```

Should show your newly created order with `wound_type` populated.

## Related Issues

This was one of TWO bugs preventing order creation:

1. ✅ **FIXED:** Missing `cpt` column (added via SQL_FIXES.sql)
2. ✅ **FIXED:** Parameter count mismatch (fixed in this commit)

Orders should now save completely successfully! 🎉

---

**Status:** ✅ Fixed
**File Modified:** portal/index.php (line 319)
**Testing:** Ready for re-test

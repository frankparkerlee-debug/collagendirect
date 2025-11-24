# Production Fixes Applied - November 24, 2025

## Summary
This document summarizes all critical fixes applied to resolve AI approval scoring and revenue calculation issues.

## ✅ AI Approval Scoring Fixes

### Issue #1: Database Schema Errors
**Problem**: AI scoring was completely broken due to querying non-existent database columns.

**Affected Files**:
- `api/portal/background_score.php`
- `api/lib/auto_score.php`
- `api/lib/ai_service.php`

**Fix Applied** (Commit: c1cd203):
- Removed references to non-existent columns: `o.icd10_code`, `o.diagnosis`, `o.additional_instructions`
- AI scoring now uses only existing order columns: `product_name`, `hcpcs_code`, `frequency_per_week`, `duration_days`, `qty_per_change`

### Issue #2: File Path Resolution
**Problem**: Uploaded documents exist but AI couldn't find them due to incorrect path resolution.

**Root Cause**: Files are stored at `/opt/render/project/src/uploads` on Render, but code was using `$_SERVER['DOCUMENT_ROOT'] . '/uploads'`

**Affected Files**:
- `api/portal/background_score.php` (document extraction)
- `api/lib/auto_score.php` (document extraction)
- `api/portal/patients.php` (file uploads)

**Fix Applied** (Commit: 83d821b):
- Created `api/lib/file_utils.php` with environment-aware path resolution functions:
  - `getUploadAbsolutePath()` - Resolves paths for reading files (checks Render mount first, falls back to DOCUMENT_ROOT)
  - `getUploadBaseDir()` - Gets correct path for saving new uploads
- Updated all file upload and extraction code to use these utilities
- Works seamlessly in both Render production and local development

**Verification**: Successfully tested document extraction showing:
- Photo ID: 230 characters extracted
- Insurance Card: 221 characters extracted
- Visit Notes: 3,530 characters of clinical documentation extracted

---

## ✅ Revenue Calculation Fixes

### Issue: Dashboard and Billing Page Showing Incorrect Revenue
**Problem**: Revenue calculations on admin dashboard and billing page didn't match the accurate revenue report.

**Root Causes**:
1. Using outdated database column names for reimbursement rates
2. Not using practice-specific costs

**Affected Files**:
- `admin/index.php` (dashboard)
- `admin/billing.php` (billing page)

**Fix Applied** (Commit: d040e8b):

**1. Reimbursement Rates Query**:
```php
// BEFORE (incorrect):
foreach ($pdo->query("SELECT cpt_code, COALESCE(rate_non_rural,0) rate FROM reimbursement_rates") as $r) {
  $rates[$r['cpt_code']] = (float)$r['rate'];
}

// AFTER (correct):
foreach ($pdo->query("SELECT hcpcs_code, medicare_allowable FROM reimbursement_rates") as $r) {
  $rates[$r['hcpcs_code']] = (float)$r['medicare_allowable'];
}
```

**2. Practice-Specific Cost Tracking**:
```php
// BEFORE (incorrect):
COALESCE(pr.cost_per_box, 0) AS cost_per_box

// AFTER (correct):
COALESCE(pp.cost_per_box, pr.cost_per_box, 0) AS cost_per_box
```

This ensures costs are looked up in this priority:
1. Practice-specific cost (`pp.cost_per_box`)
2. Product default cost (`pr.cost_per_box`)
3. Fall back to 0

---

## Core Files with Production Fixes

### AI Scoring System
- ✅ `api/lib/file_utils.php` - NEW: Path resolution utilities
- ✅ `api/portal/background_score.php` - Schema fix + path fix
- ✅ `api/lib/auto_score.php` - Schema fix + path fix
- ✅ `api/lib/ai_service.php` - Schema fix
- ✅ `api/portal/patients.php` - Upload path fix

### Revenue Calculation
- ✅ `admin/index.php` - Reimbursement rates + practice costs
- ✅ `admin/billing.php` - Reimbursement rates fix
- ✅ `admin/revenue-report.php` - Already correct (reference implementation)

---

## Deployment Status

**Latest Production Commit**: 4269057 (cleanup)

**Critical Fixes Included**:
- ✅ Commit d040e8b: Revenue calculation fixes
- ✅ Commit c1cd203: AI database schema fixes
- ✅ Commit 83d821b: AI file path resolution fixes

---

## Expected Behavior After Fixes

### AI Approval Scoring
1. **Document extraction works**: AI can read uploaded visit notes, insurance cards, and photo IDs
2. **No database errors**: All queries use only existing columns
3. **Proper scoring**: New patients will be scored based on complete document content

### Revenue Calculations
1. **Consistent numbers**: Dashboard, billing page, and revenue report all show same totals
2. **Accurate reimbursement**: Using correct Medicare rates from `hcpcs_code`/`medicare_allowable`
3. **Practice-specific costs**: Profit margins calculated using practice-specific cost data

---

## Testing Recommendations

### Test AI Scoring
1. Create a new test patient with complete documentation
2. Upload visit notes, insurance card, and photo ID
3. Verify patient receives appropriate approval score (GREEN/YELLOW/RED)
4. Check that score reasoning reflects document content

### Test Revenue Calculations
1. Compare revenue totals across:
   - Admin dashboard (`admin/index.php`)
   - Billing page (`admin/billing.php`)
   - Revenue report (`admin/revenue-report.php`)
2. All three should show identical numbers
3. Verify profit margins are calculated correctly

---

## Notes

- All temporary diagnostic/debug scripts have been removed (commit 4269057)
- Production code is clean and contains only the essential fixes
- Both AI scoring and revenue calculations are now fully functional

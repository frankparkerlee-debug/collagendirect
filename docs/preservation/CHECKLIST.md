# CollagenDirect Preservation Checklist

**Generated:** 2025-12-15
**Purpose:** Pre-change verification checklist to ensure no functionality is broken during system modifications.

---

## Pre-Change Verification

### Database Tables

Run these queries before and after changes to verify table structure integrity:

- [ ] All 24+ tables exist and have correct column counts
- [ ] Foreign key constraints are intact
- [ ] Indexes are present and functional
- [ ] Triggers are registered and firing

### Core User Functions

- [ ] User login works for physicians
- [ ] User login works for admins
- [ ] Password reset email sends
- [ ] New user registration creates account
- [ ] Session management works correctly

### Patient Management

- [ ] Create new patient
- [ ] Edit existing patient
- [ ] Upload patient documents (ID card, insurance card)
- [ ] Patient search by name works
- [ ] Patient list pagination works

### Order Management

- [ ] Create referral order
- [ ] Create wholesale order
- [ ] Edit order details
- [ ] Update order status
- [ ] Order search works
- [ ] Order filtering by status works

### Sales Rep Functions

- [ ] View sales rep list
- [ ] View sales rep detail page
- [ ] Update commission rate
- [ ] Assign clinic to rep
- [ ] Unassign clinic from rep
- [ ] Commission calculation works
- [ ] Commission ledger entries created correctly

### Wholesale Billing

- [ ] View wholesale orders
- [ ] Record payment (reduces balance correctly)
- [ ] Send invoice email
- [ ] Payment history tracks correctly
- [ ] Commission calculated on payment

### AI Features

- [ ] Approval score generation works
- [ ] Visit note generation works
- [ ] Medical necessity letter generation works
- [ ] Insurance card OCR works

### Email/SMS

- [ ] SMTP email sends
- [ ] Twilio SMS sends
- [ ] Delivery confirmation SMS works

---

## Post-Change Verification Queries

### Table Existence Check

```sql
SELECT table_name
FROM information_schema.tables
WHERE table_schema = 'public'
ORDER BY table_name;
```

Expected tables (minimum):
- admin_permissions
- admin_physicians
- admin_users
- eligibility_cache
- leads
- login_attempts
- orders
- outreach_campaigns
- outreach_log
- patients
- practice_locations
- preauth_audit_log
- preauth_requests
- preauth_rules
- products
- rep_commission_ledger
- rep_commission_payouts
- rep_commission_rates
- rep_signed_documents
- sales_reps
- users
- wholesale_payments

### Foreign Key Check

```sql
SELECT
  tc.table_name,
  kcu.column_name,
  ccu.table_name AS foreign_table_name,
  ccu.column_name AS foreign_column_name
FROM information_schema.table_constraints AS tc
JOIN information_schema.key_column_usage AS kcu
  ON tc.constraint_name = kcu.constraint_name
JOIN information_schema.constraint_column_usage AS ccu
  ON ccu.constraint_name = tc.constraint_name
WHERE tc.constraint_type = 'FOREIGN KEY'
ORDER BY tc.table_name;
```

### Index Check

```sql
SELECT
  tablename,
  indexname,
  indexdef
FROM pg_indexes
WHERE schemaname = 'public'
ORDER BY tablename, indexname;
```

### Trigger Check

```sql
SELECT
  trigger_name,
  event_manipulation,
  event_object_table,
  action_statement
FROM information_schema.triggers
WHERE trigger_schema = 'public'
ORDER BY event_object_table;
```

---

## Data Integrity Checks

### User-Patient Relationship

```sql
-- All patients should have valid user_id
SELECT COUNT(*) as orphaned_patients
FROM patients p
LEFT JOIN users u ON p.user_id = u.id
WHERE u.id IS NULL;
-- Expected: 0
```

### Order-Patient Relationship

```sql
-- All orders should have valid patient_id
SELECT COUNT(*) as orphaned_orders
FROM orders o
LEFT JOIN patients p ON o.patient_id = p.id
WHERE p.id IS NULL;
-- Expected: 0
```

### Rep-User Relationship

```sql
-- All sales reps should have valid user_id
SELECT COUNT(*) as orphaned_reps
FROM sales_reps sr
LEFT JOIN users u ON sr.user_id = u.id
WHERE u.id IS NULL;
-- Expected: 0
```

### Commission Integrity

```sql
-- All ledger entries should have valid rep_id
SELECT COUNT(*) as orphaned_commissions
FROM rep_commission_ledger rcl
LEFT JOIN sales_reps sr ON rcl.rep_id = sr.id
WHERE sr.id IS NULL;
-- Expected: 0
```

### Assigned Rep Integrity

```sql
-- All assigned_rep_id should reference valid sales_reps
SELECT COUNT(*) as invalid_assignments
FROM users u
LEFT JOIN sales_reps sr ON u.assigned_rep_id = sr.id
WHERE u.assigned_rep_id IS NOT NULL
AND u.assigned_rep_id != ''
AND sr.id IS NULL;
-- Expected: 0
```

---

## Critical Business Logic Tests

### Commission Calculation Test

```sql
-- Verify commission calculation formula
SELECT
  rcl.id,
  rcl.collected_amount,
  rcl.commission_rate,
  rcl.commission_amount,
  ROUND(rcl.collected_amount * rcl.commission_rate, 2) as expected_amount,
  CASE
    WHEN rcl.commission_amount = ROUND(rcl.collected_amount * rcl.commission_rate, 2)
    THEN 'PASS'
    ELSE 'FAIL'
  END as test_result
FROM rep_commission_ledger rcl
LIMIT 10;
```

### Balance Calculation Test

```sql
-- Verify order balance calculations
SELECT
  id,
  amount_due,
  amount_paid,
  balance_due,
  ROUND(amount_due - amount_paid, 2) as expected_balance,
  CASE
    WHEN balance_due = ROUND(amount_due - amount_paid, 2)
    THEN 'PASS'
    ELSE 'FAIL'
  END as test_result
FROM orders
WHERE billed_by = 'practice_dme'
AND amount_due IS NOT NULL
LIMIT 10;
```

### Rate Query Test

```sql
-- Verify current rate query returns correct result
SELECT
  sr.id as rep_id,
  u.first_name,
  u.last_name,
  (
    SELECT rate FROM rep_commission_rates rcr
    WHERE rcr.rep_id = sr.id
    AND (rcr.effective_date IS NULL OR rcr.effective_date <= CURRENT_DATE)
    ORDER BY rcr.effective_date DESC NULLS LAST, rcr.created_at DESC
    LIMIT 1
  ) as current_rate
FROM sales_reps sr
JOIN users u ON sr.user_id = u.id
WHERE sr.status = 'active';
```

---

## API Endpoint Tests

### Authentication

```bash
# Test login endpoint
curl -X POST https://[domain]/api/auth.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"test"}'
```

### Health Check

```bash
# Test health endpoint
curl https://[domain]/api/health.php
```

### ICD-10 Search

```bash
# Test ICD-10 lookup
curl "https://[domain]/api/icd10_search.php?q=diabetes"
```

---

## UI Verification

### Admin Panel - Core Pages

- [ ] `/admin/` - Dashboard loads
- [ ] `/admin/orders.php` - Order list loads
- [ ] `/admin/patients.php` - Patient list loads
- [ ] `/admin/products.php` - Product list loads
- [ ] `/admin/billing-wholesale.php` - Billing page loads
- [ ] `/admin/wholesale-orders.php` - Wholesale orders load

### Admin Panel - Admin Settings (Phase 10 Restructure)

- [ ] `/admin/platform/practices.php` - Practice Management loads
  - [ ] Practices tab with search/filter
  - [ ] Locations tab (flat view)
  - [ ] Practice Users tab (flat view)
- [ ] `/admin/platform/internal-users.php` - Internal Users loads (Super Admin/Admin only)
  - [ ] Create/Edit user works
  - [ ] Suspend/Reactivate works
  - [ ] Role visibility restrictions work
- [ ] `/admin/platform/distributors.php` - Distributors loads
  - [ ] Active Distributors tab
  - [ ] Pending Applications tab
  - [ ] Assignment Requests tab
  - [ ] Commission Payouts tab
- [ ] `/admin/platform/roles-permissions.php` - Roles & Permissions loads (Super Admin/Admin only)
  - [ ] Role Templates view
  - [ ] Permission Matrix view/edit
  - [ ] User Overrides tab (Super Admin only)
- [ ] `/admin/sales-reps.php` - Redirects to `/admin/platform/distributors.php`
- [ ] `/admin/sales-rep-detail.php?id=X` - Rep detail loads (back link to distributors)

### Portal

- [ ] `/portal/` - Dashboard loads
- [ ] Patient creation form works
- [ ] Order creation form works
- [ ] Order list displays correctly

---

## Rollback Procedure

If changes break functionality:

1. **Identify the breaking change** via error logs
2. **Restore from backup** if database changes made:
   ```bash
   pg_restore -d collagen_db backup_YYYYMMDD.sql
   ```
3. **Revert code changes**:
   ```bash
   git checkout [previous_commit_hash] -- [affected_files]
   ```
4. **Clear any caches** (PHP opcache, etc.)
5. **Re-run verification checklist**

---

## Backup Checklist

Before making changes:

- [ ] Database backup created
- [ ] Code committed to git
- [ ] `.env` file backed up (not in repo)
- [ ] Upload directory backed up (if applicable)
- [ ] Document current state in change log

---

## Change Log Template

```markdown
## Change: [Description]
**Date:** YYYY-MM-DD
**Files Modified:**
- file1.php
- file2.php

**Database Changes:**
- Added column X to table Y
- Created index on Z

**Pre-Change Verification:**
- [x] All tests passing
- [x] Database backed up

**Post-Change Verification:**
- [ ] All tests passing
- [ ] No new errors in logs

**Rollback Notes:**
If needed, revert commit [hash] and run migration rollback script.
```

---

*Run this checklist before and after any significant system changes.*

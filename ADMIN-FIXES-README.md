# Admin Fixes and Patient Status Workflow

## Issues Fixed

### 1. Tracking Number Error (admin/shipments.php and admin/orders.php)
**Error**: `SQLSTATE[22P02]: Invalid text representation: 7 ERROR: invalid input syntax for type integer`

**Root Cause**:
- The `order_status_changes.order_id` column was INTEGER, but `orders.id` is VARCHAR
- The database trigger was trying to cast VARCHAR to INTEGER which failed

**Fix**: Created migration script that:
- Changes `order_status_changes.order_id` to VARCHAR(64)
- Updates trigger to use correct columns (`tracking_number` and `carrier`)
- Removes the incorrect INTEGER cast

### 2. Undefined Carrier Key Errors (admin/orders.php)
**Error**: `Undefined array key "carrier"` at lines 152-154

**Root Cause**: The SELECT query didn't include the `carrier` column

**Fix**: Added explicit `COALESCE(o.carrier, '') AS carrier` to the query

### 3. Patient Status Column Missing (admin/patients.php)
**Error**: `SQLSTATE[42703]: Undefined column: 7 ERROR: column "status_comment" of relation "patients" does not exist`

**Root Cause**: The patient status migration hasn't been run yet

**Fix**: Created PHP-based migration that adds:
- `state` column (VARCHAR) for authorization status
- `status_comment` column (TEXT) for manufacturer feedback
- `status_updated_at` column (TIMESTAMP) for tracking changes
- `status_updated_by` column (VARCHAR) for audit trail

### 4. UI Improvements (admin/patients.php)
**Changes**:
- Removed "Contact" column from main table
- Contact info (email/phone) now shown in "View Details" section
- Added "Update Status" column with button for superadmin/manufacturer
- Removed duplicate "Update Status" button from details view

### 5. Portal Enhancements (portal/index.php)
**Added**: Authorization Status section on patient detail page showing:
- Color-coded status badges (Approved, Pending, Not Covered, etc.)
- Manufacturer notes/comments in styled box
- Last updated timestamp
- Status-specific border colors for visual clarity

---

## How to Apply Fixes

### Step 1: Deploy Code Changes
```bash
cd /Users/parkerlee/CollageDirect2.1/collagendirect
git push origin main
```

Then on the Render server, the code will auto-deploy.

### Step 2: Run Migrations

You have two options:

#### Option A: Run All Migrations at Once (Recommended)
```bash
cd /Users/parkerlee/CollageDirect2.1/collagendirect
./admin/run-all-migrations.sh
```

This will:
1. Fix the order status trigger
2. Add patient status columns
3. Verify both migrations succeeded

#### Option B: Run Migrations Individually

**Fix Order Status Trigger:**
```bash
curl -f "https://collagendirect.health/admin/fix-order-status-trigger.php"
```

**Add Patient Status Columns:**
```bash
curl -f "https://collagendirect.health/admin/run-patient-status-migration.php"
```

---

## Verification Steps

### 1. Test Tracking Numbers
1. Go to https://collagendirect.health/admin/shipments.php
2. Try updating a tracking number (e.g., `1Z12345E1505270452`)
3. Should save without errors

### 2. Test Order Status Changes
1. Go to https://collagendirect.health/admin/orders.php
2. Try clicking "Ship" or "Reject" on an order
3. Should work without carrier errors

### 3. Test Patient Status Updates
1. Go to https://collagendirect.health/admin/patients.php
2. Click "Update" button in the Update Status column
3. Select a status and add a comment
4. Should save successfully

### 4. Test Portal Status Display
1. Go to https://collagendirect.health/portal/ as a physician
2. View a patient detail page
3. Should see "Authorization Status" section with status badge
4. If manufacturer added comments, they should display

---

## Files Changed

### New Files
- `admin/fix-order-status-trigger.php` - Migration to fix trigger
- `admin/run-patient-status-migration.php` - Migration for patient status
- `admin/run-all-migrations.sh` - Convenience script to run both
- `admin/run-fix-order-trigger.sh` - Individual trigger fix script

### Modified Files
- `admin/orders.php` - Added carrier column to query
- `admin/patients.php` - UI improvements (contact column, Update Status button)
- `portal/index.php` - Added Authorization Status section to patient detail

---

## Database Changes

### order_status_changes table
```sql
ALTER TABLE order_status_changes
ALTER COLUMN order_id TYPE VARCHAR(64);
```

### patients table
```sql
ALTER TABLE patients
  ADD COLUMN state VARCHAR(50) DEFAULT 'pending',
  ADD COLUMN status_comment TEXT,
  ADD COLUMN status_updated_at TIMESTAMP,
  ADD COLUMN status_updated_by VARCHAR(64);

CREATE INDEX idx_patients_state ON patients(state);
```

---

## Important Notes

1. **Migration Scripts Are Idempotent**: They check if columns/changes already exist before applying them, so they're safe to run multiple times.

2. **No Downtime**: These migrations can be run on a live database without downtime.

3. **Backward Compatible**: The code works both before and after migrations by:
   - Checking for column existence before querying
   - Using COALESCE for potentially missing values
   - Graceful fallbacks for missing data

4. **Billing Attachments**: The attachments issue mentioned was actually working - orders are working correctly. If there are specific attachment issues, please provide the exact error message.

---

## Support

If you encounter any issues:
1. Check the migration output for errors
2. Verify the columns exist: `SELECT column_name FROM information_schema.columns WHERE table_name='patients' AND column_name LIKE 'status%';`
3. Check error logs for specific issues

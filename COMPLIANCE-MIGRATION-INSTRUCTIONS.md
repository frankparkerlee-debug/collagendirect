# Compliance Workflow Migration Instructions

## Overview
This migration adds comprehensive DME compliance tracking, role-based access control, and order status management to the CollagenDirect platform.

## What This Migration Does

### 1. **User Roles & DME License Tracking**
- Adds `role` field to users (physician, practice_admin, superadmin)
- Adds `has_dme_license` boolean to track if practice has DME license
- This affects entire order workflow and UI experience

### 2. **Order Compliance Fields**
- `delivery_location` - "patient" or "physician" (where to ship)
- `tracking_code` - UPS/FedEx/USPS tracking number
- `carrier` - Shipping carrier name
- `payment_method` - "insurance" or "cash"
- `cash_price` - Cash price amount if insurance denied
- `cash_price_approved_at` - When practice approved cash price
- `cash_price_approved_by` - User ID who approved
- `terminated_at` - When order was terminated
- `terminated_by` - User ID who terminated
- `termination_reason` - Why order was terminated
- `reviewed_at` - When super admin reviewed
- `reviewed_by` - Super admin user ID
- `review_notes` - Super admin notes
- `manufacturer_order_id` - Manufacturer's order ID
- `is_complete` - Boolean for completeness check
- `completeness_checked_at` - When completeness was checked
- `missing_fields` - Array of missing required fields

### 3. **Order Status Tracking**
New comprehensive status workflow:
- `draft` - Physician creating order
- `submitted` - Sent to super admin
- `under_review` - Super admin reviewing
- `incomplete` - Missing documentation
- `verification_pending` - Manufacturer verifying insurance
- `cash_price_required` - Insurance denied, needs practice approval
- `cash_price_approved` - Practice approved cash price
- `approved` - Ready to manufacture
- `in_production` - Being manufactured
- `shipped` - En route to patient/physician
- `delivered` - Completed
- `terminated` - Practice requested stop
- `cancelled` - Order cancelled

### 4. **New Tables**
- `order_status_history` - Audit trail of all status changes
- `order_alerts` - Notifications for physicians and super admins

### 5. **Database Functions**
- `check_order_completeness(order_id)` - Validates all required fields
- `log_order_status_change()` - Trigger that auto-logs status changes

## How to Run the Migration

### Option 1: Via Render Shell (Recommended)

1. Go to your Render dashboard
2. Open your web service
3. Click "Shell" tab
4. Run:
```bash
php run-compliance-migration.php
```

### Option 2: Via Render CLI

1. Make sure render CLI is installed and authenticated
2. From your local terminal:
```bash
render shell
php run-compliance-migration.php
```

### Option 3: Direct PostgreSQL Access

If you have direct psql access to your Render database:
```bash
psql YOUR_DATABASE_URL < migrations/compliance-workflow.sql
```

## Expected Output

You should see:
```
============================================
Running Compliance Workflow Migration
============================================

✓ Connected to database

Executing migration...

✓ Migration completed successfully!

Verifying schema updates...

Users table new columns:
  - has_dme_license (boolean)
  - role (character varying)

Orders table new columns:
  - carrier (character varying)
  - cash_price (numeric)
  - delivery_location (character varying)
  - is_complete (boolean)
  - missing_fields (ARRAY)
  - payment_method (character varying)
  - reviewed_at (timestamp without time zone)
  - terminated_at (timestamp without time zone)
  - tracking_code (character varying)

New tables created:
  - order_alerts
  - order_status_history

✓ check_order_completeness() function created
✓ order_status_change_trigger created

============================================
Migration completed successfully!
============================================
```

## After Migration

### 1. Set User Roles
Run this to ensure proper roles:
```sql
-- Super admins (already set in previous migration)
UPDATE users SET role = 'superadmin' WHERE email IN ('sparkingmatt@gmail.com', 'parker@senecawest.com');

-- Practice admins (if any)
UPDATE users SET role = 'practice_admin' WHERE email = 'some-practice-admin@example.com';

-- Everyone else is physician (default)
UPDATE users SET role = 'physician' WHERE role IS NULL;
```

### 2. Set DME License Status
For each practice, set whether they have their own DME license:
```sql
-- Example: Practice WITH DME license
UPDATE users SET has_dme_license = TRUE WHERE email = 'doctor@practice.com';

-- Example: Practice WITHOUT DME license (default)
UPDATE users SET has_dme_license = FALSE WHERE email = 'another@practice.com';
```

### 3. Update Existing Orders
Set default values for existing orders:
```sql
UPDATE orders SET delivery_location = 'patient' WHERE delivery_location IS NULL;
UPDATE orders SET payment_method = 'insurance' WHERE payment_method IS NULL;
UPDATE orders SET is_complete = FALSE WHERE is_complete IS NULL;
```

## Testing Order Completeness

To test the completeness checker:
```sql
-- Check a specific order
SELECT * FROM check_order_completeness('order_id_here');

-- This returns:
-- is_complete: true/false
-- missing_fields: array of missing field names
```

## Rollback (If Needed)

If something goes wrong, you can rollback with:
```sql
-- Remove new tables
DROP TABLE IF EXISTS order_alerts CASCADE;
DROP TABLE IF EXISTS order_status_history CASCADE;

-- Remove functions
DROP FUNCTION IF EXISTS check_order_completeness(VARCHAR);
DROP FUNCTION IF EXISTS log_order_status_change() CASCADE;

-- Remove new columns (optional - will lose data!)
ALTER TABLE users DROP COLUMN IF EXISTS role;
ALTER TABLE users DROP COLUMN IF EXISTS has_dme_license;
ALTER TABLE orders DROP COLUMN IF EXISTS delivery_location;
-- ... (add other columns as needed)
```

## Next Steps After Migration

1. **Super Admin Dashboard** - Build order review interface
2. **Cash Price Workflow** - Implement alerts when insurance is denied
3. **Order Termination** - Add UI for practices to request terminations
4. **Tracking Updates** - Add interface for super admin to add tracking codes
5. **DME License Toggle** - Add UI for practice admins to set license status
6. **Conditional UI** - Show different interfaces based on has_dme_license flag

## Support

If you encounter any errors during migration, check:
1. Database connection (ensure environment variables are set)
2. PostgreSQL version (should be 12+)
3. User permissions (need CREATE/ALTER permissions)

Error logs will show exactly which step failed.

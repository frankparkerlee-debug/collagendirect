# Run the Migration NOW

## All code has been pushed to GitHub and is deploying to Render.

Once the deployment completes (check Render dashboard), run the migration by visiting this URL in your browser:

```
https://collagendirect.onrender.com/portal/run-migration.php?key=change-me-in-production
```

**Important:** This URL includes a security key. The default key is `change-me-in-production`.

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

## After Running the Migration

1. **Delete the migration file for security:**
   The file `portal/run-migration.php` should be deleted after running to prevent unauthorized access.

2. **Verify your user roles:**
   - Login to https://collagendirect.onrender.com/portal/
   - Your session should persist for 7 days now
   - No more "Physician" job title in top left profile

3. **Next steps:**
   - Review [COMPLIANCE-IMPLEMENTATION-PLAN.md](COMPLIANCE-IMPLEMENTATION-PLAN.md)
   - Start building API endpoints
   - Build super admin dashboard

## What This Migration Does

- ✅ Adds persistent sessions (7-day cookies)
- ✅ Adds role system (physician, practice_admin, superadmin)
- ✅ Adds DME license tracking
- ✅ Adds 13-status order workflow
- ✅ Adds order completeness validation
- ✅ Adds cash price workflow
- ✅ Adds order termination tracking
- ✅ Adds tracking code management
- ✅ Creates audit trail tables
- ✅ Creates alert system

See full details in [COMPLIANCE-IMPLEMENTATION-PLAN.md](COMPLIANCE-IMPLEMENTATION-PLAN.md)

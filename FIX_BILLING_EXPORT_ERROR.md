# Fix Billing Export Error

## Error You're Seeing

```
Fatal error: Uncaught PDOException: SQLSTATE[42703]: Undefined column:
ERROR: column p.insurance_company does not exist
```

## The Problem

The billing export expects insurance fields in the `patients` table that don't exist yet.

## The Solution

Run the database migrations to add the missing columns.

## Step-by-Step Fix

### 1. Run All Migrations

Go to this URL in your browser:
```
https://collagendirect.health/admin/run-all-migrations.php
```

You should see output like:
```
=== Running All Migrations ===

Running: Add provider response fields
  ✓ Success

Running: Add comment read tracking
  ✓ Success

Running: Add wound photo upload and E/M billing tables
  ✓ Success

Running: Link wound photos to treatment orders
  ✓ Success

Running: Add insurance and billing fields to patients table
  ✓ Added column: sex
  ✓ Added column: insurance_company
  ✓ Added column: insurance_id
  ✓ Added column: group_number
  ✓ Success

Running: Add NPI field to users table
  ✓ Added column: npi
  ✓ Success

=== Migration Summary ===
Success: 6
Failed: 0

✓ All migrations completed successfully!
```

### 2. Verify the Fix

After migrations complete, try the billing export again:

1. Go to: `https://collagendirect.health/portal/?page=photo-reviews`
2. Click "Export CSV" button
3. Download should start successfully

## What Was Added

### To `patients` table:
- `sex` (VARCHAR 1) - Patient biological sex: M, F, or U
- `insurance_company` (VARCHAR 255) - Insurance provider name
- `insurance_id` (VARCHAR 100) - Member/policy ID
- `group_number` (VARCHAR 100) - Group/employer number

### To `users` table:
- `npi` (VARCHAR 10) - National Provider Identifier for physicians

## Next Steps

### 1. Update Patient Records (Optional)

You can now add insurance information to patient records. This will be included in the billing export CSV.

To update a patient:
1. Go to Patients page
2. Click Edit on a patient
3. Add insurance information:
   - Insurance Company: "Blue Cross Blue Shield"
   - Insurance ID: "ABC123456789"
   - Group Number: "GRP456"
   - Sex: M/F/U

### 2. Update Provider NPI (Optional)

Add NPI numbers to physician user accounts for billing:

This would require a database update or UI addition. For now, NPIs can be added directly via database:

```sql
UPDATE users
SET npi = '1234567890'
WHERE email = 'doctor@example.com';
```

## Billing Export Will Now Include

With or without the optional insurance data, the export will work. Missing fields will show as empty/default values:

- **Patient Demographics**: All fields from patients table
- **Insurance Info**: Defaults to "Self Pay" if not provided
- **Provider NPI**: Empty if not set (can be added manually in billing system)
- **Diagnosis Codes**: Auto-detected from wound type
- **CPT Codes**: 99213/99214/99215 with modifier 95
- **Place of Service**: 02 (Telehealth)

## Still Having Issues?

If you still get errors after running migrations:

1. **Check migration output** - Make sure all 6 migrations show "✓ Success"
2. **Refresh browser** - Clear cache and reload the photo-reviews page
3. **Check error details** - If different error, share the new error message

## Summary

✅ **Run**: https://collagendirect.health/admin/run-all-migrations.php
✅ **Test**: Export CSV from photo-reviews page
✅ **Done**: Billing export should work!

The export will generate a complete billing file even if insurance fields are empty.

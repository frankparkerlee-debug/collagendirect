# Superadmin Migration Instructions

## What This Does
Updates the existing practice admin users (sparkingmatt@gmail.com and parker@senecawest.com) from 'practice_admin' role to 'superadmin' role so they can access both Practice Admin and Platform Admin features without needing a separate admin login.

## How to Run the Migration

### Option 1: Via Render Shell (Recommended)
1. Go to your Render dashboard: https://dashboard.render.com
2. Select your web service (collagendirect)
3. Click "Shell" tab in the left sidebar
4. Run this command:
   ```bash
   php migrate-superadmin.php
   ```

### Option 2: Via Local Database Connection
If you have the production database credentials, you can run it locally:
```bash
php migrate-superadmin.php
```

## What the Migration Does

```sql
UPDATE users
SET role = 'superadmin'
WHERE email IN ('sparkingmatt@gmail.com', 'parker@senecawest.com');
```

## Expected Output
```
Updating user roles to superadmin...

✓ Updated 2 users to superadmin role

Current superadmin users:
  - [First Name] [Last Name] (sparkingmatt@gmail.com) - Role: superadmin
  - [First Name] [Last Name] (parker@senecawest.com) - Role: superadmin

✓ Migration complete!

These users now have access to:
  - Practice Admin (manage orders & physicians)
  - Platform Admin (manage practices & system)
```

## After Running
1. Log out of the portal if currently logged in
2. Log back in with sparkingmatt@gmail.com or parker@senecawest.com
3. Navigate to the Practice Admin link in the sidebar
4. You should now see the context switcher at the top to switch between Practice Admin and Platform Admin

## Code Changes Made
- Updated `admin/auth.php` to recognize 'superadmin' role from the users table
- Users with 'superadmin' role can now access admin console without separate login
- Platform admin features (practices, subscriptions, system settings) are only visible to superadmins

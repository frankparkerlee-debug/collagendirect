# Unified Authentication System

**Date:** 2025-10-24
**Status:** ✅ IMPLEMENTED

## Overview

CollagenDirect now uses a **single login page** for all users. No more confusion about which login to use or duplicate credentials. Role-based access control determines what each user can access after authentication.

## One Login URL

**https://collagendirect.onrender.com/login**

Everyone logs in here:
- Physicians
- Practice Admins
- Superadmins
- CollagenDirect Business Admins

## How It Works

### 1. User Logs In
- Navigate to `/login` (or `/admin/login.php` which redirects to `/login`)
- Enter email and password
- Click "Sign in"

### 2. Authentication Flow
```
/login page
    ↓
POST to /api/login.php
    ↓
Check users table
    ↓
Verify password
    ↓
Set sessions based on role:
  - $_SESSION['user_id'] for portal access
  - $_SESSION['admin'] for admin access (if superadmin/practice_admin)
    ↓
Return redirect URL
    ↓
Frontend redirects user
```

### 3. Role-Based Access

| Role | Portal Access | Admin Access | Redirect After Login |
|------|---------------|--------------|---------------------|
| `physician` | ✅ Yes | ❌ No | `/portal/` |
| `practice_admin` | ✅ Yes | ✅ Yes | `/portal/` |
| `superadmin` | ✅ Yes | ✅ Yes | `/portal/` |

**Note:** Superadmins and practice admins can navigate to `/admin/` after logging in. They have access to both interfaces.

## Access Control

### Portal Pages (`/portal/*`)
**Check:** `$_SESSION['user_id']`
- Physicians: ✅ Access granted
- Practice Admins: ✅ Access granted
- Superadmins: ✅ Access granted
- Not logged in: ❌ Redirect to `/login`

### Admin Pages (`/admin/*`)
**Check:** `$_SESSION['admin']`
- Physicians: ❌ Access denied (session not set)
- Practice Admins: ✅ Access granted
- Superadmins: ✅ Access granted
- Not logged in: ❌ Redirect to `/login`

## Technical Implementation

### File: [api/login.php](api/login.php)

**Before:**
```php
$_SESSION['user_id'] = $user['id'];
// Everyone goes to portal
json_out(200, ['ok'=>true]);
```

**After:**
```php
$_SESSION['user_id'] = $user['id'];

// Set admin session for admin roles
$userRole = $user['role'] ?? 'physician';
if (in_array($userRole, ['superadmin', 'practice_admin'])) {
  $_SESSION['admin'] = [
    'id' => $user['id'],
    'email' => $user['email'],
    'name' => trim($user['first_name'] . ' ' . $user['last_name']),
    'role' => $userRole
  ];
}

json_out(200, ['ok'=>true, 'redirect'=>'/portal/', 'user'=>['role'=>$userRole]]);
```

### File: [admin/login.php](admin/login.php)

**Before:** Full separate login form (235 lines)

**After:** Simple redirect (8 lines)
```php
<?php
// Redirect to unified login
$next = $_GET['next'] ?? '/admin/index.php';
header('Location: /login?next=' . urlencode($next));
exit;
```

## Migration Impact

### What Changed
- ✅ `/admin/login.php` now redirects to `/login`
- ✅ `admin_users` table no longer needed for authentication
- ✅ All users authenticate against `users` table
- ✅ Role stored in `users.role` column

### What Stayed The Same
- ✅ Session security (7-day cookies, HttpOnly, SameSite=Lax)
- ✅ Password verification
- ✅ CSRF protection
- ✅ Access control checks in each interface

### Backward Compatibility
- ✅ Old `/admin/login.php` URLs still work (redirect to `/login`)
- ✅ Existing sessions remain valid
- ✅ No user action required

## User Experience

### Before (Confusing)
```
❓ "Should I use /login or /admin/login.php?"
❓ "Do I need two accounts?"
❓ "Why can't sparkingmatt@gmail.com log in to admin?"
```

### After (Simple)
```
✅ Everyone uses /login
✅ One set of credentials
✅ Automatic routing to correct interface
```

## Security Benefits

1. **Single Source of Truth**
   - All authentication logic in one place
   - Easier to audit and secure
   - Consistent password policies

2. **Role-Based Access Control**
   - Access determined by `users.role` column
   - Easy to promote/demote users
   - Clear permission hierarchy

3. **Session Management**
   - Both portal and admin sessions set in one place
   - Logout clears all sessions
   - Session hijacking protection maintained

## Database Schema

### users Table
```sql
CREATE TABLE users (
  id SERIAL PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  role VARCHAR(50) DEFAULT 'physician', -- physician | practice_admin | superadmin
  ...
);
```

**Roles:**
- `physician` - Regular doctors, access portal only
- `practice_admin` - Practice administrators, access portal + admin
- `superadmin` - Full system access, portal + admin + platform features

### admin_users Table (Deprecated)
No longer used for authentication. Kept for historical data only.

## Login URLs

All these URLs work and lead to the same unified login:

| URL | Behavior |
|-----|----------|
| `/login` | Main login page |
| `/admin/login.php` | Redirects to `/login?next=/admin/index.php` |
| `/portal/` (not logged in) | Redirects to `/login?next=/portal/` |
| `/admin/` (not logged in) | Redirects to `/login?next=/admin/` |

## Testing

### Test Case 1: Physician Login
```
Email: doctor@practice.com
Role: physician
Expected: Login → /portal/ → Can access portal only
```

### Test Case 2: Superadmin Login
```
Email: sparkingmatt@gmail.com
Role: superadmin
Expected: Login → /portal/ → Can also navigate to /admin/
```

### Test Case 3: Admin Login Redirect
```
URL: /admin/login.php
Expected: Redirects to /login?next=/admin/index.php
After login: Goes to /admin/index.php
```

## Troubleshooting

### "Invalid credentials" Error
- ✅ Check you're using the correct email (lowercase)
- ✅ Verify password is correct
- ✅ Try resetting password via "Forgot password?"
- ✅ Contact admin if account doesn't exist

### "Access Denied" to Admin
- ✅ Check your role: `SELECT role FROM users WHERE email = 'your@email.com'`
- ✅ Only `practice_admin` and `superadmin` can access `/admin/`
- ✅ Contact superadmin to change your role if needed

### Session Expires Too Quickly
- ✅ Sessions last 7 days by default
- ✅ Check browser isn't clearing cookies
- ✅ "Remember me" checkbox extends session

## Related Documentation

- [ADMIN-INTERFACE-FIXES.md](ADMIN-INTERFACE-FIXES.md) - Layout and authentication fixes
- [ADMIN-ACCESS-CONFIRMED.md](ADMIN-ACCESS-CONFIRMED.md) - Superadmin user confirmation
- [README.md](README.md) - Full system documentation

## Summary

✅ **One login form for all users**
✅ **Role-based automatic routing**
✅ **No duplicate credentials needed**
✅ **Simpler, clearer user experience**
✅ **Same security standards maintained**

The authentication system is now unified, role-driven, and user-friendly!

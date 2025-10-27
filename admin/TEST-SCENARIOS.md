# Admin Role Testing Scenarios

## Overview
The admin portal has 3 main roles with different access levels:
1. **Superadmin** - Full access to all data and management features
2. **Manufacturer** - Same data access as superadmin, can view all patients/orders
3. **Employee** - Restricted access based on assigned physicians

## Test Scenarios

### Scenario 1: Superadmin Full Access
**Role:** Superadmin (`parker@collagendirect.health`)

**Expected Behavior:**
- ✓ Can see ALL patients in `/admin/patients.php`
- ✓ Can see ALL orders in `/admin/billing.php`
- ✓ Can access `/admin/users.php` and manage all users
- ✓ Can create new employees and assign physicians to them
- ✓ Can create new physicians/practices
- ✓ Can see "Assign Physicians" button for each employee
- ✓ Can assign any physicians to any employee
- ✓ No filtering applied to data queries

**Test Steps:**
1. Log in as superadmin
2. Navigate to Patients page - verify all patients visible
3. Navigate to Billing page - verify all orders visible
4. Navigate to Users > Employees tab
5. Click "Assign Physicians" on an employee
6. Verify modal shows all physicians with checkboxes
7. Select/deselect physicians and save
8. Verify employee can now only access assigned physicians' data

**Edge Cases to Check:**
- Employee with NO physicians assigned sees zero patients/orders
- Employee with ALL physicians assigned sees everything
- Removing all assignments from an employee locks them out

---

### Scenario 2: Manufacturer Full Data Access
**Role:** Manufacturer (any user in `admin_users` with `role='manufacturer'`)

**Expected Behavior:**
- ✓ Can see ALL patients in `/admin/patients.php` (same as superadmin)
- ✓ Can see ALL orders in `/admin/billing.php` (same as superadmin)
- ✓ Can access `/admin/users.php` and manage users
- ✓ Can assign physicians to employees
- ✓ Can create manufacturers and employees
- ✓ Has same $isOwner permissions as superadmin
- ✓ No filtering applied to data queries

**Test Steps:**
1. Create a manufacturer user in `/admin/users.php?tab=manufacturer`
2. Log in as that manufacturer
3. Navigate to Patients page - verify all patients visible
4. Navigate to Billing page - verify all orders visible
5. Navigate to Users page - verify can manage all user types
6. Click "Assign Physicians" on an employee - verify full access
7. Navigate to Messages - verify can see all provider messages

**Edge Cases to Check:**
- Manufacturer can create OTHER manufacturer accounts
- Manufacturer cannot accidentally lock themselves out
- Manufacturer sees same data regardless of `admin_physicians` table entries

---

### Scenario 3: Employee with Assigned Physicians
**Role:** Employee (any user in `admin_users` with `role='employee'` or `role='admin'`)

**Expected Behavior:**
- ✓ Only sees patients from ASSIGNED physicians
- ✓ Only sees orders from ASSIGNED physicians
- ✓ Cannot see `/admin/users.php` management features (or sees limited view)
- ✓ Cannot assign physicians to other employees
- ✓ Filtering applied via `admin_physicians` table JOIN

**Test Steps:**
1. Log in as superadmin
2. Go to Users > Employees
3. Create a new employee
4. Click "Assign Physicians" and assign 2 specific physicians
5. Log out and log in as that employee
6. Navigate to Patients page:
   - Count total patients visible
   - Verify they ALL belong to the 2 assigned physicians
7. Navigate to Billing page:
   - Count total orders visible
   - Verify they ALL belong to the 2 assigned physicians' patients
8. Try to access Users page - verify limited access
9. Navigate to Messages - verify only sees messages from assigned physicians

**Edge Cases to Check:**
- Employee assigned to physician with NO patients sees empty list
- Employee assigned to multiple physicians sees combined data
- Changing assignments immediately updates visible data on next page load

---

### Scenario 4: Employee with NO Assigned Physicians
**Role:** Employee with zero rows in `admin_physicians` table

**Expected Behavior:**
- ✓ Sees ZERO patients (empty list)
- ✓ Sees ZERO orders (empty list)
- ✓ Can still log in and access pages
- ✓ Gets "No patients" or "No orders" messages
- ✓ Messages page shows nothing

**Test Steps:**
1. Log in as superadmin
2. Create a new employee
3. DO NOT assign any physicians
4. Log out and log in as that employee
5. Navigate to Patients page - verify empty state
6. Navigate to Billing page - verify empty state
7. Navigate to Messages - verify empty state
8. Verify no errors, just empty data

**Purpose:** Security by default - new employees have no access until explicitly granted

**Edge Cases to Check:**
- No database errors from empty JOINs
- UI shows helpful empty state messages
- Employee can still update their profile/settings

---

### Scenario 5: Practice Admin Role Handling
**Role:** Practice Admin (user in `users` table with `role='practice_admin'`)

**Expected Behavior:**
- ✓ Practice admins should ONLY access `/portal`, NOT `/admin`
- ✓ If they somehow access `/admin`, they see limited scope
- ✓ Users page treats practice_admin as $isOwner for THEIR practice only
- ✓ Excluded from "Physicians" tab (line 21 of users.php)

**Test Steps:**
1. Create a practice admin in portal
2. Try to access `/admin` directly
3. Verify they're redirected or see appropriate scope
4. If they access Users page, verify they only see their practice physicians
5. Verify they cannot create admin employees
6. Verify they cannot access other practices' data

**Edge Cases to Check:**
- Practice admin accessing `/admin` instead of `/portal`
- Practice admin trying to create users outside their practice
- Practice admin seeing only their practice data, not all practices

---

## Edge Cases Found and Fixed

### 1. Manufacturer Missing from $isOwner
**Issue:** Line 8 of `admin/users.php` did not include 'manufacturer' in $isOwner array
**Impact:** Manufacturers couldn't manage users or assign physicians
**Fix:** Added 'manufacturer' to the array: `['owner','superadmin','admin','practice_admin','manufacturer']`

### 2. No UI for Physician Assignment
**Issue:** No way for superadmin to assign physicians to employees through UI
**Impact:** Had to manually INSERT into admin_physicians table or run SQL scripts
**Fix:** Created modal dialog with checkboxes for physician assignment

### 3. Database Constraint Mismatch
**Issue:** Portal code used 'collagendirect' as recipient_type, but constraint only allowed 'provider', 'admin', 'all_admins'
**Impact:** 500 errors when replying to messages
**Fix:** Changed all instances of 'collagendirect' to 'all_admins'

### 4. Message Threading Not Working
**Issue:** Replies created separate conversations instead of threading
**Impact:** Conversations were fragmented and hard to follow
**Fix:** Added parent_message_id and thread_id column handling to both admin and portal message sending

## Recommendations

1. **Default Security:** New employees should have NO physician assignments by default (already implemented)

2. **Assignment Audit Log:** Consider logging changes to admin_physicians table for compliance:
   ```sql
   CREATE TABLE admin_physician_audit (
     id SERIAL PRIMARY KEY,
     action VARCHAR(20), -- 'assigned' or 'removed'
     admin_id INTEGER,
     physician_user_id VARCHAR(64),
     changed_by VARCHAR(64),
     changed_at TIMESTAMP DEFAULT NOW()
   );
   ```

3. **Bulk Assignment Helper:** The "Assign All Physicians" script at `/admin/assign-all-physicians.php` is useful for development but should be REMOVED or RESTRICTED in production

4. **Practice Admin Separation:** Enforce that practice_admin users can ONLY access `/portal` via middleware/routing, not just auth checks

5. **Role Documentation:** Add role descriptions to the Users page UI so superadmins understand the difference between roles

6. **Employee Onboarding:** When creating a new employee, show a prompt: "Don't forget to assign physicians to this employee so they can see data"

7. **Empty State UX:** Improve empty state messages when employees have no assignments:
   - "You don't have access to any physicians yet. Contact your administrator."
   - Show admin contact info

8. **Manufacturer Tab Order:** Consider moving Manufacturer tab before Employees tab since manufacturers have higher privileges

## SQL Queries for Verification

### Check employee assignments:
```sql
SELECT
  au.id,
  au.name,
  au.email,
  au.role,
  COUNT(ap.physician_user_id) as assigned_physicians
FROM admin_users au
LEFT JOIN admin_physicians ap ON ap.admin_id = au.id
WHERE au.role IN ('employee', 'admin')
GROUP BY au.id, au.name, au.email, au.role
ORDER BY au.name;
```

### Check which employees can see a specific physician:
```sql
SELECT
  au.name as employee_name,
  au.email as employee_email,
  u.first_name || ' ' || u.last_name as physician_name
FROM admin_physicians ap
JOIN admin_users au ON au.id = ap.admin_id
JOIN users u ON u.id = ap.physician_user_id
WHERE ap.physician_user_id = 'PHYSICIAN_ID_HERE'
ORDER BY au.name;
```

### Find employees with no assignments:
```sql
SELECT
  au.id,
  au.name,
  au.email,
  au.role
FROM admin_users au
LEFT JOIN admin_physicians ap ON ap.admin_id = au.id
WHERE au.role IN ('employee', 'admin')
  AND ap.physician_user_id IS NULL
GROUP BY au.id, au.name, au.email, au.role;
```

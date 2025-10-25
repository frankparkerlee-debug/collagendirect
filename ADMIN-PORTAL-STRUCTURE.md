# Admin Portal Structure

## Overview

The CollagenDirect platform has TWO distinct portals with different access control:

### 1. Physician Portal (`/portal`)
**Purpose:** For physicians and practice managers to manage their patients and orders

**Access:**
- Physicians (`role: 'physician'` in `users` table)
- Practice Managers (`role: 'practice_admin'` in `users` table)

**Features:**
- View/manage their own patients and orders
- Submit new orders
- Communicate with CollagenDirect
- View order status and history

### 2. Admin Portal (`/admin`)
**Purpose:** For CollagenDirect employees and manufacturer to manage the platform

**Access:** RESTRICTED to:
1. **Super Admin** (parker@collagendirect.health)
   - Table: `users` with `role = 'superadmin'`
   - Full access to everything
   - Can manage both portals

2. **Employees** (CollagenDirect staff)
   - Table: `admin_users` with `role = 'employee'` or `'admin'`
   - Can view/edit patients & orders for physicians they manage
   - Can add/edit physicians assigned to them
   - Limited to their assigned physicians (via `admin_physicians` table)

3. **Manufacturer**
   - Table: `admin_users` with `role = 'manufacturer'`
   - Can view/download ALL patient/order information
   - Can set patient/order status
   - Can reply to messages from ALL physicians
   - Read-only for most data, write access for status and messages

## Important Access Rules

### Who CANNOT Access Admin Portal

- ❌ Practice Managers (`practice_admin` role) - They use `/portal` only
- ❌ Physicians (`physician` role) - They use `/portal` only
- ❌ Anyone who registers through the registration page - They are assigned to `/portal`

### Authentication Tables

**`users` table:**
- Physicians
- Practice Managers
- Super Admin (parker@collagendirect.health)

**`admin_users` table:**
- CollagenDirect Employees
- Manufacturer accounts

## Login Flow

1. User enters credentials at `/login`
2. System checks `users` table first
   - If found: Authenticate and route based on role
     - `superadmin` → `/portal` (can access `/admin` too)
     - `practice_admin` → `/portal` (CANNOT access `/admin`)
     - `physician` → `/portal` (CANNOT access `/admin`)
3. If not found in `users`, check `admin_users` table
   - If found: Authenticate and route to `/admin`
     - `employee` or `admin` → `/admin` (limited to assigned physicians)
     - `manufacturer` → `/admin` (full read access)

## Role-Based Permissions in Admin Portal

### Super Admin
- Full CRUD on all data
- Can manage employees and manufacturers
- Can manage all physicians
- Access to all features

### Employee
- View/Edit: Only patients and orders for assigned physicians
- Can add/edit physicians assigned to them
- Cannot access data from other employees' physicians
- Uses `admin_physicians` join table for assignment

### Manufacturer
- View/Download: ALL patient and order data
- Update: Patient status, order status
- Reply to: All physician messages
- Cannot: Create/delete physicians, access employee management

## Database Structure

```sql
-- Physicians, practice managers, and super admin
CREATE TABLE users (
  id VARCHAR(64) PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  role VARCHAR(50) DEFAULT 'physician', -- physician | practice_admin | superadmin
  ...
);

-- CollagenDirect employees and manufacturer
CREATE TABLE admin_users (
  id SERIAL PRIMARY KEY,
  email VARCHAR(255) UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  name VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL, -- employee | admin | manufacturer
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Maps employees to their assigned physicians
CREATE TABLE admin_physicians (
  id SERIAL PRIMARY KEY,
  admin_id INT REFERENCES admin_users(id),
  physician_user_id VARCHAR(64) REFERENCES users(id),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(admin_id, physician_user_id)
);
```

## Current Accounts

### Super Admin
- Email: `parker@collagendirect.health`
- Password: `Password321!`
- Name: Parker Lee
- Access: Both `/portal` and `/admin`

### Example Employee Setup
To create an employee:
1. Log in as super admin at `/admin`
2. Go to Users → Employees tab
3. Click "Add Employee"
4. Fill in name, email, role (employee/admin), password
5. Assign physicians to this employee

### Example Manufacturer Setup
Same process as employee, but select `role = 'manufacturer'`

## Security Notes

- Practice managers (practice_admin) are explicitly BLOCKED from `/admin`
- All admin access requires authentication via `current_admin()` function
- Session management is unified across both portals
- 7-day persistent sessions for convenience
- CSRF protection on all POST requests

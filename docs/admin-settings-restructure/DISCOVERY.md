# Admin Settings Restructure - Phase 10a: Discovery Document

**Generated:** 2025-12-15
**Purpose:** Complete documentation of current architecture before making any settings restructure changes.

---

## Table of Contents

1. [Admin Settings File Map](#1-admin-settings-file-map)
2. [User Storage by Type](#2-user-storage-by-type)
3. [Practice/Location Schema](#3-practicelocation-schema)
4. [Distributor Data Comparison](#4-distributor-data-comparison)
5. [Manufacturer Rep Migration List](#5-manufacturer-rep-migration-list)
6. [Current Permission System](#6-current-permission-system)
7. [Recommended Migration Approach](#7-recommended-migration-approach)

---

## 1. Admin Settings File Map

### Primary Settings File

| File | Purpose | Lines |
|------|---------|-------|
| `/admin/users.php` | Main Admin Settings page with all user management | ~1,200 |

### Supporting Files

| File | Purpose |
|------|---------|
| `/admin/_header.php` | Navigation structure and sidebar |
| `/admin/auth.php` | Authentication and role checking |
| `/admin/db.php` | Database connection |
| `/admin/config.php` | API keys configuration |

### Navigation Structure (in `_header.php`)

```
Sidebar Navigation:
├── Dashboard (index.php)
├── Revenue Report (revenue-report.php)
├── Referrals (Submenu)
│   ├── Patients (patients.php)
│   ├── Orders (orders.php)
│   └── Delivery Audit (delivery-audit.php)
├── Wholesale (Submenu)
│   ├── Create Order (create-wholesale-order.php)
│   ├── View Orders (wholesale-orders.php)
│   └── Practice Pricing (practice-pricing.php)
├── Shipments (shipments.php)
├── Billing (Submenu)
│   ├── Referral (billing.php)
│   └── Wholesale (billing-wholesale.php)
├── Products (products.php)
├── Rep Management (sales-reps.php) [Superadmin/Manufacturer only]
├── Admin Settings (users.php) ← TARGET FOR RESTRUCTURE
├── Messages (messages.php)
├── Physician Portal [External Link]
└── Log out
```

### Current Tab Structure in Admin Settings (`users.php`)

| Tab | URL Parameter | Content |
|-----|---------------|---------|
| **Providers** | `?tab=physicians` | Physician/Practice management |
| **Employees** | `?tab=employees` | Internal staff (employee, admin, sales, ops) |
| **Manufacturer** | `?tab=manufacturer` | Manufacturer representatives |
| **Practice Locations** | `?tab=locations` | Multi-location management |

### Providers Section Implementation

**Located in:** `/admin/users.php` lines 482-925

**Features:**
- List all physicians/practices with columns: Name, Email, Account Type, Assigned Rep, Status
- Filter by assigned sales rep (dropdown)
- Expandable row detail view with all profile fields
- Reset password, Delete actions
- Rep assignment (superadmin/manufacturer only)
- Add Provider form with two types:
  - Practice Owner (creates new practice)
  - Physician to Practice (adds to existing)

---

## 2. User Storage by Type

### Summary Table

| User Type | Primary Table | Role Column | Status Column | Portal Access | Admin Access |
|-----------|---------------|-------------|---------------|---------------|--------------|
| **Super Admin** | `users` | `role = 'superadmin'` | N/A | Yes | Yes |
| **Manufacturer Rep** | `admin_users` | `role = 'manufacturer'` | N/A | No | Yes |
| **Practice Manager** | `users` | `role = 'practice_admin'` | N/A | Yes | **No** |
| **Physician** | `users` | `role = NULL` or `'physician'` | N/A | Yes | No |
| **Sales Rep (Distributor)** | `users` + `sales_reps` | N/A | `sales_reps.status = 'active'` | No | Yes (`/admin/rep/`) |

### Detailed Storage

#### Super Admin
```sql
-- Table: users
-- Identification: role = 'superadmin'
SELECT id, email, first_name, last_name, role
FROM users WHERE role = 'superadmin';

-- Example: parker@collagendirect.health
```

**Key Points:**
- Stored in `users` table (same as physicians)
- Full access to both `/portal` and `/admin`
- Can approve sales reps, manage all users

#### Manufacturer Rep
```sql
-- Table: admin_users
-- Identification: role = 'manufacturer'
SELECT id, email, name, role, created_at
FROM admin_users WHERE role = 'manufacturer';
```

**Key Points:**
- Stored in `admin_users` table (separate from physicians)
- Access to `/admin` portal only
- Cannot delete superadmins or employees
- Can view all patient/order data
- Can manage manufacturers and some user operations

#### Practice Manager
```sql
-- Table: users
-- Identification: role = 'practice_admin'
SELECT id, email, first_name, last_name, practice_name, account_type
FROM users WHERE role = 'practice_admin';
```

**Key Points:**
- Stored in `users` table
- **CANNOT access `/admin` portal** - portal only
- Manages their practice's patients and orders
- Can have multiple locations via `practice_locations`

#### Physician
```sql
-- Table: users
-- Identification: role IS NULL OR role = 'physician'
SELECT id, email, first_name, last_name, npi, practice_name
FROM users WHERE role IS NULL OR role = 'physician';
```

**Key Points:**
- Default user type in `users` table
- Portal access only
- Can be assigned to a sales rep via `assigned_rep_id`

#### Sales Rep (Independent Distributor)
```sql
-- Tables: users + sales_reps (dual-table system)
SELECT u.id, u.email, u.first_name, u.last_name,
       sr.id as rep_id, sr.status, sr.company_name
FROM users u
JOIN sales_reps sr ON sr.user_id = u.id
WHERE sr.status = 'active';
```

**Key Points:**
- Base account in `users` table
- Extended profile in `sales_reps` table
- Must have `sales_reps.status = 'active'` to access
- Access to `/admin/rep/` (dedicated portal)
- Can have clinics assigned via `users.assigned_rep_id`

---

## 3. Practice/Location Schema

### Practice Storage

Practices are stored in the `users` table:

```sql
-- Users table columns for practices
id            VARCHAR(64)   -- UUID
practice_name VARCHAR(255)  -- Name of practice/clinic
role          VARCHAR(50)   -- 'practice_admin' or 'physician'
npi           VARCHAR(20)   -- National Provider Identifier
account_type  VARCHAR(40)   -- 'referral', 'wholesale', 'dme_wholesale'
address       VARCHAR(255)  -- Legacy: original address (deprecated)
city          VARCHAR(120)  -- Legacy: city
state         VARCHAR(10)   -- Legacy: state
zip           VARCHAR(15)   -- Legacy: ZIP
phone         VARCHAR(15)   -- Legacy: phone
```

### Location Storage

Multi-location support via `practice_locations` table:

```sql
CREATE TABLE practice_locations (
  id            SERIAL PRIMARY KEY,
  user_id       VARCHAR(64) NOT NULL,  -- FK to users.id
  location_name VARCHAR(255) NOT NULL,  -- e.g., "Main Office"
  address       TEXT NOT NULL,
  city          VARCHAR(100) NOT NULL,
  state         VARCHAR(50) NOT NULL,
  zip           VARCHAR(20) NOT NULL,
  phone         VARCHAR(50),
  is_primary    BOOLEAN DEFAULT FALSE,
  is_active     BOOLEAN DEFAULT TRUE,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE(user_id, location_name)
);
```

### Physician Roster Storage

Practice admins can manage multiple physicians:

```sql
CREATE TABLE practice_physicians (
  id                  SERIAL PRIMARY KEY,
  practice_user_id    VARCHAR(32) NOT NULL,  -- FK to users.id
  physician_name      VARCHAR(255) NOT NULL,
  npi                 VARCHAR(20),
  license_number      VARCHAR(50),
  signature_text      TEXT,
  signature_image_path TEXT,
  is_active           BOOLEAN DEFAULT TRUE,

  FOREIGN KEY (practice_user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Relationships

```
users (practice/clinic)
  ├─→ practice_locations (one-to-many)
  │    └─→ orders.location_id (delivery destination)
  │
  └─→ practice_physicians (one-to-many)
       └─→ orders.physician_id (credentials on order)
```

**Key Constraints:**
- One practice can have MANY locations
- Location cannot exist without a practice (CASCADE delete)
- One location per practice can be `is_primary = TRUE`

---

## 4. Distributor Data Comparison

### Finding: No Separate "Independent Distributor" Table

The codebase does **NOT** have a separate "independent distributor" user type or table. The terms "Sales Rep" and "Independent Distributor" refer to the **same entity**.

### Data Mapping

| Legacy Term | Current Storage | Status |
|-------------|-----------------|--------|
| Independent Distributor | `sales_reps` table | Same |
| Sales Rep | `sales_reps` table | Same |
| Distributor | N/A | Not used |

### Sales Reps Table Structure

```sql
CREATE TABLE sales_reps (
  id                VARCHAR(64) PRIMARY KEY,
  user_id           VARCHAR(64) UNIQUE NOT NULL,  -- FK to users.id
  company_name      VARCHAR(255),
  status            VARCHAR(20) DEFAULT 'pending',  -- pending/active/suspended/terminated/invited
  application_date  TIMESTAMP,
  approved_date     TIMESTAMP,
  approved_by       VARCHAR(64),  -- FK to user who approved
  how_heard_about_us TEXT,
  notes             TEXT,
  invite_token      VARCHAR(64),
  invite_token_expires_at TIMESTAMP,
  invited_by        VARCHAR(64),

  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Verification Query

```sql
-- No duplicate records between systems
-- Only one storage location for distributors/sales reps
SELECT 'sales_reps' as table_name, COUNT(*) as count FROM sales_reps;
-- This is the ONLY distributor/rep storage
```

---

## 5. Manufacturer Rep Migration List

### Current Manufacturer Rep Query

```sql
SELECT id, name, email, role, created_at
FROM admin_users
WHERE role = 'manufacturer'
ORDER BY created_at DESC;
```

### Migration Considerations

**Question:** Should Manufacturer Reps become Admin users?

**Current State:**
- Manufacturer reps are in `admin_users` table with `role = 'manufacturer'`
- They have near-admin access but cannot delete superadmins/employees
- They CAN view all data, manage physicians, assign reps

**Options:**
1. **Keep as-is** - They already function as needed
2. **Promote to Admin** - Change role to 'admin' (would gain employee management)
3. **Create new role** - More granular permissions

**Recommendation:** Keep current structure. Manufacturer role already provides appropriate access level.

### Action Items

- [ ] Run inventory query on production to get exact user list
- [ ] Document each manufacturer rep's email and current permissions
- [ ] Confirm with stakeholder if any role changes needed

---

## 6. Current Permission System

### Role-Based Permissions (Hardcoded)

Permissions are **hardcoded per role** in `/admin/users.php`:

```php
$adminRole = $admin['role'] ?? '';
$isSuperadmin = $adminRole === 'superadmin'; // Only parker@collagendirect.health
$isOwner = in_array($adminRole, ['owner','superadmin','admin','practice_admin']);
$isAdmin = in_array($adminRole, ['owner','superadmin','admin']);
$isSales = $adminRole === 'sales';
$isManufacturer = $adminRole === 'manufacturer';
```

### Permission Matrix

| Action | Superadmin | Admin | Manufacturer | Sales | Employee |
|--------|-----------|-------|--------------|-------|----------|
| View Providers | ✓ | ✓ | ✓ | ✓ | Assigned only |
| Create Provider | ✓ | ✓ | ✓ | ✓ | Limited |
| Delete Provider | ✓ | ✓ | ✓ | ✓ | ✗ |
| Assign Rep | ✓ | ✗ | ✓ | ✗ | ✗ |
| Manage Employees | ✓ | ✓ | ✗ | ✗ | ✗ |
| Manage Manufacturers | ✓ | ✓ | ✗ | ✗ | ✗ |
| Delete Superadmin | ✓ | ✓ | ✗ | ✗ | ✗ |
| Manage Locations | ✓ | ✓ | ✗ | ✗ | ✗ |

### Granular Permissions System (Prepared but Optional)

A granular permission system exists but is **not enforced by default**:

**Table:** `admin_permissions`

```sql
CREATE TABLE admin_permissions (
  id              SERIAL PRIMARY KEY,
  admin_user_id   INTEGER NOT NULL,      -- FK to admin_users.id
  permission_key  VARCHAR(100) NOT NULL, -- e.g., 'users.view', 'orders.edit'
  granted         BOOLEAN DEFAULT TRUE,
  granted_by      INTEGER,               -- FK to admin_users.id
  granted_at      TIMESTAMP,

  FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE,
  UNIQUE(admin_user_id, permission_key)
);
```

**Available Permission Keys:**
```
users.view, users.create, users.edit, users.delete
products.view, products.create, products.edit, products.delete
orders.view, orders.create, orders.edit, orders.delete
billing.view, billing.edit
revenue.view
shipments.view, shipments.edit
pricing.view, pricing.edit
messages.view, messages.send
practices.view, practices.edit
reports.view, reports.export
```

**Activation:** `admin_users.use_custom_permissions = TRUE`

### Sales Rep Permissions (Separate System)

Sales reps have their own permission system documented in `/docs/sales-rep-feature/PERMISSIONS.md`:

- Cannot access main admin pages (redirected to `/admin/rep/`)
- Can only view data for their assigned clinics
- Guard function: `deny_sales_rep()` in `/admin/auth.php`

---

## 7. Recommended Migration Approach

### Issues Identified

1. **No separate "Providers" section** - Currently under Admin Settings → Physicians tab
2. **Mixed terminology** - "Providers" vs "Physicians" used interchangeably
3. **Distributor confusion** - Same as Sales Rep, no separate entity
4. **Manufacturer role placement** - Already well-positioned in admin_users

### Recommended Changes

#### 1. Navigation Restructure

**Current:**
```
Admin Settings (users.php)
├── Providers tab
├── Employees tab
├── Manufacturer tab
└── Practice Locations tab
```

**Proposed:**
```
Settings (top-level menu item)
├── Practices (practice management)
├── Internal Users (employees, manufacturers)
├── Roles & Permissions (granular permissions UI)
└── System Settings (future: configuration)

Distributors (relocate from Rep Management)
└── Sales Reps (current sales-reps.php)
```

#### 2. Data Migrations Needed

| Change | Database Impact | Risk Level |
|--------|-----------------|------------|
| Rename navigation | None | Low |
| Split users.php into separate files | None | Low |
| Add Practice Management UI | None (data exists) | Low |
| Activate granular permissions | Optional column | Low |

#### 3. Preservation Requirements

**DO NOT:**
- Rename any existing database tables
- Remove any existing columns
- Change any foreign key relationships
- Modify existing API endpoints

**DO:**
- Create new PHP files for split UI
- Add new navigation menu items
- Leverage existing granular permissions system
- Add new routes/endpoints as needed

### Implementation Order

1. **Phase 10b: Database & Migrations**
   - No schema changes required for basic restructure
   - Optional: Activate granular permissions if desired

2. **Phase 10c: Practice Management UI**
   - Extract Providers tab → new `/admin/platform/practices.php`
   - Extract Locations tab → integrate with practices

3. **Phase 10d: Internal Users UI**
   - Extract Employees + Manufacturer tabs → new `/admin/platform/users.php`
   - Keep same data queries, new layout

4. **Phase 10e: Distributor Relocation**
   - Move "Rep Management" to new section
   - Rename to "Distributors" in navigation

5. **Phase 10f: Roles & Permissions UI**
   - Create UI for granular permissions system
   - Allow admin to assign specific permissions

6. **Phase 10g: Integration & Testing**
   - Full regression testing
   - Verify all preservation requirements met

---

## Appendix: Key File Locations

| Purpose | File Path |
|---------|-----------|
| Admin Settings (current) | `/admin/users.php` |
| Navigation | `/admin/_header.php` |
| Authentication | `/admin/auth.php` |
| Sales Rep Portal Header | `/admin/rep/_header.php` |
| Permissions Migration | `/admin/migrate-create-admin-permissions.php` |
| Preservation Documentation | `/docs/preservation/` |
| Sales Rep Permissions | `/docs/sales-rep-feature/PERMISSIONS.md` |

---

*This document should be reviewed before proceeding to Phase 10b.*

# Sales Rep Feature - Phase 3: Permissions Documentation

> **Purpose**: Document the sales_rep role permissions, navigation structure, and access controls.
>
> **Created**: December 14, 2025
> **Status**: Phase 3 Complete

---

## Table of Contents

1. [Role Overview](#1-role-overview)
2. [Permission Matrix](#2-permission-matrix)
3. [Navigation Structure](#3-navigation-structure)
4. [Access Control Implementation](#4-access-control-implementation)
5. [Data Scoping Rules](#5-data-scoping-rules)
6. [Auth Functions Reference](#6-auth-functions-reference)

---

## 1. Role Overview

### Sales Rep Role Definition

Sales reps are users in the `users` table who have an active profile in the `sales_reps` table. Unlike other admin roles (stored in `admin_users`), sales reps are linked to the physician user system, allowing them to:

- Create and manage clinics (auto-assigned to themselves)
- View orders from their assigned clinics
- Track commissions earned from clinic orders
- Receive payouts for collected payments

### Role Characteristics

| Attribute | Value |
|-----------|-------|
| **Storage** | `users` table + `sales_reps` table |
| **Role String** | `'sales_rep'` (virtual, derived from sales_reps.status) |
| **Session Key** | `$_SESSION['admin']` with `role => 'sales_rep'` |
| **Portal** | `/admin/rep/` (dedicated sales rep portal) |
| **Activation** | Requires `sales_reps.status = 'active'` |

### Role Hierarchy

```
superadmin (highest)
  └─ admin
       └─ manufacturer
            └─ sales (internal staff)
                 └─ employee
                      └─ sales_rep (external)
```

---

## 2. Permission Matrix

### What Sales Reps CAN Do

| Permission | Description |
|------------|-------------|
| View own profile | Access My Account page |
| View assigned clinics | See all clinics in `users.assigned_rep_id = rep_id` |
| Create new clinics | Auto-assigned to self (`rep_assigned_by = 'self_onboard'`) |
| Add physicians to clinics | Add to practices they are assigned to |
| Submit assignment requests | Request to be assigned to existing clinics |
| View orders (scoped) | Only orders from assigned clinics |
| Create wholesale orders | Only for assigned wholesale-enabled clinics |
| View commission ledger | Own commission entries only |
| View payout history | Own payout records only |
| Access Messages | Basic messaging with CollagenDirect staff |
| Change password | Via My Account page |

### What Sales Reps CANNOT Do

| Restriction | Reason |
|-------------|--------|
| View other reps' data | Data scoped by `assigned_rep_id` |
| View unassigned clinics | Must request assignment first |
| Modify commission rates | Admin-controlled |
| Approve/deny requests | Admin-only workflow |
| Access Revenue Report | Sensitive financial data |
| Access Shipments | Operations-only |
| Access Products | Admin/manufacturer only |
| Access Admin Settings | User management restricted |
| Access Billing (actions) | View-only where applicable |
| Access Delivery Audit | Compliance/audit restricted |
| Access Practice Pricing | Pricing is admin-controlled |
| Access main Admin Dashboard | Has own dashboard at `/admin/rep/` |

---

## 3. Navigation Structure

### Sales Rep Portal Navigation (`/admin/rep/`)

```
Sales Rep Portal
├── Dashboard (/admin/rep/)
│   └── KPIs: Assigned clinics, orders, commissions, pending requests
│
├── My Clinics
│   ├── Clinic Roster (/admin/rep/clinics.php)
│   ├── Onboard New Clinic (/admin/rep/onboard-clinic.php)
│   ├── Add Physician (/admin/rep/add-physician.php)
│   └── My Assignment Requests (/admin/rep/assignment-requests.php)
│
├── Orders (/admin/rep/orders.php)
│   └── Scoped to assigned clinics only
│
├── Wholesale
│   ├── Create Order (/admin/rep/create-wholesale-order.php)
│   └── View Orders (/admin/rep/wholesale-orders.php)
│
├── Commissions
│   ├── Commission Ledger (/admin/rep/commissions.php)
│   └── Payout History (/admin/rep/payouts.php)
│
├── Messages (/admin/rep/messages.php)
│
└── My Account (/admin/rep/account.php)
    └── Profile info, password change, signed documents
```

### Blocked Admin Routes

Sales reps are redirected to `/admin/rep/` when attempting to access:

| Route | Guard Function |
|-------|---------------|
| `/admin/index.php` | `deny_sales_rep()` |
| `/admin/revenue-report.php` | `deny_sales_rep()` |
| `/admin/products.php` | `deny_sales_rep()` (also has superadmin/manufacturer check) |
| `/admin/shipments.php` | `deny_sales_rep()` |
| `/admin/patients.php` | `deny_sales_rep()` |
| `/admin/orders.php` | `deny_sales_rep()` |
| `/admin/billing.php` | `deny_sales_rep()` |
| `/admin/billing-wholesale.php` | `deny_sales_rep()` |
| `/admin/wholesale-orders.php` | `deny_sales_rep()` |
| `/admin/create-wholesale-order.php` | `deny_sales_rep()` |
| `/admin/practice-pricing.php` | `deny_sales_rep()` |
| `/admin/delivery-audit.php` | `deny_sales_rep()` |
| `/admin/users.php` | `deny_sales_rep()` |

---

## 4. Access Control Implementation

### Authentication Flow

```php
// In /api/login.php
// After user is authenticated from users table:

// Check if user is an active sales rep
$repStmt = $pdo->prepare("SELECT id, status FROM sales_reps WHERE user_id = ? AND status = 'active'");
$repStmt->execute([$user['id']]);
$repRecord = $repStmt->fetch();

if ($repRecord) {
  $_SESSION['admin'] = [
    'id' => $user['id'],
    'email' => $user['email'],
    'name' => $user['first_name'] . ' ' . $user['last_name'],
    'role' => 'sales_rep',
    'rep_id' => $repRecord['id']  // sales_reps.id for commission lookups
  ];
  // Redirect to /admin/rep/
}
```

### Guard Functions (in `/admin/auth.php`)

```php
// Check if current user is a sales rep
function is_sales_rep(): bool {
  $admin = current_admin();
  return $admin && $admin['role'] === 'sales_rep';
}

// Redirect sales reps away from admin pages
function deny_sales_rep(): void {
  if (is_sales_rep()) {
    header('Location: /admin/rep/');
    exit;
  }
}

// Require sales rep role (for rep-only pages)
function require_sales_rep(): void {
  $admin = current_admin();
  if (!$admin || $admin['role'] !== 'sales_rep') {
    header('Location: /admin/login.php');
    exit;
  }
}

// Get full sales rep profile
function current_sales_rep() {
  $admin = current_admin();
  if (!$admin || $admin['role'] !== 'sales_rep') {
    return null;
  }
  global $pdo;
  $stmt = $pdo->prepare("SELECT sr.*, u.email, u.first_name, u.last_name FROM sales_reps sr JOIN users u ON u.id = sr.user_id WHERE sr.id = ?");
  $stmt->execute([$admin['rep_id']]);
  return $stmt->fetch(PDO::FETCH_ASSOC);
}
```

### Page Protection Pattern

```php
// At top of each sales rep page (/admin/rep/*.php)
require __DIR__ . '/_header.php';  // Includes auth check

// _header.php ensures:
$admin = current_admin();
if (!$admin || $admin['role'] !== 'sales_rep') {
  header('Location: /admin/login.php');
  exit;
}

// At top of each admin page that sales reps cannot access
require_admin();
if (function_exists('deny_sales_rep')) deny_sales_rep();
```

---

## 5. Data Scoping Rules

### Clinic Visibility

```sql
-- Sales reps see only their assigned clinics
SELECT * FROM users
WHERE assigned_rep_id = :rep_id
AND (role IN ('physician', 'practice_admin') OR role IS NULL)
```

### Patient Visibility

Sales reps cannot directly access patients. They see patient names only in order context.

### Order Visibility

```sql
-- Sales reps see orders from assigned clinics only
SELECT o.* FROM orders o
JOIN users u ON u.id = o.user_id
WHERE u.assigned_rep_id = :rep_id
AND o.status NOT IN ('draft')
AND o.deleted_at IS NULL
```

### Commission Visibility

```sql
-- Sales reps see only their own commission entries
SELECT * FROM rep_commission_ledger
WHERE rep_id = :rep_id

-- And their own payouts
SELECT * FROM rep_commission_payouts
WHERE rep_id = :rep_id
```

### Assignment Requests

```sql
-- Sales reps see only their own requests
SELECT * FROM rep_assignment_requests
WHERE rep_id = :rep_id
```

---

## 6. Auth Functions Reference

### Available Functions

| Function | Returns | Purpose |
|----------|---------|---------|
| `current_admin()` | `array\|null` | Get current admin user data |
| `require_admin()` | `void` | Require any admin login |
| `current_sales_rep()` | `array\|null` | Get full sales rep profile |
| `require_sales_rep()` | `void` | Require sales rep role |
| `is_sales_rep()` | `bool` | Check if current user is sales rep |
| `deny_sales_rep()` | `void` | Redirect sales reps to their portal |

### Session Structure for Sales Reps

```php
$_SESSION['admin'] = [
  'id' => 'abc123...',           // users.id (VARCHAR 64)
  'email' => 'rep@example.com',
  'name' => 'John Smith',
  'role' => 'sales_rep',         // Virtual role (derived)
  'rep_id' => 'xyz789...'        // sales_reps.id (VARCHAR 64)
];

// Also maintains user session for potential portal access
$_SESSION['user_id'] = 'abc123...';  // Same as admin.id
```

---

## Testing Checklist

### Login & Routing
- [ ] Sales rep with active status can log in
- [ ] Sales rep is redirected to `/admin/rep/` after login
- [ ] Sales rep with non-active status cannot access rep portal
- [ ] Sales rep cannot access main admin dashboard

### Navigation
- [ ] All sidebar links work correctly
- [ ] Submenu expansion/collapse works
- [ ] Active state highlighting is correct

### Clinic Management
- [ ] Can view assigned clinics
- [ ] Can create new clinic (auto-assigned)
- [ ] Can add physician to assigned practice
- [ ] Can submit assignment request
- [ ] Cannot see other reps' clinics

### Orders
- [ ] Can view orders from assigned clinics
- [ ] Cannot view orders from other clinics
- [ ] Orders table shows correct scoped data

### Wholesale
- [ ] Can see wholesale-enabled clinics
- [ ] Can create wholesale order for assigned clinic
- [ ] Cannot create order for non-assigned clinic

### Commissions
- [ ] Can view own commission ledger
- [ ] Can view own payout history
- [ ] Cannot see other reps' commissions

### Access Restrictions
- [ ] Redirected when accessing `/admin/index.php`
- [ ] Redirected when accessing `/admin/orders.php`
- [ ] Redirected when accessing `/admin/users.php`
- [ ] All blocked routes properly redirect

---

## Files Modified/Created

### New Files (Phase 3)
```
/admin/rep/
├── _header.php         # Sales rep portal header with navigation
├── _footer.php         # Portal footer
├── index.php           # Dashboard
├── clinics.php         # Clinic roster
├── onboard-clinic.php  # Create new clinic
├── add-physician.php   # Add physician to practice
├── assignment-requests.php  # Request clinic assignments
├── orders.php          # Scoped orders view
├── wholesale-orders.php     # Scoped wholesale orders
├── create-wholesale-order.php  # Create wholesale order
├── commissions.php     # Commission ledger
├── payouts.php         # Payout history
├── messages.php        # Messages access
└── account.php         # My Account

/docs/sales-rep-feature/
└── PERMISSIONS.md      # This document
```

### Modified Files
```
/admin/auth.php         # Added sales rep auth functions
/api/login.php          # Added sales rep detection and routing
/admin/index.php        # Added deny_sales_rep() guard
/admin/revenue-report.php
/admin/shipments.php
/admin/patients.php
/admin/orders.php
/admin/billing.php
/admin/billing-wholesale.php
/admin/wholesale-orders.php
/admin/create-wholesale-order.php
/admin/practice-pricing.php
/admin/delivery-audit.php
/admin/users.php
```

---

## Summary

Phase 3 implements a complete role-based access control system for sales representatives:

1. **Authentication**: Sales reps are detected via their `sales_reps` profile during login
2. **Routing**: Active sales reps are automatically routed to `/admin/rep/`
3. **Navigation**: Dedicated sidebar with scoped functionality
4. **Permissions**: Guards on all admin pages redirect sales reps
5. **Data Scoping**: All queries filter by `assigned_rep_id`

The implementation follows existing patterns in the codebase while creating a distinct, secure portal for external sales representatives.

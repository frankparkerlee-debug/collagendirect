# CollagenDirect Data Relationships Documentation

**Generated:** 2025-12-15
**Purpose:** Document all foreign key relationships, data flows, and entity connections for preservation.

---

## Table of Contents

1. [Entity Relationship Overview](#entity-relationship-overview)
2. [User & Role Relationships](#user--role-relationships)
3. [Sales Rep Relationships](#sales-rep-relationships)
4. [Patient & Order Relationships](#patient--order-relationships)
5. [Commission System Relationships](#commission-system-relationships)
6. [Preauthorization Relationships](#preauthorization-relationships)
7. [Sales Outreach Relationships](#sales-outreach-relationships)
8. [Cascade Behavior Matrix](#cascade-behavior-matrix)
9. [Data Flow Diagrams](#data-flow-diagrams)

---

## Entity Relationship Overview

### Primary Entity Groups

```
┌─────────────────────────────────────────────────────────────────┐
│                     USER MANAGEMENT                              │
├─────────────────────────────────────────────────────────────────┤
│  users ←──→ admin_users (separate tables)                       │
│    ↓                                                             │
│  sales_reps (1:1 extension)                                     │
│    ↓                                                             │
│  admin_physicians (many-to-many junction)                       │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     CLINICAL DATA                                │
├─────────────────────────────────────────────────────────────────┤
│  users (physicians) → patients → orders → preauth_requests      │
│                                    ↓                             │
│                              products (lookup)                   │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     SALES & COMMISSIONS                          │
├─────────────────────────────────────────────────────────────────┤
│  sales_reps → rep_commission_rates                              │
│            → rep_commission_ledger → rep_commission_payouts     │
│            → rep_signed_documents                               │
│                                                                  │
│  users.assigned_rep_id → sales_reps.id                          │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                     BILLING & PAYMENTS                           │
├─────────────────────────────────────────────────────────────────┤
│  orders → wholesale_payments                                     │
│        → (amount_due, amount_paid, balance_due fields)          │
└─────────────────────────────────────────────────────────────────┘
```

---

## User & Role Relationships

### users Table Relationships

```
users
├── id (VARCHAR 64) - UUID PRIMARY KEY
│
├── ONE-TO-MANY RELATIONSHIPS (as parent)
│   ├── → patients.user_id (CASCADE) - Owns patients
│   ├── → orders.user_id (CASCADE) - Places orders
│   └── → practice_locations.user_id (CASCADE) - Has locations
│
├── ONE-TO-ONE RELATIONSHIP
│   └── → sales_reps.user_id (CASCADE) - Sales rep extension
│
├── MANY-TO-ONE RELATIONSHIPS (as child)
│   ├── assigned_rep_id → sales_reps.id (SET NULL)
│   └── rep_assigned_by_user_id → users.id (SET NULL) - Self-ref
│
└── MANY-TO-MANY (via junction)
    └── admin_physicians (admin can view physician)
```

### admin_users Table Relationships

```
admin_users
├── id (SERIAL) - INTEGER PRIMARY KEY
│
├── ONE-TO-MANY RELATIONSHIPS
│   ├── → admin_physicians.admin_id (CASCADE)
│   ├── → admin_permissions.admin_user_id (CASCADE)
│   └── → wholesale_payments.recorded_by (SET NULL)
│
└── SEPARATE FROM users TABLE
    └── Different ID type (INTEGER vs UUID)
    └── Different authentication flow
```

### Mixed ID Type Handling

**IMPORTANT:** The system uses two ID types:
- `users.id` = VARCHAR(64) UUID string
- `admin_users.id` = SERIAL integer

When joining tables that may reference either:
```sql
-- Use CASE with regex pattern matching
SELECT
  CASE
    WHEN col.set_by ~ '^[0-9]+$'
    THEN (SELECT name FROM admin_users WHERE id = col.set_by::integer)
    WHEN col.set_by IS NOT NULL
    THEN (SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = col.set_by)
    ELSE NULL
  END as set_by_name
FROM ...
```

---

## Sales Rep Relationships

### sales_reps Entity

```
sales_reps
├── id (VARCHAR 64) - PRIMARY KEY
├── user_id → users.id (CASCADE, UNIQUE)
│   └── One user can be one sales rep
│
├── RELATIONSHIPS TO OTHER TABLES
│   ├── → rep_commission_rates.rep_id (CASCADE)
│   ├── → rep_commission_ledger.rep_id (CASCADE)
│   ├── → rep_commission_payouts.rep_id (CASCADE)
│   ├── → rep_signed_documents.rep_id (CASCADE)
│   └── ← users.assigned_rep_id (SET NULL on rep delete)
│
├── approved_by → users.id (SET NULL)
│   └── Admin who approved the rep application
│
└── invited_by → users.id (SET NULL)
    └── Admin who sent invite (Phase 9)
```

### Rep-to-Clinic Assignment

```
                  ┌─────────────┐
                  │   users     │
                  │ (clinics)   │
                  └──────┬──────┘
                         │ assigned_rep_id
                         ↓
                  ┌─────────────┐
                  │ sales_reps  │
                  └──────┬──────┘
                         │ user_id
                         ↓
                  ┌─────────────┐
                  │   users     │
                  │ (rep user)  │
                  └─────────────┘

Assignment Method: users.rep_assigned_by
- 'self_onboard'     → Rep self-assigned during clinic onboarding
- 'admin_assign'     → Admin assigned via sales-rep-detail.php
- 'approved_request' → Assignment via approval workflow
```

---

## Patient & Order Relationships

### Clinical Data Flow

```
┌─────────────┐      ┌─────────────┐      ┌─────────────┐
│   users     │──1:N─│  patients   │──1:N─│   orders    │
│ (physician) │      │             │      │             │
└─────────────┘      └─────────────┘      └──────┬──────┘
                                                  │
                     ┌────────────────────────────┼────────────────────────────┐
                     │                            │                            │
                     ↓                            ↓                            ↓
              ┌─────────────┐            ┌─────────────┐            ┌─────────────┐
              │  products   │            │  preauth_   │            │ wholesale_  │
              │  (lookup)   │            │  requests   │            │  payments   │
              └─────────────┘            └─────────────┘            └─────────────┘
```

### orders Table Foreign Keys

```
orders
├── patient_id → patients.id (CASCADE)
│   └── Order belongs to patient; delete patient = delete orders
│
├── user_id → users.id (CASCADE)
│   └── Order placed by physician/clinic
│
├── product_id → products.id (implicit, no enforced FK)
│   └── Referenced but not enforced at DB level
│
└── Additional References
    ├── voided_by → admin_users.id (not enforced)
    └── (product name stored as text for history)
```

---

## Commission System Relationships

### Commission Data Model

```
┌─────────────────┐
│   sales_reps    │
└────────┬────────┘
         │
    ┌────┴────┬─────────────────┬─────────────────┐
    │         │                 │                 │
    ↓         ↓                 ↓                 ↓
┌────────┐ ┌────────────┐ ┌────────────┐ ┌──────────────┐
│ rates  │ │   ledger   │ │  payouts   │ │  documents   │
└────────┘ └─────┬──────┘ └──────┬─────┘ └──────────────┘
                 │               │
                 └───────┬───────┘
                         │ payout_id
                         ↓
              (ledger entries linked to payouts)
```

### rep_commission_ledger Relationships

```
rep_commission_ledger
├── rep_id → sales_reps.id (CASCADE)
│   └── Commission belongs to sales rep
│
├── order_id → orders.id (CASCADE)
│   └── Commission generated from this order
│   └── UNIQUE(rep_id, order_id) - one commission per rep per order
│
├── clinic_id → users.id (CASCADE)
│   └── Which clinic's order generated this
│
└── payout_id → rep_commission_payouts.id (SET NULL)
    └── Links to batch payout when paid
    └── NULL = pending, has value = paid
```

### Commission Calculation Flow

```
1. Order Completed/Paid
        ↓
2. Find assigned rep: users.assigned_rep_id
        ↓
3. Get rep's rate: rep_commission_rates WHERE end_date IS NULL
        ↓
4. Calculate: commission_amount = collected_amount × rate
        ↓
5. Create ledger entry (status = 'pending')
        ↓
6. Admin processes payout
        ↓
7. Create rep_commission_payouts record
        ↓
8. Update ledger: payout_id = payout.id, status = 'paid'
```

---

## Preauthorization Relationships

### Preauth Entity Model

```
┌─────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   orders    │──1:1│ preauth_requests │──1:N│ preauth_audit_  │
│             │     │                  │     │      log        │
└─────────────┘     └────────┬─────────┘     └─────────────────┘
                             │
                    ┌────────┴────────┐
                    │                 │
                    ↓                 ↓
            ┌─────────────┐   ┌─────────────┐
            │  patients   │   │ preauth_    │
            │             │   │   rules     │
            └─────────────┘   │  (lookup)   │
                              └─────────────┘
```

### preauth_requests Foreign Keys

```
preauth_requests
├── order_id → orders.id (CASCADE)
│   └── Links to the order requiring preauth
│
├── patient_id → patients.id (CASCADE)
│   └── Patient whose insurance is being verified
│
└── Implicit References
    ├── created_by → users.id (audit)
    └── updated_by → users.id (audit)
```

### preauth_audit_log Relationships

```
preauth_audit_log
├── preauth_request_id → preauth_requests.id (CASCADE)
│   └── All audit entries deleted when preauth deleted
│
└── actor_id → users.id (implicit, not enforced)
    └── Who performed the action
```

---

## Sales Outreach Relationships

### Lead Management Model

```
┌─────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   leads     │──1:N│  outreach_log   │──N:1│   outreach_     │
│             │     │                  │     │   campaigns     │
└─────────────┘     └─────────────────┘     └─────────────────┘
                              │
                              │ TEXT references (not FK)
                              ↓
                    ┌─────────────────┐
                    │  email_templates│
                    │  sms_templates  │
                    │  call_scripts   │
                    └─────────────────┘
```

### outreach_log Foreign Keys

```
outreach_log
├── lead_id → leads.id (CASCADE)
│   └── Outreach associated with lead
│
└── campaign_id → outreach_campaigns.id (SET NULL)
    └── Optional campaign association
    └── NULL for ad-hoc outreach
```

---

## Cascade Behavior Matrix

### ON DELETE CASCADE (Deletes Child Records)

| Parent Table | Child Table | FK Column | Effect |
|--------------|-------------|-----------|--------|
| users | patients | user_id | Delete patients |
| users | orders | user_id | Delete orders |
| users | sales_reps | user_id | Delete rep profile |
| users | practice_locations | user_id | Delete locations |
| patients | orders | patient_id | Delete orders |
| orders | preauth_requests | order_id | Delete preauth |
| orders | rep_commission_ledger | order_id | Delete commissions |
| sales_reps | rep_commission_rates | rep_id | Delete rates |
| sales_reps | rep_commission_ledger | rep_id | Delete commissions |
| sales_reps | rep_commission_payouts | rep_id | Delete payouts |
| sales_reps | rep_signed_documents | rep_id | Delete documents |
| preauth_requests | preauth_audit_log | preauth_request_id | Delete audit |
| leads | outreach_log | lead_id | Delete outreach |
| admin_users | admin_physicians | admin_id | Delete mappings |
| admin_users | admin_permissions | admin_user_id | Delete perms |

### ON DELETE SET NULL (Preserves Child, Nulls FK)

| Parent Table | Child Table | FK Column | Effect |
|--------------|-------------|-----------|--------|
| sales_reps | users | assigned_rep_id | Unassign clinics |
| users | sales_reps | approved_by | Clear approver |
| users | sales_reps | invited_by | Clear inviter |
| users | rep_commission_rates | set_by | Clear setter |
| users | rep_commission_payouts | processed_by | Clear processor |
| users | rep_assignment_requests | reviewed_by | Clear reviewer |
| orders | wholesale_payments | order_id | Orphan payment |
| users | wholesale_payments | user_id | Orphan payment |
| admin_users | wholesale_payments | recorded_by | Clear recorder |
| rep_commission_payouts | rep_commission_ledger | payout_id | Reset to pending |
| outreach_campaigns | outreach_log | campaign_id | Orphan log |

---

## Data Flow Diagrams

### Order Creation Flow

```
Physician Login
      │
      ↓
Select/Create Patient (users → patients)
      │
      ↓
Select Product (products lookup)
      │
      ↓
Enter Clinical Info (ICD-10, wound details)
      │
      ↓
Create Order (orders table)
      │
      ├──→ IF wholesale: Calculate pricing
      │         └──→ Create wholesale_payments on payment
      │
      └──→ IF referral: Check insurance
               └──→ Create preauth_requests if needed
                        └──→ Log to preauth_audit_log
```

### Commission Calculation Flow

```
Order Delivered/Paid
      │
      ↓
Check: users.assigned_rep_id IS NOT NULL?
      │
      ├── NO → No commission
      │
      └── YES → Get sales_reps record
                    │
                    ↓
               Get current rate from rep_commission_rates
               WHERE end_date IS NULL
               ORDER BY effective_date DESC, created_at DESC
                    │
                    ↓
               Calculate: collected_amount × rate
                    │
                    ↓
               Insert into rep_commission_ledger
               status = 'pending'
                    │
                    ↓
               [Later] Admin creates payout
                    │
                    ↓
               Update ledger: payout_id, status = 'paid'
```

### Rep Assignment Flow

```
Admin Opens sales-rep-detail.php
      │
      ↓
Query Available Clinics:
  SELECT * FROM users
  WHERE role IN ('physician', 'practice_admin')
    AND (assigned_rep_id IS NULL OR assigned_rep_id = '')
    AND id NOT IN (SELECT user_id FROM sales_reps WHERE user_id IS NOT NULL)
      │
      ↓
Admin Selects Clinic
      │
      ↓
UPDATE users SET
  assigned_rep_id = [rep_id],
  rep_assignment_date = NOW(),
  rep_assigned_by = 'admin_assign',
  rep_assigned_by_user_id = [admin_user_id]
WHERE id = [clinic_id]
      │
      ↓
Clinic now appears in rep's assigned clinics
Orders from this clinic generate commissions for this rep
```

---

## Query Patterns

### Finding Rep's Assigned Clinics

```sql
SELECT u.id, u.email, u.practice_name, u.first_name, u.last_name
FROM users u
WHERE u.assigned_rep_id = :rep_id
AND u.role IN ('physician', 'practice_admin')
ORDER BY u.practice_name;
```

### Finding Clinic's Assigned Rep

```sql
SELECT sr.*, u.first_name, u.last_name, u.email
FROM sales_reps sr
JOIN users u ON sr.user_id = u.id
WHERE sr.id = (SELECT assigned_rep_id FROM users WHERE id = :clinic_id);
```

### Calculating Rep's Pending Commission Balance

```sql
SELECT
  COALESCE(SUM(commission_amount), 0) as pending_total
FROM rep_commission_ledger
WHERE rep_id = :rep_id
AND status = 'pending';
```

### Getting Rep's Current Rate

```sql
SELECT rate
FROM rep_commission_rates
WHERE rep_id = :rep_id
AND (effective_date IS NULL OR effective_date <= CURRENT_DATE)
ORDER BY effective_date DESC NULLS LAST, created_at DESC
LIMIT 1;
```

### Wholesale Orders with Balance Due

```sql
SELECT o.*, u.practice_name, u.billing_contact_email
FROM orders o
JOIN users u ON o.user_id = u.id
WHERE o.billed_by = 'practice_dme'
AND o.invoice_status IN ('invoiced', 'partial', 'overdue')
AND (o.amount_due - o.amount_paid) > 0
ORDER BY o.due_date ASC;
```

---

## Integrity Constraints Summary

### Unique Constraints

| Table | Columns | Purpose |
|-------|---------|---------|
| users | email | One account per email |
| admin_users | email | One admin per email |
| products | sku | One product per SKU |
| sales_reps | user_id | One rep profile per user |
| rep_commission_ledger | (rep_id, order_id) | One commission per rep per order |
| preauth_rules | (carrier_name, hcpcs_code) | One rule per carrier/product |
| eligibility_cache | (member_id, carrier_name) | One cache per member/carrier |
| rep_signed_documents | (rep_id, document_type, document_version) | One signature per doc version |

### Check Constraints

| Table | Column | Allowed Values |
|-------|--------|----------------|
| sales_reps | status | pending, invited, active, suspended, terminated |
| rep_commission_rates | rate | 0.0000 to 1.0000 |
| rep_commission_ledger | status | pending, paid, voided |
| rep_commission_ledger | order_type | referral, wholesale |
| rep_commission_payouts | payment_method | check, ach, wire, other |
| rep_signed_documents | document_type | rep_agreement, baa, nda, w9, other |
| rep_signed_documents | source | self_service, invite_completion, offline_upload, offline_attestation |
| preauth_requests | status | pending, submitted, approved, denied, expired, cancelled, need_info |
| users | rep_assigned_by | self_onboard, admin_assign, approved_request |
| orders | invoice_status | pending, invoiced, partial, paid, overdue, void |

---

*This document should be updated whenever data relationships change.*

# Sales Rep Feature - Database Schema Documentation

> **Version**: 1.0
> **Created**: December 14, 2025
> **Status**: Phase 2 Complete - Migrations Ready for Review

---

## Table of Contents

1. [Overview](#overview)
2. [Entity Relationship Diagram](#entity-relationship-diagram)
3. [New Tables](#new-tables)
4. [Modified Tables](#modified-tables)
5. [Indexes](#indexes)
6. [Migration Files](#migration-files)
7. [Usage Examples](#usage-examples)

---

## Overview

This schema extends the CollagenDirect database to support sales representative management, including:

- **Sales Rep Profiles**: Extended user data for reps
- **Commission Tracking**: Rate history, earned commissions, and payouts
- **Clinic Assignments**: Request workflow and assignment tracking
- **Compliance**: Signed agreement tracking with e-signature data

### Design Principles

1. **Extends existing patterns**: Uses VARCHAR(64) IDs, PostgreSQL features, and existing FK relationships
2. **Audit-friendly**: Timestamps, user references for all changes
3. **Immutable history**: Commission rates and signatures are never deleted
4. **Soft deletes where appropriate**: Via CASCADE from parent tables

---

## Entity Relationship Diagram

```
                                    ┌─────────────────┐
                                    │     users       │
                                    │ (existing)      │
                                    ├─────────────────┤
                                    │ + assigned_rep_id ──────────────┐
                                    │ + rep_assignment_date           │
                                    │ + rep_assigned_by               │
                                    │ + rep_assigned_by_user_id       │
                                    └─────────┬───────┘               │
                                              │                       │
                                              │ 1:1                   │
                                              ▼                       │
┌─────────────────────────────────────────────────────────────────────┼───┐
│                           sales_reps                                │   │
├─────────────────────────────────────────────────────────────────────┤   │
│ id (PK)                                                             │◄──┘
│ user_id (FK → users) ◄──────────────────────────────────────────────┤
│ company_name                                                        │
│ status: pending | active | suspended | terminated                   │
│ application_date, approved_date, approved_by                        │
└─────────┬─────────────┬─────────────┬─────────────┬─────────────────┘
          │             │             │             │
          │             │             │             │
          ▼             ▼             ▼             ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│ rep_commission  │ │ rep_signed      │ │ rep_assignment  │ │ rep_commission  │
│ _rates          │ │ _documents      │ │ _requests       │ │ _ledger         │
├─────────────────┤ ├─────────────────┤ ├─────────────────┤ ├─────────────────┤
│ id (PK)         │ │ id (PK)         │ │ id (PK)         │ │ id (PK)         │
│ rep_id (FK)     │ │ rep_id (FK)     │ │ rep_id (FK)     │ │ rep_id (FK)     │
│ rate (0.00-1.00)│ │ document_type   │ │ clinic_id (FK)  │ │ order_id (FK)   │
│ effective_date  │ │ document_version│ │ status          │ │ clinic_id (FK)  │
│ end_date        │ │ signed_at       │ │ rep_note        │ │ payment_date    │
│ created_by      │ │ ip_address      │ │ reviewed_by     │ │ collected_amount│
│                 │ │ signature_text  │ │ denial_reason   │ │ commission_rate │
│                 │ │                 │ │                 │ │ commission_amt  │
│                 │ │                 │ │                 │ │ payout_id (FK)──┼──┐
└─────────────────┘ └─────────────────┘ └─────────────────┘ └─────────────────┘  │
                                                                                  │
                                                           ┌─────────────────────┘
                                                           │
                                                           ▼
                                              ┌─────────────────────────┐
                                              │ rep_commission_payouts  │
                                              ├─────────────────────────┤
                                              │ id (PK)                 │
                                              │ rep_id (FK)             │
                                              │ amount                  │
                                              │ payout_date             │
                                              │ payment_method          │
                                              │ reference_number        │
                                              │ processed_by            │
                                              └─────────────────────────┘
```

---

## New Tables

### 1. sales_reps

Sales rep profile extending the user account.

```sql
CREATE TABLE sales_reps (
  id              VARCHAR(64) PRIMARY KEY DEFAULT gen_random_uuid()::text,
  user_id         VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  company_name    VARCHAR(255),
  status          VARCHAR(20) NOT NULL DEFAULT 'pending'
                    CHECK (status IN ('pending', 'active', 'suspended', 'terminated')),
  application_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  approved_date   TIMESTAMP WITH TIME ZONE,
  approved_by     VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL,
  how_heard_about_us TEXT,
  notes           TEXT,
  created_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT unique_user_sales_rep UNIQUE(user_id)
);
```

| Column | Type | Description |
|--------|------|-------------|
| `id` | VARCHAR(64) | Primary key (UUID) |
| `user_id` | VARCHAR(64) | FK to users - the rep's login account |
| `company_name` | VARCHAR(255) | Rep's company (if independent) |
| `status` | VARCHAR(20) | Application/account status |
| `application_date` | TIMESTAMP | When rep applied |
| `approved_date` | TIMESTAMP | When approved (null if pending) |
| `approved_by` | VARCHAR(64) | Admin who approved |
| `how_heard_about_us` | TEXT | Lead source tracking |

**Status Values:**
- `pending` - Application submitted, awaiting review
- `active` - Approved and able to earn commissions
- `suspended` - Temporarily disabled
- `terminated` - Permanently deactivated

---

### 2. rep_commission_rates

Commission rate history per sales rep.

```sql
CREATE TABLE rep_commission_rates (
  id              SERIAL PRIMARY KEY,
  rep_id          VARCHAR(64) NOT NULL REFERENCES sales_reps(id) ON DELETE CASCADE,
  rate            DECIMAL(5,4) NOT NULL CHECK (rate >= 0 AND rate <= 1),
  effective_date  DATE NOT NULL DEFAULT CURRENT_DATE,
  end_date        DATE,
  created_by      VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL,
  notes           TEXT,
  created_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT valid_date_range CHECK (end_date IS NULL OR end_date >= effective_date)
);
```

| Column | Type | Description |
|--------|------|-------------|
| `rate` | DECIMAL(5,4) | Commission rate (0.0000-1.0000, e.g., 0.2500 = 25%) |
| `effective_date` | DATE | When this rate starts |
| `end_date` | DATE | When this rate ends (NULL = current) |
| `created_by` | VARCHAR(64) | Admin who set the rate |

**Finding Current Rate:**
```sql
SELECT rate FROM rep_commission_rates
WHERE rep_id = ? AND effective_date <= CURRENT_DATE
  AND (end_date IS NULL OR end_date >= CURRENT_DATE)
ORDER BY effective_date DESC LIMIT 1;
```

---

### 3. rep_signed_documents

E-signature records for compliance.

```sql
CREATE TABLE rep_signed_documents (
  id              SERIAL PRIMARY KEY,
  rep_id          VARCHAR(64) NOT NULL REFERENCES sales_reps(id) ON DELETE CASCADE,
  document_type   VARCHAR(50) NOT NULL
                    CHECK (document_type IN ('rep_agreement', 'baa', 'nda', 'w9', 'other')),
  document_version VARCHAR(100) NOT NULL,
  signed_at       TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip_address      VARCHAR(45),
  user_agent      TEXT,
  signature_text  VARCHAR(255) NOT NULL,
  signature_title VARCHAR(100),
  document_content TEXT,
  document_path   VARCHAR(500),
  created_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT unique_rep_document_version UNIQUE(rep_id, document_type, document_version)
);
```

| Column | Type | Description |
|--------|------|-------------|
| `document_type` | VARCHAR(50) | Type: rep_agreement, baa, nda, w9, other |
| `document_version` | VARCHAR(100) | Version ID or hash |
| `signed_at` | TIMESTAMP | Exact signing time (UTC) |
| `ip_address` | VARCHAR(45) | IPv4 or IPv6 address |
| `user_agent` | TEXT | Browser/client info |
| `signature_text` | VARCHAR(255) | Typed legal name |
| `document_path` | VARCHAR(500) | Path to stored document |

**Document Types:**
- `rep_agreement` - Sales representative agreement
- `baa` - Business Associate Agreement (HIPAA)
- `nda` - Non-disclosure agreement
- `w9` - Tax form
- `other` - Other documents

---

### 4. rep_assignment_requests

Workflow for reps requesting clinic assignments.

```sql
CREATE TABLE rep_assignment_requests (
  id              SERIAL PRIMARY KEY,
  rep_id          VARCHAR(64) NOT NULL REFERENCES sales_reps(id) ON DELETE CASCADE,
  clinic_id       VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  status          VARCHAR(20) NOT NULL DEFAULT 'pending'
                    CHECK (status IN ('pending', 'approved', 'denied')),
  rep_note        TEXT,
  request_date    TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  reviewed_by     VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL,
  reviewed_date   TIMESTAMP WITH TIME ZONE,
  denial_reason   TEXT,
  created_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT unique_pending_request UNIQUE(rep_id, clinic_id, status)
);
```

| Column | Type | Description |
|--------|------|-------------|
| `clinic_id` | VARCHAR(64) | FK to users (the practice being requested) |
| `status` | VARCHAR(20) | pending, approved, denied |
| `rep_note` | TEXT | Rep's justification for assignment |
| `reviewed_by` | VARCHAR(64) | Admin who reviewed |
| `denial_reason` | TEXT | Why request was denied |

**Workflow:**
1. Rep submits request (`status = 'pending'`)
2. Admin reviews and approves/denies
3. If approved, `users.assigned_rep_id` is updated

---

### 5. rep_commission_ledger

Individual commission entries per order.

```sql
CREATE TABLE rep_commission_ledger (
  id                SERIAL PRIMARY KEY,
  rep_id            VARCHAR(64) NOT NULL REFERENCES sales_reps(id) ON DELETE CASCADE,
  order_id          VARCHAR(64) NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
  order_type        VARCHAR(20) NOT NULL CHECK (order_type IN ('referral', 'wholesale')),
  payment_id        INTEGER,
  clinic_id         VARCHAR(64) NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  payment_date      DATE NOT NULL,
  collected_amount  DECIMAL(10,2) NOT NULL CHECK (collected_amount >= 0),
  commission_rate   DECIMAL(5,4) NOT NULL CHECK (commission_rate >= 0 AND commission_rate <= 1),
  commission_amount DECIMAL(10,2) NOT NULL CHECK (commission_amount >= 0),
  payout_id         INTEGER REFERENCES rep_commission_payouts(id) ON DELETE SET NULL,
  status            VARCHAR(20) NOT NULL DEFAULT 'pending'
                      CHECK (status IN ('pending', 'paid', 'voided')),
  notes             TEXT,
  created_at        TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT unique_order_commission UNIQUE(rep_id, order_id)
);
```

| Column | Type | Description |
|--------|------|-------------|
| `order_type` | VARCHAR(20) | 'referral' or 'wholesale' |
| `collected_amount` | DECIMAL(10,2) | Payment received on order |
| `commission_rate` | DECIMAL(5,4) | Rate snapshot at calculation time |
| `commission_amount` | DECIMAL(10,2) | collected_amount × commission_rate |
| `payout_id` | INTEGER | FK to payouts (when paid) |
| `status` | VARCHAR(20) | pending, paid, voided |

**Status Values:**
- `pending` - Earned but not yet paid out
- `paid` - Included in a payout
- `voided` - Cancelled (order refunded, etc.)

---

### 6. rep_commission_payouts

Records of commission payments to reps.

```sql
CREATE TABLE rep_commission_payouts (
  id               SERIAL PRIMARY KEY,
  rep_id           VARCHAR(64) NOT NULL REFERENCES sales_reps(id) ON DELETE CASCADE,
  amount           DECIMAL(10,2) NOT NULL CHECK (amount > 0),
  payout_date      DATE NOT NULL DEFAULT CURRENT_DATE,
  payment_method   VARCHAR(20) NOT NULL
                     CHECK (payment_method IN ('check', 'ach', 'wire', 'other')),
  reference_number VARCHAR(100),
  period_start     DATE,
  period_end       DATE,
  notes            TEXT,
  processed_by     VARCHAR(64) REFERENCES users(id) ON DELETE SET NULL,
  created_at       TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT valid_payout_period CHECK (period_end IS NULL OR period_end >= period_start)
);
```

| Column | Type | Description |
|--------|------|-------------|
| `amount` | DECIMAL(10,2) | Total payout amount |
| `payment_method` | VARCHAR(20) | check, ach, wire, other |
| `reference_number` | VARCHAR(100) | Check #, ACH trace, wire ref |
| `period_start/end` | DATE | Commission period covered |
| `processed_by` | VARCHAR(64) | Admin who processed payout |

---

## Modified Tables

### users (Existing)

**Added Columns:**

```sql
ALTER TABLE users ADD COLUMN assigned_rep_id VARCHAR(64)
  REFERENCES sales_reps(id) ON DELETE SET NULL;

ALTER TABLE users ADD COLUMN rep_assignment_date TIMESTAMP WITH TIME ZONE;

ALTER TABLE users ADD COLUMN rep_assigned_by VARCHAR(30)
  CHECK (rep_assigned_by IS NULL OR rep_assigned_by IN
    ('self_onboard', 'admin_assign', 'approved_request'));

ALTER TABLE users ADD COLUMN rep_assigned_by_user_id VARCHAR(64)
  REFERENCES users(id) ON DELETE SET NULL;
```

| Column | Type | Description |
|--------|------|-------------|
| `assigned_rep_id` | VARCHAR(64) | FK to sales_reps |
| `rep_assignment_date` | TIMESTAMP | When rep was assigned |
| `rep_assigned_by` | VARCHAR(30) | Assignment method |
| `rep_assigned_by_user_id` | VARCHAR(64) | Who performed assignment |

**Assignment Methods:**
- `self_onboard` - Rep acquired clinic during onboarding
- `admin_assign` - Admin manually assigned
- `approved_request` - Rep requested and was approved

---

## Indexes

| Table | Index | Columns | Purpose |
|-------|-------|---------|---------|
| sales_reps | idx_sales_reps_user_id | user_id | User lookup |
| sales_reps | idx_sales_reps_status | status | Filter by status |
| sales_reps | idx_sales_reps_application_date | application_date | Sort applications |
| rep_commission_rates | idx_rep_commission_rates_rep_id | rep_id | Rep's rates |
| rep_commission_rates | idx_rep_commission_rates_current | rep_id, effective_date (WHERE end_date IS NULL) | Find current rate |
| rep_signed_documents | idx_rep_signed_documents_rep_id | rep_id | Rep's documents |
| rep_signed_documents | idx_rep_signed_documents_type | document_type | Filter by type |
| rep_assignment_requests | idx_rep_assignment_requests_pending | status (WHERE = 'pending') | Pending queue |
| rep_commission_ledger | idx_rep_commission_ledger_rep_id | rep_id | Rep's commissions |
| rep_commission_ledger | idx_rep_commission_ledger_pending | rep_id, status (WHERE = 'pending') | Unpaid commissions |
| rep_commission_payouts | idx_rep_commission_payouts_rep_id | rep_id | Rep's payouts |
| users | idx_users_assigned_rep_id | assigned_rep_id | Find rep's clinics |

---

## Migration Files

Located in: `/admin/migrations/sales-rep-feature/`

| File | Purpose |
|------|---------|
| `001_create_sales_reps.php` | Create sales_reps table |
| `002_create_rep_commission_rates.php` | Create commission rates table |
| `003_create_rep_signed_documents.php` | Create signed documents table |
| `004_create_rep_assignment_requests.php` | Create assignment requests table |
| `005_create_rep_commission_ledger.php` | Create commission ledger table |
| `006_create_rep_commission_payouts.php` | Create payouts table + FK to ledger |
| `007_add_rep_columns_to_users.php` | Add rep columns to users table |
| `run-all-migrations.php` | Execute all migrations in order |

**Running Migrations:**
```bash
php admin/migrations/sales-rep-feature/run-all-migrations.php
```

---

## Usage Examples

### Create a Sales Rep

```php
// 1. Create user account first (existing pattern)
$userId = bin2hex(random_bytes(16));
$stmt = $pdo->prepare("
  INSERT INTO users (id, email, password_hash, first_name, last_name, role)
  VALUES (?, ?, ?, ?, ?, 'sales_rep')
");
$stmt->execute([$userId, $email, password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName]);

// 2. Create sales rep profile
$stmt = $pdo->prepare("
  INSERT INTO sales_reps (user_id, company_name, how_heard_about_us)
  VALUES (?, ?, ?)
  RETURNING id
");
$stmt->execute([$userId, $companyName, $howHeard]);
$repId = $stmt->fetchColumn();
```

### Set Commission Rate

```php
// Close any existing open rate
$pdo->prepare("
  UPDATE rep_commission_rates
  SET end_date = CURRENT_DATE - INTERVAL '1 day'
  WHERE rep_id = ? AND end_date IS NULL
")->execute([$repId]);

// Insert new rate
$pdo->prepare("
  INSERT INTO rep_commission_rates (rep_id, rate, effective_date, created_by)
  VALUES (?, ?, CURRENT_DATE, ?)
")->execute([$repId, 0.25, $adminUserId]);
```

### Calculate Commission on Payment

```php
// Get current rate
$stmt = $pdo->prepare("
  SELECT rate FROM rep_commission_rates
  WHERE rep_id = ?
    AND effective_date <= CURRENT_DATE
    AND (end_date IS NULL OR end_date >= CURRENT_DATE)
  ORDER BY effective_date DESC
  LIMIT 1
");
$stmt->execute([$repId]);
$rate = $stmt->fetchColumn() ?: 0;

// Calculate and record
$commissionAmount = $collectedAmount * $rate;
$pdo->prepare("
  INSERT INTO rep_commission_ledger
    (rep_id, order_id, order_type, clinic_id, payment_date, collected_amount, commission_rate, commission_amount)
  VALUES (?, ?, ?, ?, CURRENT_DATE, ?, ?, ?)
")->execute([$repId, $orderId, $orderType, $clinicId, $collectedAmount, $rate, $commissionAmount]);
```

### Process Payout

```php
$pdo->beginTransaction();

// Get pending commissions
$stmt = $pdo->prepare("
  SELECT id, commission_amount
  FROM rep_commission_ledger
  WHERE rep_id = ? AND status = 'pending'
");
$stmt->execute([$repId]);
$entries = $stmt->fetchAll();

$totalAmount = array_sum(array_column($entries, 'commission_amount'));

// Create payout record
$stmt = $pdo->prepare("
  INSERT INTO rep_commission_payouts
    (rep_id, amount, payment_method, reference_number, processed_by)
  VALUES (?, ?, ?, ?, ?)
  RETURNING id
");
$stmt->execute([$repId, $totalAmount, 'ach', $reference, $adminUserId]);
$payoutId = $stmt->fetchColumn();

// Update ledger entries
$pdo->prepare("
  UPDATE rep_commission_ledger
  SET status = 'paid', payout_id = ?
  WHERE rep_id = ? AND status = 'pending'
")->execute([$payoutId, $repId]);

$pdo->commit();
```

### Find Rep's Assigned Clinics

```php
$stmt = $pdo->prepare("
  SELECT u.id, u.practice_name, u.email, u.rep_assignment_date
  FROM users u
  WHERE u.assigned_rep_id = ?
    AND u.role IN ('practice_admin', 'physician')
  ORDER BY u.rep_assignment_date DESC
");
$stmt->execute([$repId]);
$clinics = $stmt->fetchAll();
```

---

## Summary

This schema provides a complete foundation for:

1. **Sales Rep Management**: Onboarding, approval, status tracking
2. **Commission Structure**: Flexible rate history with audit trail
3. **Clinic Assignments**: Request workflow with admin approval
4. **Financial Tracking**: Per-order ledger and payout records
5. **Compliance**: Full e-signature capture for agreements

**Next Phase**: Implement UI and API endpoints in the admin portal.

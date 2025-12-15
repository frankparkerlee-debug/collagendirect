# Sales Rep Feature - Phase 1 Discovery Documentation

> **Purpose**: Comprehensive documentation of the CollagenDirect codebase architecture to inform the Sales Rep Feature implementation.
>
> **Created**: December 14, 2025
> **Status**: Phase 1 Complete - Documentation Only

---

## Table of Contents

1. [Project Structure](#1-project-structure)
2. [Roles and Permissions System](#2-roles-and-permissions-system)
3. [Onboarding Forms](#3-onboarding-forms)
4. [Database Schema](#4-database-schema)
5. [UI/UX Patterns](#5-uiux-patterns)
6. [Public Website Structure](#6-public-website-structure)

---

## 1. Project Structure

### 1.1 Directory Overview

```
/CollagenDirect/
├── admin/                      # CollagenDirect business admin interface (232 PHP files)
├── api/                        # Backend API endpoints (54 PHP files organized by function)
├── portal/                     # Physician portal interface (57 files)
├── assets/                     # Frontend assets (CSS, JS, images, product photos)
├── uploads/                    # Patient document storage (persistent disk)
├── migrations/                 # Database schema changes
├── prisma/                     # Schema definition file
├── vendor/                     # Composer dependencies (Twilio SDK, PHPMailer)
├── docs/                       # Documentation
├── resources/                  # Training guides and clinical evidence
├── index.html                  # Public marketing homepage
└── .htaccess                   # Apache URL rewriting for routing
```

### 1.2 Technology Stack

**Backend:**
- PHP 8.3 (vanilla PHP, no framework)
- PostgreSQL database (hosted on Render.com)
- Raw PDO with prepared statements (no ORM)

**Frontend:**
- Tailwind CSS (CDN via cdn.tailwindcss.com)
- Vanilla JavaScript (no React/Vue/Angular)
- Inter font family

**Dependencies (composer.json):**
- `twilio/sdk: ^8.8` - SMS delivery confirmations
- `phpmailer/phpmailer: ^6.9` - Email fallback

### 1.3 Database Connection

```php
$pdo = new PDO(
  "pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}",
  $DB_USER,
  $DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, ...]
);
```

Environment variables: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`

### 1.4 API Directory Structure

```
/api/
├── db.php                      # Database connection & session config
├── auth.php                    # Auth verification helper
├── login.php                   # Login endpoint
├── portal/                     # Physician portal APIs
│   ├── orders.create.php       # Order creation
│   ├── patients.php            # Patient management
│   ├── metrics.php             # Dashboard metrics
│   └── wholesale-order.create.php
├── admin/                      # Admin-only APIs
│   ├── order.review.php
│   ├── ai_assistant.php
│   └── create-wholesale-order.php
├── auth/                       # Password reset flow
├── services/                   # Service classes (23 files)
│   ├── ai_service.php          # AI order analysis
│   ├── email_notifications.php
│   └── twilio_sms.php
├── lib/                        # Utility libraries
└── cron/                       # Scheduled jobs
```

### 1.5 Authentication Flow

1. User submits credentials to `/api/login.php`
2. Checks `users` table first (physicians, practice_admin, superadmin)
3. Falls back to `admin_users` table (employees, manufacturers)
4. Password verified with `password_verify()` (bcrypt)
5. Session created with persistent cookie (7-30 days)
6. Role-based redirect to appropriate portal

**Session Configuration:**
- Portal: 30-day persistent cookies
- Admin: 7-day persistent cookies
- CSRF protection via `$_SESSION['csrf']`

---

## 2. Roles and Permissions System

### 2.1 User Tables

The system has **two distinct user tables**:

| Table | Purpose | Roles |
|-------|---------|-------|
| `users` | Physicians & Practice Managers | physician, practice_admin, superadmin |
| `admin_users` | CollagenDirect Staff | superadmin, admin, manufacturer, sales, employee, ops |

### 2.2 Portal Users (`users` table)

| Role | Access | Capabilities |
|------|--------|--------------|
| **Physician** | `/portal/` | Create patients, submit orders, view own data only |
| **Practice Admin** | `/portal/` (extended) | Manage physicians, locations, billing for practice |
| **Superadmin** | `/portal/` + `/admin/` | Full access to both portals |

### 2.3 Admin Users (`admin_users` table)

| Role | Access | Capabilities |
|------|--------|--------------|
| **Superadmin** | `/admin/` | Full system access |
| **Admin** | `/admin/` | Create staff, manage all users |
| **Manufacturer** | `/admin/` | View all physicians, add users, read-only on existing |
| **Sales** | `/admin/` | Manage physicians, view all |
| **Employee** | `/admin/` (limited) | View assigned physicians only |

### 2.4 Account Type Modifiers

```sql
-- In users table
account_type        VARCHAR(40)  -- 'referral', 'wholesale', 'both'
is_referral_only    BOOLEAN      -- Only offers referral/insurance orders
has_dme_license     BOOLEAN      -- Has wholesale/DME license
is_hybrid           BOOLEAN      -- Can do both referral and wholesale
can_manage_physicians BOOLEAN    -- Practice manager permissions
```

### 2.5 Permission Enforcement

**Admin Portal Protection (`admin/auth.php`):**
```php
function current_admin() {
  // Checks $_SESSION['admin'] for admin_users
  // Also accepts superadmin from users table
  // Explicitly EXCLUDES practice_admin
}

function require_admin() {
  // Guard function - redirects to login if not admin
}
```

**Portal Protection (`api/auth.php`):**
```php
function verifyAuth() {
  // Verifies $_SESSION['user_id'] exists
  // Returns user data with role
}
```

### 2.6 Feature-Level Permissions (admin/users.php)

```php
$isSuperadmin = $adminRole === 'superadmin';
$isOwner = in_array($adminRole, ['owner','superadmin','admin','practice_admin']);
$isAdmin = in_array($adminRole, ['owner','superadmin','admin']);
$isSales = $adminRole === 'sales';
$isManufacturer = $adminRole === 'manufacturer';

// Manufacturer: Can view all physicians, add users, but NOT delete
if ($isManufacturer && in_array($act, ['delete_emp'])) { /* blocked */ }

// Sales: Can manage physicians but NOT employees
if ($isSales && in_array($act, ['create_employee', 'delete_emp'])) { /* blocked */ }
```

### 2.7 Data Visibility Scoping

| Role | Physicians Visible | Orders Visible |
|------|-------------------|----------------|
| Superadmin | All | All |
| Manufacturer | All | All |
| Sales | All | All |
| Employee | Assigned only (via `admin_physicians`) | Assigned physicians' orders |
| Physician | N/A | Own orders only |
| Practice Admin | Practice physicians | Practice orders |

---

## 3. Onboarding Forms

### 3.1 Physician Registration Flow (`/register`)

**8-Step Multi-Step Form:**

#### Step 0: NPI Validation
| Field | Type | Required | Notes |
|-------|------|----------|-------|
| NPI Number | numeric | Yes | 10 digits, Luhn checksum validated |

- Calls NPPES registry API for validation
- Auto-fills: first name, last name, practice name, address, phone
- Detects duplicate registrations

#### Step 1: User Type Selection
Three mutually exclusive options:
- **Practice Manager/Admin** - Manage practice and physicians
- **Physician (Solo/Within Practice)** - Join existing practice
- **DME Wholesale Provider** - Hold DME license, bill directly

#### Step 2: Account Credentials
| Field | Type | Required | Validation |
|-------|------|----------|------------|
| Work Email | email | Yes | Email format |
| Password | password | Yes | Min 8 chars, include number |

#### Step 3: Practice Information (Practice Admin & DME only)
| Field | Type | Required |
|-------|------|----------|
| Practice/Facility Name | text | Yes |
| Full Address | text + autocomplete | Yes |
| City, State, ZIP | auto-filled | Yes |
| Practice Phone | tel | Yes |

#### Step 4: Physician Credentials (All types)
| Field | Type | Required |
|-------|------|----------|
| First Name | text | Yes |
| Last Name | text | Yes |
| NPI Number | numeric (readonly) | Yes |
| PTAN | text | No |
| Medical License # | text | Yes |
| License State | select | Yes |
| License Expiry | date | Yes |

#### Step 5: Additional Physicians (Practice Admin only)
Repeatable section for adding multiple physicians with same fields as Step 4.

#### Step 6: Practice Manager Link (Physician type only)
| Field | Type | Required |
|-------|------|----------|
| Practice Manager Email | email | Yes |

#### Step 7: DME License Info (DME Wholesale only)
| Field | Type | Required |
|-------|------|----------|
| DME License # | text | Yes |
| DME State | select | Yes |
| DME Expiry | date | Yes |

#### Step 8: Agreements & E-Sign
- **MSA Agreement**: Master Services & Supply Agreement (checkbox)
- **BAA Agreement**: Business Associate Agreement (HIPAA) (checkbox)
- **E-Signature**: Full legal name, title, date, IP address captured

**Endpoint**: `POST /api/register.php`

### 3.2 Patient Intake Form (Portal)

| Field | Type | Required |
|-------|------|----------|
| First Name | text | Yes |
| Last Name | text | Yes |
| Date of Birth | date | Yes |
| Phone Number | tel | Yes |
| Address | text + autocomplete | Yes |
| City, State, ZIP | text | Yes |
| SMS Consent | checkbox | No |

### 3.3 Required Patient Documents

| Document | Field | Formats |
|----------|-------|---------|
| Patient Photo ID | `id_card_path` | JPG, PNG, WEBP, HEIC |
| Insurance Card | `ins_card_path` | JPG, PNG, WEBP, HEIC |
| Assignment of Benefits | `aob_path` | PDF |
| Clinical Note/Rx | `rx_note_path` | PDF, JPG, PNG, TXT |
| Baseline Wound Photo | `baseline_wound_photo_path` | JPG, PNG |

### 3.4 Order Form - Wound Data

| Field | Type | Required |
|-------|------|----------|
| Wound Location | text | Yes |
| Wound Laterality | select | No |
| Wound Type | select | No |
| Wound Stage | select | No |
| Length (cm) | numeric | Yes |
| Width (cm) | numeric | Yes |
| Depth (cm) | numeric | No |
| ICD-10 Primary | text + autocomplete | Yes |
| ICD-10 Secondary | text | No |

### 3.5 Order Form - Product Selection

| Field | Type | Required |
|-------|------|----------|
| Product | select | Yes |
| Primary Dressing | select | Yes |
| Secondary Dressing | text | No |
| Frequency per Week | numeric | Yes |
| Quantity per Change | numeric | Yes |
| Duration (Days) | numeric | Yes |

---

## 4. Database Schema

### 4.1 Core Tables

#### users
Primary table for physicians and practice administrators.

```sql
id                    VARCHAR(64) PRIMARY KEY
email                 VARCHAR(255) UNIQUE
password_hash         VARCHAR(255)
first_name            VARCHAR(100)
last_name             VARCHAR(100)
practice_name         VARCHAR(255)
phone                 VARCHAR(50)
npi                   VARCHAR(20)
license               VARCHAR(100)
license_state         VARCHAR(10)
license_expiry        DATE
dme_number            VARCHAR(100)
dme_state             VARCHAR(10)
dme_expiry            DATE
address               VARCHAR(255)
city                  VARCHAR(100)
state                 VARCHAR(2)
zip                   VARCHAR(10)
account_type          VARCHAR(40) DEFAULT 'referral'
role                  VARCHAR(50) DEFAULT 'physician'
has_dme_license       BOOLEAN DEFAULT FALSE
agree_msa             BOOLEAN DEFAULT FALSE
agree_baa             BOOLEAN DEFAULT FALSE
status                VARCHAR(20) DEFAULT 'active'
current_location_id   INTEGER FK → practice_locations
default_location_id   INTEGER FK → practice_locations
created_at            TIMESTAMP
updated_at            TIMESTAMP
deleted_at            TIMESTAMP NULL
```

#### admin_users
Administrative user accounts (separate from physicians).

```sql
id              SERIAL PRIMARY KEY
email           VARCHAR(255) UNIQUE
password_hash   VARCHAR(255)
name            VARCHAR(120)
role            VARCHAR(50) DEFAULT 'admin'
created_at      TIMESTAMP
```

#### patients
Patient demographics and insurance information.

```sql
id                    VARCHAR(64) PRIMARY KEY
user_id               VARCHAR(64) FK → users
first_name            VARCHAR(100)
last_name             VARCHAR(100)
dob                   DATE
sex                   VARCHAR(20)
mrn                   VARCHAR(100)
phone                 VARCHAR(50)
email                 VARCHAR(255)
address               VARCHAR(255)
city                  VARCHAR(120)
state                 VARCHAR(10)
zip                   VARCHAR(15)
location_id           INTEGER FK → practice_locations
insurance_provider    VARCHAR(255)
insurance_member_id   VARCHAR(100)
insurance_group_id    VARCHAR(100)
billing_type          VARCHAR
id_card_path          VARCHAR(255)
ins_card_path         VARCHAR(255)
aob_path              VARCHAR(255)
aob_signed_at         TIMESTAMP
state                 VARCHAR(50) DEFAULT 'pending'
created_at            TIMESTAMP
updated_at            TIMESTAMP
deleted_at            TIMESTAMP NULL
```

#### orders
Core order management with comprehensive tracking.

```sql
id                    VARCHAR(64) PRIMARY KEY
patient_id            VARCHAR(64) FK → patients
user_id               VARCHAR(64) FK → users
order_group_id        VARCHAR(64) FK → order_groups

-- Product Details
product               VARCHAR(255)
product_id            INTEGER FK → products
product_price         DECIMAL(10,2)

-- Frequency & Duration
frequency_per_week    INTEGER
qty_per_change        INTEGER
duration_days         INTEGER
refills_allowed       INTEGER
shipments_remaining   INTEGER

-- Status
status                VARCHAR(40) DEFAULT 'submitted'
is_complete           BOOLEAN DEFAULT FALSE
missing_fields        TEXT[]

-- Dates
created_at            TIMESTAMP
updated_at            TIMESTAMP
shipped_at            TIMESTAMP
delivered_at          TIMESTAMP
last_eval_date        DATE
start_date            DATE
expires_at            TIMESTAMP

-- Insurance
insurer_name          VARCHAR(255)
member_id             VARCHAR(100)
group_id              VARCHAR(100)
prior_auth            VARCHAR(100)
payment_type          VARCHAR(20) DEFAULT 'insurance'

-- Shipping
shipping_name         VARCHAR(255)
shipping_address      VARCHAR(255)
shipping_city         VARCHAR(120)
shipping_state        VARCHAR(10)
shipping_zip          VARCHAR(15)
shipping_phone        VARCHAR(50)
tracking_number       VARCHAR(100)
carrier               VARCHAR(50)

-- Documents
rx_note_path          VARCHAR(255)
ins_card_path         VARCHAR(255)
patient_id_path       VARCHAR(255)

-- E-Signature
sign_name             VARCHAR(255)
sign_title            VARCHAR(255)
signed_at             TIMESTAMP
signed_ip             VARCHAR(45)

-- Wound Details
wound_location        VARCHAR(120)
wound_laterality      VARCHAR(30)
wound_type            VARCHAR(50)
wound_stage           VARCHAR(20)
wound_length_cm       DECIMAL(6,2)
wound_width_cm        DECIMAL(6,2)
wound_depth_cm        DECIMAL(6,2)
icd10_primary         VARCHAR(10)
icd10_secondary       VARCHAR(10)

-- Billing
amount_due            DECIMAL(10,2)
amount_paid           DECIMAL(10,2) DEFAULT 0
balance_due           DECIMAL(10,2)
invoice_number        VARCHAR(50) UNIQUE
billed_by             VARCHAR

-- Soft Delete
deleted_at            TIMESTAMP NULL
deleted_by            VARCHAR(255) NULL
```

**Order Status Values:**
- `draft` → `submitted` → `under_review` → `incomplete`
- `verification_pending` → `cash_price_required` → `cash_price_approved`
- `approved` → `in_production` → `shipped` → `delivered`
- `terminated` | `cancelled`

#### products
Available wound care products.

```sql
id              INTEGER PRIMARY KEY AUTOINCREMENT
sku             VARCHAR(64) UNIQUE
name            VARCHAR(255)
description     TEXT
size            VARCHAR(50)
category        VARCHAR(100)
uom             VARCHAR(20) DEFAULT 'each'
price_admin     DECIMAL(10,2)
price_wholesale DECIMAL(10,2)
hcpcs_code      VARCHAR(20)
cpt_code        VARCHAR(20)
can_be_primary  BOOLEAN DEFAULT FALSE
can_be_secondary BOOLEAN DEFAULT FALSE
active          BOOLEAN DEFAULT TRUE
created_at      TIMESTAMP
```

### 4.2 Practice Management Tables

#### practice_locations
Multiple locations for multi-facility practices.

```sql
id              SERIAL PRIMARY KEY
user_id         VARCHAR(64) FK → users
location_name   VARCHAR(255)
address         VARCHAR(255)
city            VARCHAR(100)
state           VARCHAR(2)
zip             VARCHAR(10)
phone           VARCHAR(20)
is_primary      BOOLEAN DEFAULT FALSE
is_active       BOOLEAN DEFAULT TRUE
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### practice_physicians
Links physicians to practice administrators.

```sql
id                      SERIAL PRIMARY KEY
practice_admin_id       VARCHAR(64) FK → users
physician_id            VARCHAR(64) FK → users
first_name              VARCHAR(255)
last_name               VARCHAR(255)
physician_email         VARCHAR(255)
physician_npi           VARCHAR(10)
physician_license       VARCHAR(100)
physician_license_state VARCHAR(2)
physician_license_expiry DATE
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

#### admin_physicians
Links admin users to managed physicians.

```sql
admin_id            INTEGER FK → admin_users
physician_user_id   VARCHAR(64) FK → users
PRIMARY KEY (admin_id, physician_user_id)
```

### 4.3 Pre-Authorization Tables

#### preauth_requests
Insurance pre-authorization tracking.

```sql
id                      VARCHAR(64) PRIMARY KEY
order_id                VARCHAR(64) FK → orders
patient_id              VARCHAR(64) FK → patients
preauth_number          VARCHAR(100)
carrier_name            VARCHAR(255)
carrier_payer_id        VARCHAR(50)
member_id               VARCHAR(100)
group_id                VARCHAR(100)
hcpcs_code              VARCHAR(10)
product_name            VARCHAR(255)
quantity_requested      INTEGER
icd10_primary           VARCHAR(10)
medical_necessity_letter TEXT
status                  VARCHAR(50) DEFAULT 'pending'
submission_date         TIMESTAMP
approval_date           TIMESTAMP
denial_date             TIMESTAMP
expiration_date         TIMESTAMP
denial_reason           TEXT
approved_quantity       INTEGER
auto_submitted          BOOLEAN DEFAULT FALSE
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

**Status Values:** `pending`, `submitted`, `approved`, `denied`, `expired`, `cancelled`, `need_info`

#### preauth_rules
Carrier-specific preauth requirements.

```sql
id                      VARCHAR(64) PRIMARY KEY
carrier_name            VARCHAR(255)
carrier_payer_id        VARCHAR(50)
hcpcs_code              VARCHAR(10)
requires_preauth        BOOLEAN DEFAULT TRUE
quantity_threshold      INTEGER
dollar_threshold        DECIMAL(10,2)
approval_duration_days  INTEGER DEFAULT 365
required_icd10_codes    JSONB
submission_method       VARCHAR(50) DEFAULT 'manual'
is_active               BOOLEAN DEFAULT TRUE
created_at              TIMESTAMP
UNIQUE(carrier_name, hcpcs_code)
```

### 4.4 Billing Tables

#### practice_balances
Aging balances for wholesale orders.

```sql
id                    SERIAL PRIMARY KEY
user_id               VARCHAR(64) UNIQUE FK → users
current_balance       DECIMAL(10,2) DEFAULT 0
balance_0_30_days     DECIMAL(10,2) DEFAULT 0
balance_31_60_days    DECIMAL(10,2) DEFAULT 0
balance_61_90_days    DECIMAL(10,2) DEFAULT 0
balance_over_90_days  DECIMAL(10,2) DEFAULT 0
ordering_blocked      BOOLEAN DEFAULT FALSE
blocked_reason        TEXT
last_updated          TIMESTAMP
```

#### reimbursement_rates
Medicare and insurance rates by CPT code.

```sql
cpt_code        VARCHAR(20) PRIMARY KEY
description     VARCHAR(255)
rate_non_rural  DECIMAL(10,2)
rate_rural      DECIMAL(10,2)
effective_date  DATE
```

### 4.5 Entity Relationships

```
Users (1) ─────────────┬─→ Patients (many)
                       ├─→ Orders (many)
                       ├─→ Practice_Locations (many)
                       └─→ Practice_Physicians (many)

Patients (1) ──────────┬─→ Orders (many)
                       └─→ Preauth_Requests (many)

Orders (1) ────────────┬─→ Preauth_Requests (many)
                       ├─→ Order_Status_History (many)
                       └─→ Order_Alerts (many)

Order_Groups (1) ──────→ Orders (many)

Products (1) ──────────→ Orders (many)

Practice_Locations (1) ┬─→ Patients (many)
                       └─→ Orders (many)
```

---

## 5. UI/UX Patterns

### 5.1 CSS Framework

**Primary**: Tailwind CSS via CDN (`https://cdn.tailwindcss.com`)

**Custom Color Palette:**
```css
--brand: #4DB8A8       /* Primary teal */
--brand-dark: #3A9688  /* Darker teal */
--brand-light: #E0F5F2 /* Light teal */
--ink: #1F2937         /* Text dark */
--ink-light: #6B7280   /* Text muted */
--success: #10B981     /* Green */
--warning: #F59E0B     /* Amber */
--error: #EF4444       /* Red */
--info: #3B82F6        /* Blue */
```

**Typography**: Inter font family

### 5.2 Form Patterns

**Input Styling:**
```html
<input class="border-1 border-gray-300 rounded-lg p-2
              focus:border-brand focus:ring-2 focus:ring-brand/20">
```

**Form Layout:**
- Grid layout: `grid grid-cols-1 md:grid-cols-2 gap-4`
- Label before input: `<label class="text-sm">Label</label>`
- Required fields: `<span class="text-red-600">*</span>`
- Helper text: `<div class="text-xs text-slate-500 mt-1">Helper</div>`

**Multi-Step Forms:**
- JavaScript-based section visibility toggle
- Progress tracking through numbered steps
- Validation at each step before proceeding

### 5.3 Table Patterns

```html
<table class="w-full text-sm">
  <thead style="border-bottom: 1px solid var(--border);">
    <tr>
      <th class="text-left">Column</th>
    </tr>
  </thead>
  <tbody id="tbody">
    <!-- Dynamic rows -->
  </tbody>
</table>
```

**Features:**
- Responsive: `class="table-hide-mobile"` hides columns on mobile
- Hover state: Light gray background
- Sorting/filtering via JavaScript

### 5.4 Modal Patterns

**Native HTML Dialog:**
```html
<dialog id="modal" class="rounded-2xl w-full max-w-4xl">
  <form method="dialog" class="p-0">
    <div class="p-5 border-b">Header</div>
    <div class="p-5 max-h-[70vh] overflow-y-auto">Body</div>
    <div class="p-5 border-t">Footer</div>
  </form>
</dialog>
```

**JavaScript:**
```javascript
document.getElementById('modal').showModal();
document.getElementById('modal').close();
```

### 5.5 Button Styles

**Primary:**
```html
<button class="bg-gradient-to-r from-brand-teal to-emerald-600
               text-white font-bold rounded-xl px-8 py-4">
```

**Secondary:**
```html
<button class="bg-white text-gray-900 font-bold border-2
               border-gray-200 rounded-xl px-8 py-4">
```

### 5.6 Toast Notifications

```javascript
function showToast(message, type = 'info') {
  const toast = document.createElement('div');
  toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg
                     shadow-lg text-white z-50 ${
    type === 'success' ? 'bg-green-600' :
    type === 'error' ? 'bg-red-600' : 'bg-blue-600'
  }`;
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}
```

### 5.7 ICD-10 Autocomplete

- **File**: `/assets/icd10-autocomplete.js`
- **Min Search**: 2 characters
- **Debounce**: 300ms
- **Endpoint**: `/api/icd10_search.php?term={term}&max=15`
- **Keyboard**: Arrow keys navigate, Enter selects, Escape closes

### 5.8 Navigation Patterns

**Fixed Navigation Bar (Public):**
```html
<nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-xl">
```

**Admin Sidebar:**
- Fixed left sidebar (240px width)
- Nested submenus with chevron toggle
- Active state highlighting

### 5.9 Document Signing

**Text-based signature** (not graphical canvas):
- Physician name auto-populated from selection
- Title field (MD, PA-C, NP)
- Timestamp and IP captured for compliance

### 5.10 Responsive Design

**Breakpoints:**
- `md`: 768px (tablet)
- `lg`: 1024px (desktop)

**Patterns:**
```html
<div class="hidden md:flex">Desktop only</div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
```

---

## 6. Public Website Structure

### 6.1 Public Pages (No Auth Required)

| URL | Purpose |
|-----|---------|
| `/` | Homepage with physician positioning |
| `/products/` | Product catalog |
| `/for-healthcare-professionals/` | Professional resources |
| `/clinical-evidence/` | Research and evidence |
| `/resources/` | Knowledge base |
| `/faq-physicians.html` | Physician FAQ |
| `/faq-patients.html` | Patient FAQ |
| `/privacy.html` | Privacy policy |
| `/terms-conditions.html` | Terms |
| `/contact` | Contact form |
| `/login` | Authentication |
| `/register` | Registration |

### 6.2 Protected Areas

| URL | Auth | Users |
|-----|------|-------|
| `/portal/` | Session | Physicians, Practice Admins |
| `/admin/` | Session | Admin, Manufacturer, Sales, Employee |

### 6.3 Routing (.htaccess)

```apache
RewriteEngine On
RewriteBase /

# Serve real files/dirs as-is
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]

# Pretty routes → entry files
RewriteRule ^login/?$    login/index.php   [L,QSA]
RewriteRule ^logout/?$   logout/index.php  [L,QSA]
RewriteRule ^portal/?$   portal/index.php  [L,QSA]
```

### 6.4 API Endpoints

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `/api/csrf.php` | GET | No | Get CSRF token |
| `/api/login.php` | POST | CSRF | Authenticate |
| `/api/register.php` | POST | CSRF | Register user |
| `/api/validate-npi.php` | POST | CSRF | Validate NPI |
| `/api/portal/*` | Various | Session | Portal APIs |
| `/api/admin/*` | Various | Session | Admin APIs |

### 6.5 Key Findings

1. **No Patient Portal**: Patients don't access system directly; physicians manage their care
2. **Physician-Centric**: All ordering flows through physician portal
3. **Session-Based Auth**: PHP sessions with redirect-based access control
4. **HIPAA Compliance**: BAA enforcement, SSL messaging, audit logging
5. **Lead Capture**: Contact form at `/contact` for inquiries

---

## Summary

This discovery documentation provides a comprehensive reference for the CollagenDirect codebase architecture. Key takeaways for the Sales Rep Feature:

1. **Existing Role Infrastructure**: The `admin_users` table already supports a `sales` role with defined permissions
2. **Data Visibility**: Sales reps can see all physicians but cannot manage employees
3. **Admin Portal**: The `/admin/` interface is the natural home for sales rep features
4. **Form Patterns**: Established patterns for multi-step forms, modals, and data tables
5. **No Framework**: Vanilla PHP and JavaScript require careful implementation of new features

### Next Steps (Phase 2+)
- Design sales rep dashboard wireframes
- Plan lead management database schema
- Define sales rep permission boundaries
- Implement sales-specific API endpoints

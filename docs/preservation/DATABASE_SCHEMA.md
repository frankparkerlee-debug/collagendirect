# CollagenDirect Database Schema Documentation

**Generated:** 2025-12-15
**Database:** PostgreSQL
**Purpose:** Complete documentation of all database tables for preservation before system changes.

---

## Table of Contents

1. [Core User Tables](#core-user-tables)
2. [Patient & Order Tables](#patient--order-tables)
3. [Product Tables](#product-tables)
4. [Sales Rep Tables](#sales-rep-tables)
5. [Billing & Payment Tables](#billing--payment-tables)
6. [Preauthorization Tables](#preauthorization-tables)
7. [Sales Outreach Tables](#sales-outreach-tables)
8. [Administrative Tables](#administrative-tables)

---

## Core User Tables

### `users`
Primary user table for physicians, practices, and clinics.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | VARCHAR(64) | PRIMARY KEY | UUID string identifier |
| `email` | VARCHAR(255) | NOT NULL, UNIQUE | User email (login) |
| `password_hash` | VARCHAR(255) | NOT NULL | Bcrypt password hash |
| `first_name` | VARCHAR(120) | | User first name |
| `last_name` | VARCHAR(120) | | User last name |
| `practice_name` | VARCHAR(255) | | Practice/clinic name |
| `npi` | VARCHAR(20) | | National Provider Identifier |
| `status` | VARCHAR(20) | DEFAULT 'pending' | Account status |
| `account_type` | VARCHAR(40) | DEFAULT 'referral' | 'referral', 'wholesale', 'dme_wholesale' |
| `role` | VARCHAR(50) | | 'physician', 'practice_admin', 'sales_rep' |
| `agree_msa` | BOOLEAN | DEFAULT FALSE | Master Service Agreement signed |
| `agree_baa` | BOOLEAN | DEFAULT FALSE | BAA (HIPAA) signed |
| `reset_token` | VARCHAR(255) | | Password reset token |
| `reset_expires` | TIMESTAMP | | Reset token expiration |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |
| `assigned_rep_id` | VARCHAR(64) | FK -> sales_reps(id) | Assigned sales rep |
| `rep_assignment_date` | TIMESTAMP WITH TIME ZONE | | When rep was assigned |
| `rep_assigned_by` | VARCHAR(30) | CHECK IN ('self_onboard', 'admin_assign', 'approved_request') | Assignment method |
| `rep_assigned_by_user_id` | VARCHAR(64) | FK -> users(id) | Who performed assignment |
| `default_payment_terms` | VARCHAR(20) | DEFAULT 'net30' | Payment terms for wholesale |
| `credit_limit` | DECIMAL(10,2) | | Credit limit for wholesale |
| `collection_flag` | BOOLEAN | DEFAULT FALSE | In collections |
| `billing_notes` | TEXT | | Internal billing notes |
| `billing_contact_name` | VARCHAR(255) | | Billing contact name |
| `billing_contact_email` | VARCHAR(255) | | Billing contact email |
| `billing_contact_phone` | VARCHAR(50) | | Billing contact phone |

**Indexes:**
- `idx_users_assigned_rep_id` on `assigned_rep_id`
- `idx_users_rep_assignment_date` on `rep_assignment_date`

---

### `admin_users`
Administrative users with backend access.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | Integer auto-increment ID |
| `email` | VARCHAR(255) | NOT NULL, UNIQUE | Admin email |
| `password_hash` | VARCHAR(255) | NOT NULL | Bcrypt password hash |
| `name` | VARCHAR(120) | NOT NULL | Display name |
| `role` | VARCHAR(50) | DEFAULT 'admin' | Admin role level |
| `use_custom_permissions` | BOOLEAN | DEFAULT FALSE | Use granular permissions |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |

---

### `admin_physicians`
Links admin users to physicians they can view/manage.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `admin_id` | INTEGER | NOT NULL, FK -> admin_users(id) | Admin user ID |
| `physician_user_id` | VARCHAR(64) | NOT NULL | Physician user ID |

**Primary Key:** (admin_id, physician_user_id)

**Indexes:**
- `ap_physician` on `physician_user_id`

---

### `admin_permissions`
Granular permissions for admin users.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | |
| `admin_user_id` | INTEGER | NOT NULL, FK -> admin_users(id) | Admin user |
| `permission_key` | VARCHAR(100) | NOT NULL | Permission identifier |
| `granted` | BOOLEAN | DEFAULT TRUE | Permission granted |
| `granted_by` | INTEGER | | Who granted it |
| `granted_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |

**Constraints:**
- UNIQUE(admin_user_id, permission_key)

---

### `login_attempts`
Security audit log for login attempts.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | |
| `email` | VARCHAR(255) | | Attempted email |
| `ip_hash` | VARCHAR(255) | | Hashed IP address |
| `success` | BOOLEAN | | Login succeeded |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |

---

## Patient & Order Tables

### `patients`
Patient demographics and insurance information.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | VARCHAR(64) | PRIMARY KEY | UUID identifier |
| `user_id` | VARCHAR(64) | NOT NULL, FK -> users(id) | Owning physician/practice |
| `first_name` | VARCHAR(120) | | Patient first name |
| `last_name` | VARCHAR(120) | | Patient last name |
| `dob` | DATE | | Date of birth |
| `sex` | VARCHAR(10) | | Patient sex |
| `mrn` | VARCHAR(50) | | Medical Record Number |
| `phone` | VARCHAR(15) | | Contact phone |
| `email` | VARCHAR(255) | | Contact email |
| `address` | VARCHAR(255) | | Street address |
| `city` | VARCHAR(120) | | City |
| `state` | VARCHAR(10) | | State abbreviation |
| `zip` | VARCHAR(15) | | ZIP code |
| `insurance_provider` | VARCHAR(255) | | Insurance company name |
| `insurance_member_id` | VARCHAR(100) | | Member ID |
| `insurance_group_id` | VARCHAR(100) | | Group ID |
| `insurance_payer_phone` | VARCHAR(50) | | Payer phone number |
| `id_card_path` | VARCHAR(255) | | Path to ID card image |
| `id_card_name` | VARCHAR(255) | | Original filename |
| `id_card_mime` | VARCHAR(100) | | MIME type |
| `ins_card_path` | VARCHAR(255) | | Path to insurance card |
| `ins_card_name` | VARCHAR(255) | | Original filename |
| `ins_card_mime` | VARCHAR(100) | | MIME type |
| `aob_path` | VARCHAR(255) | | Assignment of Benefits path |
| `aob_signed_at` | TIMESTAMP | | AOB signature timestamp |
| `aob_ip` | VARCHAR(100) | | AOB signer IP address |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |

**Indexes:**
- `pat_user` on `user_id`
- `pat_name` on `(last_name, first_name)`

---

### `orders`
DME orders for wound care products.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | VARCHAR(64) | PRIMARY KEY | UUID identifier |
| `patient_id` | VARCHAR(64) | NOT NULL, FK -> patients(id) | Patient |
| `user_id` | VARCHAR(64) | NOT NULL, FK -> users(id) | Ordering physician |
| `product` | VARCHAR(255) | | Product name |
| `product_id` | INTEGER | | FK to products |
| `product_price` | DECIMAL(10,2) | | Price at time of order |
| `frequency` | VARCHAR(100) | | Delivery frequency |
| `delivery_mode` | VARCHAR(100) | | Delivery method |
| `status` | VARCHAR(40) | DEFAULT 'submitted' | Order status |
| `shipments_remaining` | INTEGER | | Remaining shipments |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |
| `shipped_at` | TIMESTAMP | | Ship date |
| `delivered_at` | TIMESTAMP | | Delivery date |
| `insurer_name` | VARCHAR(255) | | Insurance name (snapshot) |
| `member_id` | VARCHAR(100) | | Member ID (snapshot) |
| `group_id` | VARCHAR(100) | | Group ID (snapshot) |
| `payer_phone` | VARCHAR(50) | | Payer phone (snapshot) |
| `sign_name` | VARCHAR(255) | | E-signature name |
| `sign_title` | VARCHAR(255) | | E-signature title |
| `signed_at` | TIMESTAMP | | E-signature timestamp |
| `prior_auth` | VARCHAR(100) | | Prior authorization number |
| `payment_type` | VARCHAR(20) | DEFAULT 'insurance' | 'insurance' or 'wholesale' |
| `billed_by` | VARCHAR(30) | | 'practice_dme' for wholesale |
| `wound_location` | VARCHAR(120) | | Wound anatomical location |
| `wound_laterality` | VARCHAR(30) | | Left/Right/Bilateral |
| `wound_notes` | TEXT | | Clinical wound notes |
| `shipping_name` | VARCHAR(255) | | Ship-to name |
| `shipping_phone` | VARCHAR(50) | | Ship-to phone |
| `shipping_address` | VARCHAR(255) | | Ship-to address |
| `shipping_city` | VARCHAR(120) | | Ship-to city |
| `shipping_state` | VARCHAR(10) | | Ship-to state |
| `shipping_zip` | VARCHAR(15) | | Ship-to ZIP |
| `rx_note_path` | VARCHAR(255) | | Path to visit notes |
| `rx_note_name` | VARCHAR(255) | | Original filename |
| `rx_note_mime` | VARCHAR(100) | | MIME type |
| `carrier_status` | VARCHAR(50) | | Shipping carrier status |
| `carrier_eta` | TIMESTAMP | | Estimated delivery |
| `expires_at` | TIMESTAMP | | Order expiration |
| `ins_card_path` | VARCHAR(255) | | Insurance card (order-level) |
| `ins_card_name` | VARCHAR(255) | | |
| `ins_card_mime` | VARCHAR(100) | | |
| `patient_id_path` | VARCHAR(255) | | Patient ID (order-level) |
| `patient_id_name` | VARCHAR(255) | | |
| `patient_id_mime` | VARCHAR(100) | | |
| `icd10_primary` | VARCHAR(10) | | Primary diagnosis code |
| `icd10_secondary` | VARCHAR(10) | | Secondary diagnosis |
| `wound_length_cm` | DECIMAL(6,2) | | Wound length |
| `wound_width_cm` | DECIMAL(6,2) | | Wound width |
| `wound_depth_cm` | DECIMAL(6,2) | | Wound depth |
| `wound_type` | VARCHAR(50) | | Type of wound |
| `wound_stage` | VARCHAR(20) | | Wound stage/grade |
| `last_eval_date` | DATE | | Last evaluation date |
| `start_date` | DATE | | Treatment start date |
| `frequency_per_week` | INTEGER | | Applications per week |
| `qty_per_change` | INTEGER | | Quantity per application |
| `duration_days` | INTEGER | | Treatment duration |
| `refills_allowed` | INTEGER | | Number of refills |
| `additional_instructions` | TEXT | | Special instructions |
| `cpt` | VARCHAR(20) | | CPT/HCPCS code |
| `amount_due` | DECIMAL(10,2) | | Total amount due |
| `amount_paid` | DECIMAL(10,2) | | Amount paid |
| `balance_due` | DECIMAL(10,2) | | Remaining balance |
| `due_date` | DATE | | Payment due date |
| `paid_at` | TIMESTAMP | | Full payment date |
| `invoice_status` | VARCHAR(30) | DEFAULT 'pending' | Invoice lifecycle status |
| `voided_at` | TIMESTAMP | | Void timestamp |
| `voided_by` | INTEGER | | Admin who voided |
| `void_reason` | TEXT | | Reason for void |
| `statement_sent_at` | TIMESTAMP | | Last statement sent |
| `collection_flag` | BOOLEAN | DEFAULT FALSE | In collections |

**Indexes:**
- `ord_patient` on `patient_id`
- `ord_user` on `user_id`
- `ord_status` on `status`
- `idx_orders_invoice_status` on `invoice_status` WHERE `billed_by = 'practice_dme'`

---

## Product Tables

### `products`
Product catalog for wound care items.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | Product ID |
| `sku` | VARCHAR(100) | NOT NULL, UNIQUE | Stock keeping unit |
| `name` | VARCHAR(255) | NOT NULL | Product name |
| `description` | TEXT | | Product description |
| `price_admin` | DECIMAL(10,2) | | Admin/internal price |
| `price_wholesale` | DECIMAL(10,2) | | Wholesale price |
| `price_referral` | DECIMAL(10,2) | DEFAULT 0.00 | Referral price |
| `category` | VARCHAR(100) | | Product category |
| `size` | VARCHAR(50) | | Product size |
| `hcpcs_code` | VARCHAR(20) | | HCPCS billing code |
| `cpt_code` | VARCHAR(20) | | CPT billing code |
| `active` | BOOLEAN | DEFAULT TRUE | Product active |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |

**Indexes:**
- `prod_sku` on `sku`
- `prod_active` on `active`

---

## Sales Rep Tables

### `sales_reps`
Sales representative profiles extending users table.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | VARCHAR(64) | PRIMARY KEY, DEFAULT gen_random_uuid() | Rep ID |
| `user_id` | VARCHAR(64) | NOT NULL, UNIQUE, FK -> users(id) | Associated user account |
| `company_name` | VARCHAR(255) | | Rep's company |
| `status` | VARCHAR(20) | NOT NULL, DEFAULT 'pending' | 'pending', 'invited', 'active', 'suspended', 'terminated' |
| `application_date` | TIMESTAMP WITH TIME ZONE | DEFAULT CURRENT_TIMESTAMP | Application submitted |
| `approved_date` | TIMESTAMP WITH TIME ZONE | | Approval date |
| `approved_by` | VARCHAR(64) | FK -> users(id) | Who approved |
| `how_heard_about_us` | TEXT | | Lead source |
| `notes` | TEXT | | Internal notes |
| `invite_token` | VARCHAR(64) | UNIQUE | Invite completion token |
| `invite_token_expires_at` | TIMESTAMP WITH TIME ZONE | | Token expiration |
| `invited_by` | VARCHAR(64) | FK -> users(id) | Admin who sent invite |
| `created_at` | TIMESTAMP WITH TIME ZONE | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP WITH TIME ZONE | DEFAULT CURRENT_TIMESTAMP | Auto-updated |

**Indexes:**
- `idx_sales_reps_user_id` on `user_id`
- `idx_sales_reps_status` on `status`
- `idx_sales_reps_approved_by` on `approved_by`
- `idx_sales_reps_application_date` on `application_date`
- `idx_sales_reps_invite_token` on `invite_token` WHERE NOT NULL

**Triggers:**
- `trigger_sales_reps_updated_at` - Auto-updates `updated_at`

---

### `rep_commission_rates`
Commission rate history for sales reps.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | |
| `rep_id` | VARCHAR(64) | NOT NULL, FK -> sales_reps(id) | Sales rep |
| `rate` | DECIMAL(5,4) | NOT NULL, CHECK 0-1 | Commission rate (0.25 = 25%) |
| `effective_date` | DATE | DEFAULT CURRENT_DATE | When rate takes effect |
| `end_date` | DATE | | When rate ends (NULL = current) |
| `set_by` | VARCHAR(64) | | User or admin who set rate |
| `notes` | TEXT | | Rate change notes |
| `created_at` | TIMESTAMP WITH TIME ZONE | DEFAULT CURRENT_TIMESTAMP | |

**Indexes:**
- `idx_rep_commission_rates_rep_id` on `rep_id`
- `idx_rep_commission_rates_effective_date` on `effective_date`
- `idx_rep_commission_rates_current` on `(rep_id, effective_date)` WHERE `end_date IS NULL`

---

### `rep_signed_documents`
E-signature records for rep agreements.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | |
| `rep_id` | VARCHAR(64) | NOT NULL, FK -> sales_reps(id) | Sales rep |
| `document_type` | VARCHAR(50) | NOT NULL, CHECK IN (...) | 'rep_agreement', 'baa', 'nda', 'w9', 'other' |
| `document_version` | VARCHAR(100) | NOT NULL | Document version/hash |
| `signed_at` | TIMESTAMP WITH TIME ZONE | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Signature time |
| `ip_address` | VARCHAR(45) | | Signer IP (IPv4/IPv6) |
| `user_agent` | TEXT | | Browser user agent |
| `signature_text` | VARCHAR(255) | NOT NULL | Typed signature name |
| `signature_title` | VARCHAR(100) | | Signer's title |
| `document_content` | TEXT | | Document HTML at signing |
| `document_path` | VARCHAR(500) | | Path to stored document |
| `source` | VARCHAR(30) | DEFAULT 'self_service' | 'self_service', 'invite_completion', 'offline_upload', 'offline_attestation' |
| `uploaded_by` | VARCHAR(64) | FK -> users(id) | Admin who uploaded |
| `document_file_path` | VARCHAR(500) | | Uploaded document path |
| `created_at` | TIMESTAMP WITH TIME ZONE | DEFAULT CURRENT_TIMESTAMP | |

**Constraints:**
- UNIQUE(rep_id, document_type, document_version)

**Indexes:**
- `idx_rep_signed_documents_rep_id` on `rep_id`
- `idx_rep_signed_documents_type` on `document_type`
- `idx_rep_signed_documents_signed_at` on `signed_at`

---

### `rep_commission_ledger`
Commission earnings per order.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | |
| `rep_id` | VARCHAR(64) | NOT NULL, FK -> sales_reps(id) | Sales rep |
| `order_id` | VARCHAR(64) | NOT NULL, FK -> orders(id) | Order reference |
| `order_type` | VARCHAR(20) | NOT NULL, CHECK IN ('referral', 'wholesale') | Order type |
| `payment_id` | INTEGER | | Payment reference |
| `clinic_id` | VARCHAR(64) | NOT NULL, FK -> users(id) | Clinic/practice |
| `payment_date` | DATE | NOT NULL | Payment collection date |
| `collected_amount` | DECIMAL(10,2) | NOT NULL, CHECK >= 0 | Amount collected |
| `commission_rate` | DECIMAL(5,4) | NOT NULL, CHECK 0-1 | Rate at time of calc |
| `commission_amount` | DECIMAL(10,2) | NOT NULL | Calculated commission |
| `payout_id` | INTEGER | FK -> rep_commission_payouts(id) | Payout reference |
| `status` | VARCHAR(20) | NOT NULL, DEFAULT 'pending' | 'pending', 'paid', 'voided' |
| `notes` | TEXT | | Notes |
| `created_at` | TIMESTAMP WITH TIME ZONE | DEFAULT CURRENT_TIMESTAMP | |

**Constraints:**
- UNIQUE(rep_id, order_id)

**Indexes:**
- `idx_rep_commission_ledger_rep_id` on `rep_id`
- `idx_rep_commission_ledger_order_id` on `order_id`
- `idx_rep_commission_ledger_clinic_id` on `clinic_id`
- `idx_rep_commission_ledger_payment_date` on `payment_date`
- `idx_rep_commission_ledger_status` on `status`
- `idx_rep_commission_ledger_payout_id` on `payout_id`
- `idx_rep_commission_ledger_pending` on `(rep_id, status)` WHERE `status = 'pending'`

---

### `rep_commission_payouts`
Commission payments to sales reps.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | |
| `rep_id` | VARCHAR(64) | NOT NULL, FK -> sales_reps(id) | Sales rep |
| `amount` | DECIMAL(10,2) | NOT NULL, CHECK > 0 | Payout amount |
| `payout_date` | DATE | NOT NULL, DEFAULT CURRENT_DATE | Payment date |
| `payment_method` | VARCHAR(20) | NOT NULL, CHECK IN (...) | 'check', 'ach', 'wire', 'other' |
| `reference_number` | VARCHAR(100) | | Check/ACH/wire reference |
| `period_start` | DATE | | Commission period start |
| `period_end` | DATE | | Commission period end |
| `notes` | TEXT | | Notes |
| `processed_by` | VARCHAR(64) | FK -> users(id) | Admin who processed |
| `created_at` | TIMESTAMP WITH TIME ZONE | DEFAULT CURRENT_TIMESTAMP | |

**Indexes:**
- `idx_rep_commission_payouts_rep_id` on `rep_id`
- `idx_rep_commission_payouts_payout_date` on `payout_date`
- `idx_rep_commission_payouts_processed_by` on `processed_by`

---

## Billing & Payment Tables

### `wholesale_payments`
Payment history and audit trail for wholesale orders.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | |
| `order_id` | VARCHAR(64) | FK -> orders(id) | Order reference |
| `order_number` | VARCHAR(50) | | Order number display |
| `user_id` | VARCHAR(64) | FK -> users(id) | Practice/clinic |
| `amount` | DECIMAL(10,2) | NOT NULL | Payment amount |
| `payment_method` | VARCHAR(50) | | Payment method |
| `reference_number` | VARCHAR(100) | | Check/ACH reference |
| `payment_date` | DATE | NOT NULL | Payment date |
| `notes` | TEXT | | Payment notes |
| `recorded_by` | INTEGER | FK -> admin_users(id) | Admin who recorded |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |

**Indexes:**
- `idx_ws_payments_order` on `order_id`
- `idx_ws_payments_order_number` on `order_number`
- `idx_ws_payments_user` on `user_id`
- `idx_ws_payments_date` on `payment_date`

---

## Preauthorization Tables

### `preauth_requests`
Insurance preauthorization request tracking.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | VARCHAR(64) | PRIMARY KEY | |
| `order_id` | VARCHAR(64) | NOT NULL, FK -> orders(id) | Associated order |
| `patient_id` | VARCHAR(64) | NOT NULL, FK -> patients(id) | Patient |
| `preauth_number` | VARCHAR(100) | | Carrier-assigned number |
| `carrier_name` | VARCHAR(255) | NOT NULL | Insurance carrier |
| `carrier_payer_id` | VARCHAR(50) | | EDI payer ID |
| `member_id` | VARCHAR(100) | NOT NULL | Member ID |
| `group_id` | VARCHAR(100) | | Group ID |
| `hcpcs_code` | VARCHAR(10) | NOT NULL | HCPCS code |
| `product_name` | VARCHAR(255) | NOT NULL | Product |
| `quantity_requested` | INTEGER | NOT NULL | Quantity |
| `units_per_month` | INTEGER | | Monthly supply |
| `icd10_primary` | VARCHAR(10) | NOT NULL | Primary diagnosis |
| `icd10_secondary` | VARCHAR(10) | | Secondary diagnosis |
| `medical_necessity_letter` | TEXT | | AI-generated letter |
| `physician_notes` | TEXT | | Clinical notes |
| `status` | VARCHAR(50) | NOT NULL, DEFAULT 'pending' | Request status |
| `submission_date` | TIMESTAMP WITH TIME ZONE | | Submitted date |
| `approval_date` | TIMESTAMP WITH TIME ZONE | | Approval date |
| `denial_date` | TIMESTAMP WITH TIME ZONE | | Denial date |
| `expiration_date` | TIMESTAMP WITH TIME ZONE | | Preauth expiration |
| `denial_reason` | TEXT | | Denial reason |
| `approval_duration_days` | INTEGER | | Approval validity |
| `approved_quantity` | INTEGER | | Approved qty |
| `carrier_response_data` | JSONB | | Full API response |
| `auto_submitted` | BOOLEAN | DEFAULT FALSE | Auto-submitted by agent |
| `retry_count` | INTEGER | DEFAULT 0 | Retry attempts |
| `last_retry_date` | TIMESTAMP WITH TIME ZONE | | Last retry |
| `next_retry_date` | TIMESTAMP WITH TIME ZONE | | Next scheduled retry |
| `carrier_phone` | VARCHAR(20) | | Carrier phone |
| `carrier_fax` | VARCHAR(20) | | Carrier fax |
| `carrier_portal_url` | TEXT | | Portal URL |
| `created_at` | TIMESTAMP WITH TIME ZONE | DEFAULT NOW() | |
| `updated_at` | TIMESTAMP WITH TIME ZONE | DEFAULT NOW() | |
| `created_by` | VARCHAR(64) | | Creator |
| `updated_by` | VARCHAR(64) | | Last updater |

**Status values:** 'pending', 'submitted', 'approved', 'denied', 'expired', 'cancelled', 'need_info'

**Indexes:**
- `idx_preauth_order_id`, `idx_preauth_patient_id`, `idx_preauth_status`
- `idx_preauth_carrier_name`, `idx_preauth_submission_date`
- `idx_preauth_expiration_date`, `idx_preauth_next_retry`

---

### `preauth_rules`
Carrier-specific preauthorization requirements.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | VARCHAR(64) | PRIMARY KEY | |
| `carrier_name` | VARCHAR(255) | NOT NULL | Carrier name |
| `carrier_payer_id` | VARCHAR(50) | | EDI payer ID |
| `carrier_aliases` | JSONB | | Alternate names array |
| `hcpcs_code` | VARCHAR(10) | NOT NULL | HCPCS code |
| `requires_preauth` | BOOLEAN | NOT NULL, DEFAULT TRUE | Preauth required |
| `quantity_threshold` | INTEGER | | Qty threshold |
| `dollar_threshold` | DECIMAL(10,2) | | Dollar threshold |
| `frequency_limit_days` | INTEGER | | Days between preauths |
| `approval_duration_days` | INTEGER | DEFAULT 365 | Approval validity |
| `required_icd10_codes` | JSONB | | Required diagnoses |
| `excluded_icd10_codes` | JSONB | | Excluded diagnoses |
| `requires_physician_notes` | BOOLEAN | DEFAULT FALSE | |
| `requires_wound_measurements` | BOOLEAN | DEFAULT FALSE | |
| `requires_prior_treatment_history` | BOOLEAN | DEFAULT FALSE | |
| `required_documents` | JSONB | | Required doc types |
| `submission_method` | VARCHAR(50) | NOT NULL, DEFAULT 'manual' | 'manual', 'fax', 'portal', 'api', 'edi' |
| `api_endpoint` | TEXT | | API URL |
| `portal_url` | TEXT | | Portal URL |
| `fax_number` | VARCHAR(20) | | Fax number |
| `carrier_phone` | VARCHAR(20) | | Phone |
| `carrier_email` | VARCHAR(255) | | Email |
| `provider_relations_phone` | VARCHAR(20) | | Provider relations |
| `typical_turnaround_days` | INTEGER | DEFAULT 5 | Expected days |
| `auto_approval_eligible` | BOOLEAN | DEFAULT FALSE | Can auto-submit |
| `special_instructions` | TEXT | | Notes |
| `form_template_url` | TEXT | | Form template |
| `is_active` | BOOLEAN | DEFAULT TRUE | Rule active |
| `effective_date` | DATE | | Rule effective |
| `termination_date` | DATE | | Rule termination |
| `priority` | INTEGER | DEFAULT 0 | Matching priority |
| `created_at` | TIMESTAMP WITH TIME ZONE | DEFAULT NOW() | |
| `updated_at` | TIMESTAMP WITH TIME ZONE | DEFAULT NOW() | |
| `created_by` | VARCHAR(64) | | |
| `updated_by` | VARCHAR(64) | | |
| `notes` | TEXT | | Internal notes |

**Constraints:**
- UNIQUE(carrier_name, hcpcs_code)

---

### `preauth_audit_log`
HIPAA-compliant audit trail for preauthorization actions.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | VARCHAR(64) | PRIMARY KEY | |
| `preauth_request_id` | VARCHAR(64) | NOT NULL, FK -> preauth_requests(id) | Request |
| `action` | VARCHAR(50) | NOT NULL | Action type |
| `actor_type` | VARCHAR(50) | NOT NULL | 'system', 'admin', 'agent', 'patient', 'physician', 'carrier' |
| `actor_id` | VARCHAR(64) | | User ID |
| `actor_name` | VARCHAR(255) | | Actor name |
| `field_name` | VARCHAR(100) | | Changed field |
| `old_value` | TEXT | | Previous value |
| `new_value` | TEXT | | New value |
| `change_reason` | TEXT | | Reason |
| `status_before` | VARCHAR(50) | | Status before |
| `status_after` | VARCHAR(50) | | Status after |
| `external_system` | VARCHAR(100) | | External system |
| `external_request_id` | VARCHAR(255) | | External ID |
| `external_response_code` | VARCHAR(50) | | Response code |
| `external_response_message` | TEXT | | Response message |
| `request_payload` | JSONB | | Request data |
| `response_payload` | JSONB | | Response data |
| `ip_address` | INET | | Client IP |
| `user_agent` | TEXT | | Browser |
| `session_id` | VARCHAR(255) | | Session |
| `success` | BOOLEAN | DEFAULT TRUE | Action succeeded |
| `error_message` | TEXT | | Error message |
| `error_code` | VARCHAR(50) | | Error code |
| `created_at` | TIMESTAMP WITH TIME ZONE | DEFAULT NOW() | |
| `duration_ms` | INTEGER | | Action duration |
| `metadata` | JSONB | | Additional context |
| `notes` | TEXT | | Notes |

---

### `eligibility_cache`
Cached insurance eligibility verification results.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | VARCHAR(64) | PRIMARY KEY | |
| `member_id` | VARCHAR(100) | NOT NULL | Insurance member ID |
| `carrier_name` | VARCHAR(255) | NOT NULL | Carrier name |
| `eligibility_data` | JSONB | NOT NULL | Eligibility details |
| `verified_at` | TIMESTAMP WITH TIME ZONE | DEFAULT NOW() | Verification time |

**Constraints:**
- UNIQUE(member_id, carrier_name)

---

## Sales Outreach Tables

### `leads`
Sales lead tracking for outreach.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | |
| `practice_name` | VARCHAR(255) | NOT NULL | Practice name |
| `physician_name` | VARCHAR(255) | | Physician name |
| `specialty` | VARCHAR(100) | | Medical specialty |
| `address` | TEXT | | Address |
| `city` | VARCHAR(100) | | City |
| `state` | VARCHAR(2) | | State |
| `zip` | VARCHAR(10) | | ZIP |
| `phone` | VARCHAR(20) | | Phone |
| `email` | VARCHAR(255) | | Email |
| `website` | VARCHAR(255) | | Website |
| `lead_score` | INT | DEFAULT 0 | Lead score |
| `lead_source` | VARCHAR(50) | | 'manual', 'web_scrape', 'referral', 'purchased_list' |
| `estimated_monthly_volume` | INT | | Est. monthly orders |
| `status` | VARCHAR(50) | DEFAULT 'new' | Lead status |
| `priority` | VARCHAR(10) | DEFAULT 'medium' | 'high', 'medium', 'low' |
| `assigned_rep` | VARCHAR(255) | | Assigned rep |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |
| `last_contacted_at` | TIMESTAMP | | Last contact |
| `next_followup_date` | DATE | | Next followup |
| `notes` | TEXT | | Notes |

**Status values:** 'new', 'contacted', 'qualified', 'demo_scheduled', 'registered', 'nurture', 'not_interested', 'do_not_contact'

---

### `outreach_campaigns`
Marketing campaign tracking.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | |
| `campaign_name` | VARCHAR(255) | NOT NULL | Campaign name |
| `campaign_type` | VARCHAR(20) | NOT NULL | 'email', 'sms', 'phone', 'direct_mail' |
| `subject_line` | VARCHAR(255) | | Email subject |
| `message_template` | TEXT | | Message template |
| `target_specialty` | VARCHAR(100) | | Target specialty |
| `target_state` | VARCHAR(2) | | Target state |
| `target_status` | VARCHAR(50) | | Target lead status |
| `min_volume` | INT | | Min volume filter |
| `status` | VARCHAR(20) | DEFAULT 'draft' | 'draft', 'active', 'paused', 'completed' |
| `total_sent` | INT | DEFAULT 0 | Messages sent |
| `total_opened` | INT | DEFAULT 0 | Opens |
| `total_clicked` | INT | DEFAULT 0 | Clicks |
| `total_replied` | INT | DEFAULT 0 | Replies |
| `total_converted` | INT | DEFAULT 0 | Conversions |
| `start_date` | DATE | | Start date |
| `end_date` | DATE | | End date |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |
| `created_by` | VARCHAR(255) | | Creator |

---

### `outreach_log`
Individual outreach activity log.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | |
| `lead_id` | INT | NOT NULL, FK -> leads(id) | Lead |
| `campaign_id` | INT | FK -> outreach_campaigns(id) | Campaign |
| `outreach_type` | VARCHAR(20) | NOT NULL | 'email', 'sms', 'phone_call', 'demo', 'meeting' |
| `subject` | VARCHAR(255) | | Subject |
| `message` | TEXT | | Message body |
| `sent_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | Sent time |
| `opened_at` | TIMESTAMP | | Opened time |
| `clicked_at` | TIMESTAMP | | Clicked time |
| `replied_at` | TIMESTAMP | | Reply time |
| `call_duration_seconds` | INT | | Call duration |
| `call_outcome` | VARCHAR(20) | | 'answered', 'voicemail', 'no_answer', 'busy', 'wrong_number' |
| `requires_followup` | BOOLEAN | DEFAULT FALSE | Needs followup |
| `followup_date` | DATE | | Followup date |
| `sent_by` | VARCHAR(255) | | Sender |

---

### `email_templates` / `sms_templates` / `call_scripts`
Template storage for outreach content. See schema file for full details.

---

## Administrative Tables

### `practice_locations`
Multiple locations per practice.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | |
| `user_id` | VARCHAR(32) | NOT NULL, FK -> users(id) | Practice user |
| `location_name` | VARCHAR(255) | NOT NULL | Location name |
| `address` | TEXT | NOT NULL | Address |
| `city` | VARCHAR(100) | NOT NULL | City |
| `state` | VARCHAR(50) | NOT NULL | State |
| `zip` | VARCHAR(20) | NOT NULL | ZIP |
| `phone` | VARCHAR(50) | | Phone |
| `is_primary` | BOOLEAN | DEFAULT FALSE | Primary location |
| `is_active` | BOOLEAN | DEFAULT TRUE | Active |
| `created_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |
| `updated_at` | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP | |

---

## Summary Statistics

| Category | Table Count |
|----------|-------------|
| Core User Tables | 5 |
| Patient & Order Tables | 2 |
| Product Tables | 1 |
| Sales Rep Tables | 5 |
| Billing & Payment Tables | 1 |
| Preauthorization Tables | 4 |
| Sales Outreach Tables | 5 |
| Administrative Tables | 1 |
| **Total** | **24+** |

---

## Foreign Key Relationships Summary

```
users.id <- patients.user_id
users.id <- orders.user_id
users.id <- sales_reps.user_id
users.id <- practice_locations.user_id
users.assigned_rep_id -> sales_reps.id

patients.id <- orders.patient_id
patients.id <- preauth_requests.patient_id

orders.id <- preauth_requests.order_id
orders.id <- wholesale_payments.order_id
orders.id <- rep_commission_ledger.order_id

sales_reps.id <- rep_commission_rates.rep_id
sales_reps.id <- rep_signed_documents.rep_id
sales_reps.id <- rep_commission_ledger.rep_id
sales_reps.id <- rep_commission_payouts.rep_id

admin_users.id <- admin_physicians.admin_id
admin_users.id <- admin_permissions.admin_user_id
admin_users.id <- wholesale_payments.recorded_by
```

---

*This document should be updated whenever database schema changes are made.*

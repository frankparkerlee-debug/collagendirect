# CollagenDirect Codebase Architecture & Preauthorization Integration Guide

## Executive Summary

CollagenDirect is a HIPAA-compliant PHP 8.3/PostgreSQL DME (Durable Medical Equipment) order management platform for wound care products. The system manages physician-to-manufacturer workflows with built-in compliance, insurance verification, patient pre-authorization, and multi-stakeholder communication.

---

## 1. CURRENT PATIENT & ORDER MANAGEMENT SYSTEM

### 1.1 Patient Management

**Database Schema (patients table)**
```
- id: VARCHAR(64) PRIMARY KEY (UUID string)
- user_id: VARCHAR(64) FOREIGN KEY (physician/practice owner)
- first_name, last_name, dob, sex, mrn
- phone, email, address, city, state, zip
- insurance_provider, insurance_member_id, insurance_group_id, insurance_payer_phone
- id_card_path, id_card_name, id_card_mime (patient photo ID)
- ins_card_path, ins_card_name, ins_card_mime (insurance card front/back)
- aob_path, aob_signed_at, aob_ip (Assignment of Benefits e-signature)
- **state**: VARCHAR(50) - Patient authorization status (pending, approved, not_covered, need_info, active, inactive)
- **status_comment**: TEXT - Manufacturer feedback/conversation thread
- **status_updated_at**: TIMESTAMP - Last status change
- **status_updated_by**: VARCHAR(64) - Admin user who changed status
- created_at, updated_at: TIMESTAMP
```

**Key Features:**
- Patient records created per physician (user_id linkage)
- Auto-assigned document storage paths for insurance verification docs
- Conversation thread system for manufacturer-physician communication
- State machine for preauthorization (pending → approved/not_covered/need_info)

**Admin Patient Management** (`/admin/patients.php`)
- Filter by authorization state
- Update patient status with automatic order cascading
- Send/receive conversation messages with providers
- View all associated orders and insurance docs
- Approval score calculation (AI-powered)

### 1.2 Order Management

**Database Schema (orders table)**
```
Core:
- id: VARCHAR(64) PRIMARY KEY
- patient_id, user_id: FOREIGN KEYS
- product, product_id: VARCHAR/INT (SKU reference)
- product_price: DECIMAL(10,2)
- frequency, delivery_mode, status: VARCHAR
- created_at, updated_at, shipped_at, delivered_at: TIMESTAMP

Insurance & Payment:
- insurer_name, member_id, group_id, payer_phone: VARCHAR
- prior_auth: VARCHAR(100) - Prior authorization number
- payment_type: VARCHAR(20) DEFAULT 'insurance'
- carrier_status, carrier_eta: VARCHAR/TIMESTAMP

Clinical:
- wound_location, wound_laterality, wound_notes: VARCHAR/TEXT
- wound_length_cm, wound_width_cm, wound_depth_cm: DECIMAL(6,2)
- wound_type, wound_stage: VARCHAR
- icd10_primary, icd10_secondary: VARCHAR(10) - Diagnosis codes
- last_eval_date, start_date: DATE

Prescribing:
- sign_name, sign_title, signed_at: VARCHAR/TIMESTAMP
- frequency_per_week, qty_per_change, duration_days, refills_allowed: INT
- additional_instructions: TEXT
- cpt: VARCHAR(20) - CPT/HCPCS code

Documents:
- rx_note_path, rx_note_name, rx_note_mime (Rx/note PDF)
- ins_card_path, ins_card_name, ins_card_mime (Insurance card)
- patient_id_path, patient_id_name, patient_id_mime (Patient ID)

Shipping:
- shipping_name, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_zip
- tracking_number, carrier: VARCHAR (added by migration)
```

**Order Lifecycle Statuses:**
```
- submitted: Physician creates order
- approved: CollagenDirect admin approves
- rejected: Admin rejects
- in_production: Manufacturer producing
- shipped: Order shipped (with tracking)
- delivered: Confirmed delivery
- cancelled/rejected: Terminal states
```

**Order Creation Workflow** (`/api/portal/orders.create.php`)
1. Verify e-sign confirmation
2. Resolve product from catalog
3. Create/validate patient
4. Insert order with all clinical data
5. Respond to client immediately (FastCGI finish)
6. Asynchronously upload files (Rx, insurance card, ID)
7. Send email notifications (patient, manufacturer)

---

## 2. EXISTING INSURANCE-RELATED FUNCTIONALITY

### 2.1 Insurance Fields & Data Capture

**Patient-Level Insurance:**
- insurance_provider, insurance_member_id, insurance_group_id, insurance_payer_phone
- Stored in `patients` table for profile continuity

**Order-Level Insurance:**
- Same fields copied at order time for historical tracking
- prior_auth field for manual prior authorization numbers
- payment_type: 'insurance' vs 'cash' flag

### 2.2 HCPCS Code Integration

**Products Table:**
```
- hcpcs_code VARCHAR(20) - HCPCS billing code
- cpt_code VARCHAR(20) - CPT code (alternative)
- category VARCHAR(100) - Product type
- sku VARCHAR(100) UNIQUE - Product SKU
- price_admin, price_wholesale: DECIMAL(10,2)
```

**Product Catalog (Current HCPCS Codes):**
- A6196: AlgiHeal™ Alginate 2x2
- A6197: AlgiHeal™ Alginate 4x4
- A6010: Collagen sheets/particles/powder
- A6021: Collagen dressings (pads)
- A6210: Collagen films
- A6248, A6249: Antimicrobial collagen products

### 2.3 Existing Preauthorization Workflow

**Current Status Tracking:**
1. Patient state machine: pending → approved/not_covered/need_info
2. Order status updates trigger automatic state changes
3. Conversation thread in patients.status_comment field
4. Manufacturer can approve/reject patients with feedback

**Missing Components:**
- No active API for insurance eligibility checking
- No integration with insurance carrier APIs (Availity, EDI, etc.)
- No documentation of preauth rules per HCPCS code
- No automated preauth decision engine
- No tracking of preauth request/response details

### 2.4 Email Notification System

**SendGrid Template Integration** (`/api/lib/email_notifications.php`)

```php
// 7 Email Templates Defined:
1. send_password_reset_email()
2. send_account_confirmation_email()
3. send_physician_account_created_email()
4. send_order_received_email()
5. send_order_approved_email()
6. send_order_shipped_email()
7. send_manufacturer_order_email()
```

**Environment Variables Required:**
```
SG_TMPL_PASSWORD_RESET
SG_TMPL_ACCOUNT_CONFIRM
SG_TMPL_PHYSACCOUNT_CONFIRM
SG_TMPL_ORDER_RECEIVED
SG_TMPL_ORDER_APPROVED
SG_TMPL_ORDER_SHIPPED
SG_TMPL_MANUFACTURER_ORDER
```

---

## 3. DATABASE SCHEMA FOR PREAUTHORIZATION

### 3.1 Core Preauth Tables

**patients** (Pre-existing - ENHANCED):
- `state` - Patient auth status (pending, approved, not_covered, need_info, active, inactive)
- `status_comment` - Conversation thread (timestamp + role + message format)
- `status_updated_at` - When status changed
- `status_updated_by` - Admin user ID who changed it

**orders** (Pre-existing - ENHANCED):
- `prior_auth` - Manual prior auth number entered by physician
- `payment_type` - 'insurance' or 'cash'
- `tracking_number`, `carrier` - Shipping info (added by migration)
- `icd10_primary`, `icd10_secondary` - Diagnosis codes for medical necessity

### 3.2 Planned Preauth Tables (NEW)

**preauth_requests** (NEW TABLE TO CREATE):
```sql
CREATE TABLE preauth_requests (
  id SERIAL PRIMARY KEY,
  order_id VARCHAR(64) NOT NULL UNIQUE,
  patient_id VARCHAR(64) NOT NULL,
  user_id VARCHAR(64) NOT NULL,
  
  -- Insurance info
  insurance_provider VARCHAR(255),
  insurance_member_id VARCHAR(100),
  insurance_group_id VARCHAR(100),
  insurance_payer_phone VARCHAR(50),
  
  -- Product & Clinical
  product_id INTEGER,
  hcpcs_code VARCHAR(20),
  quantity INT,
  frequency VARCHAR(100),
  duration_days INT,
  icd10_codes TEXT, -- JSON array of diagnosis codes
  
  -- Preauth Details
  preauth_number VARCHAR(100) NULL, -- From insurance response
  preauth_effective_date DATE NULL,
  preauth_expiry_date DATE NULL,
  request_date TIMESTAMP DEFAULT NOW(),
  response_date TIMESTAMP NULL,
  
  -- Status
  status VARCHAR(50) DEFAULT 'pending', -- pending, submitted, approved, denied, expired
  denial_reason TEXT NULL,
  denial_code VARCHAR(20) NULL, -- Insurance denial code
  
  -- Carrier Integration
  carrier_request_id VARCHAR(100) NULL, -- Tracking ID for carrier submission
  carrier_response_raw JSONB NULL, -- Full carrier response JSON
  submission_method VARCHAR(50), -- 'manual', 'availity', 'edi', 'api'
  
  created_at TIMESTAMP DEFAULT NOW(),
  updated_at TIMESTAMP DEFAULT NOW(),
  
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
);

CREATE INDEX idx_preauth_order ON preauth_requests(order_id);
CREATE INDEX idx_preauth_patient ON preauth_requests(patient_id);
CREATE INDEX idx_preauth_status ON preauth_requests(status);
CREATE INDEX idx_preauth_date ON preauth_requests(request_date, status);
```

**preauth_rules** (NEW - Configuration Table):
```sql
CREATE TABLE preauth_rules (
  id SERIAL PRIMARY KEY,
  hcpcs_code VARCHAR(20) NOT NULL,
  insurance_provider VARCHAR(255) NOT NULL, -- 'Medicare', 'Medicaid', 'Any', etc.
  product_id INTEGER,
  
  -- Rule conditions
  requires_preauth BOOLEAN DEFAULT TRUE,
  min_wound_size_cm2 DECIMAL(6,2) NULL,
  required_icd10_codes TEXT, -- JSON array
  documentation_requirements TEXT, -- Required docs
  
  -- Preauth timing
  valid_for_days INT DEFAULT 60,
  expedited_request BOOLEAN DEFAULT FALSE,
  
  created_at TIMESTAMP DEFAULT NOW(),
  
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  UNIQUE(hcpcs_code, insurance_provider, product_id)
);
```

**preauth_audit_log** (NEW - Compliance Tracking):
```sql
CREATE TABLE preauth_audit_log (
  id SERIAL PRIMARY KEY,
  preauth_request_id INT NOT NULL,
  action VARCHAR(100), -- 'submitted', 'approved', 'denied', 'expired', 'recalled'
  actor_type VARCHAR(50), -- 'system', 'admin', 'carrier'
  actor_id VARCHAR(64) NULL,
  details JSONB,
  created_at TIMESTAMP DEFAULT NOW(),
  
  FOREIGN KEY (preauth_request_id) REFERENCES preauth_requests(id) ON DELETE CASCADE
);
```

---

## 4. API PATTERNS & CONVENTIONS

### 4.1 API Structure

**Organization:**
```
/api/
  ├── db.php                    # Portal DB connection (30-day sessions)
  ├── login.php                 # Portal authentication
  ├── me.php                    # Get current user info
  ├── csrf.php                  # CSRF token endpoint
  ├── register.php              # Physician registration
  ├── auth/
  │   ├── request_reset.php     # Password reset request
  │   └── reset_password.php    # Reset password completion
  ├── admin/
  │   ├── ai_assistant.php      # AI-powered admin helper
  │   ├── patients.php          # Patient management API
  │   └── orders/
  │       ├── pending-review.php
  │       └── update-status.php
  ├── portal/
  │   ├── orders.create.php     # Create new order
  │   ├── patients.php          # Manage patient records
  │   └── generate_approval_score.php
  ├── lib/
  │   ├── ai_service.php        # Claude API integration
  │   ├── email_notifications.php
  │   ├── icd10_api.php         # NIH ICD-10 code lookup
  │   ├── twilio_sms.php        # SMS notifications
  │   ├── sg_curl.php           # SendGrid API wrapper
  │   └── ...
  └── cron/
      ├── send-delivery-confirmations.php
      └── send-physician-status-updates.php
```

### 4.2 Authentication Pattern

**Database:** PostgreSQL with PDO
**Session:** 30-day persistent cookies (portal), 7-day (admin)
**CSRF:** Token-based validation

```php
// Portal Auth (users table)
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized']); 
  exit;
}

// Admin Auth (admin_users table OR superadmin users)
if (!current_admin()) {
  header('Location: /admin/login.php');
  exit;
}
```

### 4.3 JSON Response Pattern

**Standard Success Response:**
```json
{
  "ok": true,
  "data": { /* response data */ }
}
```

**Standard Error Response:**
```json
{
  "ok": false,
  "error": "error_code_or_message"
}
```

**HTTP Status Codes:**
- 200: Success
- 400: Bad request
- 401: Unauthorized
- 404: Not found
- 419: CSRF token invalid
- 500: Server error

### 4.4 File Upload Pattern

**Upload Handling** (`orders.create.php`):
```php
// Save files AFTER responding to client (FastCGI finish)
function save_upload(string $field, string $subdir): array {
  // Check finfo for MIME type (not $_FILES['type'])
  $fi = new finfo(FILEINFO_MIME_TYPE);
  $mime = $fi->file($tmp) ?: 'application/octet-stream';
  
  // Allow-list: pdf, jpg, png, webp, heic, txt
  // Generate random token prefix
  // Store in /var/data/uploads (persistent disk on Render)
  // Return [web_path, mime_type]
}

// Async file operations
respond_now(['ok'=>true,'data'=>['order_id'=>$order_id]]);
// Continue with file uploads...
```

### 4.5 Database Query Pattern

**Prepared Statements (PDO):**
```php
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ? AND user_id = ?");
$stmt->execute([$patient_id, $uid]);
$patient = $stmt->fetch(PDO::FETCH_ASSOC);
```

**Transaction Pattern:**
```php
$pdo->beginTransaction();
try {
  // Multiple operations
  $pdo->rollBack();
} catch (Throwable $e) {
  $pdo->rollBack();
}
```

---

## 5. ADMIN TOOLS STRUCTURE

### 5.1 Admin Portal (`/admin/`)

**Main Interface Files:**
- `index.php` - Dashboard (orders, patients, users, analytics)
- `login.php` - Admin authentication
- `patients.php` - Patient management with state/comment workflow
- `orders.php` - Order review and approval
- `users.php` - User/role management
- `billing.php` - Revenue reporting
- `messages.php` - Communication threading
- `shipments.php` - Shipping & tracking management

**Admin Roles:**
1. **superadmin** - God mode (from users table with role='superadmin')
2. **manufacturer** - Can approve/reject patient pre-authorization
3. **employee** - Limited access to assigned practices
4. **admin** - General CollagenDirect staff

**Database:** PostgreSQL via `/admin/db.php`

### 5.2 Key Admin Workflows

**Patient Pre-Authorization:**
```
1. Manufacturer views pending patients list (/admin/patients.php)
2. Reviews patient state = 'pending'
3. Clicks "Approve", "Need Info", or "Not Covered"
4. Adds comment with feedback
5. Automatically updates associated orders if state='not_covered'
6. Sends notification to physician via status_comment thread
```

**Order Approval:**
```
1. Admin views submitted orders
2. Verifies patient is state='approved'
3. Clicks "Approve" to move to production
4. Sends order_approved email notification
5. Can add tracking later (UPS/FedEx/USPS)
6. System tracks carrier status changes
```

**AI-Powered Assistance:**
```
/api/admin/ai_assistant.php
- Analyzes order for completeness
- Generates professional response messages
- Suggests missing information
- Uses Claude API (Anthropic)
```

### 5.3 Migration & Setup Tools

**Key Migration Files:**
- `add-patient-status-and-comments.sql` - Added state machine
- `add-tracking-columns.sql` - Added tracking_number, carrier
- `create-notification-tables.sql` - Order delivery confirmations

**Admin Setup Scripts:**
- `run-all-migrations.php` - Execute all migrations
- `setup-database.php` - Initial DB schema
- `add-products-web.php` - Load product catalog

---

## 6. EMAIL & NOTIFICATION SYSTEMS

### 6.1 SendGrid Integration

**Template-Based Emails** (`/api/lib/email_notifications.php`):

1. **Password Reset** (`SG_TMPL_PASSWORD_RESET`)
   - Audience: All users
   - Trigger: Forgot password click
   - Data: reset_url, expires_minutes

2. **Account Confirmation** (`SG_TMPL_ACCOUNT_CONFIRM`)
   - Audience: Physicians (self-registration)
   - Trigger: Register account
   - Data: portal_url, practice_name

3. **Physician Account Created** (`SG_TMPL_PHYSACCOUNT_CONFIRM`)
   - Audience: Physicians (admin-created)
   - Trigger: Admin creates account
   - Data: temp_password, portal_url

4. **Order Received** (`SG_TMPL_ORDER_RECEIVED`)
   - Audience: Patients
   - Trigger: Order submitted
   - Data: order_id, products, physician_name, practice_name

5. **Order Approved** (`SG_TMPL_ORDER_APPROVED`)
   - Audience: Physicians
   - Trigger: Admin approves order
   - Data: approved_datetime, patient_name, products

6. **Order Shipped** (`SG_TMPL_ORDER_SHIPPED`)
   - Audience: Patients
   - Trigger: Tracking info added
   - Data: tracking_number, carrier, tracking_url

7. **Manufacturer New Order** (`SG_TMPL_MANUFACTURER_ORDER`)
   - Audience: Manufacturer
   - Trigger: Order submitted
   - Data: Order details, patient info, admin portal URL

### 6.2 SendGrid API Wrapper

**Function:** `sg_send()` in `/api/lib/sg_curl.php`
```php
sg_send(
  ['email' => $email, 'name' => $name],        // recipient
  null,                                         // cc
  null,                                         // bcc
  [
    'template_id' => $templateId,
    'dynamic_data' => [...],                    // Template variables
    'categories' => ['tag1', 'tag2']            // For filtering
  ]
);
```

### 6.3 Cron Jobs for Notifications

**Scheduled Workflows:**
- `/api/cron/send-delivery-confirmations.php` - Email patients 2-3 days after order
- `/api/cron/send-physician-status-updates.php` - Notify physicians of patient status changes

### 6.4 Twilio SMS Integration

**Library:** `/api/lib/twilio_sms.php`
- Send delivery confirmation SMS
- Patient appointment reminders
- Insurance updates

---

## 7. AI SERVICE INTEGRATION

### 7.1 Claude API Integration

**Service Class:** `/api/lib/ai_service.php`

**Capabilities:**
```php
// 1. Analyze order completeness
$ai->analyzeOrder($orderData, $patientData)

// 2. Generate professional response messages
$ai->generateResponseMessage($orderData, $patientData, $conversationHistory)

// 3. Generate medical necessity letters
$ai->generateMedicalNecessityLetter($orderData, $patientData)

// 4. Generate visit notes (defensible clinical documentation)
$ai->generateVisitNote($orderData, $patientData, $physicianData)

// 5. Generate approval score (Red/Yellow/Green)
$ai->generateApprovalScore($patientData, $documents)
```

**Model:** claude-sonnet-4-5-20250929
**API:** Anthropic Messages API

### 7.2 Approval Score System

**Endpoint:** `/api/portal/generate_approval_score.php`
**Trigger:** Automatic when physician completes patient documentation
**Output:** Color-coded score (Red/Yellow/Green) with detailed feedback

---

## 8. RECOMMENDED PREAUTH AGENT ARCHITECTURE

### 8.1 High-Level Design

```
┌─────────────────┐
│   Physician     │
│    Orders       │
└────────┬────────┘
         │
         ▼
┌──────────────────────────────┐
│ Order Created                │
│ - Clinical data collected    │
│ - Insurance info provided    │
└────────┬─────────────────────┘
         │
         ▼
┌──────────────────────────────────────────────────────┐
│ PREAUTH AGENT (NEW)                                  │
│ ┌──────────────────────────────────────────────────┐ │
│ │ 1. Extract Coverage Requirements                 │ │
│ │    - HCPCS code lookup                          │ │
│ │    - Insurance rules from preauth_rules table   │ │
│ │    - Medical necessity validation               │ │
│ └──────────────────────────────────────────────────┘ │
│ ┌──────────────────────────────────────────────────┐ │
│ │ 2. Check Patient Eligibility                     │ │
│ │    - ICD-10 diagnosis codes valid?              │ │
│ │    - Wound measurements meet threshold?         │ │
│ │    - Documentation complete?                    │ │
│ └──────────────────────────────────────────────────┘ │
│ ┌──────────────────────────────────────────────────┐ │
│ │ 3. Determine Preauth Need                        │ │
│ │    - Query preauth_rules table                  │ │
│ │    - Check if insurance requires preauth        │ │
│ │    - Generate decision logic                    │ │
│ └──────────────────────────────────────────────────┘ │
│ ┌──────────────────────────────────────────────────┐ │
│ │ 4. Submit Preauth Request (if needed)           │ │
│ │    - Format request for insurance carrier       │ │
│ │    - Submit via API, EDI, or manual form        │ │
│ │    - Store request in preauth_requests table    │ │
│ └──────────────────────────────────────────────────┘ │
│ ┌──────────────────────────────────────────────────┐ │
│ │ 5. Monitor & Track Preauth Status               │ │
│ │    - Check for carrier response                 │ │
│ │    - Handle approvals/denials/expirations       │ │
│ │    - Update order status accordingly            │ │
│ └──────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
         │
         ▼
┌──────────────────────────────────────────────────────┐
│ RESULTS                                              │
│ - preauth_requests table populated                  │
│ - Order status updated                              │
│ - Physician notified of preauth result             │
│ - Patient eligibility status reflected in UI       │
└──────────────────────────────────────────────────────┘
```

### 8.2 Implementation Approach

**Phase 1: Database & Rules Engine**
1. Create `preauth_requests` table
2. Create `preauth_rules` table (config)
3. Create `preauth_audit_log` table
4. Populate preauth_rules with initial HCPCS/insurance combos

**Phase 2: Core Preauth Logic**
1. Create `/api/preauth/eligibility-check.php` endpoint
2. Create `/api/lib/preauth_engine.php` class
3. Implement logic: Rule lookup → Eligibility check → Preauth decision
4. Test with sample orders

**Phase 3: Insurance Carrier Integration**
1. Implement manual submission (form-based)
2. Add Availity API integration (if available)
3. Add EDI submission capability
4. Create carrier response handler

**Phase 4: Monitoring & Automation**
1. Add `/api/cron/monitor-preauth-status.php`
2. Implement carrier response polling
3. Implement expiration tracking
4. Create notification system for physicians

**Phase 5: UI & Admin Tools**
1. Add preauth status to patient/order admin views
2. Create preauth request history view
3. Add manual override/resubmission capability
4. Create preauth analytics dashboard

### 8.3 API Endpoints to Create

**NEW ENDPOINTS:**

```php
// 1. Check if order needs preauth
POST /api/preauth/eligibility-check.php
Body: { order_id, hcpcs_code, insurance_provider, icd10_codes, ... }
Response: { requires_preauth: bool, rules: [...], required_docs: [...] }

// 2. Submit preauth request
POST /api/preauth/submit.php
Body: { order_id, submission_method: 'manual|availity|edi|api' }
Response: { preauth_request_id, submission_status, carrier_reference_id }

// 3. Check preauth status
GET /api/preauth/status.php?order_id=X
Response: { status, preauth_number, effective_date, expiry_date, denial_reason }

// 4. Update preauth from carrier (webhook)
POST /api/preauth/carrier-webhook.php
Body: { carrier: 'cms', request_id: X, status: 'approved', preauth_number: '...' }
Response: { ok: true }

// 5. Get preauth history
GET /api/preauth/history.php?patient_id=X
Response: { requests: [{ order_id, status, preauth_number, dates, ... }] }

// 6. Admin: Override preauth decision
POST /api/admin/preauth/override.php
Body: { preauth_request_id, new_status, reason, reviewed_by }
Response: { ok: true }
```

### 8.4 Database Triggers & Functions

**Automated Workflows:**

```sql
-- Trigger: When order status changes to 'submitted', create preauth_request
CREATE TRIGGER trigger_create_preauth_request
AFTER INSERT ON orders
FOR EACH ROW
EXECUTE FUNCTION create_preauth_for_new_order();

-- Trigger: When preauth expires, update order status to 'preauth_expired'
CREATE TRIGGER trigger_preauth_expiry_check
BEFORE UPDATE ON preauth_requests
FOR EACH ROW
EXECUTE FUNCTION check_preauth_expiry();

-- Function: Auto-approve orders that don't need preauth
CREATE FUNCTION auto_approve_exempt_orders()
RETURNS void AS $$
  UPDATE orders o
  SET status = 'approved'
  WHERE status = 'submitted'
    AND NOT EXISTS (
      SELECT 1 FROM preauth_rules pr
      WHERE pr.hcpcs_code = o.cpt
        AND pr.requires_preauth = TRUE
    );
$$ LANGUAGE SQL;
```

### 8.5 Key Integration Points

**1. Order Creation → Preauth Agent**
- Trigger preauth eligibility check in `/api/portal/orders.create.php`
- Store initial preauth_request record

**2. Admin Order Review → Preauth Status**
- Display preauth status in `/admin/orders.php`
- Show: "Preauth #12345 (Valid until 01/15/2025)"
- Show: "No preauth required" or "Preauth DENIED - Cash option available"

**3. Patient Admin → Preauth History**
- Link from `/admin/patients.php` to preauth request history
- Show all preauth submissions for patient's orders

**4. Cron Jobs → Monitoring**
- `monitor-preauth-status.php` polls carrier APIs
- Updates preauth_requests with responses
- Triggers order status updates
- Sends physician notifications

**5. AI Service → Preauth Recommendations**
- Enhance `generateApprovalScore()` to include preauth likelihood
- Suggest missing docs needed for preauth

---

## 9. SECURITY & COMPLIANCE CONSIDERATIONS

### 9.1 HIPAA Compliance

**Already Implemented:**
- Patient data encryption (via HTTPS/TLS)
- Session security (HTTPOnly, SameSite cookies)
- Role-based access control
- CSRF protection
- Error suppression (no internal details in responses)

**Preauth Agent Additions:**
- Audit logging in `preauth_audit_log` table
- Who requested preauth, when, what decision, why
- Encrypted storage of carrier credentials
- Secure transmission of insurance data to carriers

### 9.2 Data Sensitivity

**HIGH SENSITIVITY:**
- Insurance member IDs, group IDs
- Preauth numbers, carrier reference IDs
- ICD-10 diagnosis codes (PHI)

**MITIGATION:**
- All transmitted over HTTPS
- No logging of sensitive values (log actions, not data)
- Database-level encryption for carrier credentials
- Access limited to authorized admin roles

---

## 10. PRODUCT CATALOG & HCPCS CODES

### Current Products

| Product Name | SKU | HCPCS | Category | Notes |
|---|---|---|---|---|
| AlgiHeal™ Alginate 2x2 | ALG-2X2 | A6196 | Alginate | $12.50 admin / $8.00 wholesale |
| AlgiHeal™ Alginate 4x4 | ALG-4X4 | A6197 | Alginate | $18.00 admin / $12.00 wholesale |
| Collagen Sheets | COL-SHT | A6010 | Collagen | Standard preauth |
| Collagen Pads | COL-PAD | A6021 | Collagen | Common wound dressing |
| Collagen Films | COL-FLM | A6210 | Collagen | Thin protective barrier |
| Antimicrobial Collagen | AMC-001 | A6248, A6249 | Collagen | For infected wounds |

### Preauth Considerations

**No Preauth Required** (Direct Medicare approval):
- A6010, A6021, A6210 (straightforward Medicare approval)
- Typically 24-48 hour approval window

**Preauth Usually Required:**
- Complex products
- High-cost items
- When combined with other DME
- Off-label indications

---

## 11. IMPLEMENTATION TIMELINE & DEPENDENCIES

### Phase 1: Foundation (Week 1-2)
- [ ] Create database schema (preauth_requests, preauth_rules, preauth_audit_log)
- [ ] Create `/api/lib/preauth_engine.php` class
- [ ] Populate preauth_rules with HCPCS/insurance matrix
- [ ] Unit test eligibility logic

### Phase 2: Core API (Week 3-4)
- [ ] Implement eligibility check endpoint
- [ ] Implement submit preauth endpoint
- [ ] Create manual submission form
- [ ] Integrate with order creation workflow

### Phase 3: Carrier Integration (Week 5-6)
- [ ] Research carrier APIs (CMS, Availity, TMHCC)
- [ ] Implement carrier submission format
- [ ] Create webhook handler for carrier responses
- [ ] Test with sandbox environments

### Phase 4: Monitoring (Week 7-8)
- [ ] Create cron job for status monitoring
- [ ] Implement expiration tracking
- [ ] Create notification system
- [ ] Update order status based on preauth result

### Phase 5: Admin UI & Testing (Week 9-10)
- [ ] Add preauth status to admin interfaces
- [ ] Create preauth history viewer
- [ ] Create override capability
- [ ] Full QA and testing

---

## 12. KEY FILES FOR PREAUTH IMPLEMENTATION

### Database
- `/collagendirect/schema-postgresql.sql` - Extend with preauth tables
- `/collagendirect/migrations/` - Add preauth migrations

### API
- `/api/preauth/` (NEW DIR) - All preauth endpoints
- `/api/lib/preauth_engine.php` (NEW) - Core logic
- `/api/lib/email_notifications.php` - Extend for preauth emails

### Admin
- `/admin/preauth.php` (NEW) - Preauth management UI
- `/admin/patients.php` - Add preauth status column
- `/admin/orders.php` - Add preauth request display

### Cron
- `/api/cron/monitor-preauth-status.php` (NEW)
- `/api/cron/check-preauth-expiry.php` (NEW)

---

## CONCLUSION

The CollagenDirect system has a robust foundation for implementing preauthorization:

1. **Strong Patient/Order Management** - Complete patient tracking with state machine
2. **Existing Insurance Data** - Fields for insurance info, diagnosis codes, HCPCS codes
3. **Proven API Patterns** - RESTful endpoints with CSRF, prepared statements, error handling
4. **AI Integration Ready** - Claude API already integrated for admin assistance
5. **Email System** - SendGrid templates for all notifications
6. **Role-Based Access** - Admin portal with manufacturer and employee roles

**Next Steps:**
1. Design preauth_requests, preauth_rules, preauth_audit_log tables
2. Create preauth eligibility check engine
3. Integrate with order creation workflow
4. Build carrier submission capability
5. Implement monitoring and automation

The system is ready for a production-grade preauthorization agent implementation.

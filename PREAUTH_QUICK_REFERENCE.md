# CollagenDirect Preauthorization Agent - Quick Reference

## System Overview

**Technology Stack:**
- PHP 8.3 + PostgreSQL
- HIPAA-compliant DME order management
- Claude AI integration (Anthropic)
- SendGrid email notifications
- 30-day persistent sessions

---

## Key Database Tables

### Existing Tables (Patient & Order Management)

| Table | Purpose | Key Fields |
|---|---|---|
| `patients` | Patient profiles | id, user_id, state, status_comment, status_updated_at |
| `orders` | Clinical orders | id, patient_id, prior_auth, payment_type, icd10_primary/secondary, tracking_number |
| `products` | Product catalog | id, sku, hcpcs_code, cpt_code, price_admin |
| `users` | Physicians/practices | id, email, role, practice_name, npi |
| `admin_users` | System admins | id, email, role (superadmin/manufacturer/employee/admin) |

### Patient Authorization States

```
pending      → Need to review for coverage
approved     → Eligible for orders
not_covered  → Insurance won't cover
need_info    → Requesting more documentation
active       → Has active orders
inactive     → No longer active
```

---

## Current Insurance Features

### HCPCS Codes in System
- A6010: Collagen sheets/particles
- A6021: Collagen dressings (pads)
- A6196: Alginate 2x2
- A6197: Alginate 4x4
- A6210: Collagen films
- A6248, A6249: Antimicrobial collagen

### Existing Fields for Preauth
```
patients:
  - insurance_provider
  - insurance_member_id
  - insurance_group_id
  - insurance_payer_phone
  
orders:
  - insurer_name, member_id, group_id, payer_phone
  - prior_auth (manual number)
  - payment_type (insurance/cash)
  - icd10_primary, icd10_secondary
  - tracking_number, carrier
```

---

## API Architecture Patterns

### Authentication
```php
// Portal: 30-day sessions via /api/db.php
if (empty($_SESSION['user_id'])) return 401;

// Admin: 7-day sessions via /admin/db.php
if (!current_admin()) return 403;
```

### JSON Response Format
```json
Success: { "ok": true, "data": {...} }
Error:   { "ok": false, "error": "message" }
```

### Database Pattern
```php
$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $uid]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
```

---

## Email Notification System

### SendGrid Templates Available
1. `SG_TMPL_PASSWORD_RESET` - Password reset
2. `SG_TMPL_ACCOUNT_CONFIRM` - Account confirmation
3. `SG_TMPL_PHYSACCOUNT_CONFIRM` - Physician account created
4. `SG_TMPL_ORDER_RECEIVED` - Order received (patient)
5. `SG_TMPL_ORDER_APPROVED` - Order approved (physician)
6. `SG_TMPL_ORDER_SHIPPED` - Order shipped (patient)
7. `SG_TMPL_MANUFACTURER_ORDER` - New order (manufacturer)

### Example Usage
```php
require_once '/api/lib/email_notifications.php';

send_order_approved_email([
  'physician_email' => 'dr@practice.com',
  'patient_name' => 'John Doe',
  'order_id' => 'order123',
  'approved_datetime' => date('m/d/Y'),
  'product_name' => 'Collagen Sheet 4x4'
]);
```

---

## AI Service Integration

### Available Claude API Features
```php
$ai = new AIService();

// 1. Analyze order completeness
$analysis = $ai->analyzeOrder($orderData, $patientData);

// 2. Generate response messages
$response = $ai->generateResponseMessage($orderData, $patientData, []);

// 3. Generate approval score (Red/Yellow/Green)
$score = $ai->generateApprovalScore($patientData, $documents);

// 4. Medical necessity letter
$letter = $ai->generateMedicalNecessityLetter($orderData, $patientData);

// 5. Visit note generation
$note = $ai->generateVisitNote($orderData, $patientData, []);
```

---

## Admin Workflow

### Patient Pre-Authorization Status
1. Manufacturer views `/admin/patients.php`
2. Filters by `state = 'pending'`
3. Reviews insurance eligibility
4. Updates status: Approve → Not Covered → Need Info
5. Adds feedback in `status_comment`
6. Orders cascade if status='not_covered'

### Order Approval Workflow
1. Admin views `/admin/orders.php`
2. Verifies patient `state = 'approved'`
3. Approves order
4. Sends order_approved email
5. Later adds tracking info
6. System monitors shipment

---

## Files & Directory Structure

### Critical Directories
```
/collagendirect/
├── api/
│   ├── db.php (30-day sessions)
│   ├── portal/orders.create.php (Order creation)
│   ├── portal/patients.php (Patient management)
│   ├── admin/patients.php (Admin patient API)
│   ├── lib/ai_service.php (Claude AI)
│   ├── lib/email_notifications.php
│   ├── lib/icd10_api.php (ICD-10 lookup)
│   └── cron/ (Scheduled jobs)
├── admin/
│   ├── db.php (7-day sessions)
│   ├── patients.php (Patient UI)
│   ├── orders.php (Order UI)
│   └── auth.php (Authorization)
├── migrations/ (Database migrations)
└── schema-postgresql.sql (Base schema)
```

---

## What's Missing for Preauth Agent

### Not Currently Implemented
1. Automated insurance eligibility checking APIs
2. Integration with carrier APIs (Availity, EDI, etc.)
3. Preauth decision rules per HCPCS/insurance combo
4. Automated preauth request submission
5. Preauth expiration tracking
6. Carrier response handling
7. Preauth audit trails

### To Build
1. `preauth_requests` table - Track all preauth submissions
2. `preauth_rules` table - Config for coverage requirements
3. `preauth_audit_log` table - Compliance tracking
4. `/api/preauth/` endpoints - Core preauth APIs
5. `/api/lib/preauth_engine.php` - Business logic
6. `/admin/preauth.php` - Admin UI
7. Cron jobs for status monitoring

---

## Security Considerations

### HIPAA Compliance Already Built In
- HTTPS/TLS encryption
- HTTPOnly, SameSite cookies
- Role-based access control
- CSRF token protection
- Error suppression (no internal details exposed)

### For Preauth Agent Add
- Audit logging of all preauth decisions
- No logging of sensitive values (member IDs, etc.)
- Database encryption for carrier credentials
- Secure transmission to insurance carriers

---

## Implementation Priority

### Phase 1: Quick Wins (Week 1-2)
- Create preauth_requests, preauth_rules, preauth_audit_log tables
- Populate preauth_rules with HCPCS/insurance combos
- Create preauth_engine.php class

### Phase 2: Core Logic (Week 3-4)
- Eligibility check endpoint
- Integration with order creation
- Manual preauth submission

### Phase 3: Automation (Week 5-6)
- Carrier API integration (manual submission first)
- Webhook handler for responses
- Status monitoring cron

### Phase 4: Polish (Week 7-8)
- Admin UI enhancements
- Notification system
- Historical tracking

---

## Key Contact Points in Code

### Order Creation
**File:** `/api/portal/orders.create.php` (lines 1-330)
- Creates order record
- Sends notifications
- **INTEGRATION POINT:** Call preauth check after order insert

### Patient Status Management
**File:** `/admin/patients.php` (lines 90-120)
- Updates patient state
- Cascades to orders
- **INTEGRATION POINT:** Link to preauth status

### Admin Order View
**File:** `/admin/orders.php` (lines 33-100)
- Shows order details
- Approval workflow
- **INTEGRATION POINT:** Display preauth status & history

### Email Notifications
**File:** `/api/lib/email_notifications.php` (lines 1-330)
- 7 email templates
- **INTEGRATION POINT:** Add preauth status email template

---

## Database Connection Details

### Portal (Physicians)
```php
// /api/db.php
$pdo = new PDO("pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}", $DB_USER, $DB_PASS);
```

### Admin (System)
```php
// /admin/db.php
$pdo = new PDO("pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME}", $DB_USER, $DB_PASS);
```

### Environment Variables
```
DB_HOST=localhost (or Render PostgreSQL URL)
DB_NAME=collagen_db
DB_USER=postgres
DB_PASS=xxx
DB_PORT=5432
ANTHROPIC_API_KEY=sk-ant-...
SENDGRID_API_KEY=SG.xxx
```

---

## Next Steps

1. **Read Full Document:** `/collagendirect/PREAUTH_AGENT_ARCHITECTURE.md` (31KB)
2. **Start Database Work:** Create preauth tables based on Section 3.2
3. **Build Core Engine:** Create `/api/lib/preauth_engine.php` 
4. **Test Integration:** Insert preauth checks into order creation flow
5. **Expand:** Add carrier APIs and monitoring

The system is ready. You have a solid foundation to build on!

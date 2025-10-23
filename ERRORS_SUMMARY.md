# CollagenDirect - Error Summary & Analysis

## 🚨 Critical SQL Error

### Error #1: Missing Column `cpt` in `orders` Table

**Severity:** 🔴 CRITICAL - Blocks order creation

**Location:** [portal/index.php:306](portal/index.php#L306)

**Description:**
The PHP code attempts to INSERT into a column `cpt` that doesn't exist in the database schema. This will cause all order creation attempts to fail with a SQL error.

**Code Reference:**
```php
// Line 299-322 in portal/index.php
$ins=$pdo->prepare("INSERT INTO orders
  (id, patient_id, user_id, product, product_id, product_price, status,
   shipments_remaining, delivery_mode, payment_type,
   wound_location, wound_laterality, wound_notes,
   shipping_name, shipping_phone, shipping_address, shipping_city,
   shipping_state, shipping_zip,
   sign_name, sign_title, signed_at, created_at, updated_at,
   icd10_primary, icd10_secondary, wound_length_cm, wound_width_cm,
   wound_depth_cm,
   wound_type, wound_stage, last_eval_date, start_date,
   frequency_per_week, qty_per_change, duration_days,
   refills_allowed, additional_instructions,
   cpt)  // ← THIS COLUMN DOESN'T EXIST
  VALUES (?,?,?,?,?,?,?,?,?,?,
          ?,?,?,
          ?,?,?,?,?,?,
          ?,?,NOW(),NOW(),NOW(),
          ?,?,?,?,?,?,
          ?,?,?,?,?,?,?,?,?,
          ?)");
```

**Error Message You'll See:**
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'cpt' in 'field list'
```

**Fix:**
```sql
-- Run this SQL command
ALTER TABLE orders
ADD COLUMN cpt VARCHAR(20) NULL
COMMENT 'CPT code for billing - duplicates product.cpt_code for historical record'
AFTER additional_instructions;
```

**Or run the complete fix script:**
```bash
mysql -u frxnaisp_collagendirect -p frxnaisp_collagendirect < SQL_FIXES.sql
```

**Why This Happened:**
The AI likely intended to store the CPT code at the order level for historical/audit purposes (since products can change), but forgot to add the column to the database schema. The `products` table has `cpt_code`, but orders need their own copy.

**Testing After Fix:**
1. Try creating a new order through the portal
2. Check for SQL errors
3. Verify the order appears in the database with the CPT code populated

---

## ⚠️ Schema Design Issues

### Issue #2: Enum vs String Mismatch

**Severity:** 🟡 MEDIUM - May cause data inconsistency

**Tables Affected:**
- `orders.payment_type` - Defined as `ENUM('insurance','self_pay')`
- `patients.billing_type` - Defined as `ENUM('insurance','self_pay')`

**Problem:**
The PHP code treats these as regular strings, but the database defines them as ENUMs. This can cause:
- Silent data truncation if unexpected values are passed
- Harder to add new payment types without ALTER TABLE
- Inconsistent with other VARCHAR fields in the codebase

**Recommendation:**
```sql
-- Change to VARCHAR for flexibility
ALTER TABLE orders MODIFY COLUMN payment_type VARCHAR(20) DEFAULT 'insurance';
ALTER TABLE patients MODIFY COLUMN billing_type VARCHAR(20) NULL;
```

**Or enforce enum values in PHP:**
```php
// Add validation
$valid_payment_types = ['insurance', 'self_pay'];
if (!in_array($payment_type, $valid_payment_types)) {
    jerr('Invalid payment type');
}
```

---

## 🔍 Missing Functionality

### Issue #3: Incomplete Billing Module

**File:** [admin/billing.php](admin/billing.php#L1)

**Status:** Placeholder exists but no implementation

**Missing Features:**
- Invoice generation
- Payment processing
- Insurance claim submission
- Reimbursement calculation
- Financial reporting

**Evidence:**
- `reimbursement_rates` table is empty
- No integration with payment gateway
- No invoice templates

---

### Issue #4: Shipment Tracking Not Tested

**Files:**
- [admin/shipments.php](admin/shipments.php)
- [admin/carriers/webhook.php](admin/carriers/webhook.php)
- [admin/carriers/poll.php](admin/carriers/poll.php)

**Status:** Code exists but integration incomplete

**Missing:**
- No carrier API credentials configured
- Webhook endpoint not registered with carriers
- No tracking number validation
- No automatic status updates

---

### Issue #5: Password Reset Flow Untested

**Files:**
- [portal/forgot/index.php](portal/forgot/index.php)
- [portal/reset/index.php](portal/reset/index.php)
- [api/auth/request_reset.php](api/auth/request_reset.php)
- [api/auth/reset_password.php](api/auth/reset_password.php)

**Status:** Basic implementation present

**Concerns:**
- Email sending depends on SendGrid (not tested)
- Token expiration handling unclear
- No rate limiting on reset requests
- User experience not verified

---

## 🔐 Security Issues

### Issue #6: Exposed Credentials

**Severity:** 🔴 HIGH

**Files:**
- [api/.env](api/.env) - Contains actual SendGrid API key
- [api/db.php](api/db.php#L44) - Database password in code
- [admin/db.php](admin/db.php#L10) - Database password in code

**Exposed:**
```
SENDGRID_API_KEY=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
DB_PASS=YEW!ad10jeo
```

**Action Required:**
1. ✅ Moved to `.env` (root level)
2. ⚠️ Rotate SendGrid API key
3. ⚠️ Change database password
4. ⚠️ Add `.env` to `.gitignore` (done)
5. ⚠️ Never commit `.env` to version control

---

### Issue #7: CSRF Protection Incomplete

**Severity:** 🟡 MEDIUM

**Problem:**
- Some endpoints check CSRF tokens
- Others don't
- Inconsistent implementation

**Files with CSRF:**
- Portal endpoints generally check

**Files without CSRF:**
- Some API endpoints in [api/portal/](api/portal/)

**Fix Required:**
Audit all POST endpoints and ensure CSRF validation.

---

### Issue #8: File Upload Security

**Severity:** 🟡 MEDIUM

**Files:** [portal/index.php](portal/index.php#L130-173)

**Current State:**
- MIME type checking present
- File size limits enforced (25MB)
- Files stored with random names

**Concerns:**
- No virus scanning
- Upload directory accessible via web (needs .htaccess)
- No file integrity verification
- Patient files not encrypted at rest

**Recommendations:**
```apache
# Add to uploads/.htaccess
<FilesMatch "\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

---

## 📊 Data Integrity Issues

### Issue #9: Missing Foreign Key Validation

**Problem:**
The code references `product_id` but doesn't validate:
- Product exists
- Product is active
- Price hasn't changed

**Example:** [portal/index.php:242-244](portal/index.php#L242-244)

**Risk:**
Orders could be created with invalid product references if product is deleted.

**Fix:**
Database has proper foreign keys, but PHP should validate before INSERT.

---

### Issue #10: No Audit Trail

**Severity:** 🔴 HIGH (for HIPAA compliance)

**Problem:**
No systematic logging of:
- Who accessed what patient records
- When orders were modified
- Who approved/denied orders
- Configuration changes

**Required for HIPAA:**
- Access logs
- Modification logs
- Security event logs
- Retention for 6 years

**Implementation Needed:**
Create `audit_log` table:
```sql
CREATE TABLE audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id VARCHAR(64),
  action VARCHAR(50),
  entity_type VARCHAR(50),
  entity_id VARCHAR(64),
  old_values JSON,
  new_values JSON,
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🐛 Code Quality Issues

### Issue #11: No Error Handling

**Examples:**
- File operations don't check for disk space
- Database operations assume success
- No try-catch blocks in critical paths
- Errors echoed to user (information disclosure)

---

### Issue #12: No Input Validation Framework

**Problem:**
Validation scattered throughout code:
- Some fields validated
- Others trust user input
- No centralized validation rules

**Example Issues:**
- Phone number format inconsistent
- Email validation basic
- No ZIP code format checking
- No NPI number validation

---

### Issue #13: No Testing

**Missing:**
- Unit tests
- Integration tests
- End-to-end tests
- Security tests
- Load tests

**Risk:**
Can't verify fixes don't break existing functionality.

---

## 📈 Performance Concerns

### Issue #14: N+1 Query Problem

**Location:** Patient listing with orders

**Example:**
Loading 100 patients = 1 query for patients + 100 queries for last orders

**Fix:**
Use JOIN or subquery (already partially implemented).

---

### Issue #15: No Caching

**Problem:**
- Products fetched on every page load
- User session data re-fetched constantly
- No query result caching

**Recommendation:**
Implement Redis for:
- Session storage
- Query result caching
- Rate limiting

---

## 🎯 Priority Action Items

### Immediate (Before First Test)
1. ✅ Run SQL_FIXES.sql to add `cpt` column
2. ⚠️ Start MySQL/MariaDB server
3. ⚠️ Test database connection
4. ⚠️ Test order creation flow
5. ⚠️ Verify file uploads work

### Short Term (This Week)
1. ⚠️ Rotate API keys
2. ⚠️ Test email notifications
3. ⚠️ Add .htaccess to uploads/
4. ⚠️ Implement audit logging
5. ⚠️ Test all user flows

### Medium Term (This Month)
1. Complete billing module
2. Implement comprehensive error handling
3. Add input validation framework
4. Set up automated testing
5. Security audit

### Long Term (Before Production)
1. HIPAA compliance audit
2. Penetration testing
3. Load testing
4. Backup/disaster recovery plan
5. Documentation for support team

---

## 🎬 Getting Started Now

Run these commands in order:

```bash
# 1. Install dependencies (already done)
npm install

# 2. Start MySQL (choose one)
brew services start mysql
# OR
docker run --name collagen-mysql -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=frxnaisp_collagendirect \
  -e MYSQL_USER=frxnaisp_collagendirect \
  -e MYSQL_PASSWORD="YEW!ad10jeo" \
  -p 3306:3306 -d mysql:8.0

# 3. Import database and fixes
mysql -u root -p < frxnaisp_collagendirect.sql
mysql -u root -p frxnaisp_collagendirect < SQL_FIXES.sql

# 4. Test connection
node test-db-connection.js

# 5. Start application
php -S localhost:8000

# 6. Open in browser
open http://localhost:8000/portal
```

---

## 📞 Questions to Ask Your Friend

1. **Was billing intentionally left incomplete?**
2. **Which carrier service was intended for shipping?**
3. **Is there a staging/production database?**
4. **Have any users actually tested this?**
5. **What's the deployment target? (shared hosting, VPS, cloud)**
6. **Is HIPAA compliance required? (affects audit requirements)**
7. **Expected user load? (affects architecture decisions)**

---

**Report Generated:** 2025-10-22
**Analyzed By:** Claude (Anthropic)
**Status:** Ready for fixes and testing

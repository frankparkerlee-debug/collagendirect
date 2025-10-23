# CollagenDirect - Setup & Error Documentation

## Project Overview

**CollagenDirect** is a HIPAA-compliant healthcare application for managing:
- Patient records for wound care
- Medical orders for collagen-based wound therapy products
- Insurance processing and prior authorization
- Document management (insurance cards, prescriptions, AOB forms)
- Provider and admin portals

## Technology Stack

- **Backend:** PHP 8.3+ (procedural, no framework)
- **Database:** MySQL 8.0+ / MariaDB 11.4+
- **Frontend:** HTML, JavaScript, TailwindCSS
- **ORM:** Prisma (newly added)
- **Email:** SendGrid
- **Server:** Apache with .htaccess

## Critical SQL Errors Found

### 1. Missing Column: `cpt` in `orders` table

**Location:** [portal/index.php:306](portal/index.php#L306)

**Error:** The INSERT statement references a column `cpt` that doesn't exist in the database schema.

```php
// Line 306 in portal/index.php
INSERT INTO orders (..., cpt) VALUES (..., ?)
```

**Fix Required:** Add the missing column to the database:

```sql
ALTER TABLE orders ADD COLUMN cpt VARCHAR(20) NULL AFTER additional_instructions;
```

**Alternative:** Update the PHP code to remove the `cpt` reference (it may be redundant since `product_id` already links to the products table which has `cpt_code`).

### 2. Enum Column Type Mismatch

**Tables Affected:**
- `orders.payment_type` - defined as ENUM but used as VARCHAR in code
- `patients.billing_type` - defined as ENUM but used flexibly

**Recommendation:** Keep as VARCHAR for flexibility or ensure all code strictly uses enum values.

## Missing Functionality Identified

### 1. Database Connection
- **Issue:** No MySQL/MariaDB server running locally
- **Action Required:** Install and start MySQL or configure to use remote database

### 2. SendGrid Email Integration
- **Status:** Configuration exists but untested
- **Files:** `api/lib/mailer_sendgrid.php`, `api/test_sendgrid.php`
- **Action:** Test email sending functionality

### 3. File Upload Security
- **Issue:** Upload directories created with 0755 permissions
- **Files:** Patient IDs, insurance cards, clinical notes stored in `/uploads`
- **Recommendation:** Review permissions and add web server protection

### 4. CSRF Protection
- **Status:** Partially implemented
- **Missing:** CSRF tokens not verified on all POST endpoints in portal

### 5. Password Reset Flow
- **Files:** `portal/forgot/`, `portal/reset/`, `api/auth/reset_password.php`
- **Status:** Basic implementation present, needs testing

## Setup Instructions

### Step 1: Install MySQL/MariaDB

**macOS (Homebrew):**
```bash
brew install mysql
brew services start mysql
```

**Alternative - Use Docker:**
```bash
docker run --name collagen-mysql \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=frxnaisp_collagendirect \
  -e MYSQL_USER=frxnaisp_collagendirect \
  -e MYSQL_PASSWORD="YEW!ad10jeo" \
  -p 3306:3306 \
  -d mysql:8.0
```

### Step 2: Import Database Schema

```bash
# If using local MySQL
mysql -u root -p < frxnaisp_collagendirect.sql

# If using Docker
docker exec -i collagen-mysql mysql -uroot -proot < frxnaisp_collagendirect.sql
```

### Step 3: Fix Missing Column

```bash
mysql -u frxnaisp_collagendirect -p frxnaisp_collagendirect
```

Then run:
```sql
ALTER TABLE orders ADD COLUMN cpt VARCHAR(20) NULL AFTER additional_instructions;
```

### Step 4: Install Node Dependencies (Already Done)

```bash
npm install
```

### Step 5: Generate Prisma Client (Already Done)

```bash
npx prisma generate
```

### Step 6: Test Database Connection

```bash
node test-db-connection.js
```

### Step 7: Configure Web Server

**For PHP Built-in Server (Development Only):**
```bash
cd /Users/matthew/Downloads/parker
php -S localhost:8000
```

**For Apache:**
- Ensure mod_rewrite is enabled
- Point document root to `/Users/matthew/Downloads/parker`
- Ensure .htaccess files are processed

### Step 8: Test Endpoints

1. **Home:** http://localhost:8000/
2. **Portal Login:** http://localhost:8000/portal
3. **Admin Login:** http://localhost:8000/admin
4. **API Health:** http://localhost:8000/api/health.php

## Default Login Credentials

### Physician Portal
- **Email:** parker@senecawest.com
- **Password:** (hashed in DB - needs reset or check hash)

### Admin Panel
- **Email:** admin@collagen.health
- **Password:** (hashed in DB - needs reset or check hash)

## Known Issues & Recommendations

### Security Concerns

1. **Exposed Credentials**
   - Database credentials in PHP files
   - SendGrid API key committed to repo
   - **Action:** Move to environment variables

2. **Session Security**
   - Session settings could be hardened
   - Consider implementing session regeneration after login

3. **File Upload Validation**
   - MIME type checking present but could be strengthened
   - Add file size limits enforcement

### Missing Features

1. **Order Shipment Tracking**
   - Carrier webhook endpoint exists (`admin/carriers/webhook.php`)
   - Integration needs testing

2. **Billing Module**
   - Placeholder at `admin/billing.php`
   - Reimbursement rates table empty

3. **Audit Logging**
   - No systematic audit trail
   - Recommend adding for HIPAA compliance

4. **API Documentation**
   - No OpenAPI/Swagger docs
   - Endpoints discovered through code review

### Performance Optimizations

1. **Database Indexes**
   - Well-indexed for common queries
   - Consider composite indexes for frequent joins

2. **File Serving**
   - Large files served through PHP
   - Consider X-Sendfile or direct web server serving with auth

3. **Caching**
   - No caching layer present
   - Consider Redis for session storage and query caching

## File Structure

```
parker/
├── admin/              # Admin dashboard
│   ├── orders.php      # Order management
│   ├── users.php       # User management
│   ├── billing.php     # Billing (incomplete)
│   └── shipments.php   # Shipment tracking
├── api/                # Backend API endpoints
│   ├── auth/           # Authentication endpoints
│   ├── lib/            # Helper libraries
│   ├── portal/         # Portal-specific APIs
│   ├── db.php          # Database connection
│   └── csrf.php        # CSRF token generation
├── portal/             # Physician portal
│   ├── index.php       # Main portal app (SPA-like)
│   ├── patients.php    # Patient management
│   └── forgot/         # Password reset
├── uploads/            # User-uploaded files (excluded from git)
│   ├── ids/            # Patient ID cards
│   ├── insurance/      # Insurance cards
│   ├── notes/          # Clinical notes
│   └── aob/            # Assignment of Benefits
├── assets/             # Static assets
├── prisma/             # Prisma schema (NEW)
│   └── schema.prisma
├── index.html          # Public landing page
├── frxnaisp_collagendirect.sql  # Database dump
└── package.json        # Node dependencies (NEW)
```

## Next Steps

1. **Start MySQL/MariaDB server**
2. **Import database schema**
3. **Fix missing `cpt` column**
4. **Test database connection**
5. **Start PHP development server**
6. **Test login functionality**
7. **Verify file upload functionality**
8. **Test order creation workflow**

## Support & Development

This appears to be an AI-generated codebase with some missing implementations. Key areas that need completion:

- Billing module implementation
- Complete shipment tracking integration
- Enhanced security hardening
- Comprehensive testing suite
- API documentation
- Production deployment configuration

## Database Schema Notes

The Prisma schema has been generated and includes all tables:
- `users` (physicians/providers)
- `patients`
- `orders` (with clinical data)
- `products` (wound care products)
- `admin_users` (administrative staff)
- `admin_physicians` (admin-physician relationships)
- `password_resets` (password reset tokens)
- `login_attempts` (security logging)
- `reimbursement_rates` (insurance rates - empty)

All foreign keys and indexes have been preserved.

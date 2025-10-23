# CollagenDirect - Healthcare Platform

A HIPAA-compliant web application for managing wound care orders, patient records, and medical product distribution.

## 🚨 Critical Findings

Your friend's AI-generated code has **1 critical SQL error** that will prevent order creation:

### Missing Database Column
- **Location:** [portal/index.php:306](portal/index.php#L306)
- **Error:** INSERT statement references column `cpt` that doesn't exist in the `orders` table
- **Impact:** Order creation will fail with SQL error
- **Fix:** Run `SQL_FIXES.sql` to add the missing column

## 📋 What I Found & Fixed

### ✅ Created Files
1. **`prisma/schema.prisma`** - Full database schema with Prisma ORM
2. **`package.json`** - Node.js dependencies
3. **`.env`** - Environment configuration (with your existing SendGrid keys)
4. **`.gitignore`** - Security (prevents committing sensitive files)
5. **`SQL_FIXES.sql`** - Database fix script
6. **`SETUP.md`** - Comprehensive setup documentation
7. **`test-db-connection.js`** - Database connection test
8. **`quick-start.sh`** - Automated setup script

### 🔍 Issues Identified

**SQL Errors:**
- Missing `cpt` column in `orders` table (CRITICAL)

**Missing Functionality:**
- Billing module incomplete (`admin/billing.php` is placeholder)
- Shipment tracking webhook not tested
- Password reset flow needs testing
- No comprehensive error handling

**Security Concerns:**
- API keys exposed in code (moved to .env)
- Database credentials hardcoded (documented)
- Upload directory permissions need review
- CSRF protection incomplete

## 🚀 Quick Start

### Option 1: Automated Setup
```bash
./quick-start.sh
```

### Option 2: Manual Setup

#### 1. Install MySQL (choose one)

**Using Homebrew:**
```bash
brew install mysql
brew services start mysql
```

**Using Docker:**
```bash
docker run --name collagen-mysql \
  -e MYSQL_ROOT_PASSWORD=root \
  -e MYSQL_DATABASE=frxnaisp_collagendirect \
  -e MYSQL_USER=frxnaisp_collagendirect \
  -e MYSQL_PASSWORD="YEW!ad10jeo" \
  -p 3306:3306 \
  -d mysql:8.0
```

#### 2. Import Database & Apply Fixes

**For local MySQL:**
```bash
mysql -u root -p < frxnaisp_collagendirect.sql
mysql -u root -p frxnaisp_collagendirect < SQL_FIXES.sql
```

**For Docker:**
```bash
docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect < frxnaisp_collagendirect.sql
docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect < SQL_FIXES.sql
```

#### 3. Test Database Connection
```bash
npm install
node test-db-connection.js
```

#### 4. Start Application
```bash
php -S localhost:8000
```

#### 5. Access the App
- **Homepage:** http://localhost:8000/
- **Physician Portal:** http://localhost:8000/portal
- **Admin Panel:** http://localhost:8000/admin

## 🏗️ Project Structure

```
parker/
├── admin/                  # Admin dashboard
│   ├── index.php          # Main dashboard
│   ├── orders.php         # Order management
│   ├── users.php          # User management
│   ├── billing.php        # Billing (⚠️ incomplete)
│   └── shipments.php      # Shipment tracking
├── api/                    # Backend API
│   ├── auth/              # Authentication
│   │   ├── request_reset.php
│   │   └── reset_password.php
│   ├── lib/               # Helper libraries
│   │   ├── mailer_sendgrid.php
│   │   └── env.php
│   ├── portal/            # Portal APIs
│   ├── db.php             # Database connection
│   ├── login.php          # User login
│   └── register.php       # User registration
├── portal/                 # Physician portal
│   ├── index.php          # Main portal (SPA-style)
│   ├── forgot/            # Password reset
│   └── reset/             # Password reset form
├── prisma/                 # 🆕 Prisma ORM
│   └── schema.prisma      # Database schema
├── uploads/                # User uploads (git-ignored)
│   ├── ids/               # Patient ID cards
│   ├── insurance/         # Insurance cards
│   ├── notes/             # Clinical notes
│   └── aob/               # Assignment of Benefits
├── assets/                 # Static assets
│   ├── collagendirect.png
│   └── hero-collagen-sample.jpg
├── frxnaisp_collagendirect.sql  # Database dump
├── SQL_FIXES.sql          # 🆕 Required fixes
├── SETUP.md               # 🆕 Detailed setup guide
├── test-db-connection.js  # 🆕 Connection test
├── quick-start.sh         # 🆕 Automated setup
├── package.json           # 🆕 Node dependencies
├── .env                   # 🆕 Configuration
├── .gitignore             # 🆕 Security
└── README.md              # This file
```

## 🗄️ Database Schema

### Core Tables
- **users** - Physicians/healthcare providers
- **patients** - Patient records
- **orders** - Medical orders with clinical data
- **products** - Wound care products catalog
- **admin_users** - Administrative staff
- **admin_physicians** - Admin-physician relationships

### Supporting Tables
- **password_resets** - Password reset tokens
- **login_attempts** - Security audit log
- **reimbursement_rates** - Insurance reimbursement (empty)

## 🔐 Security Notes

### Credentials in Database
The SQL dump includes hashed passwords. You'll need to:
1. Reset passwords using the password reset flow
2. Or directly set new bcrypt hashes in the database

### Default Users
**Physician Portal:**
- Email: `parker@senecawest.com`
- Email: `parker@ideaworx.co`

**Admin Panel:**
- Email: `admin@collagen.health`

### Environment Variables
Sensitive data moved to `.env`:
- Database credentials
- SendGrid API key
- Email configuration

⚠️ **Never commit `.env` to version control**

## 🐛 Known Issues & TODOs

### High Priority
- [ ] Fix missing `cpt` column (run SQL_FIXES.sql)
- [ ] Test order creation flow end-to-end
- [ ] Verify file upload security
- [ ] Test email notifications
- [ ] Implement proper error logging

### Medium Priority
- [ ] Complete billing module
- [ ] Test shipment tracking webhook
- [ ] Add comprehensive input validation
- [ ] Implement rate limiting
- [ ] Add audit trail for HIPAA compliance

### Low Priority
- [ ] Add API documentation (OpenAPI/Swagger)
- [ ] Implement caching layer
- [ ] Add automated testing
- [ ] Performance optimization
- [ ] Mobile responsiveness improvements

## 📊 Testing the Application

### Test Database Connection
```bash
node test-db-connection.js
```

### Test Endpoints
```bash
# Health check
curl http://localhost:8000/api/health.php

# Portal health
curl http://localhost:8000/portal/health.php

# Admin health
curl http://localhost:8000/admin/auth.php
```

### Test Order Creation
1. Log in to physician portal
2. Navigate to "New Order"
3. Create or select a patient
4. Fill in required clinical fields
5. Submit order
6. Check for SQL errors in PHP error log

## 🆘 Troubleshooting

### Database Connection Failed
```bash
# Check if MySQL is running
brew services list | grep mysql

# Or for Docker
docker ps | grep collagen-mysql

# Test connection manually
mysql -u frxnaisp_collagendirect -p -h localhost
```

### Order Creation Fails
1. Check if SQL_FIXES.sql was applied
2. Verify `cpt` column exists:
   ```sql
   DESCRIBE orders;
   ```
3. Check PHP error logs

### File Upload Fails
```bash
# Verify upload directories exist
ls -la uploads/

# Fix permissions if needed
chmod 755 uploads uploads/*
```

## 📚 Documentation

- **[SETUP.md](SETUP.md)** - Comprehensive setup guide
- **[SQL_FIXES.sql](SQL_FIXES.sql)** - Database fixes with comments
- **[Prisma Schema](prisma/schema.prisma)** - Full database schema

## 🔄 Development Workflow

### Working with Prisma
```bash
# Generate Prisma Client
npx prisma generate

# Open Prisma Studio (database GUI)
npx prisma studio

# Pull schema from database
npx prisma db pull

# Push schema changes to database
npx prisma db push
```

### Database Migrations
```bash
# Create migration
npx prisma migrate dev --name description_of_change

# Apply migrations
npx prisma migrate deploy
```

## 🚀 Production Deployment

Before deploying to production:

1. **Security Hardening**
   - [ ] Change all default passwords
   - [ ] Rotate API keys
   - [ ] Enable HTTPS only
   - [ ] Configure firewall rules
   - [ ] Set up intrusion detection

2. **Environment**
   - [ ] Use production database
   - [ ] Configure proper file permissions
   - [ ] Set up proper logging
   - [ ] Configure email service
   - [ ] Set up monitoring

3. **Compliance**
   - [ ] HIPAA security assessment
   - [ ] Business Associate Agreements
   - [ ] Audit logging enabled
   - [ ] Backup strategy implemented
   - [ ] Incident response plan

## 📞 Support

This codebase was AI-generated and has missing functionality. Key areas need human review:
- Security hardening
- HIPAA compliance validation
- Production infrastructure setup
- Comprehensive testing

## 📄 License

[Add appropriate license]

---

**Status:** ⚠️ Development - Not production ready

**Last Updated:** 2025-10-22

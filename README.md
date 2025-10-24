# CollagenDirect - Healthcare DME Order Management System

## Overview
CollagenDirect is a HIPAA-compliant physician portal for managing wound care DME (Durable Medical Equipment) orders. The platform connects medical practices with manufacturers through a compliance workflow managed by CollagenDirect business administrators.

## System Architecture

### Technology Stack
- **Backend**: PHP 8.3
- **Database**: PostgreSQL (hosted on Render.com)
- **Frontend**: Vanilla JavaScript, Tailwind CSS
- **Session Management**: 7-day persistent cookies
- **File Storage**: Local filesystem with database path references

### Directory Structure
```
/Users/matthew/Downloads/parker/
├── api/                    # Backend API endpoints
│   ├── db.php             # Database connection & session config
│   ├── login.php          # Portal authentication
│   └── admin/             # Admin-specific API endpoints
│       └── orders/        # Order management APIs
├── admin/                 # CollagenDirect business admin interface
│   ├── db.php            # Admin database connection
│   ├── login.php         # Admin authentication
│   ├── order-review.php  # Super admin order review queue
│   └── _header.php       # Admin navigation template
├── portal/               # Physician portal interface
│   ├── index.php         # Main portal dashboard & order creation
│   ├── patients.php      # Patient management
│   └── set-superadmin-roles.php  # Role assignment utility
├── migrations/           # Database migration scripts
│   └── compliance-workflow.sql   # DME compliance schema
└── uploads/             # Patient document storage
```

## User Roles & Permissions

### Current Role System
1. **Practice Super Admin** - Full access to practice data and settings
2. **Physician** - Access to own patients and orders only
3. **CollagenDirect Super Admin** - God mode access across all practices
4. **CollagenDirect Employee** - Access to designated practices (permission-based)
5. **Manufacturer** - Read-only + approve/reject benefits verification

### Planned: Granular Permission System
Moving from rigid roles to domain-based permissions:
- **Domains**: Billing, Orders, Patients, Revenue Reports, Benefits Approval
- **Per-user assignment**: Email-based (e.g., user@example.com)
- **Read/Write flags**: Granular control per feature

Example:
```
matt@collagendirect.com:
  - Billing: read ✓, write ✓
  - Orders: read ✓, write ✓
  - Revenue Reports: read ✓, write ✗

manufacturer@supplier.com:
  - Orders: read ✓, write ✗
  - Benefits Approval: read ✓, write ✓
```

## DME Compliance Workflow

### Order Lifecycle (13 Statuses)
1. `draft` - Order created but not submitted
2. `submitted` - Submitted by physician, awaiting review
3. `under_review` - Being reviewed by CollagenDirect admin
4. `incomplete` - Missing required information/documents
5. `verification_pending` - Sent to manufacturer for insurance verification
6. `manufacturer_approved` - Manufacturer verified benefits (PLANNED)
7. `manufacturer_rejected` - Manufacturer denied benefits (PLANNED)
8. `cash_price_required` - Insurance denied, awaiting cash payment approval
9. `cash_price_approved` - Practice approved cash payment
10. `approved` - Order approved and ready for production
11. `in_production` - Manufacturer producing order
12. `shipped` - Order shipped to patient/practice
13. `delivered` - Confirmed delivery
14. `terminated` - Order cancelled (blocks future orders)
15. `cancelled` - Order cancelled

### Required Patient Documents
Before any order can be submitted:
- **Photo ID** (Driver's License, Passport, Government ID, etc.)
- **Insurance Card** (front/back)
- **Assignment of Benefits (AOB)** - Auto-generated and e-signed

### Order Completeness Validation
PostgreSQL function checks:
- Patient demographics (name, DOB, address)
- Clinical data (ICD-10, wound measurements, evaluation date)
- Required documents (ID, Insurance, AOB)
- Physician signature and NPI

## Key Features Implemented

### ✅ Session Persistence (Completed)
- 7-day session cookies for portal and admin
- Configured in `api/db.php`, `admin/db.php`, `api/login.php`, `admin/login.php`
- Users don't need to re-login after code changes

### ✅ Patient Document Workflow (Completed)
**New Patient Creation:**
- Photo ID and Insurance Card required at creation
- Documents uploaded immediately after patient save
- Success confirmation with document status

**Existing Patient Selection:**
- Automatic document status check when selected
- Visual indicators (✓ uploaded, ⚠️ missing)
- Inline upload UI for missing documents
- Real-time status updates after upload

**Order Validation:**
- Frontend checks documents before submission
- Backend enforces requirement for ALL orders
- Clear error messages listing missing documents

### ✅ Super Admin Order Review (Completed)
- Queue of orders awaiting review (`submitted`, `under_review`)
- Order completeness indicator
- Approve or mark incomplete actions
- Status update with tracking codes

### ✅ Sidebar Layout Fix (Completed)
- Fixed sidebar overlay blocking main content
- Proper width calculations: `calc(100% - 240px)`
- Collapse functionality with smooth transitions

### ✅ Admin Access for Superadmin (Completed)
- Superadmin role can access `/admin/` interface
- Role assignment script for sparkingmatt@gmail.com and parker@senecawest.com

### ✅ Real Error Reporting (Completed)
- Detailed error messages with stack traces
- No mock data - all database queries are real
- Failures visible for debugging

## Current Implementation Progress

### Phase 1: Foundation & UX (Current Focus)
- [ ] HIPAA credibility messaging on login page
- [ ] Mobile responsiveness - all portal pages
- [ ] Mobile responsiveness - all admin pages
- [ ] HCPCS codes + product dimensions in dropdown
- [ ] Secondary dressing field (gauze, etc.)
- [ ] Cell phone number field in patient profile
- [ ] Manual insurance info fields (carrier, group, member ID)

### Phase 2: Core Workflow Features
- [ ] Multiple wounds per order (separate dimensions, location, ICD-10 per wound)
- [ ] Standalone patient creation (without order, for benefit checks)
- [ ] Patient attachment management (add/edit/remove anytime)
- [ ] Dropdowns and autocomplete throughout system
- [ ] 30-day visit validation (order start within 30 days of last eval)

### Phase 3: Delivery & Notifications
- [ ] Proof of delivery system (email/SMS with confirmation link)

### Phase 4: Permission System
- [ ] Design permission schema (domains, read/write flags)
- [ ] Permission management UI for admins
- [ ] Enforce permissions across portal and admin

### Phase 5: Role-Specific Features
- [ ] Practice Super Admin vs Physician access separation
- [ ] Manufacturer role with approve/reject workflow
- [ ] Manufacturer approval statuses (`manufacturer_approved`, `manufacturer_rejected`)
- [ ] Referral-only practice flag (hide billing features)

### Phase 6: CollagenDirect Admin Dashboard
- [ ] Revenue visualization (CPT Rate × Frequency × Duration)
- [ ] Cross-practice patient database view
- [ ] Rejection comments system
- [ ] Billing page with revenue per patient
- [ ] Document links (ID, Insurance, Orders, Visit Notes)
- [ ] Consolidated order forms as downloadable links

## Database Schema

### Core Tables
- **users** - Physicians and practice admins
  - Fields: `id`, `email`, `first_name`, `last_name`, `role`, `npi`, `practice_name`, `has_dme_license`

- **patients** - Patient records
  - Fields: `id`, `user_id`, `first_name`, `last_name`, `dob`, `phone`, `email`, `address`, `city`, `state`, `zip`
  - Documents: `id_card_path`, `ins_card_path`, `aob_path`, `aob_signed_at`
  - Insurance: `insurance_provider`, `insurance_member_id`, `insurance_group_id`

- **orders** - DME orders
  - Fields: `id`, `patient_id`, `user_id`, `product_id`, `status`, `payment_method`
  - Clinical: `icd10_primary`, `icd10_secondary`, `wound_length_cm`, `wound_width_cm`, `wound_depth_cm`
  - Delivery: `delivery_location`, `tracking_code`, `carrier`
  - Compliance: `is_complete`, `missing_fields`, `cash_price`, `signature_data`

- **order_status_history** - Audit trail
  - Auto-populated via trigger on orders.status changes

- **order_alerts** - Notifications
  - Tracks alerts for physicians and admins

### Planned Schema Changes
- Add permission system tables (`user_permissions`, `permission_domains`)
- Add manufacturer approval tracking
- Add multiple wounds support (new table or JSON field)
- Add practice-level `is_referral_only` flag
- Add `cell_phone` to patients table
- Expand insurance fields (carrier, group, member ID)

## API Endpoints

### Portal APIs (portal/index.php)
- `?action=patients` - List patients with search
- `?action=patient.get&id={id}` - Get patient details + orders
- `?action=patient.save` - Create/update patient
- `?action=patient.upload` - Upload ID/Insurance/AOB/Visit notes
- `?action=patient.delete` - Delete patient and orders
- `?action=order.create` - Create new order with validation
- `?action=metrics` - Dashboard metrics

### Admin APIs (api/admin/orders/)
- `pending-review.php` - Get orders awaiting review
- `update-status.php` - Update order status with tracking
- `check-completeness.php` - Validate order completeness

## Configuration

### Environment Variables (Render.com)
- `DB_HOST` - PostgreSQL host
- `DB_NAME` - Database name
- `DB_USER` - Database user
- `DB_PASS` - Database password
- `DB_PORT` - Database port (default: 5432)
- `MIGRATION_SECRET` - Secret key for running migrations

### Session Configuration
- **Lifetime**: 7 days (604800 seconds)
- **Cookie Settings**: HttpOnly, SameSite=Lax, Secure (HTTPS only)
- **Session GC**: 7 days max lifetime

## Development Workflow

### Making Changes
1. Edit files locally in `/Users/matthew/Downloads/parker/`
2. Test locally if possible (or deploy to Render)
3. Commit with descriptive message including "🤖 Generated with Claude Code"
4. Push to GitHub: `git push origin main`
5. Render auto-deploys from main branch

### Running Migrations
Access via browser with secret key:
```
https://collagendirect.onrender.com/portal/run-migration.php?key=change-me-in-production
```

### Setting Superadmin Roles
```
https://collagendirect.onrender.com/portal/set-superadmin-roles.php?key=change-me-in-production
```

## Known Issues / Technical Debt
- [ ] Need to migrate from inline SQL in PHP to proper migration system
- [ ] Error handling could be more consistent across endpoints
- [ ] Some validation happens only on frontend (needs backend enforcement)
- [ ] File upload size limits not explicitly set
- [ ] No rate limiting on API endpoints
- [ ] Need to add CSRF protection more consistently
- [ ] Mobile responsiveness needs improvement across all pages

## Security Considerations
- All patient data is HIPAA-regulated
- Session tokens rotated on login
- File uploads validated by MIME type
- SQL queries use prepared statements
- Transactions protect data integrity
- Document paths stored in DB, not exposed directly

## Testing Checklist
Before deploying major features:
- [ ] Test with existing patient (has documents)
- [ ] Test with new patient creation (upload documents)
- [ ] Test with existing patient (missing documents)
- [ ] Test order submission validation
- [ ] Test on mobile device
- [ ] Test with different user roles
- [ ] Verify database persistence
- [ ] Check error messages are user-friendly

## Deployment
- **Platform**: Render.com
- **URL**: https://collagendirect.onrender.com
- **Auto-deploy**: Enabled from `main` branch
- **Build Command**: None (PHP served directly)
- **Start Command**: Apache/PHP-FPM

## Support & Credentials
- **SuperAdmin Users**: sparkingmatt@gmail.com, parker@senecawest.com
- **GitHub Repo**: mattedesign/collagendirect
- **Render Service**: Parker

## Recent Session Summary (2025-10-24 Continued)

### Completed This Session:
1. ✅ HIPAA credibility messaging on login pages (portal & admin)
2. ✅ HCPCS codes + product dimensions in dropdown
3. ✅ Secondary dressing field (gauze options)
4. ✅ Cell phone field in patient profile
5. ✅ Manual insurance info fields (carrier, member ID, group, payer phone)
6. ✅ **Multiple wounds per order capability**
   - Dynamic UI with add/remove wound buttons
   - Each wound: location, laterality, dimensions (L/W/D), type, stage, ICD-10 codes, notes
   - Stored as JSONB array in `orders.wounds_data`
   - Backward compatible with legacy single-wound columns
   - Migration completed ✓
7. ✅ **Standalone patient creation flow**
   - Full patient dialog accessible from Patients page "Add Patient" button
   - All fields: demographics, contact info, address, insurance details
   - Required document uploads (Photo ID + Insurance Card)
   - Three-step validation: patient creation → ID upload → insurance upload
   - Supports benefit checks before order creation
8. ✅ **Patient document management** (already existed - verified)
   - Upload/replace ID cards anytime from patient accordion
   - Upload/replace Insurance cards anytime
   - Generate/regenerate AOB documents
   - View current documents with links
   - Visual status indicators (✓/*)
9. ✅ **30-day order validation**
   - Backend: Orders must start within 30 days of last evaluation
   - Frontend: Real-time validation with visual feedback
   - Prevents orders starting before evaluation date
   - Clear error messages with date calculations
10. ✅ **Referral-only practice flag**
   - Database column: `users.is_referral_only` (BOOLEAN)
   - Hides Billing and Transactions navigation for referral practices
   - Redirects to dashboard if accessing billing pages directly
   - Allows order creation without billing exposure

### Migrations Completed:
- ✅ wounds_data JSONB column added to orders table
- ✅ Existing wound data migrated to new format
- ✅ is_referral_only flag added to users table (migration script ready)

### Previous Session Work:
1. ✅ Fixed sidebar overlay blocking content (width calculations)
2. ✅ Implemented complete patient document workflow
3. ✅ Superadmin role access to admin interface
4. ✅ Real error reporting (no mock data)

### Clarifications Received:
- Photo ID = Any valid ID (license, passport, government ID)
- Multiple wounds = Separate dimensions, location, ICD-10 per wound
- Referral-only practices = Add flag to hide billing features
- Manufacturer approval = Creates new order statuses
- "Practice Admin" → Actually "CollagenDirect Business Admin"
- Permissions = Domain-based (Billing, Orders, etc.) with read/write per user

### Next to Implement (Sequential):
1. **Proof of delivery system** (email/text with confirmation link)
   - Requires email/SMS integration
   - Confirmation link generation
   - Delivery status tracking
2. **Granular permission system** (domain-based with read/write)
   - Move from role-based to permission-based
   - Domains: Billing, Orders, Patients, Reports
   - Read/write flags per user per domain
3. **Manufacturer role** with approve/reject benefits verification
   - New role type
   - Approve/reject workflow
   - New order statuses (manufacturer_approved, manufacturer_rejected)
4. **Hide billing for referral-only practices**
   - Add practice-level flag
   - Conditionally hide billing features
5. **Dropdowns and autocomplete improvements**
   - ICD-10 code autocomplete (requires ICD-10 database)
   - Product search/filter
   - Other field improvements
6. **UI/UX Consistency**: Redesign CollagenDirect admin pages to match portal styling
   - Use same layout/navigation as physician portal
   - Match background, spacing, and responsive breakpoints
   - Admin pages are just protected routes by user/role, should look identical to portal
   - Current admin has different menu, styling, and layout that needs unification
7. **CollagenDirect Admin Features**:
   - Revenue visualization graphs (CPT Rate × Frequency × Duration)
   - Cross-practice patient database view
   - Rejection comments system
   - Consolidated document links
8. [Continue through comprehensive requirements list...]

---

**Last Updated**: 2025-10-24
**Version**: 0.3.0-alpha
**Status**: Active Development

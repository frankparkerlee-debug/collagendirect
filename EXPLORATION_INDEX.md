# CollagenDirect Codebase Exploration - Complete Documentation Index

**Exploration Date:** November 2, 2024  
**Thoroughness Level:** Very Thorough  
**Status:** Complete and Ready for Implementation

---

## Generated Documentation

### 1. PREAUTH_AGENT_ARCHITECTURE.md (31 KB)
**Primary comprehensive guide covering all aspects of the system**

Sections:
- Executive Summary
- Current Patient & Order Management System
- Existing Insurance-Related Functionality
- Database Schema for Preauthorization
- API Patterns & Conventions
- Admin Tools Structure
- Email & Notification Systems
- AI Service Integration
- Recommended Preauth Agent Architecture
- Security & Compliance Considerations
- Product Catalog & HCPCS Codes
- Implementation Timeline & Dependencies
- Key Files for Preauth Implementation

**Best for:** Architecture understanding, detailed implementation planning, database design

---

### 2. PREAUTH_QUICK_REFERENCE.md (8.1 KB)
**Quick lookup guide for developers**

Sections:
- System Overview
- Key Database Tables
- Current Insurance Features
- API Architecture Patterns
- Email Notification System
- AI Service Integration
- Admin Workflow
- Files & Directory Structure
- What's Missing for Preauth Agent
- Security Considerations
- Implementation Priority
- Key Contact Points in Code

**Best for:** Quick reference during development, API pattern lookups, integration points

---

### 3. EXPLORATION_INDEX.md (this file)
**Navigation guide for all documentation**

---

## System Summary

**Platform:** CollagenDirect - HIPAA-Compliant DME Order Management  
**Tech Stack:** PHP 8.3, PostgreSQL, SendGrid, Claude AI, Twilio SMS  
**Key Feature:** Patient pre-authorization with state machine workflow

### What's Already Built
- Patient management with 6-state authorization system
- Order management with clinical/insurance data
- Product catalog with HCPCS codes (A6010, A6021, A6196, A6197, A6210, A6248, A6249)
- Email notification system (7 SendGrid templates)
- Admin interface with role-based access
- AI-powered approval scoring (Claude API)
- ICD-10 code lookup API

### What Needs to Be Built for Preauth Agent
- Automated eligibility checking
- Preauth rules configuration system
- Preauth request tracking (3 new database tables)
- Carrier API integration
- Preauth status monitoring
- Expiration tracking
- Comprehensive audit logging

---

## Key Database Tables

### Existing (Patient & Order Management)
| Table | Purpose | Key Columns |
|-------|---------|------------|
| patients | Patient profiles | id, user_id, state, status_comment, insurance_* |
| orders | Clinical orders | id, patient_id, prior_auth, icd10_*, tracking_number |
| products | Product catalog | id, sku, hcpcs_code, cpt_code, price_* |
| users | Physicians/practices | id, email, practice_name, npi, role |
| admin_users | System administrators | id, email, role |

### To Create (Preauth System)
| Table | Purpose |
|-------|---------|
| preauth_requests | Track all preauth submissions, statuses, carrier responses |
| preauth_rules | Configuration for coverage requirements per HCPCS/insurance |
| preauth_audit_log | Compliance tracking for all preauth decisions |

---

## Critical File Locations

### API Layer
```
/api/
  db.php                              (30-day session config)
  portal/orders.create.php            (Order creation - KEY INTEGRATION POINT)
  admin/patients.php                  (Admin patient API)
  lib/ai_service.php                  (Claude AI integration)
  lib/email_notifications.php         (SendGrid templates)
  lib/icd10_api.php                   (ICD-10 code lookup)
  cron/                               (Scheduled notification jobs)
```

### Admin Interface
```
/admin/
  db.php                              (7-day session config)
  patients.php                        (Patient state management UI)
  orders.php                          (Order approval workflow)
  auth.php                            (Authorization checks)
```

### Database
```
schema-postgresql.sql                 (Base schema)
migrations/                           (Schema migrations)
  add-patient-status-and-comments.sql (Added state machine)
  add-tracking-columns.sql            (Added shipping tracking)
```

---

## API Pattern Quick Reference

### Authentication
```php
// Portal: 30-day persistent
if (empty($_SESSION['user_id'])) { /* 401 Unauthorized */ }

// Admin: 7-day persistent  
if (!current_admin()) { /* 403 Forbidden */ }
```

### JSON Response
```json
Success: { "ok": true, "data": {...} }
Error:   { "ok": false, "error": "message" }
```

### Database Pattern
```php
$stmt = $pdo->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
```

---

## Implementation Roadmap

### Phase 1: Database & Rules (Weeks 1-2)
- Create preauth_requests, preauth_rules, preauth_audit_log tables
- Populate rules with HCPCS/insurance combinations
- Create /api/lib/preauth_engine.php class

### Phase 2: Core Logic (Weeks 3-4)
- Eligibility check endpoint
- Integration with order creation flow
- Manual preauth submission

### Phase 3: Carrier Integration (Weeks 5-6)
- Carrier API research and implementation
- Request/response formatting
- Webhook handlers

### Phase 4: Monitoring (Weeks 7-8)
- Status monitoring cron job
- Expiration tracking
- Physician notifications

### Phase 5: UI & Testing (Weeks 9-10)
- Admin interface enhancements
- Override capabilities
- Full QA

---

## HCPCS Codes in System

| Code | Product | Notes |
|------|---------|-------|
| A6010 | Collagen sheets/particles | Standard preauth required |
| A6021 | Collagen dressings/pads | Common, 24-48hr approval |
| A6196 | AlgiHeal Alginate 2x2 | No preauth typically |
| A6197 | AlgiHeal Alginate 4x4 | No preauth typically |
| A6210 | Collagen films | Standard preauth required |
| A6248/A6249 | Antimicrobial collagen | Preauth for infections |

---

## Security Built-In

### HIPAA Compliance
- HTTPS/TLS encryption
- HTTPOnly, SameSite cookies
- Role-based access control (RBAC)
- CSRF token protection
- Session regeneration (1 hour)
- Error suppression (no internal details)

### For Preauth Agent Add
- Audit logging of all decisions
- No logging of sensitive values
- Database encryption for credentials
- Secure carrier API transmission

---

## Email Notification System

### 7 SendGrid Templates
1. SG_TMPL_PASSWORD_RESET - Password reset
2. SG_TMPL_ACCOUNT_CONFIRM - Account confirmation
3. SG_TMPL_PHYSACCOUNT_CONFIRM - Account created (admin)
4. SG_TMPL_ORDER_RECEIVED - Order received (patient)
5. SG_TMPL_ORDER_APPROVED - Order approved (physician)
6. SG_TMPL_ORDER_SHIPPED - Order shipped with tracking
7. SG_TMPL_MANUFACTURER_ORDER - New order notification

### Cron Jobs
- `send-delivery-confirmations.php` - 2-3 days after order
- `send-physician-status-updates.php` - Patient status changes

---

## AI Service Integration

### Claude API (Anthropic)
- Model: claude-sonnet-4-5-20250929
- Capabilities:
  1. Order completeness analysis
  2. Response message generation
  3. Medical necessity letters
  4. Visit note generation
  5. Approval score (Red/Yellow/Green)

---

## Admin Workflows

### Patient Authorization
1. View pending patients in /admin/patients.php
2. Review insurance eligibility and documentation
3. Update state: approve / not_covered / need_info
4. Add feedback in status_comment (conversation thread)
5. Auto-cascade to orders if state='not_covered'

### Order Approval
1. View submitted orders in /admin/orders.php
2. Verify patient state='approved'
3. Approve order (moves to production)
4. Later add tracking info (UPS/FedEx/USPS)
5. System monitors carrier status

---

## System Readiness

### Present (Ready to Use)
✓ Patient/order management  
✓ Insurance data fields  
✓ HCPCS code integration  
✓ Email notification system  
✓ AI integration (Claude)  
✓ Role-based access control  
✓ Admin interface  
✓ HIPAA architecture  

### Missing (To Build)
- Automated eligibility API  
- Carrier API integration  
- Preauth rules engine  
- Preauth tracking tables  
- Expiration monitoring  
- Compliance audit logs  

### Overall Assessment: **READY FOR IMPLEMENTATION**

---

## Quick Start for Development

1. **Understand Architecture**
   - Read PREAUTH_AGENT_ARCHITECTURE.md (full guide)
   - Review PREAUTH_QUICK_REFERENCE.md (patterns)

2. **Database Design**
   - Review Section 3.2 of architecture doc
   - Create migration: create-preauth-tables.sql
   - Execute migration

3. **Build Core Engine**
   - Create /api/lib/preauth_engine.php
   - Implement eligibility checking logic
   - Create unit tests

4. **Integration Point 1: Order Creation**
   - Modify /api/portal/orders.create.php
   - Add preauth eligibility check
   - Create preauth_request record
   - Test with sample orders

5. **Integration Point 2: Admin View**
   - Modify /admin/orders.php
   - Display preauth status and history
   - Add override capability

6. **Expand: Carrier Integration**
   - Research carrier APIs
   - Implement manual submission
   - Add API integration
   - Create webhook handlers

7. **Monitoring & Automation**
   - Create cron job: monitor-preauth-status.php
   - Implement expiration tracking
   - Create notification templates

---

## Contact Points for Integration

| Component | File | Lines | Purpose |
|-----------|------|-------|---------|
| Order Creation | /api/portal/orders.create.php | 1-330 | Insert preauth check |
| Patient Admin | /admin/patients.php | 90-120 | Link to preauth status |
| Order Admin | /admin/orders.php | 33-100 | Display preauth info |
| Email System | /api/lib/email_notifications.php | 1-330 | Add preauth template |
| Cron Jobs | /api/cron/ | - | Monitor preauth status |

---

## Development Standards

### From Existing Codebase
- Prepared statements (no SQL injection)
- PDO FETCH_ASSOC mode
- CSRF token validation
- Session-based auth
- Error suppression in API
- Async file operations
- Email template system

### Apply to Preauth Agent
- Follow same DB patterns
- Use same auth mechanism
- Implement CSRF protection
- Follow response format
- Add comprehensive error handling
- Implement audit logging

---

## Environment Variables Required

```
DB_HOST=localhost (or Render URL)
DB_NAME=collagen_db
DB_USER=postgres
DB_PASS=xxxxx
DB_PORT=5432
ANTHROPIC_API_KEY=sk-ant-xxxxx
SENDGRID_API_KEY=SG.xxxxx
TWILIO_ACCOUNT_SID=xxxxx
TWILIO_AUTH_TOKEN=xxxxx
```

---

## File Size Summary

| Document | Size | Purpose |
|----------|------|---------|
| PREAUTH_AGENT_ARCHITECTURE.md | 31 KB | Complete implementation guide |
| PREAUTH_QUICK_REFERENCE.md | 8.1 KB | Developer quick reference |
| EXPLORATION_INDEX.md | This file | Navigation & summary |

**Total Documentation:** ~39 KB of detailed, actionable guidance

---

## Next Action

**Start Here:** Open PREAUTH_AGENT_ARCHITECTURE.md

This comprehensive guide contains:
- Complete system architecture explanation
- Detailed database schema designs for new tables
- Full API endpoint specifications
- Integration points with existing code
- Security and compliance requirements
- 5-phase implementation timeline
- Detailed code examples and patterns

---

**Exploration completed by:** Claude Code Agent  
**Exploration type:** Very Thorough (all patient, order, insurance, admin, email systems)  
**Documentation generated:** 3 markdown files, ~39 KB  
**System status:** Ready for preauth agent implementation

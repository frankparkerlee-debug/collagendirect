# Database Connection Audit Report
**Date**: 2025-11-08
**System**: CollageDirect Portal & Admin

## Executive Summary

Analyzed all database connections across the CollageDirect application to identify inconsistencies, potential issues, and security concerns.

**Key Findings:**
- ✅ 2 primary database connection files (API and Admin)
- ⚠️ Inconsistent usage patterns across admin files (64 use admin/db.php, 47 use api/db.php)
- ⚠️ Session lifetime mismatch (API: 30 days, Admin: 7 days)
- ⚠️ 17+ migration/utility scripts with direct PDO connections
- ✅ Connection parameters are consistent (encoding, PDO options)
- ✅ All use environment variables for credentials

---

## Database Connection Files

### 1. `/api/db.php` (Primary API Connection)
**Purpose**: Used by all API endpoints and portal pages
**Session Lifetime**: 30 days
**Session Check**: `PHP_SESSION_NONE`
**Features**:
- Security headers (X-Frame-Options, Referrer-Policy, X-Content-Type-Options)
- Session regeneration every 1 hour (security)
- Helper functions: `json_out()`, `require_csrf()`, `uid()`
- Self-test capability when accessed directly

**Used By**:
- 43 API endpoint files (`/api/*`)
- Most portal pages (`/portal/*`)
- Some admin utilities

**Session Configuration**:
```php
session.gc_maxlifetime = 60*60*24*30 (30 days)
session.cookie_lifetime = 60*60*24*30 (30 days)
samesite = 'Lax'
httponly = true
```

### 2. `/admin/db.php` (Admin Connection)
**Purpose**: Used by admin panel pages
**Session Lifetime**: 7 days
**Session Check**: `PHP_SESSION_ACTIVE`
**Features**:
- CSRF token generation
- Helper functions: `e()`, `csrf_field()`, `verify_csrf()`
- Function guard to prevent redeclare errors

**Used By**:
- 64 admin files (`/admin/*`)
- Admin panel main pages

**Session Configuration**:
```php
session.gc_maxlifetime = 60*60*24*7 (7 days)
session.cookie_lifetime = 60*60*24*7 (7 days)
samesite = 'Lax'
httponly = true
```

### 3. `/admin/db-cli.php` (CLI-only Connection)
**Purpose**: Command-line scripts and migrations
**Session**: None (CLI only)
**Features**: Minimal - just PDO connection

**Used By**:
- CLI migration scripts
- Batch processing scripts
- Database utilities

---

## Connection Parameters (All Consistent)

```php
// Environment Variables
DB_HOST: getenv('DB_HOST') ?: '127.0.0.1'
DB_NAME: getenv('DB_NAME') ?: 'collagen_db'
DB_USER: getenv('DB_USER') ?: 'postgres'
DB_PASS: getenv('DB_PASS') ?: ''
DB_PORT: getenv('DB_PORT') ?: '5432'

// DSN
"pgsql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};options='--client_encoding=UTF8'"

// PDO Options (Consistent across all files)
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
```

---

## Critical Issues Identified

### ⚠️ ISSUE #1: Inconsistent Session Lifetimes
**Severity**: MEDIUM
**Impact**: User confusion, potential security implications

**Problem**:
- API/Portal sessions: 30 days
- Admin sessions: 7 days
- Users switching between portal and admin may experience unexpected logouts

**Recommendation**: Standardize to 7 days for all sessions (better security)

### ⚠️ ISSUE #2: Mixed Database Connection Usage in Admin
**Severity**: LOW
**Impact**: Code maintenance confusion

**Problem**:
Admin directory has files using TWO different db.php files:
- 64 files use `require __DIR__ . '/db.php'` (admin/db.php)
- 47 files use `require __DIR__ . '/../api/db.php'` (api/db.php)

**Files Using api/db.php in Admin**:
```
admin/deactivate-15day-kits.php
admin/update-order-status-to-pending.php
admin/run-all-migrations.php
admin/check-products.php
admin/add-products-web.php
... and 42 more
```

**Recommendation**: Standardize to one connection file per directory

### ⚠️ ISSUE #3: Direct PDO Connections Bypass Central Config
**Severity**: MEDIUM
**Impact**: Harder to maintain, potential security issues

**Problem**:
17+ utility scripts create their own PDO connections instead of using db.php:

```
portal/add-wounds-data-column.php
portal/add-referral-only-flag.php
portal/add-secondary-dressing-column.php
admin/setup-database.php
admin/migrate-billing-routing-cli.php
... and 12 more
```

**Recommendation**: Migration scripts can keep direct connections, but ensure they use same connection parameters

### ⚠️ ISSUE #4: Session Check Method Inconsistency
**Severity**: LOW
**Impact**: Potential for double session start

**Problem**:
- api/db.php uses: `if (session_status() === PHP_SESSION_NONE)`
- admin/db.php uses: `if (session_status() !== PHP_SESSION_ACTIVE)`

These are functionally equivalent, but inconsistent naming.

**Recommendation**: Standardize to one check method

---

## Session Security Analysis

### ✅ Security Features (Good)
1. **Session Regeneration**: API regenerates session ID every 1 hour (prevents fixation)
2. **HttpOnly Flag**: Prevents JavaScript access to session cookies
3. **SameSite**: Set to 'Lax' (good balance of security and usability)
4. **Secure Flag**: Enabled when HTTPS detected
5. **CSRF Protection**: Both connection files implement CSRF tokens

### ⚠️ Security Concerns
1. **30-Day Sessions**: Very long session lifetime in api/db.php
   - Increases risk if session token compromised
   - Recommendation: Reduce to 7 days max

2. **No Session Fingerprinting**: No user agent or IP validation
   - Could add additional validation for high-security operations

---

## File Usage Statistics

| Connection File | Total Uses | Primary Context |
|----------------|------------|----------------|
| `/api/db.php` | 90+ files | API endpoints, Portal pages |
| `/admin/db.php` | 64 files | Admin panel pages |
| `/admin/db-cli.php` | 1 file | CLI scripts |
| Direct PDO | 17+ files | Migration/utility scripts |

---

## Recommendations

### High Priority
1. **Standardize session lifetime to 7 days** across all connection files
2. **Document which db.php to use** in developer guidelines:
   - API endpoints → `/api/db.php`
   - Admin pages → `/admin/db.php`
   - CLI scripts → `/admin/db-cli.php`

### Medium Priority
3. **Audit admin files** using api/db.php and migrate to admin/db.php if appropriate
4. **Add connection pooling** consideration for high-traffic endpoints
5. **Implement query caching** for frequently-accessed data

### Low Priority
6. **Standardize session check method** to one approach
7. **Add database connection monitoring** to track connection count and performance
8. **Consider read replicas** for reporting/analytics queries

---

## Connection Flow Diagram

```
User Request
    │
    ├─→ API Endpoint (/api/*)
    │   └─→ require /api/db.php
    │       ├─→ Session: 30 days
    │       ├─→ CSRF check
    │       └─→ PDO connection
    │
    ├─→ Portal Page (/portal/*)
    │   └─→ require ../api/db.php
    │       ├─→ Session: 30 days
    │       └─→ PDO connection
    │
    ├─→ Admin Page (/admin/*)
    │   ├─→ require /admin/db.php (64 files)
    │   │   ├─→ Session: 7 days
    │   │   ├─→ CSRF token
    │   │   └─→ PDO connection
    │   │
    │   └─→ require ../api/db.php (47 files)
    │       ├─→ Session: 30 days
    │       └─→ PDO connection
    │
    └─→ CLI Script
        └─→ require /admin/db-cli.php
            ├─→ No session
            └─→ PDO connection
```

---

## Testing Recommendations

1. **Session Persistence Test**: Verify sessions persist for expected duration
2. **Cross-Context Test**: Test switching between portal and admin
3. **Connection Pool Test**: Monitor connection count under load
4. **CSRF Protection Test**: Verify CSRF tokens work across both connection types
5. **Session Regeneration Test**: Verify session IDs regenerate correctly

---

## Conclusion

The database connection infrastructure is **generally sound** with consistent parameters and good security practices. The main areas for improvement are:

1. Standardizing session lifetimes (security)
2. Clarifying which connection file to use where (maintainability)
3. Potentially reducing session duration in API contexts (security)

**Overall Risk Level**: LOW to MEDIUM
**Action Required**: Recommended improvements, not urgent fixes

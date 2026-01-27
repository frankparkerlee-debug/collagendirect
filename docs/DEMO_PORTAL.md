# Demo Portal Documentation

## Overview

The Demo Portal is a sandboxed environment that allows sales reps to showcase the CollagenDirect physician portal to prospective practices. It features a guided tour and interactive sandbox with ephemeral data for HIPAA compliance.

## Access

**URL:** `https://collagendirect.health/demo-portal/login.html`

### Email-Only Access
The demo portal uses simple email-only access for ease of use:
- Enter email address (required for tracking)
- Optionally enter name
- No password or account required
- Click "Start Demo" to begin

### Who Can Access
- Anyone with a valid email address
- Sales reps demonstrating to prospects
- Prospective physicians evaluating the platform
- No existing account needed

## Features

### Guided Tour (Shepherd.js)
An 8-step interactive tour walks users through key features:

1. **Welcome** - Introduction and overview
2. **Dashboard Overview** - Practice activity metrics
3. **Patient Management** - Patient roster and search
4. **Referral Orders Intro** - How referral ordering works
5. **Referral Order Form** - Wound details, ICD-10, documents
6. **Order Tracking** - Order status from submission to delivery
7. **Wholesale Orders** - DME ordering for licensed practices
8. **Tour Complete** - Summary and next steps

### Interactive Sandbox
After the tour, users can:
- Create test patients
- Place referral orders (CollagenDirect bills insurance)
- Place wholesale orders (practice bills as DME)
- Track order status

### Demo Banner
Orange banner at top provides:
- **Restart Tour** - Start the guided tour over
- **Reset Demo** - Clear and re-seed demo data
- **Exit Demo** - Log out and return to main site

## Data Management

### HIPAA Compliance
- All demo data is synthetic (no real PHI)
- Demo sessions expire after 24 hours
- Data is automatically purged by cleanup cron job

### Demo Tables
- `demo_sessions` - Active demo sessions with expiry
- `demo_patients` - Synthetic patient data per session
- `demo_orders` - Demo orders per session

### Data Seeding
On login, the system seeds:
- 5 sample patients with realistic fake data
- 3 sample orders in various statuses (submitted, approved, shipped)

## Technical Architecture

### Files
```
/demo-portal/
  index.php              # Main demo portal page
  login.html             # Login page (email-only)
  logout.php             # Session cleanup
  assets/
    tour-config.js       # Shepherd.js tour configuration
    demo-styles.css      # Demo-specific styles

/api/demo/
  login.php              # Authentication endpoint
  seed-data.php          # Generate synthetic data
  reset.php              # Reset demo session
  patients.php           # Demo patient CRUD
  orders.php             # Demo order CRUD
  tour.php               # Tour progress tracking

/cron/
  cleanup-demo-data.php  # Purge expired sessions
```

### Tour Navigation
The tour handles page navigation by:
1. Storing target step index in `sessionStorage` before navigation
2. Navigating via `window.location.href`
3. On page load, checking `sessionStorage` and resuming at correct step

## Admin Features

### Send Announcement Email
**URL:** `/admin/send-demo-portal-announcement.php`

Allows superadmins to send demo portal announcement emails to active sales reps. Features:
- Select individual recipients or all
- Preview email before sending
- Track sent/failed counts

## Database Migration

To enable email-only access, run the migration:
```sql
-- admin/migrations/demo-portal/002_email_only_access.sql
```

This migration:
- Removes the foreign key constraint on `user_id` (allows guest users)
- Adds `demo_email` and `demo_name` columns for tracking
- Creates index for email lookups

## Troubleshooting

### Tour Not Advancing
If the tour gets stuck:
1. Click "Restart Tour" in the demo banner
2. Clear browser sessionStorage
3. Check browser console for JavaScript errors

### Gray Overlay Stuck
If Shepherd.js overlay remains after tour:
1. The cleanup function runs on page load
2. Click "Restart Tour" to reset
3. Refresh the page

### Login Issues
- Ensure valid email format
- Check browser cookies are enabled
- Clear session and try again

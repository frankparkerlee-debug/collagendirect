# Priority Fixes Summary

## COMPLETED ✅

### 1. Order Actions Error (Critical) - FIXED
**Issue:** Internal error when clicking approve/reject/ship/edit
**Root Cause:** MySQL-specific syntax (IF(), DATABASE()) incompatible with PostgreSQL
**Fix:** Changed to PostgreSQL COALESCE() and removed DATABASE()
**Status:** ✅ Deployed
**Commit:** de27629

---

## IN PROGRESS 🔄

### 2. Attachment Links Not Working (Issue #13)
**Issue:** Links returning 'not found' in admin/patients.php and admin/orders.php
**Investigation Needed:**
- Check how links are generated in both files
- Verify file paths match upload directory structure
- Ensure CSRF tokens are passed correctly
**File:** `/admin/file.dl.php` (download handler looks correct)

---

## HIGH PRIORITY 🔴

### 3. Ship Field Populated with Attachment Filenames (Issue #16)
**Impact:** Confusing UX, wrong data in tracking field
**Investigation:** Check orders.php form fields and database column mapping

### 4. Patient List Filters Not Working (Issue #3)
**Location:** https://collagendirect.health/portal/
**Investigation:** Check JavaScript filter implementation in portal/index.php

### 5. Patient List Checkboxes Not Working (Issue #4)
**Location:** https://collagendirect.health/portal/
**Investigation:** Check checkbox event handlers

---

## MEDIUM PRIORITY 🟡

### 6. Patient Names Not Clickable (Issue #5)
**Simple Fix:** Add onclick/href to patient name cells
**Estimated Time:** 15 minutes

### 7. Change "Bandage Count" to "Product Count" (Issue #9)
**Type:** Global search and replace
**Files to Check:** All portal and admin pages
**Estimated Time:** 30 minutes

### 8. Top Bar Notifications Not Clickable (Issue #6)
**Investigation:** Check notification rendering code

### 9. File Attachments on Patient-Add Page (Issue #8)
**Location:** portal/index.php?page=patient-add
**Current:** Likely text fields only
**Needed:** File upload inputs for Notes, Insurance, ID

---

## WORKFLOW ENHANCEMENTS 🎯

### 10. Pre-Authorization Workflow (Issue #1) - NEW FEATURE
**Scope:** Large feature requiring design
**Components Needed:**
- Patient status field (Approved, Not Covered, Need Info)
- Comments system visible to both portals
- Notification system for status changes
- Clear communication flow

### 11. Status-Driven Filters (Issue #10) - NEW FEATURE
**For Orders:**
- Approved
- Rejected (with comments)
- Need Info (with comments)

**For Patients:**
- Approved
- Not Covered (with comments)
- Need Info (with comments)

### 12. Patient Profile Columns (Issue #11)
**Changes Needed:**
- Hide: City/State, Email columns
- Add: Comments for insurance approval & medical necessity

### 13. Past Orders in Patient Profile (Issue #19)
**Show:** Last 3 orders with Product, Date, Wound Type(s)

---

## INTEGRATIONS & OPTIONAL ⭐

### 14. Physician Password Setup (Issue #12)
**Options:**
- A) Send temporary password (current method)
- B) Send password reset link (more secure)
**Recommendation:** Send reset link

### 15. Metrics Drill-Down (Issue #2)
**Location:** Portal dashboard tiles
**Needed:** Make tiles clickable to filtered views

### 16. Messages Improvements (Issues #17, #18)
- Admin can select patient from dropdown when messaging providers
- Messages appear in notifications system

### 17. Notes Input (Issue #14)
**Portal Order Form:** Allow paste OR file attachment for notes

---

## FUTURE ENHANCEMENTS 🚀

### 18. ICD-10 Prepopulation (Issue #20)
**Type:** Database + autocomplete feature

### 19. AI Medical Necessity Scrubbing (Issue #21)
**Type:** AI integration

### 20. Multiple Wounds Per Order (Issue #22)
**Type:** UI/UX redesign of order form

### 21. Insurance Authorization Integration (Issue #23)
**Integration:** Availity or similar 3rd party
**Type:** Major integration project

---

## TOKEN BUDGET STATUS

**Remaining:** ~68,000 tokens
**Reset:** October 31 at 4pm

**Recommended Approach:**
1. ✅ Fix all critical bugs first (order actions - DONE)
2. 🔄 Fix attachment links (investigating)
3. Fix patient list functionality (filters, checkboxes, clickable names)
4. Quick wins (rename bandage count, make things clickable)
5. New features in next session after token reset

---

## NEXT STEPS

### Immediate (This Session if Tokens Allow):
1. ✅ Fix order actions - COMPLETE
2. Investigate attachment links issue
3. Fix ship field showing wrong data
4. Make patient names clickable
5. Fix patient list filters/checkboxes

### Next Session (After Token Reset):
1. Implement status-driven filters
2. Add comment fields for statuses
3. Build pre-authorization workflow
4. Implement notifications for messages
5. Add patient dropdown to messages

---

**Last Updated:** 2025-10-27
**Status:** In Progress - Order actions fixed, continuing with attachment links

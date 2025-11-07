# Wound Photos System - Current Status

## ✅ Completed (Nov 6, 2025)

### 1. Schema Fixes
- ✓ Added `order_id` column to `wound_photos` table
- ✓ Added `updated_at` column to `wound_photos` table
- ✓ Added `order_id` column to `photo_requests` table
- ✓ Created indexes on `order_id` columns
- ✓ Migration run successfully via `admin/run-wound-photos-migration.php`

### 2. Backend API Improvements
- ✓ Fixed `photo.assign_order` endpoint to use `order_id` column
- ✓ Added filtering to exclude archived/rejected/cancelled orders
- ✓ Updated `patient.get` API to include `order_id` in photos response
- ✓ Fixed `check-webhook-logs.php` schema references

### 3. Frontend Enhancements
- ✓ Filter archived/rejected/cancelled orders from assignment dropdown
- ✓ Display currently assigned order in photo viewer modal
- ✓ Pre-select assigned order in dropdown
- ✓ Dynamic button text based on assignment status
- ✓ Show assigned order in photo metadata grid

## 🔴 Critical Issue: Photo Files Not Saving

### Problem
Photos are being recorded in the database but **files are not on disk**:

**Database Records:** 6 photos
**Files on Disk:** 0 files

**Upload directories exist but are EMPTY:**
- `/var/www/html/uploads/wound_photos` - EXISTS, 0 files
- `/var/www/html/admin/../uploads/wound_photos` - EXISTS, 0 files

### Root Causes (Suspected)

1. **receive-mms.php** (line 206):
   ```php
   $savedBytes = file_put_contents($fullPath, $photoData);
   ```
   - May be failing silently
   - Permission issues on `/var/www/html/uploads/wound_photos/`
   - Photo data from Twilio may be empty/corrupt

2. **upload.php** (line 85-86):
   ```php
   if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
       throw new Exception('Failed to save photo');
   }
   ```
   - This SHOULD throw error if failing
   - Need to check if web uploads work vs MMS

### Next Steps to Debug

1. **Check directory permissions:**
   ```bash
   ls -la /var/www/html/uploads/wound_photos/
   # Should be: drwxr-xr-x www-data www-data
   ```

2. **Test Twilio download:**
   - Check if `TwilioHelper::downloadMedia()` is actually returning photo data
   - Add logging to see size of `$photoData` before `file_put_contents()`

3. **Check server error logs:**
   ```bash
   tail -f /var/log/apache2/error.log
   # While sending MMS
   ```

4. **Test web upload vs MMS:**
   - Try uploading via `/upload/{token}` link
   - If web uploads work, issue is in MMS webhook
   - If web uploads also fail, it's a permissions issue

### Diagnostic Scripts Created
- `admin/fix-photo-paths.php` - Shows where photos should be
- `admin/find-photos.php` - Searches filesystem for actual photos

## 📋 Outstanding Feature Requests

### 1. SMS Tokenization per Order ⏳
**User Request:** "SMS should be tokenized in case two providers in different practices see the same patient and issue different orders for different wounds."

**Current State:** `photo_requests` table has `order_id` column added

**TODO:**
1. Update "Request Photo" button to send `order_id`
2. Modify photo request creation to store `order_id`
3. Update `receive-mms.php` to:
   - Match SMS reply to specific photo_request by token
   - Auto-assign photo to the order_id from photo_request
4. Update `upload.php` to auto-assign from photo_request.order_id

### 2. AI Note Generation for Photo Review ⏳
**User Request:** "When going through the photo review, when the user clicks the status, it should automatically populate a note using AI that the doctor can review and submit."

**TODO:**
1. Add AI integration to photo review page
2. On status change, call AI API with:
   - Photo image data
   - Patient history
   - Order details
3. Generate note suggestions:
   - Wound assessment
   - Healing progress
   - Recommendations
4. Allow doctor to edit before saving

### 3. Enhanced Activity Log ⏳
**User Request:** "Make sure we include ALL patient activity (excluding comments) in the activity log ie Delivery, when the patient acknowledges delivery, etc."

**TODO:**
1. Query `delivery_confirmations` table
2. Query `wound_photos` for upload events
3. Combine with order events
4. Sort chronologically
5. Display in Activity Log section

## 🔧 Immediate Action Required

**Priority 1:** Fix photo file saving issue
- Without files on disk, the entire photo system is non-functional
- Database records are useless without actual images
- Blocks all other enhancements

**Commands to investigate:**
```bash
# On server
cd /var/www/html
ls -la uploads/wound_photos/
chmod 755 uploads/wound_photos/
chown www-data:www-data uploads/wound_photos/
tail -f /var/log/apache2/error.log

# Test MMS webhook
curl -X POST https://collagendirect.health/api/twilio/receive-mms.php \
  -d "From=+13057836633" \
  -d "NumMedia=1" \
  -d "MediaUrl0=https://..." \
  -d "MediaContentType0=image/jpeg"
```

## Files Modified
- `portal/index.php` - photo.assign_order endpoint, patient.get API, photo viewer UI
- `admin/check-webhook-logs.php` - schema fixes
- `admin/run-wound-photos-migration.php` - migration runner
- `admin/fix-photo-paths.php` - diagnostic tool
- `migrations/fix-wound-photos-schema.sql` - schema changes

## Deployment Status
- ✓ Code pushed to GitHub
- ✓ Deployed to https://collagendirect.health
- ✓ Migration run successfully
- ⚠️ Photo files not saving - URGENT FIX NEEDED

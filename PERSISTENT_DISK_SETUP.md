# Persistent Disk Setup Guide for Render

## Problem

Currently, uploaded files (visit notes, wound photos, insurance cards, patient IDs) are being saved to the **ephemeral container filesystem**, which means:
- ✗ Files are lost on every deployment
- ✗ Files are lost when the container restarts
- ✗ Data is not persistent across deployments

## Solution

Configure a **persistent disk** on Render that mounts to `/var/www/html/uploads`.

---

## Step 1: Configure Persistent Disk on Render

### Via Render Dashboard

1. **Go to Render Dashboard**
   - Navigate to: https://dashboard.render.com
   - Select your web service (collagendirect)

2. **Add Persistent Disk**
   - Click **"Disks"** tab in the left sidebar
   - Click **"Add Disk"** button

3. **Configure Disk Settings**
   ```
   Name:       uploads
   Mount Path: /var/www/html/uploads
   Size:       1 GB (start small, can increase later)
   ```

4. **Save and Deploy**
   - Click **"Save"**
   - Render will automatically restart your service with the disk mounted
   - This restart takes about 2-3 minutes

### Recommended Disk Sizes

- **1 GB**: Good for testing, ~1000 documents
- **5 GB**: Small practice, ~5000 documents
- **10 GB**: Medium practice, ~10,000 documents
- **25+ GB**: Large practice or multi-practice platform

**Note:** You can increase disk size later without data loss.

---

## Step 2: Verify Disk Setup

After Render restarts, run the diagnostic script:

```
https://collagendirect.health/admin/check-disk-setup.php
```

**Expected Output:**
```
=== Persistent Disk Setup Diagnostic ===

1. Checking if persistent disk exists...
   ✓ Persistent disk directory exists: /var/www/html/uploads

2. Checking disk permissions...
   ✓ Directory is writable

3. Checking subdirectories...
   - notes/ (will be created on first upload)
   - wound_photos/ (will be created on first upload)
   - insurance/ (will be created on first upload)
   - ids/ (will be created on first upload)

4. Testing write capability...
   ✓ Successfully wrote test file
   ✓ Successfully deleted test file

=== ✓ Persistent Disk is Properly Configured ===
```

---

## Step 3: Migrate Existing Files (if any)

If you already have uploaded files in the ephemeral location, migrate them:

```
https://collagendirect.health/admin/migrate-uploads-to-persistent-disk.php
```

This script will:
- Copy all files from ephemeral storage to persistent disk
- Skip duplicates (based on filename and size)
- Preserve original file paths (no database updates needed)

---

## Step 4: Test Upload Functionality

1. **Create a new order** with file uploads:
   - Visit note PDF
   - Baseline wound photo
   - Insurance card
   - Patient ID card

2. **Verify files persist**:
   - Run check-disk-setup.php again
   - Should show uploaded files in respective subdirectories

3. **Trigger a deployment** (e.g., push a small commit):
   - Render restarts the container
   - Re-run check-disk-setup.php
   - Files should still be there ✓

---

## How It Works

### Code Logic (api/portal/orders.create.php)

```php
function dir_from_docroot(string $subdir): string {
  // Check for persistent disk first (Render)
  if (is_dir('/var/www/html/uploads')) {
    // Use persistent disk
    return '/var/www/html' . $subdir;
  }

  // Fallback to ephemeral storage (development)
  $root = $_SERVER['DOCUMENT_ROOT'] ?? '/var/www/html';
  return $root . $subdir;
}
```

**When persistent disk exists:**
- Files saved to: `/var/www/html/uploads/notes/`
- Web path stored in DB: `/uploads/notes/abc123-file.pdf`
- Accessed via: `https://collagendirect.health/uploads/notes/abc123-file.pdf`

**When persistent disk does NOT exist:**
- Files saved to: `/var/www/html/uploads/notes/` (ephemeral)
- Same web paths, but files are lost on restart

---

## Directory Structure

After setup, the persistent disk will have:

```
/var/www/html/uploads/
├── notes/              # Visit notes (PDFs)
├── wound_photos/       # Baseline wound photos (JPG, PNG, HEIC)
├── insurance/          # Insurance cards (JPG, PNG, PDF)
└── ids/                # Patient photo IDs (JPG, PNG, PDF)
```

Each file is named with a random prefix:
```
abc123def456-Original_Filename.pdf
```

---

## Troubleshooting

### Issue: "Persistent disk directory does NOT exist"

**Cause:** Disk not configured on Render yet.

**Fix:** Follow Step 1 above to add the disk.

---

### Issue: "Directory is NOT writable"

**Cause:** Permission issue with mounted disk.

**Fix:**
```bash
# SSH into Render container (if available) or contact support
chmod 775 /var/www/html/uploads
chown www-data:www-data /var/www/html/uploads
```

Or contact Render support - this is usually automatic.

---

### Issue: Files disappeared after deployment

**Cause:** Persistent disk not configured, using ephemeral storage.

**Fix:**
1. Configure persistent disk (Step 1)
2. Files are unfortunately lost - need to be re-uploaded
3. Going forward, files will persist

---

### Issue: Old files in ephemeral location, new files in persistent location

**Cause:** Persistent disk was added after some uploads already occurred.

**Fix:** Run migration script (Step 3):
```
https://collagendirect.health/admin/migrate-uploads-to-persistent-disk.php
```

---

## Monitoring & Maintenance

### Check Disk Usage

Run diagnostic script periodically:
```
https://collagendirect.health/admin/check-disk-setup.php
```

### Increase Disk Size (if needed)

1. Go to Render Dashboard → Disks
2. Click on "uploads" disk
3. Increase size
4. Save (no data loss, service may restart briefly)

### Backup Strategy

**Render automatically backs up persistent disks**, but you can also:

1. **Periodic snapshots** (via Render dashboard)
2. **Sync to S3** (optional, for extra redundancy):
   ```bash
   aws s3 sync /var/www/html/uploads s3://your-backup-bucket/uploads/
   ```

---

## Cost

Render persistent disks cost:
- **$0.25/GB/month**

Examples:
- 1 GB disk = $0.25/month
- 5 GB disk = $1.25/month
- 10 GB disk = $2.50/month
- 25 GB disk = $6.25/month

This is **much cheaper** than losing critical patient documents!

---

## FAQ

**Q: What happens if I don't set up persistent disk?**
A: Uploaded files will be lost on every deployment or container restart. Not recommended for production.

**Q: Can I change the mount path?**
A: Yes, but you'd need to update the code in multiple places. `/var/www/html/uploads` is recommended.

**Q: Do I need to update database paths after setup?**
A: No - the code stores web paths (e.g., `/uploads/notes/file.pdf`), which work with both ephemeral and persistent storage.

**Q: What if I run out of disk space?**
A: Increase disk size via Render dashboard. No data loss, brief restart.

**Q: Are files encrypted?**
A: Files are stored on Render's encrypted disks. For extra security, consider encrypting sensitive files before upload.

---

## Summary Checklist

- [ ] Configure persistent disk on Render (mount to `/var/www/html/uploads`)
- [ ] Wait for service to restart (~2-3 minutes)
- [ ] Verify setup: https://collagendirect.health/admin/check-disk-setup.php
- [ ] Migrate existing files (if any): https://collagendirect.health/admin/migrate-uploads-to-persistent-disk.php
- [ ] Test upload functionality (create order with files)
- [ ] Verify files persist after deployment
- [ ] Set calendar reminder to check disk usage monthly

---

**Last Updated:** November 18, 2024
**Status:** Ready to implement
**Priority:** HIGH - Prevents data loss

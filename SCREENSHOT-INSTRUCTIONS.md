# Portal Screenshot Capture Instructions

## Quick Start

Since automated screenshot capture requires complex authentication handling, the easiest approach is to capture screenshots manually using your browser's built-in tools.

## Method 1: Chrome DevTools (Recommended - Full Page Screenshots)

1. **Login to the portal:**
   - Go to https://collagendirect.health/portal/
   - Login with: `parker@senecawest.com` / `Password321`

2. **Open Chrome DevTools:**
   - Press `F12` (Windows) or `Cmd + Option + I` (Mac)

3. **Capture screenshot:**
   - Press `Cmd + Shift + P` (Mac) or `Ctrl + Shift + P` (Windows)
   - Type "screenshot"
   - Select **"Capture full size screenshot"** (captures entire page, even parts not visible)
   - Screenshot will download automatically

4. **Repeat for each page** (see checklist below)

## Method 2: macOS Screenshot Tool

- **Selected area:** `Cmd + Shift + 4` (then drag to select)
- **Specific window:** `Cmd + Shift + 4` then press `Space` (click window)

## Screenshots Needed

### Priority Screenshots (Most Important)

1. **✅ Dashboard** (`/portal/`)
   - Shows: Revenue metrics, patient count, recent activity
   - Save as: `dashboard.png`

2. **✅ ICD-10 Autocomplete** (`/portal/?page=order-add&patient_id=<any-patient>`)
   - ⭐ **IMPORTANT**: Type "wound" or "ulcer" in the ICD-10 field to show dropdown
   - Shows: Autocomplete suggestions appearing as you type
   - Save as: `icd10-autocomplete.png`

3. **✅ Patient List** (`/portal/?page=patients`)
   - Shows: List of all patients with search/filter
   - Save as: `patients-list.png`

4. **✅ Create Order Form** (`/portal/?page=order-add&patient_id=<any-patient>`)
   - Shows: Full order creation workflow
   - Save as: `order-create.png`

### Additional Screenshots (Nice to Have)

5. **Patient Detail** (`/portal/?page=patient-detail&id=<patient-id>`)
   - Save as: `patient-detail.png`

6. **Orders List** (`/portal/?page=orders`)
   - Save as: `orders-list.png`

7. **Documents Page** (`/portal/?page=documents`)
   - Save as: `documents.png`

8. **Add Patient Form** (`/portal/?page=patient-add`)
   - Save as: `patient-add.png`

## Where to Save Screenshots

### Option A: Local Development
```bash
mkdir -p /Users/parkerlee/CollageDirect2.1/collagendirect/assets/screenshots
# Save all .png files here
```

### Option B: Production Server
```bash
mkdir -p /var/data/uploads/portal-screenshots
# Upload via SFTP or admin interface
```

## Adding Screenshots to the Guide

Once you have the screenshots, you'll need to:

1. Upload them to the server
2. Update `/portal-guide.php` to reference them

I'll create a script to help with this - see `update-guide-screenshots.php`

## Tips for Best Results

- **Use incognito/private mode** to get clean screenshots without browser extensions
- **Zoom to 100%** in browser (Cmd+0 / Ctrl+0)
- **Close unnecessary tabs** to keep interface clean
- **Use test data** that looks realistic
- **Capture the ICD-10 autocomplete IN ACTION** - this is the most important screenshot!

## Need Help?

If screenshots aren't working, we can:
1. Use detailed text descriptions instead
2. Create SVG mockups of the interface
3. Use browser automation tools (Playwright/Puppeteer)

# File Upload JSON Error Fix

## Error Encountered

```
Upload error: Unexpected token '<', "<!doctype "... is not valid JSON
```

## Root Cause

**The `action` parameter was being sent in the wrong place!**

The backend PHP code reads the action from the **query string** (`$_GET['action']`):
```php
$action = $_GET['action'] ?? null;
```

But our JavaScript was sending it in the **POST body** (FormData):
```javascript
form.append('action', 'patient.upload'); // WRONG!
await fetch('', { method: 'POST', body: form });
```

This meant the server didn't recognize the request as an upload action, so it rendered the full HTML page instead of returning JSON.

## The Fix

**Move the action to the URL query string:**

### Before (BROKEN):
```javascript
const form = new FormData();
form.append('action', 'patient.upload'); // ← action in POST body
form.append('patient_id', patientId);
form.append('type', type);
form.append('file', file);

await fetch('', { method: 'POST', body: form }); // ← empty URL
```

### After (FIXED):
```javascript
const form = new FormData();
// action removed from FormData
form.append('patient_id', patientId);
form.append('type', type);
form.append('file', file);

await fetch('?action=patient.upload', { method: 'POST', body: form }); // ← action in URL!
```

## Files Modified

**portal/index.php** - Two functions updated:

1. **`uploadPatientFile()`** (line 1147)
   - Changed from `fetch('')` to `fetch('?action=patient.upload')`
   - Removed `form.append('action', ...)`

2. **`generateAOB()`** (line 1183)
   - Changed from `fetch('')` to `fetch('?action=patient.upload')`
   - Removed `form.append('action', ...)`

## How to Test

1. **Clear your browser cache** (important!)
   - Press Ctrl+Shift+Delete (Windows) or Cmd+Shift+Delete (Mac)
   - Clear cached images and files
   - OR just do a hard refresh: Ctrl+F5 or Cmd+Shift+R

2. **Log in to portal:**
   ```
   http://localhost:8000/portal
   Email: sparkingmatt@gmail.com
   Password: TempPassword123!
   ```

3. **Go to Patients tab**

4. **Click "View / Edit"** on any patient

5. **Scroll down** to "Required Documents" section

6. **Upload ID Card:**
   - Click "Choose File"
   - Select any PDF or image file
   - File should upload automatically
   - You should see: "ID Card uploaded successfully!"
   - Page reloads and shows green ✓ checkmark

7. **Upload Insurance Card:**
   - Click "Choose File"
   - Select any PDF or image file
   - Should upload and show success message

8. **Generate AOB:**
   - Click "Generate & Sign AOB"
   - Click "OK" to confirm
   - Should see: "AOB generated and signed successfully!"

## Expected Behavior

**Success flow:**
1. File is selected → JavaScript calls `uploadPatientFile()`
2. Request sent to `?action=patient.upload` with file data
3. Backend processes upload and updates database
4. Backend returns JSON: `{"ok":true,"path":"...","name":"...","mime":"..."}`
5. JavaScript parses JSON successfully
6. Shows success alert
7. Page reloads to show updated file status

**If you still get an error:**
1. Open browser console (F12)
2. Look for the error message that says "Server response was not JSON:"
3. You'll see the actual HTML that was returned
4. Copy that and send it - that will tell us what's wrong

## Verification

After uploading files, verify they're in the database:

```bash
docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect -e "
SELECT
    id,
    CONCAT(first_name, ' ', last_name) as name,
    id_card_path,
    ins_card_path,
    aob_path
FROM patients
WHERE id = '37a48e443174cee3ee4e454d4c83bb04';
" 2>/dev/null
```

Should show file paths populated!

## Additional Improvements Made

Added better error handling in JavaScript:
- Now catches JSON parse errors
- Logs actual server response to console
- Shows more helpful error messages
- Includes full error details in console

This will help debug any future issues!

---

**Status:** ✅ Fixed
**Testing:** Ready - try uploading files now!
**Next:** If uploads work, try creating an order!

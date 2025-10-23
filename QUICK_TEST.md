# 🚀 Quick Order Creation Test

## TL;DR - Test in 5 Minutes

### 1. Log In
```
URL:      http://localhost:8000/portal
Email:    sparkingmatt@gmail.com
Password: TempPassword123!
```

### 2. Create Patient (Click "New Order")
```
First Name: Test
Last Name:  Patient
DOB:        1980-01-01
Phone:      5555555555
Email:      test@example.com
Address:    123 Test St
City:       Dallas
State:      TX
ZIP:        75201
```
Click "Save Patient & Use"

### 3. Upload Files (BEFORE creating order!)

**This is in the order dialog - scroll down:**

**Upload ID Card:**
- Create test file: `echo "Test ID" > /tmp/id.txt`
- Click "Upload ID" → Select `/tmp/id.txt`

**Upload Insurance:**
- Create test file: `echo "Test Insurance" > /tmp/ins.txt`
- Click "Upload Insurance" → Select `/tmp/ins.txt`

**Generate AOB:**
- Click "Generate & Sign AOB" button
- Click "Sign AOB" in popup

### 4. Fill Order Form

**Product:**
```
Product:  CollaHeal™ Sheet 2x2
Payment:  Insurance
```

**Clinical Info (ALL REQUIRED):**
```
Primary ICD-10:      L97.412
Wound Length (cm):   3.5
Wound Width (cm):    2.0
Wound Type:          Diabetic ulcer
Date of Last Eval:   2025-10-22
Frequency/week:      3
Qty per Change:      1
Duration (days):     30
```

**E-Signature:**
```
Name:  Dr. Test
☑ Check the acknowledgment box
```

### 5. Submit & Verify

Click "Submit Order"

**Check it saved:**
```bash
docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect \
  -e "SELECT id, product, status, created_at FROM orders ORDER BY created_at DESC LIMIT 1;"
```

**Should show:**
```
id      | product            | status    | created_at
--------|--------------------|-----------|-----------------
abc123  | CollaHeal™ Sheet  | submitted | 2025-10-22 ...
```

✅ **If you see the order = SUCCESS!**

❌ **If not, check:**
```bash
tail -50 /tmp/php-server.log
```

---

## Common Errors & Fixes

### "Patient ID and Insurance Card must be on file"
→ Upload ID card AND insurance card first

### "Assignment of Benefits (AOB) must be signed"
→ Click "Generate & Sign AOB" button

### "Primary diagnosis (ICD-10) is required"
→ Fill in the ICD-10 code field

### "Wound length and width are required"
→ Fill in both wound measurements

### "E-signature name is required"
→ Fill in signature name AND check the box

---

## Debug Commands

```bash
# Check services
docker ps | grep collagen
ps aux | grep "php -S"

# Check database connection
curl http://localhost:8000/api/db.php

# Check if cpt column exists (the fix)
docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect \
  -e "DESCRIBE orders" | grep cpt

# Count orders
docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect \
  -e "SELECT COUNT(*) FROM orders;"

# View recent orders
docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect \
  -e "SELECT * FROM orders ORDER BY created_at DESC LIMIT 3\G"

# Check upload directories exist
ls -la uploads/ids/
ls -la uploads/insurance/
ls -la uploads/aob/
```

---

## What Your Friend's System Does

✅ Patient files (ID + Insurance + AOB) stored with PATIENT
✅ Visit notes stored with ORDER
✅ Orders save to database (bug fixed)
✅ Patients are fully editable
✅ Admin can generate PDF of order (needs Dompdf)

**The bug:** Missing `cpt` column → NOW FIXED
**The fix:** Added column via SQL_FIXES.sql

---

**Need full details?** See TESTING_GUIDE.md
**Need verification?** See VERIFICATION_SUMMARY.md

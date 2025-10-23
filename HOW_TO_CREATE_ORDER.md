# How to Create an Order - Step by Step

## ✅ Patient is Ready!

Your patient "Your Mom" (ID: 37a48e443174cee3ee4e454d4c83bb04) now has:
- ✓ ID Card uploaded
- ✓ Insurance Card uploaded
- ✓ AOB signed

**You can now create an insurance order!**

---

## 📝 Step-by-Step Order Creation

### Step 1: Log In
```
URL:      http://localhost:8000/portal
Email:    sparkingmatt@gmail.com
Password: TempPassword123!
```

### Step 2: Start New Order
Click the **"New Order"** button (top right or in navigation)

### Step 3: Select Patient
In the patient search box, type "Your Mom" or "Mom"
- The patient should appear in the dropdown
- Click to select
- You should see confirmation that ID, Insurance, and AOB are on file ✓

### Step 4: Fill Product Section
```
Product:      CollaHeal™ Sheet 2x2  (or any product)
Payment Type: Insurance  (or Cash if you want to skip file validation)
```

### Step 5: Fill Clinical Information (ALL REQUIRED!)

**Diagnoses:**
```
Primary ICD-10:       L97.412
  (This is "Non-pressure chronic ulcer of left heel and midfoot")
Secondary ICD-10:     [Leave empty or add E11.621 for diabetic foot ulcer]
```

**Wound Measurements:**
```
Wound Length (cm):    3.5
Wound Width (cm):     2.0
Wound Depth (cm):     0.5   [Optional but recommended]
```

**Wound Classification:**
```
Wound Type:           Diabetic ulcer
Wound Stage:          III   [Or leave blank if not applicable]
```

**Wound Location:**
```
Location:             Foot — Plantar
Laterality:           Left   [Or specify in the text box]
```

**Assessment:**
```
Date of Last Evaluation:  2025-10-22   [Today's date or recent]
```

### Step 6: Fill Treatment Plan

```
Start Date:           2025-10-22
Frequency (per week): 3
Qty per Change:       1
Duration (days):      30
Refills Allowed:      0
Additional Instructions: Apply to clean, dry wound. Change dressing 3x weekly.
```

### Step 7: Clinical Notes (Optional but Recommended)

**Option A - Paste Text:**
```
Paste in the text box:

Patient presents with chronic diabetic foot ulcer on left plantar surface.
Wound measures 3.5cm x 2.0cm x 0.5cm.
Wound bed is granulating with minimal exudate.
Periwound skin intact without signs of infection.
Ordered collagen sheet dressing for advanced wound healing.
Patient educated on proper foot care and dressing change technique.
```

**Option B - Upload File:**
- Click "Choose File" under Visit Notes
- Upload a PDF or text file with clinical notes

### Step 8: Delivery Information

**Option A - Deliver to Patient (Default):**
- Select "Patient" radio button
- Address auto-fills from patient record
- Verify the address is correct

**Option B - Deliver to Office:**
- Select "Office" radio button
- Fill in:
  ```
  Recipient:  Medical Practice
  Phone:      5555551234
  Address:    456 Medical Plaza
  City:       Dallas
  State:      TX
  ZIP:        75201
  ```

### Step 9: E-Signature (REQUIRED!)

```
Signature Name:  Dr. Matthew User
Title:          MD   [Or PA-C, NP, etc.]

☑ Check the box: "I certify medical necessity and authorize this order (e-signature)."
```

**⚠️ CRITICAL:** You MUST check this box or the order will fail!

### Step 10: Submit

Click **"Submit Order"** button

---

## ✅ Success Indicators

After clicking Submit, you should see:

1. **Success message** - Order submitted successfully
2. **Order ID** - Displayed in confirmation
3. **Order appears in Orders list** - Go to Orders tab to verify

---

## 🔍 Verify Order Saved to Database

Run this command to confirm:

```bash
docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect -e "
SELECT
    o.id,
    CONCAT(p.first_name, ' ', p.last_name) as patient,
    o.product,
    o.status,
    o.icd10_primary,
    o.wound_length_cm,
    o.wound_width_cm,
    o.cpt,
    o.created_at
FROM orders o
JOIN patients p ON p.id = o.patient_id
ORDER BY o.created_at DESC
LIMIT 3;
"
```

**Expected output:**
```
id      | patient  | product           | status    | icd10_primary | wound_length_cm | wound_width_cm | cpt   | created_at
--------|----------|-------------------|-----------|---------------|-----------------|----------------|-------|------------------
abc123  | Your Mom | CollaHeal™ Sheet | submitted | L97.412       | 3.50            | 2.00           | A6021 | 2025-10-22 18:30
```

If you see your order = **SUCCESS!** 🎉

---

## ❌ Troubleshooting

### Error: "Patient ID and Insurance Card must be on file"

**Check files exist:**
```bash
php upload-patient-files.php
```

**Or manually verify:**
```bash
docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect -e "
SELECT
    CONCAT(first_name, ' ', last_name) as name,
    id_card_path IS NOT NULL as has_id,
    ins_card_path IS NOT NULL as has_ins,
    aob_path IS NOT NULL as has_aob
FROM patients
WHERE id = '37a48e443174cee3ee4e454d4c83bb04';
"
```

All should show `1` (true)

### Error: "Primary diagnosis (ICD-10) is required"

Fill in the ICD-10 Primary field with a valid code (e.g., `L97.412`)

### Error: "Wound length and width are required"

Fill in both wound length AND width in centimeters

### Error: "Date of last evaluation is required"

Select a date in the "Date of Last Evaluation" field

### Error: "Frequency per week is required"

Enter a number in "Frequency (per week)" field (must be > 0)

### Error: "E-signature name is required"

1. Fill in the signature name field
2. **AND** check the acknowledgment checkbox

### Error: "Please acknowledge the e-signature statement"

Check the checkbox below the signature fields!

### Error: "Provider NPI is required"

Your account should have NPI = 1234567890. Verify:
```bash
docker exec -i collagen-mysql mysql -uroot -proot frxnaisp_collagendirect -e "
SELECT email, npi FROM users WHERE email = 'sparkingmatt@gmail.com';
"
```

### Order submits but doesn't appear in database

Check PHP error log:
```bash
tail -50 /tmp/php-server.log
```

Look for SQL errors or exceptions

---

## 🎯 Minimum Required Fields Checklist

Before submitting, make sure you have:

- [ ] Patient selected (with ID + Insurance + AOB for insurance orders)
- [ ] Product selected
- [ ] Payment type selected
- [ ] **ICD-10 Primary** ✓
- [ ] **Wound Length (cm)** ✓
- [ ] **Wound Width (cm)** ✓
- [ ] **Date of Last Evaluation** ✓
- [ ] **Frequency per week** ✓
- [ ] **E-signature Name** ✓
- [ ] **E-signature checkbox checked** ✓

---

## 💡 Pro Tips

1. **Save time:** Use "Cash" payment type to skip ID/Insurance validation
2. **Valid ICD-10 codes:** L97.xxx for chronic ulcers, E11.621 for diabetic foot
3. **Wound measurements:** Can be approximate for testing (e.g., 3.5 x 2.0)
4. **Clinical notes:** Can be brief for testing, just paste a sentence
5. **Check logs:** If it fails, check `/tmp/php-server.log` for exact error

---

## 🚀 Quick Test Order (Copy & Paste Values)

Use these values for a quick test:

```
Patient:        Your Mom (select from dropdown)
Product:        CollaHeal™ Sheet 2x2
Payment:        Insurance
ICD-10 Primary: L97.412
Wound Length:   3.5
Wound Width:    2.0
Wound Type:     Diabetic ulcer
Last Eval Date: 2025-10-22
Start Date:     2025-10-22
Frequency:      3
Qty per Change: 1
Duration:       30
Signature:      Dr. Matthew User
Title:          MD
[✓] Check box
```

Then click Submit!

---

**Good luck!** If you get an error, paste it here and I'll help debug. 🚀

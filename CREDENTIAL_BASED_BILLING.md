# Credential-Based Billing for Wound Photo Reviews

**Date:** 2025-11-03
**Status:** ✅ IMPLEMENTED

## Overview

The wound photo billing system now supports multiple provider credential types (MD, DO, NP, PA, RN) with appropriate reimbursement rates and compliance controls.

## Supported Credentials

### Medical Doctors (MD) and Osteopaths (DO)
- **Can bill E/M codes:** ✅ Yes
- **Reimbursement rate:** 100% of Medicare rate
- **Requirements:** Valid NPI number
- **Example charges:**
  - 99213 (improving/stable): $92.00
  - 99214 (concern): $130.00
  - 99215 (urgent): $180.00

### Nurse Practitioners (NP)
- **Can bill E/M codes:** ✅ Yes
- **Reimbursement rate:** 85% of Medicare rate (can be 100% with direct credentialing)
- **Requirements:**
  - Own NPI number
  - State licensure
  - Credentialed with payers (varies by state)
- **Example charges (85% rate):**
  - 99213 (improving/stable): $78.20
  - 99214 (concern): $110.50
  - 99215 (urgent): $153.00

**State Variations:**
- Most states: NPs can bill independently
- Some states: Require collaboration agreement with physician
- Check state regulations for practice authority

### Physician Assistants (PA)
- **Can bill E/M codes:** ✅ Yes
- **Reimbursement rate:** 85% of Medicare rate
- **Requirements:**
  - Own NPI number
  - Supervising physician required (all states)
  - `supervising_physician_id` must be set in database
- **Example charges (85% rate):**
  - 99213 (improving/stable): $78.20
  - 99214 (concern): $110.50
  - 99215 (urgent): $153.00

**Billing Methods for PAs:**
1. **Direct billing (85%)**: Bill under PA's NPI
2. **Incident to (100%)**: Bill under supervising physician's NPI (requires physician direct supervision)

**Note:** For telehealth wound photo reviews, "incident to" typically doesn't apply since physician isn't on-site.

### Registered Nurses (RN)
- **Can bill E/M codes:** ❌ **NO**
- **System behavior:** System blocks E/M billing attempts by RNs
- **Error message:** "Registered Nurses (RN) cannot bill E/M codes for wound photo reviews. Only MD, DO, NP, or PA credentials are eligible."

**What RNs CAN bill (alternative codes):**
- CPT 99211: Nurse visit (minimal, ~$25-40) - NOT reimbursed for telehealth by Medicare
- HCPCS G2012: Brief communication (5-10 min, ~$14)

**Recommended workflow for RN staff:**
- RN can perform triage and initial review
- Forward to MD/DO/NP/PA for billable assessment
- Bill under supervising provider's NPI ("incident to")

## Database Schema

### Users Table
```sql
ALTER TABLE users
  ADD COLUMN credential_type VARCHAR(10) DEFAULT 'MD'
  CHECK (credential_type IN ('MD', 'DO', 'NP', 'PA', 'RN', 'OTHER'));

ALTER TABLE users
  ADD COLUMN supervising_physician_id VARCHAR(64)
  REFERENCES users(id) ON DELETE SET NULL;
```

### Example Data
```sql
-- Medical Doctor
UPDATE users
SET credential_type = 'MD', npi = '1234567890'
WHERE email = 'dr.smith@example.com';

-- Physician Assistant with supervision
UPDATE users
SET credential_type = 'PA',
    npi = '0987654321',
    supervising_physician_id = 'dr_smith_user_id'
WHERE email = 'pa.jones@example.com';

-- Nurse Practitioner (independent)
UPDATE users
SET credential_type = 'NP', npi = '1122334455'
WHERE email = 'np.williams@example.com';

-- Registered Nurse (cannot bill E/M)
UPDATE users
SET credential_type = 'RN'
WHERE email = 'rn.davis@example.com';
```

## Billing Logic Flow

### 1. Photo Review Submission

When a provider reviews a wound photo:

```php
// Get provider's credential type
$userStmt = $pdo->prepare("SELECT credential_type FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$credentialType = $userStmt->fetch()['credential_type'] ?? 'MD';

// Block RNs from billing E/M codes
if ($credentialType === 'RN') {
  jerr('Registered Nurses (RN) cannot bill E/M codes...');
}

// Calculate charges based on credential type
$billing = getBillingInfo($assessment, $credentialType);
// Returns: ['cpt_code', 'charge_amount', 'reimbursement_rate', ...]
```

### 2. Reimbursement Calculation

```php
function getBillingInfo(string $assessment, string $credentialType = 'MD'): array {
  // Base rates (100%)
  $baseRates = [
    '99213' => 92.00,  // Level 3
    '99214' => 130.00, // Level 4
    '99215' => 180.00  // Level 5
  ];

  // Apply credential-based rate
  $reimbursementRate = 1.0; // MD/DO

  if ($credentialType === 'NP' || $credentialType === 'PA') {
    $reimbursementRate = 0.85; // 85% for mid-levels
  }

  $chargeAmount = round($baseRates[$cptCode] * $reimbursementRate, 2);

  return [
    'cpt_code' => $cptCode,
    'charge_amount' => $chargeAmount,
    'reimbursement_rate' => $reimbursementRate,
    'credential_type' => $credentialType
  ];
}
```

### 3. Billing Export

CSV export now includes provider credential type (30 fields total):

| Field | Example | Notes |
|-------|---------|-------|
| Provider Last Name | Smith | |
| Provider First Name | John | |
| **Provider Credential** | **MD** | **NEW: MD/DO/NP/PA** |
| Provider NPI | 1234567890 | |
| CPT Code | 99214 | |
| Charge Amount | 130.00 | Auto-calculated based on credential |

This helps billing systems identify credential type and verify reimbursement rates.

## Compliance Requirements

### For All Mid-Level Providers (NP/PA)

1. **NPI Registration:**
   - Must have individual NPI (Type 1)
   - Cannot bill under physician's NPI unless "incident to"

2. **Payer Credentialing:**
   - Each insurance payer requires separate credentialing
   - Process can take 60-120 days
   - Some payers reimburse at 100%, others at 85%

3. **State Licensure:**
   - Must be licensed in state where patient is located (telehealth rules)
   - Some states accept compact licensure (eNLC for NPs)

### For Physician Assistants (PA)

4. **Supervising Physician:**
   - Required in all 50 states
   - Supervision level varies by state:
     - Some: General supervision (physician available by phone)
     - Some: On-site supervision required
   - Set `supervising_physician_id` in database for audit trail

5. **Collaborative Agreement:**
   - Written agreement with supervising physician
   - May need to specify scope of practice
   - Store copy for compliance audits

### For Nurse Practitioners (NP)

6. **Full Practice Authority:**
   - 26 states allow independent practice
   - 24 states require physician collaboration/supervision
   - Check state requirements at: https://www.aanp.org/advocacy/state/state-practice-environment

7. **Direct Credentialing:**
   - If directly credentialed with payer → 100% reimbursement
   - If billing through physician practice → typically 85%

## CCM Billing Conflicts

### Same Provider, Same Patient
**Question:** Can the same provider bill both CCM and E/M for the same patient?

**Answer:** ❌ **Generally NO**

- CCM reimburses for ongoing care management (~$40-70/month)
- Cannot also bill E/M codes for same time/same patient
- Would constitute double billing

**Implementation:**
```php
// Check for CCM conflict before billing E/M
$ccmCheck = $pdo->prepare("
  SELECT COUNT(*) FROM ccm_billing
  WHERE patient_id = ? AND physician_id = ? AND billing_month = ?
");
$ccmCheck->execute([$patientId, $physicianId, date('Y-m')]);

if ($ccmCheck->fetchColumn() > 0) {
  jerr('Cannot bill E/M - already billing CCM for this patient this month');
}
```

### Different Providers, Same Patient
**Question:** Can Dr. A bill CCM while Dr. B bills E/M for the same patient?

**Answer:** ✅ **YES** (with proper documentation)

**Example Scenario:**
- Dr. A (SNF physician): Bills CCM for chronic disease management
- Dr. B (wound care specialist): Bills E/M for wound photo reviews

**Requirements:**
1. **Different specialties** or **different problems addressed**
2. **Clear documentation** in clinical notes:
   ```
   "Patient receiving CCM from Dr. A (PCP) for chronic disease management
   (diabetes, hypertension). This E/M is for distinct acute wound care
   evaluation and treatment planning."
   ```
3. **Different NPIs** in billing records
4. **No time overlap** between services

**Payer Rules:**
- Medicare: Allows when services are distinct
- Commercial payers: Varies - check contract terms

## Setting Up Provider Credentials

### Step 1: Run Migration

Access: `https://collagendirect.health/admin/run-all-migrations.php`

Or run directly: `https://collagendirect.health/admin/add-credential-type.php`

This creates:
- `credential_type` column in users table
- `supervising_physician_id` column for PA supervision tracking
- Constraints to ensure valid credential types

### Step 2: Update User Profiles

**Via SQL:**
```sql
-- Update individual provider
UPDATE users
SET credential_type = 'NP', npi = '1234567890'
WHERE email = 'provider@example.com';

-- Update PA with supervising physician
UPDATE users
SET credential_type = 'PA',
    npi = '0987654321',
    supervising_physician_id = (
      SELECT id FROM users WHERE email = 'supervisor@example.com'
    )
WHERE email = 'pa@example.com';
```

**Via Admin Interface (future enhancement):**
- Add credential dropdown to user profile edit page
- Add supervising physician selector for PAs
- Show reimbursement rate preview

### Step 3: Verify Billing Configuration

Test with sample wound photo review:
1. Log in as NP/PA provider
2. Review wound photo
3. Check billable encounter:
   - Should show reduced charge (85% for NP/PA)
   - Should include credential_type in billing record
4. Export billing CSV
   - Verify "Provider Credential" column shows correct value

## Troubleshooting

### Error: "Registered Nurses (RN) cannot bill E/M codes..."

**Cause:** User has `credential_type = 'RN'` in database

**Solutions:**
1. **If provider is actually NP:** Update credential_type to 'NP'
   ```sql
   UPDATE users SET credential_type = 'NP' WHERE id = 'user_id';
   ```

2. **If provider is actually RN:**
   - RN cannot perform billable wound photo reviews
   - Assign reviews to MD/DO/NP/PA providers instead
   - Or: Have RN do initial triage, then escalate to billable provider

### Charge Amount Doesn't Match Expected Rate

**Check:**
1. Provider's `credential_type` in database:
   ```sql
   SELECT credential_type FROM users WHERE id = 'user_id';
   ```

2. Expected rates:
   - MD/DO: 100% ($92, $130, $180)
   - NP/PA: 85% ($78.20, $110.50, $153.00)

3. If incorrect, update credential_type and re-review photos

### PA Supervision Not Showing in Export

**Check:**
```sql
SELECT
  u.email,
  u.credential_type,
  u.supervising_physician_id,
  s.email as supervisor_email
FROM users u
LEFT JOIN users s ON s.id = u.supervising_physician_id
WHERE u.credential_type = 'PA';
```

If `supervising_physician_id` is NULL, update it:
```sql
UPDATE users
SET supervising_physician_id = 'supervisor_user_id'
WHERE credential_type = 'PA' AND email = 'pa@example.com';
```

## Future Enhancements

### 1. Incident-To Billing Option
Allow PAs to bill at 100% rate under supervising physician's NPI:
- Add `bill_incident_to` boolean flag
- When true: Use supervisor's NPI instead of PA's NPI
- Show 100% charge amount
- Requires: Physician direct supervision documentation

### 2. Payer-Specific Reimbursement Rates
Different payers reimburse at different rates:
- Create `payer_reimbursement_rates` table
- Link patients to payers
- Calculate charge based on patient's payer + provider credential

### 3. Collaborative Agreement Tracking
For states requiring physician collaboration:
- Upload collaborative agreement documents
- Track expiration dates
- Alert when agreements need renewal

### 4. State Licensure Validation
- Track provider licensure by state
- Validate provider can bill for patient's state (telehealth)
- Alert for cross-state billing compliance issues

## Summary

✅ **Implemented:**
- Credential type tracking (MD, DO, NP, PA, RN)
- Automatic reimbursement rate calculation (100% vs 85%)
- RN billing prevention with clear error messages
- Supervising physician tracking for PAs
- Credential type in billing export (30-field CSV)

📋 **Billing Rules:**
- **MD/DO**: 100% reimbursement, independent billing
- **NP**: 85% reimbursement (or 100% with direct credentialing), mostly independent
- **PA**: 85% reimbursement, requires supervising physician
- **RN**: Cannot bill E/M codes for wound photo reviews

🚫 **CCM Conflicts:**
- Same provider + same patient = Cannot bill both CCM and E/M
- Different providers + same patient = Can bill separately (with documentation)

📊 **High-Volume Compliance:**
For practices doing many reviews per day:
1. Use credential-based auto-calculations
2. Export includes all compliance data
3. Built-in checks prevent RN billing errors
4. Clear audit trail with provider credentials in every encounter

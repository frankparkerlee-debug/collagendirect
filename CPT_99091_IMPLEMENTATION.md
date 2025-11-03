# CPT 99091 Implementation Guide

**Date:** 2025-11-03
**Status:** 📋 ANALYSIS ONLY (Not Recommended)

## Overview

CPT 99091 is an alternative to E/M codes for billing wound photo reviews. However, it generates **significantly less revenue** and has **complex time tracking requirements**.

## Revenue Comparison

### High-Volume Patient (4 photos/month)

**E/M Codes (Current):**
- Review 1: 99213 = $92
- Review 2: 99213 = $92
- Review 3: 99213 = $92
- Review 4: 99213 = $92
- **Monthly total: $368**

**CPT 99091:**
- 4 reviews = 34 min aggregate
- Bill once: $60-80
- **Monthly total: $60-80**

**Lost revenue: -$288 to -$308 per patient per month (-78% to -84%)**

### Low-Volume Patient (1 photo/month)

**E/M Codes:**
- Review 1: 99213 = $92
- **Monthly total: $92**

**CPT 99091:**
- 1 review = 8 min
- Cannot bill (need 30 min minimum)
- **Monthly total: $0**

**Lost revenue: -$92 per patient (-100%)**

## Billing Rules for 99091

### Frequency Limits

✅ **Can bill:** Once per calendar month per patient
❌ **Cannot bill:** Multiple times per month

### Time Requirements

✅ **Minimum:** 30 minutes aggregate time per month
- Includes ALL time reviewing patient data that month
- Review photos, document findings, communicate with patient
- Time must be documented in clinical notes

❌ **Cannot bill if:**
- Less than 30 minutes total time in the month
- Already billed another time-based code that month (CCM, RPM, etc.)

### Documentation Requirements

**Must document:**
1. **Date and time** of each review session
2. **Cumulative time** spent on patient data that month
3. **What data was reviewed** (wound photos, patient notes, etc.)
4. **Clinical decisions made** based on data review

**Example Note:**
```
May 2025 - CPT 99091 Time Log for Patient John Doe

5/5/25 - 10:15 AM - Reviewed wound photo #1 (right heel)
         Analysis of wound progression, comparison to baseline
         Time: 9 minutes

5/12/25 - 2:30 PM - Reviewed wound photo #2 (right heel)
          Assessment of healing progress, no change in treatment
          Time: 8 minutes

5/19/25 - 11:45 AM - Reviewed wound photo #3 (right heel)
          Noted improvement, continued current regimen
          Time: 7 minutes

5/26/25 - 3:20 PM - Reviewed wound photo #4 (right heel)
          Final assessment for month, wound healing well
          Time: 10 minutes

TOTAL TIME FOR MAY 2025: 34 minutes

Billing: CPT 99091 x 1
```

## Conflicts with Other Codes

### Cannot Bill 99091 Same Month As:

❌ **Chronic Care Management (CCM)** - 99490, 99439, 99491, 99487, 99489
- Both reimburse for ongoing care coordination
- Choose one or the other per patient per month

❌ **Remote Physiologic Monitoring (RPM)** - 99453, 99454, 99457, 99458
- 99091 is often considered part of RPM service bundle
- Many payers won't reimburse separately

❌ **Principal Care Management (PCM)** - 99424, 99425, 99426, 99427
- Similar ongoing care management service

### CAN Bill 99091 Same Month As:

✅ **E/M codes** (99213-99215) - But only for DIFFERENT encounters
- Example: In-person visit (99214) + Remote data review (99091) = OK
- Cannot use E/M code for the same wound photo review time counted toward 99091

✅ **Preventive visits** - 99385-99387, 99395-99397

✅ **Procedures** - As long as not related to the physiologic data being monitored

## Implementation Requirements

### Database Changes

Add time tracking to billable_encounters table:

```sql
-- Track cumulative time for 99091
ALTER TABLE billable_encounters
  ADD COLUMN review_time_minutes INT DEFAULT 0;

-- Track 99091 billing status
CREATE TABLE cpt_99091_billing (
  id VARCHAR(64) PRIMARY KEY,
  patient_id VARCHAR(64) NOT NULL REFERENCES patients(id),
  physician_id VARCHAR(64) NOT NULL REFERENCES users(id),
  billing_month VARCHAR(7) NOT NULL, -- YYYY-MM format
  total_time_minutes INT NOT NULL,
  encounter_count INT NOT NULL,
  charge_amount NUMERIC(10,2) NOT NULL,
  status VARCHAR(20) DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT NOW(),
  exported BOOLEAN DEFAULT FALSE,
  UNIQUE(patient_id, physician_id, billing_month)
);
```

### Workflow Changes

**Current (E/M codes):**
1. Doctor reviews photo → Immediate billing ($92-180)
2. Export encounters monthly

**New (99091):**
1. Doctor reviews photo → **Track time only** (no billing yet)
2. **End of month:** Aggregate time per patient
3. **If ≥30 min:** Create 99091 billing record
4. **If <30 min:** No billing for that patient that month
5. Export 99091 records

### Code Changes Required

**1. Update review_wound_photo action:**
```php
if ($action === 'review_wound_photo') {
  // ... existing validation ...

  // Track review time (don't create billable encounter yet)
  $reviewTime = (int)($_POST['review_time_minutes'] ?? 5);

  $stmt = $pdo->prepare("
    INSERT INTO wound_photo_reviews (
      id, patient_id, physician_id, wound_photo_id,
      assessment, notes, review_time_minutes, review_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
  ");
  $stmt->execute([
    bin2hex(random_bytes(16)),
    $photo['patient_id'],
    $userId,
    $photoId,
    $assessment,
    $notes,
    $reviewTime
  ]);

  jok(['message' => 'Review saved. Time tracked for monthly 99091 billing.']);
}
```

**2. Create monthly aggregation script:**
```php
if ($action === 'generate_99091_billing') {
  $month = $_GET['month'] ?? date('Y-m');

  // Get all reviews for this physician this month
  $sql = "
    SELECT patient_id, SUM(review_time_minutes) as total_time, COUNT(*) as review_count
    FROM wound_photo_reviews
    WHERE physician_id = ?
      AND DATE_TRUNC('month', review_date) = ?
      AND billed_99091 = FALSE
    GROUP BY patient_id
    HAVING SUM(review_time_minutes) >= 30
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([$userId, $month . '-01']);

  $eligiblePatients = $stmt->fetchAll();

  foreach ($eligiblePatients as $p) {
    // Create 99091 billing record
    $pdo->prepare("
      INSERT INTO cpt_99091_billing (
        id, patient_id, physician_id, billing_month,
        total_time_minutes, encounter_count, charge_amount
      ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ")->execute([
      bin2hex(random_bytes(16)),
      $p['patient_id'],
      $userId,
      $month,
      $p['total_time'],
      $p['review_count'],
      75.00 // 99091 charge
    ]);

    // Mark reviews as billed
    $pdo->prepare("
      UPDATE wound_photo_reviews
      SET billed_99091 = TRUE
      WHERE patient_id = ? AND physician_id = ?
        AND DATE_TRUNC('month', review_date) = ?
    ")->execute([$p['patient_id'], $userId, $month . '-01']);
  }

  jok(['patients_billed' => count($eligiblePatients)]);
}
```

**3. Update billing export:**
```php
// Export 99091 records instead of individual E/M encounters
$sql = "
  SELECT
    billing_month,
    patient_id,
    total_time_minutes,
    encounter_count,
    charge_amount
  FROM cpt_99091_billing
  WHERE physician_id = ? AND exported = FALSE
";
```

## User Interface Changes

### Photo Review Page

**Current:**
```
[Review wound photo]
Assessment: [Improving/Stable/Concern/Urgent]
Notes: [text area]
[Submit Review] → Immediate billing: $92-180
```

**With 99091:**
```
[Review wound photo]
Assessment: [Improving/Stable/Concern/Urgent]
Review time: [5 minutes] (required for 99091)
Notes: [text area]
[Submit Review] → Time tracked. Billing at month end.

Current month totals for this patient:
- Reviews: 3
- Total time: 24 minutes
- Status: Need 6 more minutes to bill 99091 this month
```

### Monthly Billing Dashboard

Add new section:
```
99091 Billing Summary - May 2025

Patients with ≥30 min (Billable):
- John Doe: 34 min (4 reviews) → $75
- Jane Smith: 45 min (5 reviews) → $75
- Bob Jones: 31 min (3 reviews) → $75
Total: 3 patients × $75 = $225

Patients with <30 min (Not billable):
- Alice Brown: 18 min (2 reviews) → $0
- Charlie Davis: 12 min (1 review) → $0
Lost revenue: ~$184 (2 patients couldn't reach threshold)

[Generate 99091 Billing Records]
```

## Pros and Cons

### Pros of 99091

✅ **More compliant for asynchronous reviews**
- Designed for store-and-forward telehealth
- No modifier 95 confusion
- Clear time documentation requirements

✅ **Simpler documentation**
- Don't need full MDM elements for each review
- Just track time and what was reviewed

✅ **Lower audit risk**
- Well-established code for remote monitoring
- Payers understand the use case

### Cons of 99091

❌ **Massive revenue loss: -78% to -84%**
- 4 photos/month: $368 (E/M) vs $75 (99091)
- 100 patients × 4 photos = $36,800/mo (E/M) vs $7,500/mo (99091)

❌ **Complex time tracking**
- Must track every minute spent
- Must aggregate monthly per patient
- Can't bill if <30 min threshold not met

❌ **Delayed billing**
- E/M: Bill immediately after each review
- 99091: Wait until end of month, aggregate, then bill

❌ **All-or-nothing per patient**
- Patient sends 3 photos (27 min) = $0 revenue
- E/M would have generated $276

❌ **Conflicts with CCM/RPM programs**
- Can't bill 99091 if already billing CCM
- Limits service offerings

## Recommendation

### For High-Volume Wound Care Practice:

**Use E/M codes (99213-99215) with these compliance fixes:**

1. ✅ **Switch to MDM-based documentation** (not time-based)
   - Avoids "3 minutes vs 30 minutes" problem
   - Already implemented in your system

2. ✅ **Remove modifier 95 OR verify payer acceptance**
   - Some payers accept 95 for store-and-forward
   - Others require synchronous video
   - Check each payer contract

3. ✅ **Use credential-based billing** (already implemented)
   - MD/DO: 100% rate
   - NP/PA: 85% rate
   - Proper reimbursement calculation

4. ✅ **Add duplicate prevention** (same patient, same day)
   - Already recommended in compliance review

**Revenue outcome:**
- 100 patients × 4 photos/month = **$36,800/month**
- vs 99091: **$7,500/month**
- **Difference: $29,300/month ($351,600/year)**

### Only Use 99091 If:

- Payers explicitly reject E/M codes for asynchronous wound reviews
- You're already running CCM/RPM programs (consolidate billing)
- Very low review frequency (<1 per month per patient)

## Conclusion

**CPT 99091 is NOT recommended for your high-volume wound photo review business.**

The 78-84% revenue loss and complex time tracking requirements outweigh the compliance benefits. Instead, focus on:

1. **MDM-based E/M billing** (not time-based)
2. **Proper modifier usage** (verify payer rules for modifier 95)
3. **Credential-based rates** (already implemented)
4. **Strong documentation** (AI-generated MDM notes already working)

This gives you **strong compliance + high revenue** without the limitations of 99091.

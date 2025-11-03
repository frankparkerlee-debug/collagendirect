# E/M Code Compliance Review - Wound Photo Telehealth

## Overview

This document reviews compliance requirements, potential conflicts, and billing best practices for using E/M codes 99213-99215 with modifier 95 for asynchronous wound photo reviews.

## Current Implementation

### CPT Codes Used

| Code | Description | Charge | When Used |
|------|-------------|--------|-----------|
| 99213-95 | Office/Outpatient E/M, Level 3 (Telehealth) | $92 | Improving, Stable |
| 99214-95 | Office/Outpatient E/M, Level 4 (Telehealth) | $135 | Concern |
| 99215-95 | Office/Outpatient E/M, Level 5 (Telehealth) | $180 | Urgent |

### Modifier
- **95**: Synchronous telemedicine service rendered via real-time interactive audio and video telecommunications system

## ⚠️ CRITICAL COMPLIANCE ISSUES

### 1. Modifier 95 Misuse - **HIGH RISK**

**Problem**: We're using modifier **95** which is for **SYNCHRONOUS** (real-time) telehealth, but wound photo reviews are **ASYNCHRONOUS** (store-and-forward).

**Why This Matters**:
- CMS defines modifier 95 as requiring "real-time interactive audio and video"
- Our wound photo reviews are asynchronous - patient uploads photo, physician reviews later
- This is a **modifier mismatch** that could trigger claim denials or audits

**Correct Approach**:
There is **NO approved modifier** for asynchronous telehealth under current CMS rules for E/M codes.

**Options**:
1. **Use G-codes instead** (telemedicine-specific codes)
2. **Require live video review** with patient to use modifier 95 legitimately
3. **Bill as in-office visit** if part of established care plan (risky)
4. **Use 99091** for remote image review (different reimbursement)

---

### 2. Time-Based vs. MDM Documentation

**Current Issue**:
- E/M codes 99213-99215 can be billed based on either:
  - **Time** (total time on date of encounter), OR
  - **Medical Decision Making (MDM)** complexity

**What We're Doing**:
- Auto-generating notes that claim "3 minutes" of review time
- Assigning code based on assessment (improving/stable/concern/urgent)

**Compliance Problem**:
- **3 minutes is NOT sufficient** for any of these codes
- 99213 requires 20-29 minutes
- 99214 requires 30-39 minutes
- 99215 requires 40-54 minutes

**If Audited**: Claims could be downcoded or denied for insufficient time documentation.

**Solution**: Must bill based on **MDM complexity**, not time. Our notes should:
- ✅ Remove time statement or increase to realistic time
- ✅ Focus on MDM elements (diagnosis, treatment options, risk)
- ✅ Document complexity appropriately for each level

---

### 3. Place of Service Code

**Current**: Using **02** (Telehealth)

**Issue**: Place of Service 02 is specifically for telehealth provided to patients in their home. This is correct IF:
- Patient is at home when photo taken
- Service is delivered via telehealth platform
- Complies with state telehealth laws

**Alternative**: **11** (Office) if considered part of ongoing office care

**Recommendation**: Keep POS 02, but ensure telehealth consent is documented

---

## POTENTIAL BILLING CONFLICTS

### 1. Same-Day Service Conflicts

**Cannot Bill Both**:
- E/M visit (99213-99215) AND other E/M on same day for same patient
- Exception: Different providers, different specialties, modifier 25

**Scenario**:
- Patient has office visit morning (99214)
- Uploads wound photo same day
- Wound photo review (99213) → **DENIED** as duplicate

**Solution**:
- Only bill wound photo review if NO other E/M same day
- Or use **modifier 25** if wound review is "separately identifiable" (risky)
- Better: Build into existing visit workflow

### 2. Frequency Limits

**Medicare Rules**:
- E/M codes generally limited to **1 per day** per provider
- Multiple E/Ms same day require exceptional documentation

**Our System**: Could theoretically review multiple wound photos same day

**Risk**: If physician reviews 3 wound photos in one day, billing 3x E/M codes will likely trigger audit

**Solution**:
- Limit to 1 wound photo review per patient per day
- Batch reviews on different days
- Consider alternative codes (see below)

### 3. Global Period Conflicts

**Surgical Global Periods**:
- Minor procedures: 10-day global period
- Major procedures: 90-day global period

**Issue**: If patient had wound-related surgery, E/M during global period is **included** in surgical fee

**Example**:
- Patient has debridement (11042) on 11/01
- Wound photo review on 11/10
- E/M 99213 → **DENIED** (within 10-day global)

**Solution**:
- Check if patient has recent procedures
- Use modifier 24 if truly unrelated to procedure
- Consider if wound care is included in global package

### 4. Chronic Care Management (CCM) Overlap

**If Practice Bills CCM (99490)**:
- CCM covers 20+ minutes of non-face-to-face care per month
- Wound photo review could be considered part of CCM
- Cannot bill both for same service

**Solution**:
- Choose either CCM or E/M for wound reviews
- Document wound review as separate from CCM activities
- Use different provider for each

### 5. Remote Physiologic Monitoring (RPM) Conflict

**If Using RPM Codes (99453, 99454, 99457)**:
- Wound measurements could qualify as "physiologic data"
- Cannot bill E/M for same monitoring activity

**Solution**: Pick one approach - either RPM or E/M, not both for wound care

---

## ALTERNATIVE BILLING CODES

### Option 1: CPT 99091 (Preferred for Async)

**Code**: 99091 - Collection and interpretation of physiologic data

**Advantages**:
- ✅ Specifically designed for asynchronous data review
- ✅ Includes review of patient-provided photos/data
- ✅ No modifier confusion
- ✅ Can be billed separately from E/M visits

**Requirements**:
- At least 30 minutes of physician/QHP time per month
- Documented review of patient-transmitted data
- Clinical decision-making and report to patient

**Reimbursement**: ~$60-80 (lower than E/M but more defensible)

**How to Use**:
- Collect wound photos throughout month
- Monthly batch review (30+ min total)
- Single 99091 code per patient per month
- Document time and medical decisions

---

### Option 2: HCPCS G-Codes for Telehealth

**G2010**: Remote evaluation of recorded video/images

**Advantages**:
- ✅ Specifically for store-and-forward telehealth
- ✅ Designed for async image review
- ✅ Clear compliance path

**Requirements**:
- 5-10 minutes of physician review
- Established patient relationship
- Clinical decision and communication to patient

**Reimbursement**: ~$20-30 (much lower, but compliant)

**Note**: Not all payers recognize G-codes

---

### Option 3: Make It Synchronous (Use 95 Correctly)

**Change Workflow**:
1. Patient uploads wound photo
2. **Schedule brief live video call** to review photo together
3. Use real-time video to discuss wound, treatment plan
4. Bill 99213-95 appropriately

**Advantages**:
- ✅ Modifier 95 is now appropriate
- ✅ Meets synchronous requirement
- ✅ Higher reimbursement justified

**Disadvantages**:
- Scheduling overhead
- Patient availability issues
- More physician time required

---

## DOCUMENTATION REQUIREMENTS

### For E/M Codes (99213-99215)

To support current billing, documentation must include:

#### Medical Decision Making (MDM) Elements

**1. Number/Complexity of Problems**:
- ✅ Already doing: Documenting wound location, type, assessment
- ⚠️ Need: Link to ICD-10 diagnosis with specificity

**2. Amount/Complexity of Data**:
- ✅ Already doing: Review of patient-submitted photo
- ⚠️ Need: Document review of prior photos for comparison
- ⚠️ Need: Note if reviewing medical records, lab results

**3. Risk of Complications**:
- ✅ Already doing: Assessment levels (stable/concern/urgent)
- ⚠️ Need: Explicit risk stratification statement

#### Required Elements in Note

✅ **Currently Have**:
- Patient demographics
- Date of service
- Clinical findings from photo
- Assessment
- Plan of care
- Provider signature

⚠️ **Need to Add**:
- **HPI (History of Present Illness)**: How long, symptoms, what makes better/worse
- **Pertinent history**: Diabetes, vascular disease, prior wounds
- **Differential diagnosis**: Why chose specific ICD-10 code
- **Risk discussion**: What could go wrong if not treated appropriately
- **Time statement OR MDM level**: Explicitly state which you're using

---

## RECOMMENDED CHANGES

### Immediate (High Priority)

1. **Change Modifier from 95 to No Modifier** (or switch to 99091)
   - Modifier 95 is technically incorrect for async reviews
   - Removes audit risk

2. **Remove "3 minutes" Time Statement**
   - Replace with: "Medical decision making: [Low/Moderate/High] complexity"
   - Or: Realistic time (20-40 min) if billing on time

3. **Enhance MDM Documentation**
   - Add risk stratification: "Risk of complications: [Low/Moderate/High]"
   - Add differential diagnosis consideration
   - Document review of prior photos/records

### Short-Term (1-2 months)

4. **Add Same-Day Service Check**
   - Alert if patient already has E/M code same day
   - Prevent duplicate billing

5. **Add Global Period Check**
   - Check if patient has recent procedure codes
   - Alert if within global period

6. **Consider Alternative Codes**
   - Evaluate 99091 for async reviews
   - Pilot G2010 with select payers
   - Consider making reviews synchronous

### Long-Term (Strategic)

7. **Payer Policy Review**
   - Check specific payer policies for telehealth wound care
   - Some payers may have specific telehealth wound codes
   - Consider participating in telehealth pilot programs

8. **State Telehealth Law Compliance**
   - Verify compliance with state-specific telehealth regulations
   - Ensure proper patient consent
   - Check if physician licensed in patient's state

---

## AUDIT RISK ASSESSMENT

### High Risk Areas

🔴 **Modifier 95 for Async Service**:
- Risk Level: **HIGH**
- Could trigger: Claim denials, payer audits
- Fix: Remove modifier or make synchronous

🔴 **Time Documentation (3 minutes)**:
- Risk Level: **HIGH**
- Could trigger: Downcoding, recoupment
- Fix: Use MDM instead of time

🟡 **Same-Day Service**:
- Risk Level: **MEDIUM**
- Could trigger: Duplicate service denials
- Fix: Add conflict checking

🟡 **Frequency**:
- Risk Level: **MEDIUM**
- Could trigger: Unusual billing pattern flags
- Fix: Limit frequency, add medical necessity

🟢 **Place of Service (02)**:
- Risk Level: **LOW**
- Generally acceptable for telehealth
- Ensure consent documented

---

## COMPLIANCE CHECKLIST

### Before Billing

- [ ] Is this an established patient? (New patient E/M codes different)
- [ ] Has patient consented to telehealth services?
- [ ] Is physician licensed in patient's state?
- [ ] Any other E/M services same day? (Check conflicts)
- [ ] Any recent procedures with global periods?
- [ ] Is medical necessity clearly documented?

### Documentation Review

- [ ] Diagnosis code supports service
- [ ] MDM complexity matches code level
- [ ] Risk assessment documented
- [ ] Treatment plan specified
- [ ] Patient communication documented
- [ ] Provider signature/credentials

### Billing Review

- [ ] Correct CPT code for service level
- [ ] Appropriate modifier (or no modifier)
- [ ] Correct place of service
- [ ] Diagnosis code supports medical necessity
- [ ] No conflicts with other billed services

---

## RECOMMENDATIONS SUMMARY

### Option A: Most Compliant (Lower Revenue)

**Use CPT 99091 instead of E/M codes**

✅ Pros:
- Designed for async review
- No modifier confusion
- Defensible in audit
- Can bill monthly batch

❌ Cons:
- Lower reimbursement (~$60-80 vs $92-180)
- Requires 30min aggregate time per month

**Best For**: Practices prioritizing compliance over revenue

---

### Option B: Moderate Compliance (Current Revenue)

**Keep E/M codes but fix documentation**

✅ Pros:
- Maintains current revenue
- Minimal workflow changes
- Widely recognized codes

❌ Cons:
- Still some modifier risk
- Requires enhanced documentation
- Need conflict checking

**Changes Needed**:
1. Remove modifier 95 OR make reviews synchronous
2. Change time to MDM-based justification
3. Add risk stratification
4. Check for same-day/global conflicts

**Best For**: Practices with good documentation discipline

---

### Option C: Highest Revenue (Most Risk)

**Make reviews synchronous to justify modifier 95**

✅ Pros:
- Modifier 95 now appropriate
- Full E/M reimbursement justified
- Better patient engagement

❌ Cons:
- Requires scheduling
- More physician time
- Workflow changes

**Best For**: Practices wanting premium telehealth billing

---

## NEXT STEPS

1. **Immediate**: Review current claims for audit risk
2. **Week 1**: Decide on billing approach (A, B, or C above)
3. **Week 2**: Update documentation templates
4. **Week 3**: Add compliance checking (same-day, global period)
5. **Ongoing**: Monitor payer responses and adjust

## Questions to Answer

1. **What's your risk tolerance?** (Compliant vs. Revenue)
2. **How many wound reviews per month?** (Volume affects approach)
3. **What payers are you billing?** (Medicare vs. commercial)
4. **Are you billing other telehealth services?** (CCM, RPM conflicts)
5. **Can you add synchronous component?** (Makes modifier 95 valid)

---

## Resources

- CMS Telehealth Services: https://www.cms.gov/medicare/coverage/telehealth
- AMA CPT Code Definitions: https://www.ama-assn.org/
- HIPAA Telehealth Guidance: https://www.hhs.gov/hipaa/for-professionals/special-topics/telehealth/
- State Telehealth Laws: https://www.cchpca.org/

---

**Disclaimer**: This is a compliance review, not legal advice. Consult with a healthcare attorney and/or certified coder before making billing changes. Billing practices vary by payer, state, and specific circumstances.

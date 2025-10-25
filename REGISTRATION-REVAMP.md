# Registration System Revamp Documentation

## Overview
The CollagenDirect registration system has been revamped to support four distinct user types with different capabilities and portal features.

## User Types

### 1a. Practice Manager / Admin (Non-DME Referrer)
**Description:** Manages practice details and can register multiple physicians. Refers patients to CollagenDirect for billing and fulfillment.

**Characteristics:**
- Can manage practice information (name, address, medical licenses, etc.)
- Has signatory capability for themselves
- Can add multiple physicians to their practice during registration
- No DME license required
- **Billing/Transaction tabs are hidden** (referral-only model)

**Registration Fields Required:**
- Email & Password
- Practice Information (name, address, city, state, zip, phone, tax ID optional)
- Personal Physician Credentials (first name, last name, NPI, medical license, state, expiry)
- Optional: Additional physicians (with "Add More" button)
- Agreements & E-signature

**Database Settings:**
- `user_type`: `practice_admin`
- `role`: `practice_admin`
- `account_type`: `referral`
- `is_referral_only`: `TRUE`
- `can_manage_physicians`: `TRUE`
- `has_dme_license`: `FALSE`

---

### 1b. Physician (Non-DME, Part of Practice)
**Description:** Physician who practices within a larger practice with multiple providers. Links to a Practice Manager account.

**Characteristics:**
- Limited to signing orders for their own patients only
- Must link to an existing Practice Manager via email
- No practice management capabilities
- No DME license required
- **Billing/Transaction tabs are hidden** (referral-only model)

**Registration Fields Required:**
- Email & Password
- Personal Physician Credentials (first name, last name, NPI, medical license, state, expiry)
- Practice Manager Email (to link accounts)
- Agreements & E-signature

**Database Settings:**
- `user_type`: `physician`
- `role`: `physician`
- `account_type`: `referral`
- `is_referral_only`: `TRUE`
- `can_manage_physicians`: `FALSE`
- `has_dme_license`: `FALSE`
- `parent_user_id`: Set to Practice Manager's user ID (if found)

---

### 2a. DME Hybrid Referrer
**Description:** Physicians or practices with a DME license who operate in a hybrid model - referring some patients (CollagenDirect bills) while direct billing others (practice bills and pays cash for product).

**Characteristics:**
- Holds a DME license
- Can choose on a case-by-case basis whether to refer or direct bill each patient
- Full practice management capabilities
- **Billing/Transaction tabs are visible**
- Can purchase products wholesale for direct-billed patients

**Registration Fields Required:**
- Email & Password
- Practice Information (name, address, city, state, zip, phone, tax ID optional)
- Personal Physician Credentials (first name, last name, NPI, medical license, state, expiry)
- DME License Information (DME license #, state, expiry)
- Agreements & E-signature

**Database Settings:**
- `user_type`: `dme_hybrid`
- `role`: `practice_admin`
- `account_type`: `hybrid`
- `is_referral_only`: `FALSE`
- `can_manage_physicians`: `TRUE`
- `has_dme_license`: `TRUE`
- `is_hybrid`: `TRUE`

**Portal Behavior:**
- When creating an order, user can select payment type (insurance/referral vs. direct bill)
- Billing and transaction tabs are shown
- Pricing may differ based on payment type selected

---

### 2b. DME Wholesale Only
**Description:** Physicians or practices with a DME license that exclusively direct bill and purchase products from CollagenDirect at wholesale prices.

**Characteristics:**
- Holds a DME license
- All patients are direct-billed by the practice
- Practice pays cash for products at wholesale prices
- Full practice management capabilities
- **Billing/Transaction tabs are visible**
- Portal used for compliance tracking and order management

**Registration Fields Required:**
- Email & Password
- Practice Information (name, address, city, state, zip, phone, tax ID optional)
- Personal Physician Credentials (first name, last name, NPI, medical license, state, expiry)
- DME License Information (DME license #, state, expiry)
- Agreements & E-signature

**Database Settings:**
- `user_type`: `dme_wholesale`
- `role`: `practice_admin`
- `account_type`: `wholesale`
- `is_referral_only`: `FALSE`
- `can_manage_physicians`: `TRUE`
- `has_dme_license`: `TRUE`
- `is_hybrid`: `FALSE`

**Portal Behavior:**
- All orders are direct-billed
- Wholesale pricing applied
- Billing and transaction tabs are shown
- Practice manages all billing and payments

---

## Registration Flow

### User Experience
1. **User Type Selection:** Intuitive card-based selection showing 4 options with clear descriptions
2. **Dynamic Form:** Form fields appear/hide based on selected user type
3. **Conditional Validation:** Required fields change based on user type
4. **Mobile Friendly:** Responsive design that works on all devices
5. **Add More Physicians:** Practice Managers can add multiple physicians during registration

### Technical Implementation

#### Frontend ([/register](register))
- Clean, modern card-based UI
- Conditional field display using JavaScript
- NPI validation with checksum algorithm
- Client-side validation before submission
- CSRF protection
- Responsive grid layout (2 columns on desktop, 1 on mobile)

#### Backend ([/api/register.php](api/register.php))
- Validates user type and required fields
- Sets appropriate database flags based on user type
- Links physicians to practice managers via email lookup
- Stores additional physicians in `practice_physicians` table
- Returns appropriate error messages

#### Database Schema
New columns added to `users` table:
- `user_type` - The registration type selected
- `parent_user_id` - Links physician to their practice manager
- `has_dme_license` - Boolean flag
- `is_hybrid` - Boolean flag for hybrid DME users
- `is_referral_only` - Boolean flag to hide billing features
- `can_manage_physicians` - Boolean flag for admin capabilities
- `address`, `city`, `state`, `zip`, `tax_id` - Practice detail fields

New table `practice_physicians`:
- Stores additional physicians added by practice managers during registration
- Links to practice manager via `practice_manager_id`

---

## Portal Feature Control

### Billing & Transaction Tab Visibility

**Hidden For:**
- Practice Manager / Admin (1a) - `is_referral_only = TRUE`
- Physician (1b) - `is_referral_only = TRUE`

**Visible For:**
- DME Hybrid Referrer (2a) - `is_referral_only = FALSE`
- DME Wholesale Only (2b) - `is_referral_only = FALSE`

**Implementation:**
The portal uses the `$isReferralOnly` variable (set from `users.is_referral_only`) to conditionally show/hide navigation tabs and page content.

See [portal/index.php](portal/index.php) lines 1514-1523 for navigation logic.

---

## Migration

A migration script has been created to update the database schema: [migrate-registration-revamp.php](migrate-registration-revamp.php)

**What it does:**
1. Adds new columns to `users` table
2. Creates `practice_physicians` table
3. Creates appropriate indexes
4. Migrates existing user data to new structure

**How to run:**
```bash
php migrate-registration-revamp.php
```

The migration is idempotent and safe to run multiple times.

---

## Testing Checklist

### Registration Testing
- [ ] Test Practice Manager registration with no additional physicians
- [ ] Test Practice Manager registration with 2+ additional physicians
- [ ] Test Physician registration and verify practice manager link
- [ ] Test DME Hybrid registration with valid DME license
- [ ] Test DME Wholesale registration with valid DME license
- [ ] Test mobile responsiveness on phone/tablet
- [ ] Verify email validation
- [ ] Verify NPI validation
- [ ] Verify all required field validations
- [ ] Test agreement acceptance requirement

### Portal Feature Testing
- [ ] Verify Practice Manager can see dashboard but NOT billing tabs
- [ ] Verify Physician can see dashboard but NOT billing tabs
- [ ] Verify DME Hybrid can see billing AND transaction tabs
- [ ] Verify DME Wholesale can see billing AND transaction tabs
- [ ] Verify DME Hybrid can select payment type when creating orders
- [ ] Verify practice manager can access admin features

---

## API Endpoints

### POST `/api/register.php`

**Request Body:**
```json
{
  "userType": "practice_admin|physician|dme_hybrid|dme_wholesale",
  "email": "user@example.com",
  "password": "securepass123",
  "firstName": "John",
  "lastName": "Doe",
  "npi": "1234567890",
  "license": "MD12345",
  "licenseState": "CA",
  "licenseExpiry": "2025-12-31",
  "practiceName": "ABC Medical Group",
  "address": "123 Main St",
  "city": "Los Angeles",
  "state": "CA",
  "zip": "90001",
  "phone": "5555551234",
  "taxId": "12-3456789",
  "dmeNumber": "DME12345",
  "dmeState": "CA",
  "dmeExpiry": "2025-12-31",
  "practiceManagerEmail": "manager@practice.com",
  "additionalPhysicians": [
    {
      "firstName": "Jane",
      "lastName": "Smith",
      "npi": "9876543210",
      "license": "MD67890",
      "licenseState": "CA",
      "licenseExpiry": "2026-06-30",
      "email": "jane.smith@practice.com",
      "phone": "5555555678"
    }
  ],
  "agreeMSA": true,
  "agreeBAA": true,
  "signName": "John Doe",
  "signTitle": "MD",
  "signDate": "2024-10-24"
}
```

**Response (Success):**
```json
{
  "ok": true,
  "message": "Registration successful"
}
```

**Response (Error):**
```json
{
  "error": "Error message describing what went wrong"
}
```

---

## Frequently Asked Questions

### Q: What happens when a Physician registers but the Practice Manager email doesn't exist?
**A:** The physician account is still created, but `parent_user_id` will be NULL. The practice manager can manually link the physician later through the admin interface.

### Q: Can a Practice Manager add physicians after registration?
**A:** Yes, this can be done through the admin interface (future enhancement).

### Q: Can a user change their user type after registration?
**A:** This requires manual intervention by a superadmin. User types should be carefully selected during registration.

### Q: Do DME Hybrid users see different pricing?
**A:** Yes, pricing can be controlled based on the `payment_type` selected for each order. Direct-billed orders may use wholesale pricing.

### Q: What if a practice has both DME and non-DME physicians?
**A:** The practice should register as a DME Hybrid user. Individual physicians can be managed through the portal.

---

## Support

For questions or issues with the registration system, contact:
- Technical: development@collagendirect.com
- Clinical: clinical@collagendirect.com

---

**Last Updated:** October 24, 2024
**Version:** 2.0
**Author:** Claude (Anthropic)

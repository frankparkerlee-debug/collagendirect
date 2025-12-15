# CollagenDirect External Integrations Documentation

**Generated:** 2025-12-15
**Purpose:** Document all external services, APIs, and third-party integrations for preservation.

---

## Table of Contents

1. [Integration Summary](#integration-summary)
2. [Email Services](#email-services)
3. [SMS Services](#sms-services)
4. [AI Services](#ai-services)
5. [Maps & Address Validation](#maps--address-validation)
6. [Medical Coding APIs](#medical-coding-apis)
7. [Shipping & Logistics](#shipping--logistics)
8. [Document Processing](#document-processing)
9. [Environment Variables Reference](#environment-variables-reference)
10. [Vendor Dependencies](#vendor-dependencies)

---

## Integration Summary

| Service | Category | Status | Primary File |
|---------|----------|--------|--------------|
| SMTP (Namecheap) | Email | Active | `/api/lib/email_sender.php` |
| SendGrid | Email | Active | `/api/lib/mailer_sendgrid.php` |
| Twilio | SMS | Active | `/api/lib/twilio_sms.php` |
| Claude (Anthropic) | AI/ML | Active | `/api/lib/ai_service.php` |
| Google Places | Maps | Active | `/api/portal/address-search.php` |
| NLM ICD-10 | Medical | Active | `/api/lib/icd10_api.php` |
| CMS NPI Registry | Medical | Active | `/api/portal/npi-search.php` |
| UPS/FedEx/USPS | Shipping | Tracking URLs only | `/admin/lib/shipping.php` |

---

## Email Services

### SMTP Email (Primary)

**Service:** Namecheap Private Email / Custom SMTP
**File:** `/api/lib/email_sender.php`
**Dependency:** PHPMailer (`vendor/phpmailer/phpmailer`)

**Configuration:**
```env
SMTP_HOST=mail.privateemail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER=no-reply@collagendirect.health
SMTP_PASS=[password]
SMTP_FROM=no-reply@collagendirect.health
SMTP_FROM_NAME=CollagenDirect
```

**Functions:**
- `send_email($toEmail, $toName, $subject, $html, $text)` - Main sender
- `send_email_smtp(...)` - PHPMailer wrapper
- `email_template($title, $bodyContent)` - HTML template generator

**Features:**
- TLS encryption on port 587
- Auto-generated plain text fallback
- UTF-8 character encoding
- SSL verification options for cPanel hosts

---

### SendGrid Email (Fallback/Templates)

**Service:** SendGrid API
**File:** `/api/lib/mailer_sendgrid.php`
**API Endpoint:** `https://api.sendgrid.com/v3/mail/send`

**Configuration:**
```env
SENDGRID_API_KEY=SG.xxxxx
SG_TMPL_PASSWORD_RESET=d-41ea629107c54e0abc1dcbd654c9e498
SG_TMPL_ACCOUNT_CONFIRM=d-c33b0338c94544bda58c885892ce2f53
SG_TMPL_PHYSACCOUNT_CONFIRM=d-12d5c5a34f5f4fe19424db7d88f44ab5
SG_TMPL_ORDER_RECEIVED=d-32c6aee2093b4363b10a5ab4f23c9230
SG_TMPL_ORDER_APPROVED=d-e73bec2b87bf45ba9108eb9c1fcf850b
SG_TMPL_ORDER_SHIPPED=d-0b24b64993e149329a7d0702b0db4c65
SG_TMPL_MANUFACTURER_ORDER=d-67cf6288aacd45b9a55a8d84fe0d2917
```

**Functions:**
- `send_email_sendgrid($toEmail, $toName, $subject, $html, $text)` - Direct API
- `sendgrid_send_template($templateId, $toEmail, $toName, $dynamicData)` - Templates

**Authentication:** Bearer token in Authorization header

---

## SMS Services

### Twilio SMS

**Service:** Twilio Messaging API
**Files:** `/api/lib/twilio_sms.php`, `/api/lib/twilio_helper.php`
**Dependency:** Twilio SDK (`vendor/twilio/sdk`)

**Configuration:**
```env
TWILIO_ACCOUNT_SID=ACxxxx
TWILIO_AUTH_TOKEN=xxxx
TWILIO_PHONE_NUMBER=+18884156880
```

**API Endpoints:**
- `https://api.twilio.com/2010-04-01/Accounts/{sid}/Messages.json` - Send SMS
- `https://api.twilio.com/2010-04-01/Accounts/{sid}/Messages/{sid}.json` - Status

**Functions:**
- `twilio_send_sms($toPhone, $message)` - Core sender
- `normalize_phone_number($phone)` - E.164 format conversion
- `send_delivery_confirmation_sms(...)` - Delivery confirmation
- `twilio_check_sms_status($messageSid)` - Check delivery status

**Webhook Endpoints:**
- `/api/twilio/delivery-confirmation-reply.php` - SMS replies
- `/api/twilio/receive-mms.php` - MMS media (wound photos)

**Features:**
- E.164 phone number normalization
- Delivery status tracking
- MMS media download support
- Rate limiting (36ms between messages)

---

## AI Services

### Anthropic Claude API

**Service:** Anthropic Claude (Text & Vision)
**File:** `/api/lib/ai_service.php`
**API Endpoint:** `https://api.anthropic.com/v1/messages`

**Configuration:**
```env
ANTHROPIC_API_KEY=sk-ant-xxxxx
```

**Model:** `claude-sonnet-4-5-20250929`

**Class:** `AIService`

**Methods:**
| Method | Purpose | Max Tokens |
|--------|---------|------------|
| `analyzeOrder()` | Order completeness analysis | 2048 |
| `generateResponseMessage()` | Physician communication | 2048 |
| `generateMedicalNecessityLetter()` | Insurance letter | 2048 |
| `generateVisitNote()` | Clinical documentation | 4096 |
| `generateApprovalScore()` | Patient profile scoring | 3072 |
| `extractTextFromImage()` | OCR from images | 4096 |
| `extractTextFromPDF()` | OCR from PDFs | 4096 |
| `callClaudeAPI()` | Raw API wrapper | configurable |

**Authentication:** `x-api-key` header

**Use Cases:**
1. **Approval Scoring:** RED/YELLOW/GREEN color-coded patient analysis
2. **Clinical Documentation:** Visit notes, medical necessity letters
3. **Order Analysis:** Completeness scoring, missing info detection
4. **Document OCR:** Insurance card text extraction
5. **Communication:** Professional message generation

**Response Format:** JSON with markdown fence handling

---

## Maps & Address Validation

### Google Places API

**Service:** Google Maps Platform
**Files:** `/api/portal/address-search.php`, `/api/portal/address-details.php`

**Configuration:**
```env
GOOGLE_PLACES_API_KEY=AIza_xxxxx
```

**API Endpoints:**
- `https://maps.googleapis.com/maps/api/place/autocomplete/json`
- `https://maps.googleapis.com/maps/api/place/details/json`

**Functions:**
- Address autocomplete with US-only restriction
- Place ID to full address resolution
- Structured address parsing (street, city, state, zip)

**Request Parameters:**
```
?input={query}&types=address&components=country:us&key={API_KEY}
```

**Response Format:**
```json
{
  "predictions": [
    {
      "description": "123 Main St, City, ST 12345",
      "place_id": "ChIJ...",
      "structured_formatting": {
        "main_text": "123 Main St",
        "secondary_text": "City, ST, USA"
      }
    }
  ]
}
```

---

## Medical Coding APIs

### NIH ICD-10-CM Lookup

**Service:** NIH/NLM Clinical Tables API (Free, Public)
**File:** `/api/lib/icd10_api.php`
**API Endpoint:** `https://clinicaltables.nlm.nih.gov/api/icd10cm/v3/search`

**No authentication required**

**Function:** `icd10_search(string $term, int $maxResults = 10)`

**Request Parameters:**
```
?sf=code,name&df=code,name&terms={query}&maxList={limit}
```

**Response Format:**
```json
[
  3,  // Total count
  ["L97.421", "L97.422", "L97.429"],  // Codes
  null,
  [
    ["L97.421", "Non-pressure chronic ulcer of right heel..."],
    ["L97.422", "Non-pressure chronic ulcer of left heel..."],
    ["L97.429", "Non-pressure chronic ulcer of unspecified heel..."]
  ]
]
```

---

### CMS NPI Registry

**Service:** Centers for Medicare & Medicaid Services NPPES
**File:** `/api/portal/npi-search.php`
**API Endpoint:** `https://npiregistry.cms.hhs.gov/api/`

**No authentication required**

**Search Methods:**
- NPI number lookup (10 digits)
- Name-based search
- Organization search
- State/specialty filters

**Request Parameters:**
```
?version=2.1&number={npi}&limit=10
```

**Use Cases:**
- Validate physician credentials during registration
- Verify NPI numbers on orders
- Provider lookup for referrals

---

## Shipping & Logistics

### Carrier Detection & Tracking

**File:** `/admin/lib/shipping.php`

**Functions:**
- `detect_carrier($tracking)` - Identify carrier from tracking number
- `tracking_url($tracking, $carrier)` - Generate tracking URL

**Supported Carriers:**

| Carrier | Pattern | Tracking URL |
|---------|---------|--------------|
| UPS | 1Z + 16 alphanumeric | `https://www.ups.com/track?tracknum={tracking}` |
| FedEx | 12, 15, 20, or 22 digits | `https://www.fedex.com/fedextrack/?trknbr={tracking}` |
| USPS | 20-22 digits or 92-95 prefix | `https://tools.usps.com/go/TrackConfirmAction?qtc_tLabels1={tracking}` |

**Fallback:** Google search for unrecognized patterns

**Note:** No live tracking API integration - URL generation only

---

## Document Processing

### Insurance Card OCR

**File:** `/api/insurance-ocr.php`
**Primary Service:** Anthropic Claude Vision

**Configuration:**
```env
INSURANCE_OCR_ENABLED=1
INSURANCE_OCR_PROVIDER=anthropic
GOOGLE_CLOUD_VISION_API_KEY=xxxxx  # Optional fallback
```

**Providers (in order):**
1. Anthropic Claude Vision (primary)
2. Google Cloud Vision (fallback)
3. Tesseract CLI (local fallback)

**Extracted Fields:**
- Insurance provider name
- Member ID
- Group ID
- Payer phone number
- Plan type (PPO, HMO, etc.)
- Confidence score

**Supported Image Types:** JPEG, PNG, GIF, WebP

---

## Environment Variables Reference

### Complete List

```env
# Database
DATABASE_URL=postgresql://user:pass@host:5432/db
DB_HOST=localhost
DB_PORT=5432
DB_NAME=collagen_db
DB_USER=postgres
DB_PASS=password

# Email (SMTP)
SMTP_HOST=mail.privateemail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER=no-reply@collagendirect.health
SMTP_PASS=[password]
SMTP_FROM=no-reply@collagendirect.health
SMTP_FROM_NAME=CollagenDirect

# Email (SendGrid)
SENDGRID_API_KEY=SG.xxxxx
SG_TMPL_PASSWORD_RESET=d-xxxx
SG_TMPL_ACCOUNT_CONFIRM=d-xxxx
SG_TMPL_PHYSACCOUNT_CONFIRM=d-xxxx
SG_TMPL_ORDER_RECEIVED=d-xxxx
SG_TMPL_ORDER_APPROVED=d-xxxx
SG_TMPL_ORDER_SHIPPED=d-xxxx
SG_TMPL_MANUFACTURER_ORDER=d-xxxx

# SMS (Twilio)
TWILIO_ACCOUNT_SID=ACxxxx
TWILIO_AUTH_TOKEN=xxxx
TWILIO_PHONE_NUMBER=+18884156880

# AI (Anthropic)
ANTHROPIC_API_KEY=sk-ant-xxxxx

# OCR
INSURANCE_OCR_ENABLED=1
INSURANCE_OCR_PROVIDER=anthropic
GOOGLE_CLOUD_VISION_API_KEY=xxxxx

# Maps
GOOGLE_PLACES_API_KEY=AIza_xxxxx

# Shipping (Optional)
USPS_USERID=xxxxxxx
```

### Environment Loader

**File:** `/api/lib/env.php`
**Function:** `env(string $key, string $default = '')`

**Features:**
- Reads from `.env` file in `/api/` directory
- Falls back to system environment variables
- Handles quoted values
- Strips UTF-8 BOM
- Ignores comment lines (#)
- Static caching for performance

---

## Vendor Dependencies

### Composer Packages

```json
{
  "require": {
    "twilio/sdk": "^8.8",
    "phpmailer/phpmailer": "^6.9"
  }
}
```

### Installation

```bash
cd /path/to/CollagenDirect
composer install
```

### Autoloading

```php
require_once __DIR__ . '/../../vendor/autoload.php';
```

---

## Integration Testing

### Test Endpoints

| Endpoint | Purpose |
|----------|---------|
| `/api/test-email-config.php` | Test SMTP configuration |
| `/api/admin/test_ai_key.php` | Test Anthropic API key |
| `/api/check-google-key.php` | Test Google API key |
| `/admin/test-emails.php` | Test email delivery |

### Testing Checklist

- [ ] SMTP email sends successfully
- [ ] SendGrid templates render correctly
- [ ] Twilio SMS delivers to test number
- [ ] Claude API returns valid responses
- [ ] Google Places autocomplete works
- [ ] ICD-10 search returns results
- [ ] NPI lookup returns valid providers

---

## Security Considerations

### API Key Storage
- All keys stored in environment variables (not hardcoded)
- `.env` file excluded from version control
- Production keys separate from development

### Connection Security
- TLS encryption on all SMTP connections
- HTTPS for all external API calls
- SSL verification enabled on cURL operations

### Rate Limiting
- Twilio: 36ms minimum between SMS
- SendGrid: 1000-email batch limit
- All APIs: 10-30 second timeouts

---

## Reliability & Fallbacks

| Service | Primary | Fallback |
|---------|---------|----------|
| Email | SMTP | SendGrid |
| OCR | Claude Vision | Google Vision, Tesseract |
| Carrier Tracking | Pattern Match | Google Search |
| Address | Google Places | Manual Entry |

---

*This document should be updated when integrations are added or modified.*

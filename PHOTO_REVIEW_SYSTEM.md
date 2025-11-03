# Wound Photo Review & E/M Billing System

## Overview

This system enables physicians to generate billable E/M codes (Evaluation & Management) for wound photo reviews conducted via telehealth. Patients submit wound photos via SMS, physicians review them with a single click, and the system automatically generates CPT codes, clinical documentation, and billing reports.

**Revenue Potential**: $92-$180 per photo review (CPT 99213-99215 with modifier 95)

## System Architecture

### 1. Patient Photo Submission (SMS/MMS via Twilio)

**How it works:**
1. Physician requests wound photo for a patient
2. System sends SMS to patient's phone via Twilio
3. Patient replies with photo via MMS
4. Twilio webhook receives photo and saves to database
5. Physician sees new photo in "Photo Reviews" page

**Files:**
- [api/lib/twilio_helper.php](api/lib/twilio_helper.php) - Twilio API wrapper
- [api/twilio/receive-mms.php](api/twilio/receive-mms.php) - Webhook endpoint for incoming photos
- [TWILIO_SETUP.md](TWILIO_SETUP.md) - Complete setup instructions

### 2. Physician Review Interface

**How it works:**
1. Pending photos appear in photo grid
2. Physician clicks photo to open review modal
3. Physician selects one of 4 assessment buttons:
   - **Improving** → CPT 99213 ($92)
   - **Stable** → CPT 99213 ($92)
   - **Concern** → CPT 99214 ($130)
   - **Urgent** → CPT 99215 ($180)
4. System automatically:
   - Generates clinical note with template
   - Creates billable encounter record
   - Saves CPT code and charge amount
   - Marks photo as reviewed

**Files:**
- [portal/photo-reviews.php](portal/photo-reviews.php) - Review interface
- [portal/index.php](portal/index.php) lines 102-207 - Auto-billing logic

### 3. Billing & Documentation

**Auto-Generated Clinical Notes Include:**
- Patient demographics (name, DOB, MRN)
- Visit type (Telehealth - Asynchronous Photo Review)
- Assessment based on button selection
- Clinical plan and recommendations
- CPT code and modifier (99213-95, 99214-95, or 99215-95)
- Electronic signature with timestamp

**Billing Export:**
- Monthly CSV export of all encounters
- Includes: Date, Patient Name, MRN, CPT Code, Modifier, Charge, ICD-10
- Integrates with existing billing systems
- Tracks exported vs. unexported encounters

**Files:**
- [portal/index.php](portal/index.php) lines 900-993 - CSV export logic
- [portal/index.php](portal/index.php) lines 714-898 - Review submission logic

## Database Schema

### `photo_requests` Table
Tracks physician requests for patient photos.

```sql
CREATE TABLE photo_requests (
  id VARCHAR(64) PRIMARY KEY,
  patient_id VARCHAR(64) REFERENCES patients(id),
  physician_id VARCHAR(64) REFERENCES users(id),
  upload_token VARCHAR(64) UNIQUE,
  token_expires_at TIMESTAMP,
  wound_location VARCHAR(100),
  requested_at TIMESTAMP DEFAULT NOW(),
  completed BOOLEAN DEFAULT FALSE,
  sms_sent BOOLEAN DEFAULT FALSE
);
```

### `wound_photos` Table
Stores uploaded wound photos and review status.

```sql
CREATE TABLE wound_photos (
  id VARCHAR(64) PRIMARY KEY,
  patient_id VARCHAR(64) REFERENCES patients(id),
  photo_path VARCHAR(500) NOT NULL,
  uploaded_via VARCHAR(20), -- 'sms', 'email_link', 'portal'
  patient_notes TEXT,
  uploaded_at TIMESTAMP DEFAULT NOW(),
  reviewed BOOLEAN DEFAULT FALSE,
  reviewed_at TIMESTAMP,
  reviewed_by VARCHAR(64) REFERENCES users(id),
  billed BOOLEAN DEFAULT FALSE
);
```

### `billable_encounters` Table
Records all billable photo reviews with CPT codes.

```sql
CREATE TABLE billable_encounters (
  id VARCHAR(64) PRIMARY KEY,
  patient_id VARCHAR(64) REFERENCES patients(id),
  physician_id VARCHAR(64) REFERENCES users(id),
  wound_photo_id VARCHAR(64) REFERENCES wound_photos(id),
  encounter_date DATE DEFAULT CURRENT_DATE,
  assessment VARCHAR(20), -- 'improving', 'stable', 'concern', 'urgent'
  cpt_code VARCHAR(10) DEFAULT '99213',
  modifier VARCHAR(10) DEFAULT '95',
  charge_amount DECIMAL(10,2),
  icd10_codes TEXT[],
  clinical_note TEXT,
  exported BOOLEAN DEFAULT FALSE,
  exported_at TIMESTAMP,
  created_at TIMESTAMP DEFAULT NOW()
);
```

## API Endpoints

### Request Photo from Patient
**Endpoint**: `?action=request_wound_photo`
**Method**: POST
**Parameters**:
- `patient_id` - Patient UUID
- `wound_location` - Optional location description

**Response**:
```json
{
  "ok": true,
  "message": "Photo request sent via SMS",
  "request_id": "abc123..."
}
```

### Get Pending Photos
**Endpoint**: `?action=get_pending_photos`
**Method**: GET

**Response**:
```json
{
  "ok": true,
  "count": 5,
  "photos": [
    {
      "id": "photo123",
      "patient_id": "patient456",
      "first_name": "John",
      "last_name": "Doe",
      "dob": "1960-05-15",
      "mrn": "12345",
      "photo_path": "/uploads/wound_photos/wound-20250102-143022-a1b2c3d4.jpg",
      "uploaded_at": "2025-01-02 14:30:22",
      "uploaded_via": "sms",
      "wound_location": "Left heel"
    }
  ]
}
```

### Submit Photo Review
**Endpoint**: `?action=review_wound_photo`
**Method**: POST
**Parameters**:
- `photo_id` - Wound photo UUID
- `assessment` - One of: 'improving', 'stable', 'concern', 'urgent'
- `notes` - Optional additional physician notes

**Response**:
```json
{
  "ok": true,
  "message": "Review saved",
  "cpt_code": "99213",
  "billed": 92.00
}
```

### Get Billing Summary
**Endpoint**: `?action=get_billing_summary&month=2025-01`
**Method**: GET

**Response**:
```json
{
  "ok": true,
  "summary": {
    "total_encounters": 23,
    "total_charges": 2530.00,
    "exported_count": 15,
    "unexported_count": 8
  }
}
```

### Export Billing CSV
**Endpoint**: `?action=export_billing&month=2025-01`
**Method**: GET
**Response**: CSV file download

**CSV Format**:
```csv
Date,Patient Name,MRN,CPT Code,Modifier,Charge,ICD-10,Note ID
2025-01-15,John Doe,12345,99213,95,92.00,L89.622,encounter789
2025-01-15,Jane Smith,67890,99214,95,130.00,L97.422,encounter790
```

## Billable CPT Codes

### E/M Codes for Telehealth
All reviews use modifier **95** (Synchronous Telemedicine Service)

| CPT Code | Level | Charge | When to Use |
|----------|-------|--------|-------------|
| 99213 | Level 3 | $92 | Stable or improving wounds, routine assessment |
| 99214 | Level 4 | $130 | Concerning features, possible infection, requires closer monitoring |
| 99215 | Level 5 | $180 | Urgent concerns, significant deterioration, immediate action needed |

### Documentation Requirements (Auto-Generated)

Each review automatically generates a clinical note meeting E/M documentation requirements:

1. **Patient Identification**: Name, DOB, MRN
2. **Visit Type**: Telehealth - Asynchronous Wound Photo Review
3. **Clinical Assessment**: Template-based assessment matching severity level
4. **Plan**: Wound care recommendations and follow-up instructions
5. **Medical Decision Making**: Documented complexity level justifying CPT code
6. **Electronic Signature**: Physician name and timestamp

## Setup Instructions

### 1. Run Database Migration

```bash
php admin/add-wound-photo-tables.php
```

This creates the 3 required tables: `photo_requests`, `wound_photos`, `billable_encounters`

### 2. Configure Twilio (Required for SMS)

Follow instructions in [TWILIO_SETUP.md](TWILIO_SETUP.md):

1. Create Twilio account
2. Purchase phone number
3. Add credentials to `.env` file
4. Configure webhook URL

**Cost**: ~$15-30/month depending on usage

### 3. Configure Upload Directory

Ensure `/uploads/wound_photos/` directory exists and is writable:

```bash
mkdir -p uploads/wound_photos
chmod 755 uploads/wound_photos
```

### 4. Add Navigation Link

Already added to physician portal sidebar:
- [portal/index.php](portal/index.php) lines 3347-3350

## User Workflow

### For Physicians

1. **Request Photo**:
   - Go to patient detail page
   - Click "Request Wound Photo"
   - Optional: Specify wound location
   - System sends SMS to patient

2. **Review Photo**:
   - Navigate to "Photo Reviews" page
   - View all pending photos in grid
   - Click photo to open review modal
   - Review photo and select assessment
   - Optionally add clinical notes
   - Click assessment button (auto-saves)

3. **Export Billing**:
   - View billing summary dashboard
   - Click "Export CSV" button
   - Upload CSV to billing system

### For Patients

1. Receive SMS: "Hi [Name]! Please send a photo of your wound by replying to this message."
2. Reply to SMS with photo from phone camera
3. Receive confirmation: "Thanks! Your photo has been received and will be reviewed by your physician."

## Revenue Opportunities

### Monthly Revenue Potential

**Conservative Estimate** (10 photos/week):
- 40 reviews/month × $92 avg = **$3,680/month**

**Moderate Volume** (20 photos/week):
- 80 reviews/month × $92 avg = **$7,360/month**

**High Volume** (50 photos/week):
- 200 reviews/month × $100 avg = **$20,000/month**

### Cost Analysis

**Twilio SMS/MMS Costs**:
- Phone number: $1.15/month
- Outbound SMS: $0.0079 per message
- Inbound MMS: $0.02 per photo
- **Total**: ~$15-30/month for moderate volume

**Net Revenue**: $7,360 - $30 = **$7,330/month** (moderate volume)

## Compliance Notes

### HIPAA Compliance

- All photos stored on HIPAA-compliant server
- Twilio is HIPAA-compliant (BAA required)
- Photos transmitted via encrypted MMS
- Access restricted to authorized physicians
- Audit trail of all reviews maintained

### Medicare Documentation Requirements

The auto-generated clinical notes meet CMS requirements for:
- Patient identification
- Date and time of service
- Clinical assessment
- Medical decision making
- Treatment plan
- Physician signature

### ICD-10 Codes (Common for Wound Care)

- L89.xxx - Pressure ulcers
- L97.xxx - Chronic leg ulcers
- L98.xxx - Other skin disorders
- E11.621 - Diabetic foot ulcer
- I83.xxx - Varicose veins with ulcer

## Future Enhancements

### Planned Features

1. **AI Photo Analysis** (using Claude Vision API):
   - Auto-detect wound size and characteristics
   - Suggest appropriate CPT code based on complexity
   - Track healing progress over time
   - Flag concerning changes

2. **Patient Portal Upload**:
   - Alternative to SMS for tech-savvy patients
   - Mobile-responsive upload interface
   - Photo annotation tools

3. **Automated Scheduling**:
   - Weekly/biweekly photo request automation
   - Reminders for overdue photos
   - Follow-up scheduling based on assessment

4. **Enhanced Reporting**:
   - Revenue dashboards by physician
   - Patient compliance tracking
   - Wound healing trend analysis
   - Payer mix reports

5. **Integration with EHR**:
   - Sync encounters to electronic health records
   - Import patient demographics
   - Export clinical notes

## Troubleshooting

### Photos Not Appearing

1. Check Twilio webhook configuration
2. Verify patient phone number is valid E.164 format (+1XXXXXXXXXX)
3. Check `/uploads/wound_photos/` directory permissions
4. Review Twilio logs for delivery failures

### Billing Export Issues

1. Ensure encounters exist for selected month
2. Check database connection
3. Verify `exported` flag is updating correctly

### SMS Not Sending

1. Verify Twilio credentials in `.env` file
2. Check Twilio account balance
3. Ensure phone number is verified (trial accounts)
4. Review Twilio error logs

## Support

For issues or questions:
1. Check Twilio logs: https://console.twilio.com/
2. Review PHP error logs
3. Check database query logs
4. Contact Twilio support for SMS/MMS issues

## Files Reference

| File | Purpose |
|------|---------|
| `api/lib/twilio_helper.php` | Twilio API wrapper class |
| `api/twilio/receive-mms.php` | Webhook for incoming photos |
| `portal/photo-reviews.php` | Physician review interface |
| `portal/index.php` (lines 102-207) | Billing automation logic |
| `portal/index.php` (lines 600-1030) | API endpoints |
| `admin/add-wound-photo-tables.php` | Database migration |
| `TWILIO_SETUP.md` | Twilio configuration guide |

## Changelog

### 2025-01-02 - Initial Release

**Features Added**:
- SMS-based photo upload via Twilio
- Photo review interface with 4-button assessment
- Auto-generated clinical notes
- Automatic CPT code assignment
- Monthly billing CSV export
- Billing summary dashboard
- Database schema for photo tracking

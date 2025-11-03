# Wound Photo Billing Export Format

## Overview

The wound photo billing export generates a comprehensive CSV file that can be directly imported into most medical billing systems (Kareo, AdvancedMD, athenahealth, DrChrono, etc.) without additional formatting.

## File Format

**Filename**: `wound_telehealth_billing_YYYY-MM.csv`

**Encoding**: UTF-8 with BOM (Excel-compatible)

**Total Fields**: 29 columns

## Field Breakdown

### Patient Demographics (12 fields)

| Field | Format | Example | Notes |
|-------|--------|---------|-------|
| Service Date | MM/DD/YYYY | 11/03/2025 | Date of telehealth review |
| Patient Last Name | Text | Smith | |
| Patient First Name | Text | John | |
| Patient DOB | MM/DD/YYYY | 05/15/1960 | |
| Patient Sex | M/F/U | M | M=Male, F=Female, U=Unknown |
| MRN | Text | MRN-12345 | Medical Record Number; auto-generated if missing |
| Patient Phone | (XXX) XXX-XXXX | (555) 123-4567 | Formatted for readability |
| Patient Email | Email | patient@email.com | Optional |
| Patient Address | Text | 123 Main St | Street address |
| Patient City | Text | New York | |
| Patient State | AA | NY | Two-letter state code |
| Patient ZIP | XXXXX | 10001 | 5-digit ZIP code |

### Insurance Information (3 fields)

| Field | Format | Example | Notes |
|-------|--------|---------|-------|
| Insurance Company | Text | Blue Cross Blue Shield | Defaults to "Self Pay" if blank |
| Insurance ID | Text | ABC123456789 | Member/Policy ID |
| Group Number | Text | GRP456 | Group/Employer ID |

### Provider Information (3 fields)

| Field | Format | Example | Notes |
|-------|--------|---------|-------|
| Provider Last Name | Text | Johnson | Reviewing physician |
| Provider First Name | Text | Sarah | |
| Provider NPI | 10 digits | 1234567890 | National Provider Identifier |

### Billing Details (11 fields)

| Field | Format | Example | Notes |
|-------|--------|---------|-------|
| CPT Code | XXXXX | 99213 | E/M code (99213/99214/99215) |
| Modifier | XX | 95 | Always "95" for telehealth |
| Place of Service | XX | 02 | Always "02" for telehealth |
| Diagnosis Code 1 | ICD-10 | E11.621 | Primary diagnosis (auto-detected) |
| Diagnosis Code 2 | ICD-10 | L03.90 | Secondary diagnosis (optional, auto-detected) |
| Units | Integer | 1 | Always 1 for E/M services |
| Charge Amount | Decimal | 92.00 | CPT code charge (no $ sign) |
| Service Description | Text | Telehealth Wound Photo Review - E/M 3 | Human-readable description |
| Assessment Level | Text | Improving | improving/stable/concern/urgent |
| Encounter ID | UUID | abc123... | Unique encounter identifier |
| Notes | Text (255) | Clinical note excerpt | Truncated to 255 chars for CSV |

## AI-Powered Diagnosis Code Detection

The system automatically assigns appropriate ICD-10 codes based on wound type and clinical notes:

### Primary Diagnosis Codes

**Diabetic Wounds**:
- `E11.621` - Type 2 diabetes mellitus with foot ulcer
  - Triggered by: "diabetic" + ("foot" or "heel")
- `E11.622` - Type 2 diabetes mellitus with other skin ulcer
  - Triggered by: "diabetic" + other location

**Pressure Ulcers**:
- `L89.159` - Pressure ulcer of sacral region, unspecified stage
  - Triggered by: "sacral" or "coccyx"
- `L89.619` - Pressure ulcer of right heel, unspecified stage
  - Triggered by: "pressure" + "heel"
- `L89.90` - Pressure ulcer of unspecified site, unspecified stage
  - Triggered by: "pressure" (general)

**Venous Ulcers**:
- `I83.019` - Varicose veins of unspecified lower extremity with ulcer
  - Triggered by: "venous"

**Surgical Wounds**:
- `T81.31XA` - Disruption of external operation wound, initial encounter
  - Triggered by: "surgical", "post-surgical", or "incision"

**Traumatic Wounds**:
- `S91.009A` - Unspecified open wound of unspecified foot, initial encounter
  - Triggered by: "traumatic" or "trauma"

**Default**:
- `L97.929` - Non-pressure chronic ulcer of unspecified part of left lower leg
  - Used when no specific keywords detected

### Secondary Diagnosis Codes

Only added for "concern" or "urgent" assessments:

**Infection/Cellulitis**:
- `L03.90` - Cellulitis, unspecified
  - Triggered by: "infection", "infected", "purulent", or "pus" in notes

**Delayed Healing**:
- `L89.90` - Pressure ulcer, unspecified
  - Triggered by: "delayed healing" or "not healing" in notes

## Sample Export Data

```csv
Service Date,Patient Last Name,Patient First Name,Patient DOB,Patient Sex,MRN,...
11/03/2025,Smith,John,05/15/1960,M,MRN-12345,(555) 123-4567,john@email.com,123 Main St,New York,NY,10001,Blue Cross,ABC123,GRP456,Johnson,Sarah,1234567890,99214,95,02,E11.621,L03.90,1,135.00,Telehealth Wound Photo Review - E/M 4,Concern,abc123...,Diabetic foot ulcer showing signs of infection...
11/03/2025,Doe,Jane,03/22/1955,F,MRN-67890,(555) 987-6543,jane@email.com,456 Oak Ave,Los Angeles,CA,90001,Medicare,DEF456,MED789,Johnson,Sarah,1234567890,99213,95,02,L89.159,,1,92.00,Telehealth Wound Photo Review - E/M 3,Stable,def456...,Sacral pressure ulcer healing appropriately...
```

## Billing System Import Instructions

### Kareo
1. Go to: Billing → Claims → Import Charges
2. Upload CSV file
3. Map columns to Kareo fields
4. Verify CPT codes and modifiers
5. Process charges

### AdvancedMD
1. Navigate to: Billing → Charge Entry → Import
2. Select "CSV Import"
3. Upload wound_telehealth_billing_YYYY-MM.csv
4. Review mapping (auto-maps standard fields)
5. Confirm import

### athenahealth
1. Go to: Practice → Billing → Charge Import
2. Choose "Standard CSV Upload"
3. Upload file
4. Verify encounter data
5. Post charges

### DrChrono
1. Navigate to: Billing → Appointments → Import
2. Select "CSV Import"
3. Upload billing file
4. Map diagnosis codes and CPT codes
5. Save and post

### Generic CSV Import
Most billing systems accept CSV files with these standard fields. If your system requires different field names:

1. Open CSV in Excel/Google Sheets
2. Rename column headers to match your system
3. Remove unused columns
4. Save and import

## Revenue Summary by CPT Code

| CPT Code | Modifier | Description | Charge | Use Case |
|----------|----------|-------------|--------|----------|
| 99213-95 | 95 | Level 3 E/M (Telehealth) | $92.00 | Improving, Stable |
| 99214-95 | 95 | Level 4 E/M (Telehealth) | $135.00 | Concern |
| 99215-95 | 95 | Level 5 E/M (Telehealth) | $180.00 | Urgent |

## Export Process

### How to Export

1. **Go to Photo Reviews Page**: Navigate to `portal/?page=photo-reviews`
2. **Review Photos**: Complete wound photo reviews for the month
3. **Click Export CSV**: Button at top of page
4. **Download File**: `wound_telehealth_billing_YYYY-MM.csv`
5. **Import to Billing System**: Follow your system's CSV import process

### What Gets Exported

- **Included**: All encounters with `exported = FALSE`
- **Excluded**: Previously exported encounters
- **Auto-Marked**: After export, encounters marked as `exported = TRUE`
- **Date Range**: Current month by default (can be customized)

### Monthly Workflow

1. **Throughout the month**: Review wound photos as they arrive
2. **End of month**: Click "Export CSV" button
3. **Import to billing**: Upload CSV to your billing system
4. **Verify charges**: Review imported charges in billing system
5. **Submit claims**: Process claims through your normal workflow

## Data Validation

The export includes built-in data validation:

✅ **Required Fields**: Service date, patient name, MRN, CPT code, diagnosis code
✅ **Phone Formatting**: Auto-formats to (XXX) XXX-XXXX
✅ **Date Formatting**: Consistent MM/DD/YYYY format
✅ **Decimal Precision**: Charge amounts to 2 decimal places
✅ **Place of Service**: Always "02" for telehealth
✅ **Modifier**: Always "95" for telehealth
✅ **Units**: Always "1" for E/M services

## Troubleshooting

### Missing Patient Data

**Problem**: Some fields empty (address, insurance, etc.)

**Solution**:
- Update patient records before reviewing wound photos
- Billing system can fill in gaps during import
- Missing MRN auto-generates as "TEMP-{patient_id}"

### Diagnosis Code Issues

**Problem**: ICD-10 code doesn't match wound type

**Solution**:
- Edit clinical note to include specific keywords
- Re-export after updating notes
- Manual override in billing system after import

### Duplicate Encounters

**Problem**: Same encounter appears in multiple exports

**Solution**:
- Export marks encounters as `exported = TRUE`
- Only new encounters appear in future exports
- Reset export flag in database if needed: `UPDATE billable_encounters SET exported = FALSE WHERE ...`

### Excel Formatting Issues

**Problem**: Special characters display incorrectly

**Solution**:
- File includes UTF-8 BOM for Excel
- Open in Excel, not Notepad
- If issues persist, import as "UTF-8 CSV" in Excel

## Compliance Notes

- **HIPAA**: CSV contains PHI - handle securely
- **Telehealth Modifier**: "95" required for telehealth reimbursement
- **Place of Service**: "02" designates telehealth per CMS guidelines
- **Documentation**: Clinical notes include all required E/M components
- **Date of Service**: Matches date of physician review, not photo upload

## Support

For questions about billing export format or import issues:
1. Check billing system documentation for CSV import requirements
2. Verify column mapping matches your system
3. Test with small sample file (1-2 encounters) first
4. Contact your billing system support for import-specific help

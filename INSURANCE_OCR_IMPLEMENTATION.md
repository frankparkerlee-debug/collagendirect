# Insurance Card OCR Implementation Plan

## Overview
Implement one-time OCR processing for insurance cards to automatically extract and store insurance information in patient profiles and order PDFs.

## Requirements
1. Trigger OCR when insurance card is uploaded with order OR if one exists in patient file
2. Process only once (set a flag to avoid reprocessing)
3. Extract insurance information:
   - Insurance Provider/Company Name
   - Member ID
   - Group ID
   - Payer Phone
   - Plan Type (if available)
4. Store extracted data in patient record
5. Display in order.pdf

## Implementation Options

### Option 1: Google Cloud Vision API (Recommended)
**Pros:**
- Excellent accuracy for text detection
- Good at handling various card formats
- Structured text detection with coordinates
- $1.50 per 1000 images (first 1000/month free)

**Setup:**
1. Enable Cloud Vision API in Google Cloud Console
2. Create service account and download JSON key
3. Set environment variable: `GOOGLE_APPLICATION_CREDENTIALS`

**Code:**
```php
use Google\Cloud\Vision\V1\ImageAnnotatorClient;

function extractInsuranceInfo($imagePath) {
    $imageAnnotator = new ImageAnnotatorClient();
    $image = file_get_contents($imagePath);
    $response = $imageAnnotator->textDetection($image);
    $texts = $response->getTextAnnotations();

    if ($texts) {
        $fullText = $texts[0]->getDescription();
        return parseInsuranceText($fullText);
    }

    $imageAnnotator->close();
    return null;
}
```

### Option 2: AWS Textract
**Pros:**
- Purpose-built for document extraction
- Can detect key-value pairs
- Good for structured documents

**Cons:**
- More expensive ($1.50 per 1000 pages)
- Requires AWS account setup

### Option 3: Tesseract OCR (Open Source)
**Pros:**
- Free
- No external dependencies
- Can run locally

**Cons:**
- Lower accuracy than cloud solutions
- Requires server-side installation
- More preprocessing needed

## Database Schema Changes

Add to `patients` table:
```sql
ALTER TABLE patients ADD COLUMN IF NOT EXISTS insurance_ocr_processed BOOLEAN DEFAULT FALSE;
ALTER TABLE patients ADD COLUMN IF NOT EXISTS insurance_ocr_date TIMESTAMP;
ALTER TABLE patients ADD COLUMN IF NOT EXISTS insurance_ocr_data JSONB;
```

Or add to `orders` table if insurance can vary per order:
```sql
ALTER TABLE orders ADD COLUMN IF NOT EXISTS insurance_ocr_processed BOOLEAN DEFAULT FALSE;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS insurance_ocr_data JSONB;
```

## Parsing Logic

Common insurance card patterns:
- **Member ID**: Usually 9-15 alphanumeric characters
- **Group ID**: Often labeled "Group #" or "GRP"
- **Provider**: Company name at top (Aetna, UnitedHealthcare, Blue Cross, etc.)
- **Phone**: 1-800 numbers for claims/customer service

```php
function parseInsuranceText($text) {
    $result = [
        'provider' => null,
        'member_id' => null,
        'group_id' => null,
        'payer_phone' => null
    ];

    // Extract phone numbers
    if (preg_match('/1[-\s]?800[-\s]?\d{3}[-\s]?\d{4}/', $text, $matches)) {
        $result['payer_phone'] = $matches[0];
    }

    // Extract Member ID (look for patterns like "Member ID:" or "ID:")
    if (preg_match('/(?:Member\s*ID|ID|Member\s*#)[:\s]*([A-Z0-9]{9,15})/i', $text, $matches)) {
        $result['member_id'] = $matches[1];
    }

    // Extract Group ID
    if (preg_match('/(?:Group\s*#?|GRP)[:\s]*([A-Z0-9]+)/i', $text, $matches)) {
        $result['group_id'] = $matches[1];
    }

    // Provider name - look at first few lines or known providers
    $knownProviders = ['Aetna', 'UnitedHealthcare', 'Blue Cross', 'Cigna', 'Humana', 'Medicare', 'Medicaid'];
    foreach ($knownProviders as $provider) {
        if (stripos($text, $provider) !== false) {
            $result['provider'] = $provider;
            break;
        }
    }

    return $result;
}
```

## Integration Points

### 1. During Order Creation
In `orders.create.php` after insurance card upload:
```php
if ($ins_path && !empty($ins_path)) {
    // Check if already processed
    $stmt = $pdo->prepare("SELECT insurance_ocr_processed FROM patients WHERE id = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();

    if (!$patient['insurance_ocr_processed']) {
        $insuranceData = extractInsuranceInfo($ins_path);
        if ($insuranceData) {
            // Update patient record
            $pdo->prepare("
                UPDATE patients
                SET insurance_provider = COALESCE(insurance_provider, ?),
                    insurance_member_id = COALESCE(insurance_member_id, ?),
                    insurance_group_id = COALESCE(insurance_group_id, ?),
                    insurance_payer_phone = COALESCE(insurance_payer_phone, ?),
                    insurance_ocr_processed = TRUE,
                    insurance_ocr_date = NOW(),
                    insurance_ocr_data = ?
                WHERE id = ?
            ")->execute([
                $insuranceData['provider'],
                $insuranceData['member_id'],
                $insuranceData['group_id'],
                $insuranceData['payer_phone'],
                json_encode($insuranceData),
                $patient_id
            ]);
        }
    }
}
```

### 2. For Existing Cards
Create a migration script to process existing insurance cards:
```php
// admin/process-insurance-cards-ocr.php
$stmt = $pdo->query("
    SELECT p.id, p.ins_card_path
    FROM patients p
    WHERE p.ins_card_path IS NOT NULL
    AND (p.insurance_ocr_processed IS NULL OR p.insurance_ocr_processed = FALSE)
    LIMIT 100
");

foreach ($stmt->fetchAll() as $patient) {
    $insuranceData = extractInsuranceInfo($patient['ins_card_path']);
    // Update patient...
}
```

## Cost Estimates

For 1000 orders/month with insurance cards:
- **Google Vision**: $0-$1.50/month (first 1000 free)
- **AWS Textract**: ~$1.50/month
- **Tesseract**: Free (server resources only)

## Recommended Approach

**Phase 1** (Immediate):
1. Add database columns for OCR flags
2. Set up Google Cloud Vision API (free tier)
3. Implement OCR in order creation flow
4. Add manual override in case of OCR errors

**Phase 2** (Future):
1. Create admin tool to review/correct OCR results
2. Process existing insurance cards
3. Add confidence scores
4. Improve parsing with machine learning

## Security Considerations

1. **API Keys**: Store in environment variables, never in code
2. **PHI**: Insurance cards contain PHI - ensure HIPAA compliance
3. **Logging**: Don't log full card images, only metadata
4. **Encryption**: Encrypt OCR data in database
5. **Access**: Limit who can trigger OCR processing

## Testing

1. Test with various insurance card formats
2. Verify accuracy of extraction
3. Test fallback when OCR fails
4. Ensure no duplicates or reprocessing
5. Test with poor quality images

## Next Steps

1. Choose OCR provider (recommend Google Vision)
2. Set up API credentials
3. Run database migration
4. Implement extraction and parsing
5. Test with sample insurance cards
6. Deploy and monitor accuracy

<?php
/**
 * Insurance Card OCR Utility
 *
 * Extracts insurance information from insurance card images using OCR
 * Supports Claude/Anthropic API (recommended), Google Cloud Vision API, or Tesseract OCR
 */

class InsuranceOCR {
    private $ocr_enabled = false;
    private $ocr_provider = 'anthropic'; // 'anthropic', 'google', or 'tesseract'

    public function __construct() {
        // Check if OCR is enabled via environment variable
        $this->ocr_enabled = !empty(getenv('INSURANCE_OCR_ENABLED'));
        $this->ocr_provider = getenv('INSURANCE_OCR_PROVIDER') ?: 'anthropic';
    }

    /**
     * Check if OCR is enabled
     */
    public function isEnabled() {
        return $this->ocr_enabled;
    }

    /**
     * Process insurance card image and extract information
     *
     * @param string $imagePath Absolute path to insurance card image
     * @return array|null Extracted insurance data or null if processing fails
     */
    public function processInsuranceCard($imagePath) {
        if (!$this->ocr_enabled) {
            error_log("[InsuranceOCR] OCR is disabled");
            return null;
        }

        if (!file_exists($imagePath)) {
            error_log("[InsuranceOCR] Image file not found: $imagePath");
            return null;
        }

        error_log("[InsuranceOCR] Processing insurance card: $imagePath");

        try {
            // Extract text from image
            $extractedText = $this->extractTextFromImage($imagePath);

            if (!$extractedText) {
                error_log("[InsuranceOCR] No text extracted from image");
                return null;
            }

            // Parse insurance information from extracted text
            $insuranceData = $this->parseInsuranceText($extractedText);

            error_log("[InsuranceOCR] Extracted insurance data: " . json_encode($insuranceData));

            return $insuranceData;
        } catch (Exception $e) {
            error_log("[InsuranceOCR] Error processing insurance card: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract text from image using configured OCR provider
     */
    private function extractTextFromImage($imagePath) {
        if ($this->ocr_provider === 'anthropic') {
            return $this->extractWithAnthropic($imagePath);
        } elseif ($this->ocr_provider === 'google') {
            return $this->extractWithGoogleVision($imagePath);
        } elseif ($this->ocr_provider === 'tesseract') {
            return $this->extractWithTesseract($imagePath);
        }

        return null;
    }

    /**
     * Extract insurance information using Anthropic Claude API
     * Uses Claude's vision capabilities to directly extract structured data
     */
    private function extractWithAnthropic($imagePath) {
        $apiKey = getenv('ANTHROPIC_API_KEY');
        if (!$apiKey) {
            error_log("[InsuranceOCR] ANTHROPIC_API_KEY not set");
            return null;
        }

        try {
            // Read and encode the image
            $imageData = file_get_contents($imagePath);
            if (!$imageData) {
                error_log("[InsuranceOCR] Failed to read image file");
                return null;
            }

            // Detect image type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $imagePath);
            finfo_close($finfo);

            // Convert to supported format if needed
            $supportedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mimeType, $supportedTypes)) {
                error_log("[InsuranceOCR] Unsupported image type: $mimeType");
                return null;
            }

            $base64Image = base64_encode($imageData);

            // Prepare the API request
            $payload = [
                'model' => 'claude-3-5-sonnet-20250219',
                'max_tokens' => 1024,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $mimeType,
                                    'data' => $base64Image
                                ]
                            ],
                            [
                                'type' => 'text',
                                'text' => 'Please extract the following information from this insurance card image. Return ONLY a JSON object with these exact fields (use null for any field you cannot find):

{
  "provider": "Insurance company name",
  "member_id": "Member ID or Identification number",
  "group_id": "Group ID or Group number",
  "payer_phone": "Customer service phone number (format: 1-800-XXX-XXXX)",
  "plan_type": "Plan type (PPO, HMO, EPO, etc.)"
}

Important: Return ONLY the JSON object, no additional text or explanation.'
                            ]
                        ]
                    ]
                ]
            ];

            // Make API request
            $ch = curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                error_log("[InsuranceOCR] Anthropic API error (HTTP $httpCode): $response");
                return null;
            }

            $result = json_decode($response, true);
            if (!$result || !isset($result['content'][0]['text'])) {
                error_log("[InsuranceOCR] Invalid API response format");
                return null;
            }

            // Extract the JSON from Claude's response
            $text = $result['content'][0]['text'];

            // Claude might wrap the JSON in markdown code blocks, so extract it
            if (preg_match('/```json\s*(\{.*?\})\s*```/s', $text, $matches)) {
                $text = $matches[1];
            } elseif (preg_match('/(\{.*?\})/s', $text, $matches)) {
                $text = $matches[1];
            }

            $extractedData = json_decode($text, true);
            if (!$extractedData) {
                error_log("[InsuranceOCR] Failed to parse Claude response as JSON: $text");
                return null;
            }

            // Convert to the format expected by parseInsuranceText
            // We'll return a formatted text string so we can reuse the parsing logic
            $formattedText = '';
            if (!empty($extractedData['provider'])) {
                $formattedText .= "Provider: " . $extractedData['provider'] . "\n";
            }
            if (!empty($extractedData['member_id'])) {
                $formattedText .= "Member ID: " . $extractedData['member_id'] . "\n";
            }
            if (!empty($extractedData['group_id'])) {
                $formattedText .= "Group ID: " . $extractedData['group_id'] . "\n";
            }
            if (!empty($extractedData['payer_phone'])) {
                $formattedText .= "Phone: " . $extractedData['payer_phone'] . "\n";
            }
            if (!empty($extractedData['plan_type'])) {
                $formattedText .= "Plan Type: " . $extractedData['plan_type'] . "\n";
            }

            error_log("[InsuranceOCR] Claude extracted data: " . json_encode($extractedData));

            return $formattedText;

        } catch (Exception $e) {
            error_log("[InsuranceOCR] Anthropic API exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract text using Google Cloud Vision API
     */
    private function extractWithGoogleVision($imagePath) {
        // Check if Google Cloud Vision is available
        if (!class_exists('Google\Cloud\Vision\V1\ImageAnnotatorClient')) {
            error_log("[InsuranceOCR] Google Cloud Vision library not installed. Run: composer require google/cloud-vision");
            return null;
        }

        try {
            $imageAnnotator = new \Google\Cloud\Vision\V1\ImageAnnotatorClient();
            $image = file_get_contents($imagePath);
            $response = $imageAnnotator->textDetection($image);
            $texts = $response->getTextAnnotations();

            $fullText = '';
            if ($texts && count($texts) > 0) {
                $fullText = $texts[0]->getDescription();
            }

            $imageAnnotator->close();

            return $fullText;
        } catch (Exception $e) {
            error_log("[InsuranceOCR] Google Vision API error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Extract text using Tesseract OCR (fallback option)
     */
    private function extractWithTesseract($imagePath) {
        // Check if tesseract is available
        $output = [];
        $returnVar = 0;
        exec('which tesseract', $output, $returnVar);

        if ($returnVar !== 0) {
            error_log("[InsuranceOCR] Tesseract not installed. Install with: apt-get install tesseract-ocr");
            return null;
        }

        // Run tesseract
        $tempFile = tempnam(sys_get_temp_dir(), 'ocr');
        $command = escapeshellcmd("tesseract " . escapeshellarg($imagePath) . " " . escapeshellarg($tempFile));
        exec($command, $output, $returnVar);

        if ($returnVar === 0 && file_exists($tempFile . '.txt')) {
            $text = file_get_contents($tempFile . '.txt');
            unlink($tempFile . '.txt');
            return $text;
        }

        return null;
    }

    /**
     * Parse extracted text to identify insurance information
     */
    private function parseInsuranceText($text) {
        $result = [
            'provider' => null,
            'member_id' => null,
            'group_id' => null,
            'payer_phone' => null,
            'plan_type' => null,
            'confidence' => 0.5, // Default medium confidence
            'raw_text' => $text // Store for debugging/improvement
        ];

        // Extract provider name from known insurance companies
        $knownProviders = [
            'Aetna', 'UnitedHealthcare', 'United Healthcare', 'Blue Cross Blue Shield', 'BCBS',
            'Cigna', 'Humana', 'Anthem', 'Kaiser Permanente', 'Medicare', 'Medicaid',
            'WellCare', 'Centene', 'Molina Healthcare', 'HealthNet', 'Tricare'
        ];

        foreach ($knownProviders as $provider) {
            if (stripos($text, $provider) !== false) {
                $result['provider'] = $provider;
                $result['confidence'] += 0.2;
                break;
            }
        }

        // Extract Member ID
        // Common patterns: "Member ID:", "ID:", "Member #:", followed by alphanumeric
        if (preg_match('/(?:Member\s*(?:ID|#)|ID|Identification)[:\s]*([A-Z0-9]{7,20})/i', $text, $matches)) {
            $result['member_id'] = trim($matches[1]);
            $result['confidence'] += 0.2;
        }

        // Extract Group ID/Number
        if (preg_match('/(?:Group\s*(?:ID|#|Number)|GRP)[:\s]*([A-Z0-9]{3,20})/i', $text, $matches)) {
            $result['group_id'] = trim($matches[1]);
            $result['confidence'] += 0.1;
        }

        // Extract phone number (typically 1-800 numbers)
        if (preg_match('/(?:1[-\s]?)?[(\s]?800[-\s)]?\s*\d{3}[-\s]?\d{4}/', $text, $matches)) {
            $result['payer_phone'] = preg_replace('/[^\d]/', '', $matches[0]);
            if (strlen($result['payer_phone']) === 10) {
                $result['payer_phone'] = '1' . $result['payer_phone'];
            }
            $result['payer_phone'] = preg_replace('/(\d{1})(\d{3})(\d{3})(\d{4})/', '$1-$2-$3-$4', $result['payer_phone']);
        }

        // Extract plan type if mentioned
        $planTypes = ['PPO', 'HMO', 'EPO', 'POS', 'Medicare Advantage', 'Medigap'];
        foreach ($planTypes as $planType) {
            if (stripos($text, $planType) !== false) {
                $result['plan_type'] = $planType;
                break;
            }
        }

        // Cap confidence at 1.0
        $result['confidence'] = min(1.0, $result['confidence']);

        return $result;
    }

    /**
     * Check if patient already has OCR processed insurance data
     */
    public function hasBeenProcessed($pdo, $patientId) {
        $stmt = $pdo->prepare("SELECT insurance_ocr_processed FROM patients WHERE id = ?");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch();

        return $patient && $patient['insurance_ocr_processed'] === true;
    }

    /**
     * Save OCR results to patient record
     */
    public function saveToPatient($pdo, $patientId, $insuranceData) {
        if (!$insuranceData) {
            return false;
        }

        try {
            // Only update fields that are empty (don't overwrite manual entries)
            $pdo->prepare("
                UPDATE patients
                SET insurance_provider = COALESCE(NULLIF(insurance_provider, ''), ?),
                    insurance_member_id = COALESCE(NULLIF(insurance_member_id, ''), ?),
                    insurance_group_id = COALESCE(NULLIF(insurance_group_id, ''), ?),
                    insurance_payer_phone = COALESCE(NULLIF(insurance_payer_phone, ''), ?),
                    insurance_ocr_processed = TRUE,
                    insurance_ocr_date = NOW(),
                    insurance_ocr_data = ?,
                    insurance_ocr_confidence = ?
                WHERE id = ?
            ")->execute([
                $insuranceData['provider'],
                $insuranceData['member_id'],
                $insuranceData['group_id'],
                $insuranceData['payer_phone'],
                json_encode($insuranceData),
                $insuranceData['confidence'],
                $patientId
            ]);

            error_log("[InsuranceOCR] Saved OCR data for patient: $patientId");
            return true;
        } catch (PDOException $e) {
            error_log("[InsuranceOCR] Database error: " . $e->getMessage());
            return false;
        }
    }
}

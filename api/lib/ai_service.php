<?php
// /api/lib/ai_service.php — AI Service for Admin Assistance
// Uses Claude API for order analysis and response generation

class AIService {
  private $apiKey;
  private $model = 'claude-sonnet-4-5-20250929';
  private $apiUrl = 'https://api.anthropic.com/v1/messages';

  public function __construct() {
    // Get API key from environment variable
    $this->apiKey = getenv('ANTHROPIC_API_KEY') ?: '';

    if (empty($this->apiKey)) {
      error_log('[AIService] Warning: ANTHROPIC_API_KEY not set');
    }
  }

  /**
   * Analyze order for completeness and authorization readiness
   * Returns structured analysis with missing info, completeness score, etc.
   */
  public function analyzeOrder(array $orderData, array $patientData): array {
    if (empty($this->apiKey)) {
      return ['error' => 'AI service not configured. Please set ANTHROPIC_API_KEY.'];
    }

    $prompt = $this->buildOrderAnalysisPrompt($orderData, $patientData);

    try {
      $response = $this->callClaudeAPI($prompt);
      return [
        'success' => true,
        'analysis' => $response
      ];
    } catch (Exception $e) {
      error_log('[AIService] Order analysis error: ' . $e->getMessage());
      return ['error' => 'AI analysis failed: ' . $e->getMessage()];
    }
  }

  /**
   * Generate professional response message requesting missing information
   * This is the main feature - helps manufacturers request info from physicians
   */
  public function generateResponseMessage(array $orderData, array $patientData, array $conversationHistory = []): array {
    if (empty($this->apiKey)) {
      return ['error' => 'AI service not configured. Please set ANTHROPIC_API_KEY.'];
    }

    $prompt = $this->buildResponsePrompt($orderData, $patientData, $conversationHistory);

    try {
      $response = $this->callClaudeAPI($prompt);
      return [
        'success' => true,
        'message' => $response
      ];
    } catch (Exception $e) {
      error_log('[AIService] Response generation error: ' . $e->getMessage());
      return ['error' => 'AI response generation failed: ' . $e->getMessage()];
    }
  }

  /**
   * Generate medical necessity letter for insurance submission
   */
  public function generateMedicalNecessityLetter(array $orderData, array $patientData): array {
    if (empty($this->apiKey)) {
      return ['error' => 'AI service not configured. Please set ANTHROPIC_API_KEY.'];
    }

    $prompt = $this->buildMedNecessityPrompt($orderData, $patientData);

    try {
      $response = $this->callClaudeAPI($prompt);
      return [
        'success' => true,
        'letter' => $response
      ];
    } catch (Exception $e) {
      error_log('[AIService] Med necessity letter error: ' . $e->getMessage());
      return ['error' => 'Letter generation failed: ' . $e->getMessage()];
    }
  }

  /**
   * Generate comprehensive visit note for physician documentation
   * This is the primary physician-facing feature - generates defensible clinical notes
   */
  public function generateVisitNote(array $orderData, array $patientData, array $physicianData = []): array {
    if (empty($this->apiKey)) {
      return ['error' => 'AI service not configured. Please set ANTHROPIC_API_KEY.'];
    }

    $prompt = $this->buildVisitNotePrompt($orderData, $patientData, $physicianData);

    try {
      $response = $this->callClaudeAPI($prompt, 4096); // Longer output for comprehensive note
      return [
        'success' => true,
        'note' => $response
      ];
    } catch (Exception $e) {
      error_log('[AIService] Visit note generation error: ' . $e->getMessage());
      return ['error' => 'Visit note generation failed: ' . $e->getMessage()];
    }
  }

  /**
   * Generate approval score and feedback for patient profile
   * Analyzes patient demographics, diagnosis, wound details, notes, and uploaded documents
   * Returns color-coded score (Red/Yellow/Green) with detailed feedback
   */
  public function generateApprovalScore(array $patientData, array $documents = [], ?array $orderData = null): array {
    if (empty($this->apiKey)) {
      return ['error' => 'AI service not configured. Please set ANTHROPIC_API_KEY.'];
    }

    $prompt = $this->buildApprovalScorePrompt($patientData, $documents, $orderData);

    try {
      $response = $this->callClaudeAPI($prompt, 3072);

      // Extract JSON from response (handle markdown code fences)
      $jsonText = $response;

      // Remove markdown code fences if present
      if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
        $jsonText = $matches[1];
      } elseif (preg_match('/```\s*(.*?)\s*```/s', $response, $matches)) {
        $jsonText = $matches[1];
      }

      // Trim whitespace
      $jsonText = trim($jsonText);

      // Parse JSON response
      $result = json_decode($jsonText, true);

      if (!$result) {
        // Log the raw response for debugging
        error_log('[AIService] Failed to parse JSON. Raw response: ' . substr($response, 0, 500));

        // Fallback if AI doesn't return valid JSON
        return [
          'success' => true,
          'score' => 'YELLOW',
          'score_numeric' => 50,
          'summary' => 'Unable to parse AI response. Manual review recommended.',
          'missing_items' => [],
          'complete_items' => [],
          'recommendations' => ['Manual review required'],
          'concerns' => ['AI scoring error']
        ];
      }

      return [
        'success' => true,
        'score' => isset($result['score']) ? $result['score'] : 'YELLOW', // RED, YELLOW, or GREEN
        'score_numeric' => isset($result['score_numeric']) ? $result['score_numeric'] : 50, // 0-100
        'summary' => isset($result['summary']) ? $result['summary'] : '',
        'missing_items' => isset($result['missing_items']) ? $result['missing_items'] : [],
        'complete_items' => isset($result['complete_items']) ? $result['complete_items'] : [],
        'opportunities' => isset($result['opportunities']) ? $result['opportunities'] : [],
        'recommendations' => isset($result['recommendations']) ? $result['recommendations'] : [], // Legacy support
        'concerns' => isset($result['concerns']) ? $result['concerns'] : [], // Legacy support
        'document_analysis' => isset($result['document_analysis']) ? $result['document_analysis'] : null,
        'billing_readiness_checklist' => isset($result['billing_readiness_checklist']) ? $result['billing_readiness_checklist'] : null
      ];
    } catch (Exception $e) {
      error_log('[AIService] Approval score generation error: ' . $e->getMessage());
      return ['error' => 'Approval score generation failed: ' . $e->getMessage()];
    }
  }

  /**
   * Build prompt for order analysis
   */
  private function buildOrderAnalysisPrompt(array $order, array $patient): string {
    $patientAge = !empty($patient['dob']) ? date_diff(date_create($patient['dob']), date_create('today'))->y : 'Unknown';

    return <<<PROMPT
You are an expert medical billing and authorization specialist reviewing wound care orders for completeness and authorization readiness.

PATIENT INFORMATION:
- Name: {$patient['first_name']} {$patient['last_name']}
- Age: {$patientAge}
- Insurance: {$patient['insurance_provider']}

ORDER DETAILS:
- Product: {$order['product']}
- ICD-10 Primary: {$order['icd10_primary']}
- ICD-10 Secondary: {$order['icd10_secondary']}
- Wound Type: {$order['wound_type']}
- Wound Location: {$order['wound_location']}
- Wound Dimensions: {$order['wound_length_cm']} x {$order['wound_width_cm']} x {$order['wound_depth_cm']} cm
- Frequency: {$order['frequency_per_week']} times per week
- Duration: {$order['duration_days']} days
- Wound Notes: {$order['wound_notes']}

TASK: Analyze this order for completeness and authorization readiness. Provide:
1. Completeness score (0-100)
2. List of missing critical information
3. List of what's complete
4. Specific recommendations for improving authorization success
5. Any red flags or concerns

Format your response as JSON:
{
  "completeness_score": 85,
  "missing_info": ["item1", "item2"],
  "complete_items": ["item1", "item2"],
  "recommendations": ["rec1", "rec2"],
  "concerns": ["concern1"],
  "likelihood": "High/Medium/Low"
}
PROMPT;
  }

  /**
   * Build prompt for response message generation
   */
  private function buildResponsePrompt(array $order, array $patient, array $conversation): string {
    $patientName = $patient['first_name'] . ' ' . $patient['last_name'];
    $productName = isset($order['product']) ? $order['product'] : 'wound care product';

    // Build conversation context
    $conversationContext = '';
    if (!empty($conversation)) {
      $conversationContext = "\n\nPREVIOUS CONVERSATION:\n";
      foreach ($conversation as $msg) {
        $sender = $msg['type'] === 'manufacturer' ? 'You (Manufacturer)' : 'Physician';
        $conversationContext .= "{$sender}: {$msg['message']}\n\n";
      }
    }

    return <<<PROMPT
You are a professional medical equipment manufacturer representative writing to a physician.

CONTEXT:
You are reviewing an order for {$patientName} and need to request additional information to proceed with insurance authorization.

PATIENT: {$patientName}
PRODUCT: {$productName}
INSURANCE: {$patient['insurance_provider']}

ORDER INFORMATION:
- ICD-10 Primary: {$order['icd10_primary']}
- ICD-10 Secondary: {$order['icd10_secondary']}
- Wound Type: {$order['wound_type']}
- Wound Stage: {$order['wound_stage']}
- Wound Location: {$order['wound_location']}
- Dimensions: {$order['wound_length_cm']} x {$order['wound_width_cm']} x {$order['wound_depth_cm']} cm
- Frequency: {$order['frequency_per_week']} times/week
- Duration: {$order['duration_days']} days
- Notes: {$order['wound_notes']}
{$conversationContext}

TASK:
Write a professional, concise message to the physician requesting any missing information needed for insurance authorization.

GUIDELINES:
1. Be specific about what's needed and why
2. Reference insurance requirements when applicable
3. Keep it professional but friendly
4. If most info is complete, acknowledge what they provided
5. Make it easy for them to respond (numbered list of questions is good)
6. Keep it under 200 words
7. Don't include salutation or signature (those will be added automatically)

Write the message body only:
PROMPT;
  }

  /**
   * Build prompt for medical necessity letter
   */
  private function buildMedNecessityPrompt(array $order, array $patient): string {
    $patientAge = !empty($patient['dob']) ? date_diff(date_create($patient['dob']), date_create('today'))->y : 'Unknown';

    return <<<PROMPT
You are a medical professional writing a Letter of Medical Necessity for insurance authorization.

PATIENT INFORMATION:
- Name: {$patient['first_name']} {$patient['last_name']}
- DOB: {$patient['dob']}
- Age: {$patientAge}
- Member ID: {$patient['insurance_member_id']}
- Insurance: {$patient['insurance_provider']}

CLINICAL INFORMATION:
- Primary Diagnosis: {$order['icd10_primary']}
- Secondary Diagnosis: {$order['icd10_secondary']}
- Wound Type: {$order['wound_type']}
- Wound Stage: {$order['wound_stage']}
- Location: {$order['wound_location']}, {$order['wound_laterality']}
- Dimensions: {$order['wound_length_cm']}cm x {$order['wound_width_cm']}cm x {$order['wound_depth_cm']}cm
- Last Evaluation: {$order['last_eval_date']}

TREATMENT PLAN:
- Product: {$order['product']}
- HCPCS/CPT: {$order['cpt']}
- Frequency: {$order['frequency_per_week']} times per week
- Quantity per change: {$order['qty_per_change']}
- Duration: {$order['duration_days']} days
- Instructions: {$order['additional_instructions']}

CLINICAL NOTES:
{$order['wound_notes']}

TASK:
Write a comprehensive Letter of Medical Necessity that includes:
1. Patient demographics and insurance information
2. Clinical indication and diagnosis
3. Rationale for the specific product/treatment
4. Treatment plan with expected outcomes
5. Medical justification for frequency and duration
6. Professional formatting

Make it authoritative and clinically sound. Include all standard sections expected in a medical necessity letter.
PROMPT;
  }

  /**
   * Build prompt for comprehensive visit note generation
   */
  private function buildVisitNotePrompt(array $order, array $patient, array $physician): string {
    $patientAge = !empty($patient['dob']) ? date_diff(date_create($patient['dob']), date_create('today'))->y : 'Unknown';
    $todayDate = date('F j, Y');

    // Determine wound bed description based on available data
    $woundBedDesc = !empty($order['wound_notes']) ? $order['wound_notes'] : 'See physical examination';

    // Calculate wound volume if dimensions available
    $volume = '';
    if (!empty($order['wound_length_cm']) && !empty($order['wound_width_cm']) && !empty($order['wound_depth_cm'])) {
      $vol = $order['wound_length_cm'] * $order['wound_width_cm'] * $order['wound_depth_cm'];
      $volume = sprintf('%.2f', $vol) . ' cm³';
    }

    return <<<PROMPT
You are an experienced physician writing a comprehensive wound care visit note for medical documentation and insurance authorization.

PATIENT INFORMATION:
- Name: {$patient['first_name']} {$patient['last_name']}
- Date of Birth: {$patient['dob']}
- Age: {$patientAge} years
- MRN: {$patient['mrn']}
- Insurance: {$patient['insurance_provider']}
- Member ID: {$patient['insurance_member_id']}

VISIT DATE: {$todayDate}

CLINICAL INFORMATION:
- Primary Diagnosis: {$order['icd10_primary']}
- Secondary Diagnosis: {$order['icd10_secondary']}
- Wound Type: {$order['wound_type']}
- Wound Stage/Grade: {$order['wound_stage']}
- Location: {$order['wound_location']} ({$order['wound_laterality']})
- Measurements: Length {$order['wound_length_cm']} cm × Width {$order['wound_width_cm']} cm × Depth {$order['wound_depth_cm']} cm
- Volume: {$volume}
- Last Evaluation: {$order['last_eval_date']}
- Start Date: {$order['start_date']}

TREATMENT PLAN:
- Product: {$order['product']}
- HCPCS/CPT Code: {$order['cpt']}
- Frequency: {$order['frequency_per_week']} times per week
- Quantity per change: {$order['qty_per_change']}
- Duration: {$order['duration_days']} days
- Refills: {$order['refills_allowed']}

ADDITIONAL INFORMATION:
- Clinical Notes: {$woundBedDesc}
- Special Instructions: {$order['additional_instructions']}

TASK:
Write a comprehensive, insurance-auditable wound care visit note that includes ALL of the following sections:

1. HEADER: Date, patient name, DOB, MRN

2. CHIEF COMPLAINT: Brief statement of why patient is being seen

3. HISTORY OF PRESENT ILLNESS:
   - When wound developed and duration
   - Presumed cause/etiology
   - Prior treatments attempted (be specific about failed conservative care - this is CRITICAL for insurance)
   - Current wound status and trajectory
   - Impact on patient's quality of life/function

4. PHYSICAL EXAMINATION - WOUND ASSESSMENT:
   - Anatomical location with specificity
   - Measurements (include all three dimensions)
   - Wound stage/depth classification
   - Wound bed appearance (% granulation, slough, eschar, necrotic tissue)
   - Exudate amount (minimal/moderate/heavy) and character (serous/serosanguinous/purulent)
   - Periwound skin condition
   - Presence of undermining or tunneling (if applicable)
   - Odor (if present)
   - Signs of infection (if present)
   - Surrounding tissue viability

5. ASSESSMENT:
   - Primary diagnosis with ICD-10 code
   - Secondary diagnoses with ICD-10 codes
   - Comorbidities affecting wound healing
   - Current wound healing stage

6. MEDICAL NECESSITY JUSTIFICATION:
   - Why this specific advanced wound care product is medically necessary
   - Why standard/conventional care is insufficient
   - Clinical rationale for product selection based on wound characteristics
   - Expected therapeutic benefit
   - Why less expensive alternatives won't work

7. PLAN OF CARE:
   - Specific product with HCPCS code
   - Detailed application instructions
   - Frequency and rationale for frequency
   - Quantity per application and rationale
   - Duration of treatment
   - Adjunctive therapies (pressure relief, nutrition, etc.)
   - Follow-up schedule
   - Wound measurement and reassessment plan

8. EXPECTED OUTCOMES & PROGNOSIS:
   - Anticipated % reduction in wound size by 2 weeks, 4 weeks
   - Expected time to complete healing
   - Specific measurable goals

9. PHYSICIAN ATTESTATION BLOCK:
   Physician Signature: _____________________
   Date: _____________________
   Printed Name: [Physician Name]
   NPI: [Physician NPI]

CRITICAL REQUIREMENTS:
- Be specific and detailed - generic statements won't pass insurance review
- Include EXACT measurements and percentages
- Document FAILED prior treatments explicitly (insurance requires this)
- Explain WHY standard care failed and WHY advanced care is needed
- Use proper medical terminology
- Make it defendable in an audit
- Include all information needed for pre-authorization
- Reference clinical guidelines when appropriate
- Ensure medical necessity is crystal clear

Write the complete note now. Make it professional, thorough, and bulletproof for insurance review.
PROMPT;
  }

  /**
   * Build prompt for approval score generation
   * Acts as a billing professional/clinical support reviewing documentation for insurance authorization
   */
  private function buildApprovalScorePrompt(array $patient, array $documents, ?array $order = null): string {
    $patientAge = !empty($patient['dob']) ? date_diff(date_create($patient['dob']), date_create('today'))->y : 'Unknown';

    // Helper to safely get and truncate patient data
    $safe = function($key, $default = 'Not provided', $maxLength = 1000) use ($patient) {
      $value = $patient[$key] ?? $default;
      if (empty($value) || $value === '') return $default;
      // Remove any null bytes and limit length
      $value = str_replace("\0", '', $value);
      return mb_substr($value, 0, $maxLength);
    };

    // Format document information - extract full text from visit notes
    $documentInfo = '';
    $visitNotesContent = '';
    if (!empty($documents)) {
      $documentInfo = "\n\nUPLOADED DOCUMENTS:\n";
      foreach ($documents as $doc) {
        $documentInfo .= "- {$doc['type']}: {$doc['filename']}";
        if (!empty($doc['extracted_text'])) {
          // For visit notes, capture full content for deep analysis
          if ($doc['type'] === 'Order Visit Notes' || $doc['type'] === 'Clinical Notes') {
            // Give more space for clinical notes - these are critical
            $extractedText = mb_substr(str_replace("\0", '', $doc['extracted_text']), 0, 8000);
            $visitNotesContent .= "\n\n=== {$doc['type']} ({$doc['filename']}) ===\n" . $extractedText;
            $documentInfo .= " [CONTENT INCLUDED BELOW FOR ANALYSIS]\n";
          } else {
            // Limit other documents
            $extractedPreview = mb_substr(str_replace("\0", '', $doc['extracted_text']), 0, 1500);
            $documentInfo .= "\n  Content Preview: " . $extractedPreview . "\n";
          }
        }
        $documentInfo .= "\n";
      }
    } else {
      $documentInfo = "\n\nUPLOADED DOCUMENTS: None yet uploaded\n";
    }

    // Format notes (limit to 5000 characters to avoid excessive prompt length)
    $notes = !empty($patient['notes_text']) ? mb_substr(str_replace("\0", '', $patient['notes_text']), 0, 5000) : 'No clinical notes provided';

    // Pre-format document status strings (PHP 5 compatibility - can't use ternary in string interpolation)
    $idCardStatus = !empty($patient['id_card_path']) ? 'Uploaded' : 'MISSING';
    $insCardStatus = !empty($patient['ins_card_path']) ? 'Uploaded' : 'MISSING';

    $notesStatus = 'MISSING';
    if (!empty($patient['notes_path'])) {
      $notesStatus = 'Uploaded (patient profile)';
    } elseif (!empty($notes) && $notes !== 'No clinical notes provided') {
      $notesStatus = 'Entered manually';
    }

    // Check for visit notes from order (rx_note_path)
    $visitNotesStatus = 'MISSING';
    if (!empty($order['rx_note_path'])) {
      $visitNotesStatus = 'Uploaded (from order)';
    }

    // Build safe patient info strings
    $firstName = $safe('first_name');
    $lastName = $safe('last_name');
    $dob = $safe('dob');
    $sex = $safe('sex', 'U', 1);
    $phone = $safe('phone', 'Not provided', 20);
    $address = $safe('address');
    $city = $safe('city');
    $state = $safe('address_state', '', 2);
    $zip = $safe('zip', '', 10);
    $insProvider = $safe('insurance_provider');
    $insMemberId = $safe('insurance_member_id');
    $insGroupId = $safe('insurance_group_id');
    $insPayerPhone = $safe('insurance_payer_phone', 'Not provided', 20);

    // Format order information if available
    $orderInfo = '';
    if (!empty($order)) {
      $productName = $order['product_name'] ?? 'Not specified';
      $hcpcsCode = $order['hcpcs_code'] ?? 'Not specified';
      $frequency = $order['frequency_per_week'] ?? 'Not specified';
      $duration = $order['duration_days'] ?? 'Not specified';
      $qtyPerChange = $order['qty_per_change'] ?? 'Not specified';

      // Wound/Clinical information from order
      $icd10Primary = $order['icd10_primary'] ?? 'Not specified';
      $icd10Secondary = $order['icd10_secondary'] ?? '';
      $woundType = $order['wound_type'] ?? 'Not specified';
      $woundStage = $order['wound_stage'] ?? 'Not specified';
      $woundLocation = $order['wound_location'] ?? 'Not specified';
      $woundLaterality = $order['wound_laterality'] ?? '';
      $woundLength = $order['wound_length_cm'] ?? '';
      $woundWidth = $order['wound_width_cm'] ?? '';
      $woundDepth = $order['wound_depth_cm'] ?? '';
      $woundNotes = $order['wound_notes'] ?? '';
      $hasVisitNotes = !empty($order['rx_note_path']) ? 'Yes - uploaded' : 'No';

      $orderInfo = "\n\nORDER INFORMATION (Most Recent):\n";
      $orderInfo .= "- Product: {$productName}\n";
      $orderInfo .= "- HCPCS Code: {$hcpcsCode}\n";
      $orderInfo .= "- Frequency: {$frequency} times per week\n";
      $orderInfo .= "- Duration: {$duration} days\n";
      $orderInfo .= "- Quantity per change: {$qtyPerChange}\n";
      $orderInfo .= "- Visit Notes Uploaded: {$hasVisitNotes}\n";

      $orderInfo .= "\nWOUND/CLINICAL DETAILS FROM ORDER:\n";
      $orderInfo .= "- ICD-10 Primary: {$icd10Primary}\n";
      if (!empty($icd10Secondary)) {
        $orderInfo .= "- ICD-10 Secondary: {$icd10Secondary}\n";
      }
      $orderInfo .= "- Wound Type: {$woundType}\n";
      $orderInfo .= "- Wound Stage: {$woundStage}\n";
      $orderInfo .= "- Wound Location: {$woundLocation}";
      if (!empty($woundLaterality)) {
        $orderInfo .= " ({$woundLaterality})";
      }
      $orderInfo .= "\n";

      // Dimensions
      if (!empty($woundLength) || !empty($woundWidth) || !empty($woundDepth)) {
        $orderInfo .= "- Wound Dimensions: ";
        $dims = [];
        if (!empty($woundLength)) $dims[] = "L: {$woundLength}cm";
        if (!empty($woundWidth)) $dims[] = "W: {$woundWidth}cm";
        if (!empty($woundDepth)) $dims[] = "D: {$woundDepth}cm";
        $orderInfo .= implode(' x ', $dims) . "\n";
      }

      if (!empty($woundNotes)) {
        $orderInfo .= "- Order Wound Notes: " . mb_substr(str_replace("\0", '', $woundNotes), 0, 1000) . "\n";
      }
    } else {
      $orderInfo = "\n\nORDER INFORMATION: No orders found for this patient yet";
    }

    return <<<PROMPT
You are an expert medical billing professional and clinical documentation specialist reviewing a wound care patient profile for insurance authorization of advanced wound care products (collagen dressings).

YOUR ROLE: Act as if you are a billing professional or clinical support specialist who is reviewing this patient's documentation to verify that ALL criteria is met for insurance billing and reimbursement. Your job is to identify what is complete, what needs improvement, and what is missing.

PATIENT DEMOGRAPHICS:
- Name: {$firstName} {$lastName}
- DOB: {$dob}
- Age: {$patientAge}
- Sex: {$sex}
- Phone: {$phone}
- Address: {$address}, {$city}, {$state} {$zip}

INSURANCE INFORMATION:
- Provider: {$insProvider}
- Member ID: {$insMemberId}
- Group ID: {$insGroupId}
- Payer Phone: {$insPayerPhone}
{$orderInfo}

DOCUMENTATION STATUS:
- Photo ID: {$idCardStatus}
- Insurance Card: {$insCardStatus}
- Clinical Notes (Patient Profile): {$notesStatus}
- Visit Notes (Order): {$visitNotesStatus}
{$documentInfo}

PATIENT PROFILE NOTES:
{$notes}

CLINICAL VISIT NOTES (UPLOADED WITH ORDER):
{$visitNotesContent}

=== CRITICAL: VISIT NOTES ANALYSIS ===

You MUST carefully read and analyze the visit notes above as a billing professional would. Look for and evaluate:

**MEDICAL NECESSITY CRITERIA** (Required for insurance approval):
1. Wound etiology/cause documented (diabetic ulcer, pressure ulcer, venous ulcer, etc.)
2. ICD-10 diagnosis code matches the documented condition
3. Wound measurements documented (Length x Width x Depth in cm)
4. Wound characteristics: wound bed appearance, exudate amount/type, periwound condition
5. Documentation of FAILED CONSERVATIVE TREATMENT (critical!) - what was tried before?
6. Why standard dressings are insufficient
7. Medical rationale for advanced wound care product (collagen)

**BILLING COMPLIANCE CRITERIA**:
1. Provider signature/attestation
2. Date of service
3. Patient identification confirmed
4. Diagnosis supports medical necessity
5. Treatment plan with frequency and duration justified
6. Face-to-face encounter documented

**RED FLAGS TO IDENTIFY**:
- Vague wound descriptions ("wound healing slowly")
- Missing measurements
- No failed conservative care documentation
- ICD-10 code doesn't match documented wound type
- Missing medical necessity justification
- Template/generic notes without patient-specific detail

SCORING GUIDELINES:
- **GREEN (80-100 points)**: High likelihood of approval - all key criteria documented
- **YELLOW (50-79 points)**: Moderate likelihood - has gaps that should be addressed
- **RED (0-49 points)**: Low likelihood - critical documentation missing

REQUIRED OUTPUT FORMAT (JSON ONLY):
{
  "score": "RED|YELLOW|GREEN",
  "score_numeric": 0-100,
  "summary": "2-3 sentence billing-focused assessment of approval readiness",
  "complete_items": [
    "Specific item that IS documented properly (quote from notes if relevant)",
    "Another item that meets billing requirements"
  ],
  "opportunities": [
    {
      "category": "medical_necessity|billing_compliance|documentation",
      "issue": "Clear title of the issue",
      "current_state": "What the notes currently say (or 'Not documented')",
      "recommendation": "Specific recommendation for what to add/fix",
      "priority": "high|medium|low",
      "why_it_matters": "Brief explanation of billing/approval impact"
    }
  ],
  "missing_items": [
    {
      "item": "Critical missing element",
      "required_for": "What billing/approval requirement this satisfies",
      "how_to_document": "Specific guidance on what to add"
    }
  ],
  "document_analysis": {
    "id_card": "Present/Missing - feedback",
    "insurance_card": "Present/Missing - feedback",
    "clinical_notes": {
      "status": "Present/Missing",
      "quality": "Excellent/Good/Fair/Poor/Not provided",
      "medical_necessity_documented": true/false,
      "failed_conservative_care_documented": true/false,
      "wound_measurements_present": true/false,
      "diagnosis_supported": true/false,
      "key_findings": "Brief summary of what the notes contain"
    },
    "visit_notes": {
      "status": "Present/Missing",
      "quality": "Excellent/Good/Fair/Poor/Not provided",
      "contains_signature": true/false,
      "contains_date": true/false,
      "face_to_face_documented": true/false,
      "key_findings": "Brief summary of visit note content"
    }
  },
  "billing_readiness_checklist": {
    "patient_id_verified": true/false,
    "insurance_verified": true/false,
    "diagnosis_code_valid": true/false,
    "medical_necessity_established": true/false,
    "conservative_care_documented": true/false,
    "wound_measurements_complete": true/false,
    "treatment_plan_justified": true/false,
    "provider_signature_present": true/false
  }
}

IMPORTANT INSTRUCTIONS:
- READ THE VISIT NOTES THOROUGHLY - don't just note they exist, analyze their content
- Be specific about what IS documented (complete_items) vs what NEEDS improvement (opportunities) vs what is MISSING (missing_items)
- Quote relevant text from notes when assessing quality
- Provide actionable recommendations that a provider can act on
- Focus on what matters for insurance approval and billing compliance
- If visit notes are present but vague, mark as present but flag quality issues in opportunities

Return ONLY valid JSON, no additional text.
PROMPT;
  }

  /**
   * Call Claude API with the given prompt
   */
  public function callClaudeAPI(string $prompt, int $maxTokens = 2048): string {
    // Validate prompt is not empty
    if (empty($prompt) || trim($prompt) === '') {
      throw new Exception("Prompt cannot be empty");
    }

    $data = [
      'model' => $this->model,
      'max_tokens' => $maxTokens,
      'messages' => [
        [
          'role' => 'user',
          'content' => $prompt
        ]
      ]
    ];

    $jsonData = json_encode($data);

    // Validate JSON encoding succeeded
    if ($jsonData === false) {
      throw new Exception("Failed to encode request as JSON: " . json_last_error_msg());
    }

    // Log request size for debugging
    error_log("[AIService] Sending request to Claude API - Prompt length: " . strlen($prompt) . " chars, JSON length: " . strlen($jsonData) . " bytes");

    $ch = curl_init($this->apiUrl);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $jsonData,
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . $this->apiKey,
        'anthropic-version: 2023-06-01'
      ],
      CURLOPT_TIMEOUT => 90
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
      throw new Exception("cURL error: $error");
    }

    if ($httpCode !== 200) {
      error_log("[AIService] API error (HTTP $httpCode): $response");
      // Try to extract error message from response
      $errorDetails = '';
      $responseData = json_decode($response, true);
      if (isset($responseData['error']['message'])) {
        $errorDetails = $responseData['error']['message'];
      } elseif (isset($responseData['error'])) {
        $errorDetails = is_string($responseData['error']) ? $responseData['error'] : json_encode($responseData['error']);
      }
      throw new Exception("API returned HTTP $httpCode" . ($errorDetails ? ": $errorDetails" : ''));
    }

    $result = json_decode($response, true);

    if (!isset($result['content'][0]['text'])) {
      throw new Exception("Unexpected API response format");
    }

    return $result['content'][0]['text'];
  }

  /**
   * Extract text from image using Claude's vision capabilities
   *
   * @param string $imagePath Path to the image file
   * @param string $mimeType MIME type of the image
   * @return array ['text' => extracted text] or ['error' => error message]
   */
  public function extractTextFromImage(string $imagePath, string $mimeType): array {
    if (empty($this->apiKey)) {
      return ['error' => 'AI service not configured. Please set ANTHROPIC_API_KEY.'];
    }

    if (!file_exists($imagePath)) {
      return ['error' => "Image file not found: $imagePath"];
    }

    try {
      $imageData = base64_encode(file_get_contents($imagePath));

      $data = [
        'model' => $this->model,
        'max_tokens' => 4096,
        'messages' => [
          [
            'role' => 'user',
            'content' => [
              [
                'type' => 'image',
                'source' => [
                  'type' => 'base64',
                  'media_type' => $mimeType,
                  'data' => $imageData
                ]
              ],
              [
                'type' => 'text',
                'text' => 'Please extract all text from this medical document image. Return only the extracted text without any additional commentary or formatting.'
              ]
            ]
          ]
        ]
      ];

      $jsonData = json_encode($data);

      $ch = curl_init($this->apiUrl);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => [
          'Content-Type: application/json',
          'x-api-key: ' . $this->apiKey,
          'anthropic-version: 2023-06-01'
        ],
        CURLOPT_TIMEOUT => 120
      ]);

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);
      curl_close($ch);

      if ($error) {
        return ['error' => "cURL error: $error"];
      }

      if ($httpCode !== 200) {
        error_log("[AIService] Image extraction error (HTTP $httpCode): $response");
        return ['error' => "API returned HTTP $httpCode"];
      }

      $result = json_decode($response, true);

      if (!isset($result['content'][0]['text'])) {
        return ['error' => "Unexpected API response format"];
      }

      return ['text' => $result['content'][0]['text']];
    } catch (Exception $e) {
      error_log("[AIService] Error extracting text from image: " . $e->getMessage());
      return ['error' => $e->getMessage()];
    }
  }

  /**
   * Extract text from PDF using Claude
   *
   * @param string $pdfPath Path to the PDF file
   * @return array ['text' => extracted text] or ['error' => error message]
   */
  public function extractTextFromPDF(string $pdfPath): array {
    if (empty($this->apiKey)) {
      return ['error' => 'AI service not configured. Please set ANTHROPIC_API_KEY.'];
    }

    if (!file_exists($pdfPath)) {
      return ['error' => "PDF file not found: $pdfPath"];
    }

    try {
      $pdfData = base64_encode(file_get_contents($pdfPath));

      $data = [
        'model' => $this->model,
        'max_tokens' => 4096,
        'messages' => [
          [
            'role' => 'user',
            'content' => [
              [
                'type' => 'document',
                'source' => [
                  'type' => 'base64',
                  'media_type' => 'application/pdf',
                  'data' => $pdfData
                ]
              ],
              [
                'type' => 'text',
                'text' => 'Please extract all text from this medical document PDF. Return only the extracted text without any additional commentary or formatting.'
              ]
            ]
          ]
        ]
      ];

      $jsonData = json_encode($data);

      $ch = curl_init($this->apiUrl);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonData,
        CURLOPT_HTTPHEADER => [
          'Content-Type: application/json',
          'x-api-key: ' . $this->apiKey,
          'anthropic-version: 2023-06-01'
        ],
        CURLOPT_TIMEOUT => 120
      ]);

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $error = curl_error($ch);
      curl_close($ch);

      if ($error) {
        return ['error' => "cURL error: $error"];
      }

      if ($httpCode !== 200) {
        error_log("[AIService] PDF extraction error (HTTP $httpCode): $response");
        return ['error' => "API returned HTTP $httpCode"];
      }

      $result = json_decode($response, true);

      if (!isset($result['content'][0]['text'])) {
        return ['error' => "Unexpected API response format"];
      }

      return ['text' => $result['content'][0]['text']];
    } catch (Exception $e) {
      error_log("[AIService] Error extracting text from PDF: " . $e->getMessage());
      return ['error' => $e->getMessage()];
    }
  }
}

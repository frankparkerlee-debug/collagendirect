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
  public function generateApprovalScore(array $patientData, array $documents = []): array {
    if (empty($this->apiKey)) {
      return ['error' => 'AI service not configured. Please set ANTHROPIC_API_KEY.'];
    }

    $prompt = $this->buildApprovalScorePrompt($patientData, $documents);

    try {
      $response = $this->callClaudeAPI($prompt, 3072);

      // Parse JSON response
      $result = json_decode($response, true);

      if (!$result) {
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
        'recommendations' => isset($result['recommendations']) ? $result['recommendations'] : [],
        'concerns' => isset($result['concerns']) ? $result['concerns'] : [],
        'document_analysis' => isset($result['document_analysis']) ? $result['document_analysis'] : null
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
   */
  private function buildApprovalScorePrompt(array $patient, array $documents): string {
    $patientAge = !empty($patient['dob']) ? date_diff(date_create($patient['dob']), date_create('today'))->y : 'Unknown';

    // Format document information
    $documentInfo = '';
    if (!empty($documents)) {
      $documentInfo = "\n\nUPLOADED DOCUMENTS:\n";
      foreach ($documents as $doc) {
        $documentInfo .= "- {$doc['type']}: {$doc['filename']}";
        if (!empty($doc['extracted_text'])) {
          $documentInfo .= "\n  Content Preview: " . substr($doc['extracted_text'], 0, 500) . "...\n";
        }
        $documentInfo .= "\n";
      }
    } else {
      $documentInfo = "\n\nUPLOADED DOCUMENTS: None yet uploaded\n";
    }

    // Format notes
    $notes = !empty($patient['notes_text']) ? $patient['notes_text'] : 'No clinical notes provided';

    return <<<PROMPT
You are an expert medical billing and insurance authorization specialist reviewing a patient profile for wound care product authorization.

Your task is to analyze ALL available information and provide a comprehensive approval likelihood score.

PATIENT DEMOGRAPHICS:
- Name: {$patient['first_name']} {$patient['last_name']}
- DOB: {$patient['dob']}
- Age: {$patientAge}
- Sex: {$patient['sex']}
- Phone: {$patient['phone']}
- Address: {$patient['address']}, {$patient['city']}, {$patient['address_state']} {$patient['zip']}

INSURANCE INFORMATION:
- Provider: {$patient['insurance_provider']}
- Member ID: {$patient['insurance_member_id']}
- Group ID: {$patient['insurance_group_id']}
- Payer Phone: {$patient['insurance_payer_phone']}

DOCUMENTATION STATUS:
- Photo ID: {$patient['id_card_path'] ? 'Uploaded' : 'MISSING'}
- Insurance Card: {$patient['ins_card_path'] ? 'Uploaded' : 'MISSING'}
- Clinical Notes: {$patient['notes_path'] ? 'Uploaded' : (!empty($notes) && $notes !== 'No clinical notes provided' ? 'Entered manually' : 'MISSING')}
{$documentInfo}

CLINICAL NOTES/INFORMATION:
{$notes}

CRITICAL ANALYSIS REQUIREMENTS:

1. **Document Completeness** (30 points):
   - Photo ID uploaded?
   - Insurance card uploaded (both front and back)?
   - Clinical notes present (uploaded or typed)?
   - Are notes detailed enough?

2. **Patient Demographics** (15 points):
   - Complete contact information?
   - Valid insurance information?
   - All required fields populated?

3. **Clinical Information Quality** (35 points):
   - Are clinical notes detailed and specific?
   - Is there a clear diagnosis/ICD-10 code mentioned?
   - Are wound details documented (size, location, stage)?
   - Is there documentation of failed conservative care?
   - Medical necessity clearly explained?

4. **Insurance Authorization Readiness** (20 points):
   - Is insurance information complete and accurate?
   - Insurance card readable and valid?
   - Member/Group ID format looks correct?

SCORING GUIDELINES:
- **GREEN (80-100 points)**: High likelihood of approval
  - All documents uploaded
  - Comprehensive clinical notes with specific details
  - Clear medical necessity
  - Insurance info complete

- **YELLOW (50-79 points)**: Average likelihood of approval
  - Most documents present but some gaps
  - Clinical notes present but could be more detailed
  - Some minor missing information
  - Will likely need clarification

- **RED (0-49 points)**: Low likelihood of approval
  - Critical documents missing (ID, insurance card, or notes)
  - Insufficient clinical information
  - Missing medical necessity justification
  - Significant gaps in required data

REQUIRED OUTPUT FORMAT (JSON ONLY):
{
  "score": "RED|YELLOW|GREEN",
  "score_numeric": 0-100,
  "summary": "2-3 sentence overall assessment",
  "missing_items": ["Specific item 1", "Specific item 2"],
  "complete_items": ["What's good item 1", "What's good item 2"],
  "recommendations": ["Specific action 1", "Specific action 2"],
  "concerns": ["Specific concern 1", "Specific concern 2"],
  "document_analysis": {
    "id_card": "Present/Missing - specific feedback",
    "insurance_card": "Present/Missing - specific feedback",
    "clinical_notes": "Present/Missing - quality assessment"
  }
}

IMPORTANT:
- Be specific in your feedback
- Reference actual patient data in your analysis
- If clinical notes are vague, say so specifically
- If documents are missing, list exactly what's needed
- Be constructive in recommendations
- Focus on what will help get approval

Return ONLY valid JSON, no additional text.
PROMPT;
  }

  /**
   * Call Claude API with the given prompt
   */
  private function callClaudeAPI(string $prompt, int $maxTokens = 2048): string {
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

    $ch = curl_init($this->apiUrl);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => json_encode($data),
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
      throw new Exception("API returned HTTP $httpCode");
    }

    $result = json_decode($response, true);

    if (!isset($result['content'][0]['text'])) {
      throw new Exception("Unexpected API response format");
    }

    return $result['content'][0]['text'];
  }
}

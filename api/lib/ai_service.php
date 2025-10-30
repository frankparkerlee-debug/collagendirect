<?php
// /api/lib/ai_service.php — AI Service for Admin Assistance
// Uses Claude API for order analysis and response generation

class AIService {
  private $apiKey;
  private $model = 'claude-3-5-sonnet-20240620';
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
    $productName = $order['product'] ?? 'wound care product';

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
   * Call Claude API with the given prompt
   */
  private function callClaudeAPI(string $prompt): string {
    $data = [
      'model' => $this->model,
      'max_tokens' => 2048,
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
      CURLOPT_TIMEOUT => 30
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

<?php
/**
 * API endpoint to generate AI suggestions for order improvements
 * Analyzes order data and provides recommendations before submission
 */

header('Content-Type: application/json');
session_start();

try {
  require_once __DIR__ . '/../db.php';
  require_once __DIR__ . '/../lib/ai_service.php';
} catch (Exception $e) {
  echo json_encode(['ok' => false, 'error' => 'Failed to load dependencies: ' . $e->getMessage()]);
  exit;
}

// Check authentication
if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
  exit;
}

$userId = $_SESSION['user_id'];

// Get order data from request
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['order_data'])) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Order data required']);
  exit;
}

$orderData = $data['order_data'];

try {
  // Build prompt for AI to analyze order
  $prompt = buildOrderSuggestionsPrompt($orderData);

  // Call Claude API
  $response = callClaudeAPI($prompt, 'order-suggestions');

  if (!$response['ok']) {
    throw new Exception('AI analysis failed: ' . ($response['error'] ?? 'Unknown error'));
  }

  // Parse AI response
  $aiText = $response['text'] ?? '';
  $suggestions = parseOrderSuggestions($aiText);

  // Calculate approval score based on suggestions
  $approvalScore = calculateOrderApprovalScore($suggestions);

  echo json_encode([
    'ok' => true,
    'suggestions' => $suggestions,
    'approval_score' => $approvalScore,
    'raw_analysis' => $aiText
  ]);

} catch (Exception $e) {
  error_log("Order suggestions error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Failed to generate suggestions: ' . $e->getMessage()]);
}

/**
 * Build prompt for AI order analysis
 */
function buildOrderSuggestionsPrompt(array $order): string {
  $safe = function($key, $default = 'Not provided') use ($order) {
    $value = $order[$key] ?? $default;
    if (empty($value) || $value === '') return $default;
    $value = str_replace("\0", '', $value);
    return mb_substr($value, 0, 500);
  };

  // Patient info
  $patientFirstName = $safe('first_name');
  $patientLastName = $safe('last_name');
  $patientDOB = $safe('dob');
  $patientMRN = $safe('mrn');

  // Insurance info
  $insuranceProvider = $safe('insurance_provider');
  $insuranceMemberId = $safe('insurance_member_id');
  $insuranceGroupId = $safe('insurance_group_id');
  $paymentType = $safe('payment_type');

  // Clinical info
  $woundLocation = $safe('wound_location');
  $woundLaterality = $safe('wound_laterality');
  $woundNotes = $safe('wound_notes', 'Not provided', 2000);
  $frequency = $safe('frequency');

  // Product info
  $productName = $safe('product_name');
  $cptCode = $safe('cpt_code');

  // Delivery info
  $deliveryMode = $safe('delivery_mode');
  $shippingAddress = $safe('shipping_address');
  $shippingCity = $safe('shipping_city');
  $shippingState = $safe('shipping_state');
  $shippingZip = $safe('shipping_zip');

  return <<<PROMPT
You are a medical order review AI assistant. Analyze this collagen wound dressing order for completeness and accuracy.

**PATIENT INFORMATION:**
- Name: {$patientFirstName} {$patientLastName}
- DOB: {$patientDOB}
- MRN: {$patientMRN}

**INSURANCE INFORMATION:**
- Payment Type: {$paymentType}
- Insurance Provider: {$insuranceProvider}
- Member ID: {$insuranceMemberId}
- Group ID: {$insuranceGroupId}

**CLINICAL INFORMATION:**
- Product: {$productName}
- CPT Code: {$cptCode}
- Wound Location: {$woundLocation}
- Wound Laterality: {$woundLaterality}
- Clinical Notes: {$woundNotes}
- Treatment Frequency: {$frequency}

**DELIVERY INFORMATION:**
- Delivery Mode: {$deliveryMode}
- Shipping Address: {$shippingAddress}, {$shippingCity}, {$shippingState} {$shippingZip}

**TASK:**
Review this order and provide specific, actionable suggestions for improvement. Focus on:
1. Missing or incomplete information that could delay processing
2. Clinical appropriateness (frequency, product selection, wound description)
3. Insurance documentation requirements
4. Shipping logistics

**OUTPUT FORMAT:**
Return your analysis in JSON format with the following structure:
{
  "suggestions": [
    {
      "field": "field_name",
      "current_value": "current value or 'Not provided'",
      "suggested_value": "recommended value or action",
      "reason": "Brief explanation why this change would improve the order",
      "priority": "high|medium|low"
    }
  ],
  "missing_items": ["list of required fields that are missing"],
  "concerns": ["list of potential issues that need attention"],
  "overall_assessment": "Brief summary of order readiness"
}

**IMPORTANT:**
- Only suggest changes that would meaningfully improve order processing or patient care
- Be specific with suggested values when possible
- Prioritize suggestions (high = could block order, medium = should improve, low = optional enhancement)
- If the order is complete and appropriate, say so with minimal or no suggestions
PROMPT;
}

/**
 * Parse AI response into structured suggestions
 */
function parseOrderSuggestions(string $aiText): array {
  // Try to extract JSON from response
  if (preg_match('/\{[\s\S]*\}/', $aiText, $matches)) {
    $json = json_decode($matches[0], true);
    if ($json && isset($json['suggestions'])) {
      return $json;
    }
  }

  // Fallback: return raw text wrapped in structure
  return [
    'suggestions' => [],
    'missing_items' => [],
    'concerns' => [],
    'overall_assessment' => $aiText
  ];
}

/**
 * Calculate approval score based on suggestions
 */
function calculateOrderApprovalScore(array $analysis): array {
  $suggestions = $analysis['suggestions'] ?? [];
  $missingItems = $analysis['missing_items'] ?? [];
  $concerns = $analysis['concerns'] ?? [];

  // Start with perfect score
  $score = 100;

  // Deduct points for issues
  foreach ($suggestions as $suggestion) {
    $priority = $suggestion['priority'] ?? 'medium';
    if ($priority === 'high') {
      $score -= 15;
    } elseif ($priority === 'medium') {
      $score -= 10;
    } else {
      $score -= 5;
    }
  }

  // Deduct for missing items
  $score -= count($missingItems) * 10;

  // Deduct for concerns
  $score -= count($concerns) * 5;

  // Clamp to 0-100
  $score = max(0, min(100, $score));

  // Determine color grade
  if ($score >= 85) {
    $grade = 'GREEN';
  } elseif ($score >= 70) {
    $grade = 'YELLOW';
  } else {
    $grade = 'RED';
  }

  return [
    'score' => $grade,
    'score_numeric' => $score,
    'summary' => $analysis['overall_assessment'] ?? 'Order reviewed'
  ];
}

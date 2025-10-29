<?php
declare(strict_types=1);

/**
 * Twilio SMS Integration for Delivery Confirmations
 * Sends SMS messages to patients with delivery confirmation links
 */

require_once __DIR__ . '/env.php';

/**
 * Send SMS via Twilio API
 *
 * @param string $toPhone Patient phone number (E.164 format recommended: +1234567890)
 * @param string $message SMS message body (max 1600 chars for concatenated messages)
 * @return array ['success' => bool, 'sid' => string|null, 'status' => string|null, 'error' => string|null]
 */
function twilio_send_sms(string $toPhone, string $message): array {
  $accountSid = env('TWILIO_ACCOUNT_SID');
  $authToken = env('TWILIO_AUTH_TOKEN');
  $fromPhone = env('TWILIO_PHONE_NUMBER');

  // Validate configuration
  if (empty($accountSid) || empty($authToken) || empty($fromPhone)) {
    error_log('[twilio_sms] Configuration missing: TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, or TWILIO_PHONE_NUMBER not set');
    return [
      'success' => false,
      'sid' => null,
      'status' => null,
      'error' => 'Twilio configuration not set'
    ];
  }

  // Normalize phone number to E.164 format if needed
  $toPhone = normalize_phone_number($toPhone);
  if (!$toPhone) {
    error_log('[twilio_sms] Invalid phone number format');
    return [
      'success' => false,
      'sid' => null,
      'status' => null,
      'error' => 'Invalid phone number format'
    ];
  }

  // Prepare Twilio API request
  $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json";

  $data = [
    'From' => $fromPhone,
    'To' => $toPhone,
    'Body' => $message
  ];

  // Send request via cURL
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => "{$accountSid}:{$authToken}",
    CURLOPT_POSTFIELDS => http_build_query($data),
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  // Handle cURL errors
  if ($curlError) {
    error_log("[twilio_sms] cURL error: {$curlError}");
    return [
      'success' => false,
      'sid' => null,
      'status' => null,
      'error' => "Network error: {$curlError}"
    ];
  }

  // Parse response
  $responseData = json_decode($response, true);

  // Check for success (HTTP 200/201)
  if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['sid'])) {
    error_log("[twilio_sms] SMS sent successfully. SID: {$responseData['sid']}, Status: {$responseData['status']}");
    return [
      'success' => true,
      'sid' => $responseData['sid'],
      'status' => $responseData['status'], // queued, sent, delivered, failed, undelivered
      'error' => null
    ];
  }

  // Handle Twilio API errors
  $errorMessage = $responseData['message'] ?? 'Unknown Twilio error';
  $errorCode = $responseData['code'] ?? $httpCode;
  error_log("[twilio_sms] Twilio API error {$errorCode}: {$errorMessage}");

  return [
    'success' => false,
    'sid' => null,
    'status' => null,
    'error' => "Twilio error {$errorCode}: {$errorMessage}"
  ];
}

/**
 * Normalize phone number to E.164 format (+1234567890)
 *
 * @param string $phone Raw phone number
 * @return string|null Normalized phone or null if invalid
 */
function normalize_phone_number(string $phone): ?string {
  // Remove all non-digit characters
  $digits = preg_replace('/\D/', '', $phone);

  if (!$digits) {
    return null;
  }

  // Handle US/Canada numbers
  if (strlen($digits) === 10) {
    // Assume US/Canada country code
    return '+1' . $digits;
  } elseif (strlen($digits) === 11 && $digits[0] === '1') {
    // Already has country code
    return '+' . $digits;
  } elseif (strlen($digits) > 10 && strlen($digits) <= 15) {
    // International number, assume already has country code
    return '+' . $digits;
  }

  // Invalid length
  return null;
}

/**
 * Send delivery confirmation SMS to patient
 *
 * @param string $patientPhone Patient phone number
 * @param string $patientName Patient name
 * @param string $orderId Order ID
 * @param string $confirmationToken Unique confirmation token
 * @return array Result from twilio_send_sms()
 */
function send_delivery_confirmation_sms(
  string $patientPhone,
  string $patientName,
  string $orderId,
  string $confirmationToken
): array {
  $confirmUrl = "https://collagendirect.health/confirm-delivery?token=" . urlencode($confirmationToken);

  $message = "Hi {$patientName}, CollagenDirect here. Please confirm delivery of your order #{$orderId}: {$confirmUrl}";

  return twilio_send_sms($patientPhone, $message);
}

/**
 * Check SMS delivery status via Twilio API
 *
 * @param string $messageSid Twilio message SID
 * @return array ['success' => bool, 'status' => string|null, 'error' => string|null]
 */
function twilio_check_sms_status(string $messageSid): array {
  $accountSid = env('TWILIO_ACCOUNT_SID');
  $authToken = env('TWILIO_AUTH_TOKEN');

  if (empty($accountSid) || empty($authToken)) {
    return [
      'success' => false,
      'status' => null,
      'error' => 'Twilio configuration not set'
    ];
  }

  $url = "https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages/{$messageSid}.json";

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERPWD => "{$accountSid}:{$authToken}",
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true
  ]);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curlError = curl_error($ch);
  curl_close($ch);

  if ($curlError) {
    error_log("[twilio_sms] Status check cURL error: {$curlError}");
    return [
      'success' => false,
      'status' => null,
      'error' => "Network error: {$curlError}"
    ];
  }

  $responseData = json_decode($response, true);

  if ($httpCode === 200 && isset($responseData['status'])) {
    return [
      'success' => true,
      'status' => $responseData['status'], // queued, sent, delivered, failed, undelivered
      'error' => null
    ];
  }

  $errorMessage = $responseData['message'] ?? 'Unknown error';
  return [
    'success' => false,
    'status' => null,
    'error' => $errorMessage
  ];
}

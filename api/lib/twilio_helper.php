<?php
/**
 * Twilio SMS/MMS Helper Functions
 *
 * Handles sending photo requests and receiving photos via SMS/MMS
 * Uses direct Twilio API calls (no SDK required)
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/twilio_sms.php';

class TwilioHelper {
  private $fromNumber;

  public function __construct() {
    $this->fromNumber = env('TWILIO_FROM_PHONE');

    if (!$this->fromNumber) {
      throw new Exception('Twilio FROM phone not configured. Please set TWILIO_FROM_PHONE in .env');
    }
  }

  /**
   * Send photo request SMS to patient
   *
   * @param string $toPhone Patient phone number (10 digits)
   * @param string $patientName Patient first name
   * @param string $uploadToken Optional secure upload token for email fallback
   * @return array ['success' => bool, 'sid' => string|null, 'error' => string|null]
   */
  public function sendPhotoRequest($toPhone, $patientName, $uploadToken = null) {
    try {
      // Format phone number
      $toPhone = $this->formatPhoneNumber($toPhone);

      // Build message
      $message = "Hi {$patientName}! Please send a photo of your wound by replying to this text message with the photo attached.";

      // Add email fallback link if token provided
      if ($uploadToken) {
        $message .= "\n\nOr use this link:\nhttps://collagendirect.health/upload/{$uploadToken}";
      }

      $message .= "\n\nReply STOP to opt out.";

      // Send SMS using our existing function
      $result = twilio_send_sms($toPhone, $message);

      return $result;

    } catch (Exception $e) {
      error_log('[TwilioHelper] Failed to send SMS: ' . $e->getMessage());
      return [
        'success' => false,
        'sid' => null,
        'error' => $e->getMessage()
      ];
    }
  }

  /**
   * Send confirmation SMS to patient after photo received
   *
   * @param string $toPhone Patient phone number
   * @param string $patientName Patient first name
   * @return array ['success' => bool, 'error' => string|null]
   */
  public function sendPhotoConfirmation($toPhone, $patientName) {
    try {
      $toPhone = $this->formatPhoneNumber($toPhone);

      $message = "✓ Photo received, {$patientName}! Your doctor will review it shortly. Thank you!";

      $result = twilio_send_sms($toPhone, $message);

      return ['success' => $result['success'], 'error' => $result['error'] ?? null];

    } catch (Exception $e) {
      error_log('[TwilioHelper] Failed to send confirmation: ' . $e->getMessage());
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Send error message to patient
   *
   * @param string $toPhone Patient phone number
   * @param string $errorMessage Error message to send
   * @return array ['success' => bool, 'error' => string|null]
   */
  public function sendErrorMessage($toPhone, $errorMessage) {
    try {
      $toPhone = $this->formatPhoneNumber($toPhone);

      $result = twilio_send_sms($toPhone, $errorMessage);

      return ['success' => $result['success'], 'error' => $result['error'] ?? null];

    } catch (Exception $e) {
      error_log('[TwilioHelper] Failed to send error message: ' . $e->getMessage());
      return ['success' => false, 'error' => $e->getMessage()];
    }
  }

  /**
   * Format phone number to E.164 format (+1XXXXXXXXXX)
   *
   * @param string $phone Phone number (various formats accepted)
   * @return string Formatted phone number
   */
  private function formatPhoneNumber($phone) {
    // Use the existing normalize function
    return normalize_phone_number($phone) ?: $phone;
  }

  /**
   * Download MMS media from Twilio URL
   *
   * @param string $mediaUrl Twilio media URL
   * @return array ['success' => bool, 'data' => binary|null, 'mime' => string|null, 'error' => string|null]
   */
  public function downloadMedia($mediaUrl) {
    try {
      // Twilio media URLs require authentication
      $accountSid = env('TWILIO_ACCOUNT_SID');
      $authToken = env('TWILIO_AUTH_TOKEN');

      if (!$accountSid || !$authToken) {
        throw new Exception('Twilio credentials not configured');
      }

      $ch = curl_init($mediaUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_USERPWD, "$accountSid:$authToken");
      curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
      curl_setopt($ch, CURLOPT_TIMEOUT, 30);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

      $data = curl_exec($ch);
      $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      $curlError = curl_error($ch);

      curl_close($ch);

      if ($curlError) {
        throw new Exception("cURL error: $curlError");
      }

      if ($httpCode !== 200 || !$data) {
        throw new Exception("Failed to download media. HTTP code: $httpCode");
      }

      return [
        'success' => true,
        'data' => $data,
        'mime' => $contentType,
        'error' => null
      ];

    } catch (Exception $e) {
      error_log('[TwilioHelper] Failed to download media: ' . $e->getMessage());
      return [
        'success' => false,
        'data' => null,
        'mime' => null,
        'error' => $e->getMessage()
      ];
    }
  }
}

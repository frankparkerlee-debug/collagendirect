<?php
/**
 * Twilio SMS/MMS Helper Functions
 *
 * Handles sending photo requests and receiving photos via SMS/MMS
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Twilio\Rest\Client;

class TwilioHelper {
  private $client;
  private $fromNumber;

  public function __construct() {
    $sid = getenv('TWILIO_SID');
    $token = getenv('TWILIO_TOKEN');
    $this->fromNumber = getenv('TWILIO_PHONE');

    if (!$sid || !$token || !$this->fromNumber) {
      throw new Exception('Twilio credentials not configured. Please set TWILIO_SID, TWILIO_TOKEN, and TWILIO_PHONE environment variables.');
    }

    $this->client = new Client($sid, $token);
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

      // Send SMS
      $messageObj = $this->client->messages->create(
        $toPhone,
        [
          'from' => $this->fromNumber,
          'body' => $message
        ]
      );

      return [
        'success' => true,
        'sid' => $messageObj->sid,
        'error' => null
      ];

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

      $this->client->messages->create(
        $toPhone,
        [
          'from' => $this->fromNumber,
          'body' => $message
        ]
      );

      return ['success' => true, 'error' => null];

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

      $this->client->messages->create(
        $toPhone,
        [
          'from' => $this->fromNumber,
          'body' => $errorMessage
        ]
      );

      return ['success' => true, 'error' => null];

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
    // Remove all non-numeric characters
    $digits = preg_replace('/[^0-9]/', '', $phone);

    // Add +1 if not present (assuming US numbers)
    if (strlen($digits) === 10) {
      return '+1' . $digits;
    } elseif (strlen($digits) === 11 && substr($digits, 0, 1) === '1') {
      return '+' . $digits;
    }

    // Return as-is if already formatted
    return $phone;
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
      $sid = getenv('TWILIO_SID');
      $token = getenv('TWILIO_TOKEN');

      $ch = curl_init($mediaUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
      curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

      $data = curl_exec($ch);
      $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      curl_close($ch);

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

<?php
/**
 * Twilio Webhook: Receive MMS Photos
 *
 * This endpoint is called by Twilio when a patient replies with a photo.
 * Configure this URL in your Twilio console under "Messaging" webhooks.
 *
 * Webhook URL: https://collagendirect.health/api/twilio/receive-mms.php
 */

header('Content-Type: text/xml');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/twilio_helper.php';

// Log all incoming webhooks for debugging
error_log('[Twilio Webhook] Received: ' . json_encode($_POST));

try {
  // Get Twilio POST data
  $fromPhone = $_POST['From'] ?? '';
  $toPhone = $_POST['To'] ?? '';
  $body = $_POST['Body'] ?? '';
  $numMedia = intval($_POST['NumMedia'] ?? 0);

  if (empty($fromPhone)) {
    error_log('[Twilio Webhook] Missing From number');
    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit;
  }

  // Normalize phone number (remove +1, spaces, dashes)
  $phoneDigits = preg_replace('/[^0-9]/', '', $fromPhone);
  if (strlen($phoneDigits) === 11 && substr($phoneDigits, 0, 1) === '1') {
    $phoneDigits = substr($phoneDigits, 1); // Remove leading 1
  }

  error_log('[Twilio Webhook] From: ' . $fromPhone . ' (normalized: ' . $phoneDigits . ')');
  error_log('[Twilio Webhook] Body: ' . $body);
  error_log('[Twilio Webhook] Media count: ' . $numMedia);

  // Find patient by phone number
  $stmt = $pdo->prepare("
    SELECT id, first_name, last_name, user_id, phone
    FROM patients
    WHERE phone = ? OR phone = ? OR phone = ?
  ");
  $stmt->execute([
    $phoneDigits,
    '+1' . $phoneDigits,
    preg_replace('/[^0-9]/', '', $fromPhone)
  ]);
  $patient = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$patient) {
    error_log('[Twilio Webhook] Patient not found for phone: ' . $phoneDigits);

    // Send error message to sender
    $twilioHelper = new TwilioHelper();
    $twilioHelper->sendErrorMessage(
      $fromPhone,
      "We couldn't find your patient record. Please contact your doctor's office at (555) 123-4567."
    );

    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit;
  }

  error_log('[Twilio Webhook] Found patient: ' . $patient['first_name'] . ' ' . $patient['last_name'] . ' (ID: ' . $patient['id'] . ')');

  // Check if message has media (photo)
  if ($numMedia === 0) {
    error_log('[Twilio Webhook] No media attached - checking if delivery confirmation reply');

    // Check if this is a delivery confirmation reply
    $confirmStmt = $pdo->prepare("
      SELECT dc.id, dc.order_id
      FROM delivery_confirmations dc
      JOIN orders o ON o.id = dc.order_id
      WHERE o.patient_id = ?
        AND dc.confirmed_at IS NULL
        AND dc.sms_sent_at IS NOT NULL
        AND dc.sms_sent_at > NOW() - INTERVAL '7 days'
      ORDER BY dc.sms_sent_at DESC
      LIMIT 1
    ");
    $confirmStmt->execute([$patient['id']]);
    $pendingConfirmation = $confirmStmt->fetch(PDO::FETCH_ASSOC);

    if ($pendingConfirmation) {
      // Check if message contains confirmation keywords
      $bodyLower = strtolower(trim($body));
      $confirmKeywords = ['yes', 'delivered', 'confirm', 'confirmed', 'received', 'got it', 'got them'];

      $isConfirmation = false;
      foreach ($confirmKeywords as $keyword) {
        if (strpos($bodyLower, $keyword) !== false) {
          $isConfirmation = true;
          break;
        }
      }

      if ($isConfirmation) {
        // Record delivery confirmation
        $updateStmt = $pdo->prepare("
          UPDATE delivery_confirmations
          SET confirmed_at = NOW(),
              confirmation_method = 'sms_reply',
              sms_reply_text = ?,
              updated_at = NOW()
          WHERE id = ?
        ");
        $updateStmt->execute([$body, $pendingConfirmation['id']]);

        error_log('[Twilio Webhook] Delivery confirmed via SMS reply for order: ' . $pendingConfirmation['order_id']);

        // Send confirmation reply
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<Response>';
        echo '<Message>Thank you ' . htmlspecialchars($patient['first_name']) . '! Your delivery confirmation has been recorded.</Message>';
        echo '</Response>';
        exit;
      } else {
        // Ask for clearer confirmation
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<Response>';
        echo '<Message>To confirm delivery, please reply with "YES" or "DELIVERED". To send a wound photo, attach an image to your message.</Message>';
        echo '</Response>';
        exit;
      }
    }

    // No pending confirmation - must be trying to send a photo
    $twilioHelper = new TwilioHelper();
    $twilioHelper->sendErrorMessage(
      $fromPhone,
      "Hi {$patient['first_name']}! Please attach a photo when you reply. Tap the camera icon to take a photo or choose from your gallery."
    );

    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit;
  }

  // Process media (photos)
  $mediaUrl = $_POST['MediaUrl0'] ?? '';
  $mediaContentType = $_POST['MediaContentType0'] ?? '';

  error_log('[Twilio Webhook] Media URL: ' . $mediaUrl);
  error_log('[Twilio Webhook] Media Type: ' . $mediaContentType);

  // Validate media type (must be image)
  $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/heic', 'image/heif'];
  if (!in_array(strtolower($mediaContentType), $allowedTypes)) {
    error_log('[Twilio Webhook] Invalid media type: ' . $mediaContentType);

    $twilioHelper = new TwilioHelper();
    $twilioHelper->sendErrorMessage(
      $fromPhone,
      "Please send a photo (JPEG, PNG, or HEIC format). Video and other file types are not supported."
    );

    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit;
  }

  // Download media from Twilio
  $twilioHelper = new TwilioHelper();
  $mediaResult = $twilioHelper->downloadMedia($mediaUrl);

  if (!$mediaResult['success']) {
    error_log('[Twilio Webhook] Failed to download media: ' . $mediaResult['error']);

    $twilioHelper->sendErrorMessage(
      $fromPhone,
      "There was an error receiving your photo. Please try sending it again."
    );

    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit;
  }

  // Save photo to filesystem
  $photoData = $mediaResult['data'];
  $mimeType = $mediaResult['mime'];

  // Determine file extension
  $ext = 'jpg';
  if (strpos($mimeType, 'png') !== false) {
    $ext = 'png';
  } elseif (strpos($mimeType, 'heic') !== false || strpos($mimeType, 'heif') !== false) {
    $ext = 'heic';
  }

  $filename = 'wound-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
  $photoPath = '/uploads/wound_photos/' . $filename;
  $fullPath = __DIR__ . '/../../uploads/wound_photos/' . $filename;

  // Ensure directory exists
  $uploadDir = dirname($fullPath);
  if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
  }

  $savedBytes = file_put_contents($fullPath, $photoData);

  if (!$savedBytes) {
    error_log('[Twilio Webhook] Failed to save photo to: ' . $fullPath);

    $twilioHelper->sendErrorMessage(
      $fromPhone,
      "There was an error saving your photo. Please contact your doctor's office."
    );

    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    exit;
  }

  error_log('[Twilio Webhook] Photo saved: ' . $fullPath . ' (' . $savedBytes . ' bytes)');

  // Save to database
  $photoId = bin2hex(random_bytes(16));

  $stmt = $pdo->prepare("
    INSERT INTO wound_photos (
      id, patient_id, photo_path, patient_notes, uploaded_via, uploaded_at
    ) VALUES (?, ?, ?, ?, 'sms', NOW())
  ");

  $stmt->execute([
    $photoId,
    $patient['id'],
    $photoPath,
    $body // SMS message body becomes patient notes
  ]);

  error_log('[Twilio Webhook] Photo saved to database: ' . $photoId);

  // Send confirmation to patient
  $twilioHelper->sendPhotoConfirmation($fromPhone, $patient['first_name']);

  error_log('[Twilio Webhook] Success! Photo received from ' . $patient['first_name'] . ' ' . $patient['last_name']);

  // Return empty TwiML response
  echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';

} catch (Exception $e) {
  error_log('[Twilio Webhook] FATAL ERROR: ' . $e->getMessage());
  error_log('[Twilio Webhook] Stack trace: ' . $e->getTraceAsString());

  // Return empty response to prevent Twilio retries
  echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
}

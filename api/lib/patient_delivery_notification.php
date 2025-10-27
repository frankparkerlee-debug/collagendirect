<?php
// api/lib/patient_delivery_notification.php - Patient delivery confirmation emails
declare(strict_types=1);

if (!function_exists('send_delivery_confirmation_email')) {
  /**
   * Send delivery confirmation email to patient 2-3 days after order
   * Required for insurance compliance
   *
   * @param PDO $pdo Database connection
   * @param int $orderId Order ID
   * @return bool Success status
   */
  function send_delivery_confirmation_email(PDO $pdo, int $orderId): bool {
    try {
      // Get order and patient details
      $orderStmt = $pdo->prepare("
        SELECT o.*,
               p.first_name, p.last_name, p.email AS patient_email, p.phone,
               u.first_name AS phys_first, u.last_name AS phys_last, u.practice_name,
               pr.name AS product_name, pr.size AS product_size
        FROM orders o
        LEFT JOIN patients p ON p.id = o.patient_id
        LEFT JOIN users u ON u.id = o.user_id
        LEFT JOIN products pr ON pr.id = o.product_id
        WHERE o.id = ?
      ");
      $orderStmt->execute([$orderId]);
      $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

      if (!$order) {
        error_log("[delivery-notification] Order not found: $orderId");
        return false;
      }

      if (empty($order['patient_email'])) {
        error_log("[delivery-notification] No patient email for order: $orderId");
        return false;
      }

      $patientEmail = $order['patient_email'];
      $patientName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));

      // Check if already sent
      $checkStmt = $pdo->prepare("
        SELECT id FROM order_delivery_confirmations
        WHERE order_id = ?
      ");
      $checkStmt->execute([$orderId]);
      if ($checkStmt->fetch()) {
        error_log("[delivery-notification] Already sent for order: $orderId");
        return false;
      }

      // Generate unique confirmation token
      $token = bin2hex(random_bytes(32));

      // Create confirmation record
      $insertStmt = $pdo->prepare("
        INSERT INTO order_delivery_confirmations
        (order_id, patient_email, confirmation_token, sent_at)
        VALUES (?, ?, ?, NOW())
      ");
      $insertStmt->execute([$orderId, $patientEmail, $token]);

      // Build confirmation URL
      $baseUrl = 'https://collagendirect.health';
      $confirmUrl = $baseUrl . '/patient/confirm-delivery?token=' . urlencode($token);

      // Build email content
      $physicianName = trim(($order['phys_first'] ?? '') . ' ' . ($order['phys_last'] ?? ''));
      $productLabel = $order['product_name'] ?? $order['product'] ?? 'CollagenDirect Product';
      if (!empty($order['product_size'])) {
        $productLabel .= ' ' . $order['product_size'];
      }

      $subject = "CollagenDirect Order Delivery Confirmation - Order #$orderId";

      // Check if we have SendGrid template
      require_once __DIR__ . '/env.php';
      $templateId = env('SG_TMPL_ORDER_CONFIRM', '');

      if ($templateId) {
        // Use SendGrid template
        require_once __DIR__ . '/sg_curl.php';

        $result = sg_send(
          ['email' => $patientEmail, 'name' => $patientName],
          null,
          null,
          [
            'template_id' => $templateId,
            'dynamic_data' => [
              'patient_name' => $patientName,
              'order_id' => (string)$orderId,
              'product_name' => $productLabel,
              'physician_name' => $physicianName,
              'confirm_url' => $confirmUrl,
              'support_email' => 'support@collagendirect.health',
              'year' => date('Y')
            ],
            'categories' => ['delivery', 'confirmation', 'compliance']
          ]
        );
      } else {
        // Fallback to plain HTML email
        $html = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
          <h2 style='color: #2563eb;'>Delivery Confirmation Required</h2>

          <p>Dear $patientName,</p>

          <p>We hope you've received your CollagenDirect order!</p>

          <div style='background-color: #f3f4f6; padding: 15px; border-radius: 5px; margin: 20px 0;'>
            <strong>Order Details:</strong><br>
            Order #: $orderId<br>
            Product: $productLabel<br>
            Prescribing Physician: Dr. $physicianName
          </div>

          <p><strong style='color: #dc2626;'>Action Required:</strong></p>
          <p>For insurance compliance, please confirm that you have received your order by clicking the button below:</p>

          <div style='text-align: center; margin: 30px 0;'>
            <a href='$confirmUrl' style='background-color: #2563eb; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>
              Confirm Delivery
            </a>
          </div>

          <p style='font-size: 12px; color: #6b7280;'>
            If the button above doesn't work, copy and paste this link into your browser:<br>
            <a href='$confirmUrl'>$confirmUrl</a>
          </p>

          <hr style='border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;'>

          <p style='font-size: 12px; color: #6b7280;'>
            If you have any questions or did not receive your order, please contact our support team at
            <a href='mailto:support@collagendirect.health'>support@collagendirect.health</a>.
          </p>

          <p style='font-size: 12px; color: #6b7280;'>
            Thank you,<br>
            The CollagenDirect Team
          </p>
        </div>
        ";

        require_once __DIR__ . '/sg_curl.php';
        $result = sg_send(
          ['email' => $patientEmail, 'name' => $patientName],
          $subject,
          $html,
          ['categories' => ['delivery', 'confirmation', 'compliance']]
        );
      }

      if ($result) {
        error_log("[delivery-notification] Email sent to patient for order #$orderId");
      } else {
        error_log("[delivery-notification] Failed to send email for order #$orderId");
      }

      return $result;

    } catch (Throwable $e) {
      error_log("[delivery-notification] Error: " . $e->getMessage());
      return false;
    }
  }
}

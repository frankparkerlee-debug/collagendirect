<?php
// api/lib/patient_delivery_notification.php - Patient delivery confirmation emails
// Uses SMTP/Gmail via email_sender.php
declare(strict_types=1);

require_once __DIR__ . '/email_sender.php';

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

      $bodyContent = '
        <h2 style="color: #0d9488; margin: 0 0 20px 0;">Delivery Confirmation Required</h2>

        <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
          Dear <strong>' . htmlspecialchars($patientName) . '</strong>,
        </p>

        <p style="color: #475569; line-height: 1.6; margin: 0 0 15px 0;">
          We hope you\'ve received your CollagenDirect order!
        </p>

        <div style="background-color: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 20px; margin: 25px 0;">
          <p style="margin: 0 0 10px 0; font-weight: 600; color: #0f766e;">Order Details:</p>
          <p style="margin: 5px 0; color: #475569;"><strong>Order #:</strong> ' . $orderId . '</p>
          <p style="margin: 5px 0; color: #475569;"><strong>Product:</strong> ' . htmlspecialchars($productLabel) . '</p>
          <p style="margin: 5px 0; color: #475569;"><strong>Prescribing Physician:</strong> Dr. ' . htmlspecialchars($physicianName) . '</p>
        </div>

        <div style="background-color: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 15px; margin: 20px 0;">
          <p style="margin: 0; color: #92400e; font-size: 14px;">
            <strong>Action Required:</strong> For insurance compliance, please confirm that you have received your order by clicking the button below.
          </p>
        </div>

        <div style="text-align: center; margin: 30px 0;">
          <a href="' . htmlspecialchars($confirmUrl) . '" style="display: inline-block; background: linear-gradient(135deg, #0d9488, #14b8a6); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;">
            Confirm Delivery
          </a>
        </div>

        <p style="font-size: 12px; color: #64748b; margin: 20px 0;">
          If the button doesn\'t work, copy and paste this link: ' . htmlspecialchars($confirmUrl) . '
        </p>

        <p style="color: #475569; margin-top: 30px;">
          If you have questions or did not receive your order, contact us at
          <a href="mailto:support@collagendirect.health" style="color: #0d9488;">support@collagendirect.health</a>.
        </p>
      ';

      $plainText = "Delivery Confirmation Required

Dear $patientName,

We hope you've received your CollagenDirect order!

ORDER DETAILS:
Order #: $orderId
Product: $productLabel
Prescribing Physician: Dr. $physicianName

ACTION REQUIRED:
For insurance compliance, please confirm delivery at:
$confirmUrl

Questions? Contact support@collagendirect.health

Thank you,
The CollagenDirect Team
";

      $html = email_template($subject, $bodyContent);
      $result = send_email($patientEmail, $patientName, $subject, $html, $plainText);

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

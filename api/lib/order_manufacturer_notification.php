<?php
// api/lib/order_manufacturer_notification.php - Email manufacturer on order submission
declare(strict_types=1);

if (!function_exists('notify_manufacturer_of_order')) {
  /**
   * Send email notification to manufacturer when order is submitted
   * Includes all relevant documents (ID, Insurance Card, Notes, Order PDF)
   *
   * @param PDO $pdo Database connection
   * @param int $orderId Order ID
   * @return bool Success status
   */
  function notify_manufacturer_of_order(PDO $pdo, int $orderId): bool {
    try {
      // Get manufacturer email(s)
      $mfgStmt = $pdo->query("SELECT email, name FROM admin_users WHERE role = 'manufacturer' LIMIT 1");
      $manufacturer = $mfgStmt->fetch(PDO::FETCH_ASSOC);

      if (!$manufacturer) {
        error_log("[order-notification] No manufacturer found");
        return false;
      }

      // Get order details
      $orderStmt = $pdo->prepare("
        SELECT o.*,
               p.first_name, p.last_name, p.email AS patient_email, p.phone, p.dob,
               u.first_name AS phys_first, u.last_name AS phys_last, u.practice_name,
               pr.name AS product_name, pr.size AS product_size, pr.sku, pr.cpt_code
        FROM orders o
        LEFT JOIN patients p ON p.id = o.patient_id
        LEFT JOIN users u ON u.id = o.user_id
        LEFT JOIN products pr ON pr.id = o.product_id
        WHERE o.id = ?
      ");
      $orderStmt->execute([$orderId]);
      $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

      if (!$order) {
        error_log("[order-notification] Order not found: $orderId");
        return false;
      }

      // Build patient name and search tokens for documents
      $patientName = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
      $patientId = (string)($order['patient_id'] ?? '');
      $orderIdStr = (string)$orderId;
      $slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($patientName));

      // Get document URLs
      $baseUrl = 'https://collagendirect.onrender.com';
      $orderPdfUrl = $baseUrl . '/admin/order.pdf.php?id=' . urlencode($orderIdStr);

      // Build email content
      $physicianName = trim(($order['phys_first'] ?? '') . ' ' . ($order['phys_last'] ?? ''));
      $practiceName = $order['practice_name'] ?? '';
      $productLabel = $order['product_name'] ?? $order['product'] ?? 'N/A';
      if (!empty($order['product_size'])) {
        $productLabel .= ' ' . $order['product_size'];
      }

      $subject = "New Order Submitted - Order #$orderId";

      $emailBody = "Hello {$manufacturer['name']},\n\n";
      $emailBody .= "A new order has been submitted and is ready for processing.\n\n";
      $emailBody .= "ORDER DETAILS\n";
      $emailBody .= "-------------\n";
      $emailBody .= "Order ID: #$orderId\n";
      $emailBody .= "Patient: $patientName\n";
      $emailBody .= "DOB: " . ($order['dob'] ?? 'N/A') . "\n";
      $emailBody .= "Phone: " . ($order['phone'] ?? 'N/A') . "\n";
      $emailBody .= "Email: " . ($order['patient_email'] ?? 'N/A') . "\n\n";

      $emailBody .= "PROVIDER INFORMATION\n";
      $emailBody .= "--------------------\n";
      $emailBody .= "Physician: $physicianName\n";
      if ($practiceName) {
        $emailBody .= "Practice: $practiceName\n";
      }
      $emailBody .= "\n";

      $emailBody .= "PRODUCT INFORMATION\n";
      $emailBody .= "-------------------\n";
      $emailBody .= "Product: $productLabel\n";
      if (!empty($order['sku'])) {
        $emailBody .= "SKU: " . $order['sku'] . "\n";
      }
      if (!empty($order['cpt_code'])) {
        $emailBody .= "CPT Code: " . $order['cpt_code'] . "\n";
      }
      $emailBody .= "Frequency: " . ($order['frequency'] ?? 'N/A') . "\n";
      $emailBody .= "Duration: " . ($order['duration_days'] ?? 'N/A') . " days\n";
      $emailBody .= "Refills: " . ($order['refills_allowed'] ?? '0') . "\n\n";

      $emailBody .= "INSURANCE INFORMATION\n";
      $emailBody .= "---------------------\n";
      $emailBody .= "Insurer: " . ($order['insurer_name'] ?? 'N/A') . "\n";
      $emailBody .= "Member ID: " . ($order['member_id'] ?? 'N/A') . "\n";
      $emailBody .= "Group ID: " . ($order['group_id'] ?? 'N/A') . "\n";
      $emailBody .= "Payer Phone: " . ($order['payer_phone'] ?? 'N/A') . "\n\n";

      $emailBody .= "DOCUMENTS\n";
      $emailBody .= "---------\n";
      $emailBody .= "View Order PDF: $orderPdfUrl\n";
      $emailBody .= "Access all documents in the admin portal: $baseUrl/admin/orders.php?id=$orderId\n\n";

      $emailBody .= "Please log in to the CollagenDirect admin portal to view all documents and process this order.\n\n";
      $emailBody .= "Login: $baseUrl/admin/\n\n";
      $emailBody .= "Thank you,\nCollagenDirect System";

      // Send via SendGrid
      require_once __DIR__ . '/sg_curl.php';

      $apiKey = getenv('SENDGRID_API_KEY');
      if (!$apiKey) {
        error_log("[order-notification] SendGrid API key not configured");
        return false;
      }

      $data = [
        'personalizations' => [[
          'to' => [['email' => $manufacturer['email'], 'name' => $manufacturer['name']]],
          'subject' => $subject
        ]],
        'from' => [
          'email' => 'noreply@collagendirect.health',
          'name' => 'CollagenDirect'
        ],
        'content' => [[
          'type' => 'text/plain',
          'value' => $emailBody
        ]]
      ];

      $result = sg_curl_send($apiKey, $data);

      if ($result) {
        error_log("[order-notification] Email sent to manufacturer for order #$orderId");

        // Create portal notification
        create_manufacturer_notification($pdo, $orderId, $patientName, $physicianName);
      } else {
        error_log("[order-notification] Failed to send email for order #$orderId");
      }

      return $result;

    } catch (Throwable $e) {
      error_log("[order-notification] Error: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Create portal notification for manufacturer
   *
   * @param PDO $pdo Database connection
   * @param int $orderId Order ID
   * @param string $patientName Patient name
   * @param string $physicianName Physician name
   */
  function create_manufacturer_notification(PDO $pdo, int $orderId, string $patientName, string $physicianName): void {
    try {
      // Get all manufacturer users
      $mfgStmt = $pdo->query("SELECT id FROM admin_users WHERE role = 'manufacturer'");
      $manufacturers = $mfgStmt->fetchAll(PDO::FETCH_ASSOC);

      foreach ($manufacturers as $mfg) {
        $message = "New order #$orderId from Dr. $physicianName for patient $patientName";
        $link = "/admin/orders.php?id=$orderId";

        $stmt = $pdo->prepare("
          INSERT INTO notifications (user_id, user_type, type, message, link, created_at)
          VALUES (?, 'admin', 'new_order', ?, ?, NOW())
        ");
        $stmt->execute([$mfg['id'], $message, $link]);
      }

      error_log("[order-notification] Created portal notifications for order #$orderId");

    } catch (Throwable $e) {
      error_log("[order-notification] Failed to create notification: " . $e->getMessage());
    }
  }
}

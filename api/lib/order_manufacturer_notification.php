<?php
// api/lib/order_manufacturer_notification.php - Email manufacturer on order submission
declare(strict_types=1);

require_once __DIR__ . '/email_sender.php';

if (!function_exists('notify_manufacturer_of_order')) {
  /**
   * Send email notification to ALL manufacturer reps AND superadmins when order is submitted
   * Uses SMTP (Gmail/Google Workspace)
   *
   * @param PDO $pdo Database connection
   * @param int $orderId Order ID
   * @return bool Success status
   */
  function notify_manufacturer_of_order(PDO $pdo, int $orderId): bool {
    try {
      // Get ALL manufacturer reps
      $mfgStmt = $pdo->query("SELECT email, name FROM admin_users WHERE role = 'manufacturer'");
      $manufacturers = $mfgStmt->fetchAll(PDO::FETCH_ASSOC);

      // Get ALL superadmins from users table
      $adminStmt = $pdo->query("SELECT email, first_name, last_name FROM users WHERE role = 'superadmin'");
      $superadmins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);

      // Combine recipients
      $recipients = [];
      foreach ($manufacturers as $mfg) {
        $recipients[] = ['email' => $mfg['email'], 'name' => $mfg['name']];
      }
      foreach ($superadmins as $admin) {
        $recipients[] = ['email' => $admin['email'], 'name' => trim(($admin['first_name'] ?? '') . ' ' . ($admin['last_name'] ?? ''))];
      }

      if (empty($recipients)) {
        error_log("[order-notification] No manufacturer reps or superadmins found");
        return false;
      }

      error_log("[order-notification] Found " . count($recipients) . " recipients for order #$orderId");

      // Get order details with document paths
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

      // Prepare file attachments
      $attachments = [];
      $docRoot = realpath(__DIR__ . '/../..');

      // Helper to add attachment
      $addAttachment = function($filePath, $filename, $mimeType) use (&$attachments, $docRoot) {
        if (!$filePath) return;

        // Convert web path to filesystem path
        $absPath = $docRoot . $filePath;

        if (!file_exists($absPath) || !is_file($absPath)) {
          error_log("[order-notification] File not found: $absPath");
          return;
        }

        $content = @file_get_contents($absPath);
        if ($content === false) {
          error_log("[order-notification] Could not read file: $absPath");
          return;
        }

        $attachments[] = [
          'content' => base64_encode($content),
          'filename' => $filename,
          'type' => $mimeType ?: 'application/octet-stream',
          'disposition' => 'attachment'
        ];
      };

      // Add documents
      $addAttachment($order['rx_note_path'], 'prescription_note.pdf', $order['rx_note_mime']);
      $addAttachment($order['ins_card_path'], 'insurance_card.pdf', $order['ins_card_mime']);
      $addAttachment($order['id_card_path'], 'id_card.pdf', $order['id_card_mime']);

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
      if (count($attachments) > 0) {
        $emailBody .= "Attached documents:\n";
        foreach ($attachments as $att) {
          $emailBody .= "  - " . $att['filename'] . "\n";
        }
        $emailBody .= "\n";
      }
      $emailBody .= "View Order PDF: $orderPdfUrl\n";
      $emailBody .= "Access all documents in the admin portal: $baseUrl/admin/orders.php?id=$orderId\n\n";

      $emailBody .= "Please log in to the CollagenDirect admin portal to view all documents and process this order.\n\n";
      $emailBody .= "Login: $baseUrl/admin/\n\n";
      $emailBody .= "Thank you,\nCollagenDirect System";

      // Build HTML version for SMTP
      $htmlBody = email_template($subject, "
        <h2 style='color: #1e293b; margin: 0 0 20px 0;'>New Order Submitted</h2>
        <p style='color: #475569; line-height: 1.6;'>A new order has been submitted and is ready for processing.</p>

        <div style='background-color: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 20px; margin: 20px 0;'>
          <h3 style='margin: 0 0 15px 0; color: #0f766e;'>Order Details</h3>
          <p style='margin: 5px 0; color: #475569;'><strong>Order ID:</strong> #$orderId</p>
          <p style='margin: 5px 0; color: #475569;'><strong>Patient:</strong> $patientName</p>
          <p style='margin: 5px 0; color: #475569;'><strong>DOB:</strong> " . ($order['dob'] ?? 'N/A') . "</p>
          <p style='margin: 5px 0; color: #475569;'><strong>Phone:</strong> " . ($order['phone'] ?? 'N/A') . "</p>
        </div>

        <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0;'>
          <h3 style='margin: 0 0 15px 0; color: #334155;'>Provider Information</h3>
          <p style='margin: 5px 0; color: #475569;'><strong>Physician:</strong> $physicianName</p>
          <p style='margin: 5px 0; color: #475569;'><strong>Practice:</strong> $practiceName</p>
        </div>

        <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 20px 0;'>
          <h3 style='margin: 0 0 15px 0; color: #334155;'>Product Information</h3>
          <p style='margin: 5px 0; color: #475569;'><strong>Product:</strong> $productLabel</p>
          <p style='margin: 5px 0; color: #475569;'><strong>Frequency:</strong> " . ($order['frequency'] ?? 'N/A') . "</p>
          <p style='margin: 5px 0; color: #475569;'><strong>Duration:</strong> " . ($order['duration_days'] ?? 'N/A') . " days</p>
        </div>

        <div style='background-color: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 20px; margin: 20px 0;'>
          <h3 style='margin: 0 0 15px 0; color: #92400e;'>Insurance Information</h3>
          <p style='margin: 5px 0; color: #78350f;'><strong>Insurer:</strong> " . ($order['insurer_name'] ?? 'N/A') . "</p>
          <p style='margin: 5px 0; color: #78350f;'><strong>Member ID:</strong> " . ($order['member_id'] ?? 'N/A') . "</p>
          <p style='margin: 5px 0; color: #78350f;'><strong>Group ID:</strong> " . ($order['group_id'] ?? 'N/A') . "</p>
        </div>

        <div style='text-align: center; margin: 30px 0;'>
          <a href='$baseUrl/admin/orders.php?id=$orderId' style='display: inline-block; background: linear-gradient(135deg, #0d9488, #14b8a6); color: #ffffff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-weight: 600;'>
            View Order in Admin Portal
          </a>
        </div>

        <p style='color: #64748b; font-size: 14px; text-align: center;'>
          <a href='$orderPdfUrl' style='color: #0d9488;'>Download Order PDF</a>
        </p>
      ");

      // Send to ALL recipients via SMTP
      $successCount = 0;
      foreach ($recipients as $recipient) {
        $result = send_email($recipient['email'], $recipient['name'], $subject, $htmlBody, $emailBody);
        if ($result) {
          $successCount++;
          error_log("[order-notification] Email sent to {$recipient['email']} for order #$orderId");
        } else {
          error_log("[order-notification] Failed to send email to {$recipient['email']} for order #$orderId");
        }
      }

      // Create portal notifications
      if ($successCount > 0) {
        create_manufacturer_notification($pdo, $orderId, $patientName, $physicianName);
        error_log("[order-notification] Sent $successCount/" . count($recipients) . " emails for order #$orderId");
      }

      return $successCount > 0;

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
